<?php

namespace App\Actions\Pelican;

use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Idempotent: makes sure a Peregrine User has a linked Pelican account.
 *
 * Single source of truth for "find or create" logic. Called from every
 * entry point that introduces a new user (local register, OAuth callback,
 * Stripe webhook, Filament admin, login backfill, invitation accept, daily
 * orphan-link command, ProvisionServerJob safety net).
 *
 * Effects: persists `pelican_user_id` on the user when linkage succeeds.
 *
 * Concurrency: protected by a per-user cache lock so two parallel jobs for
 * the same user can't double-create. Belt-and-braces 422 recovery handles
 * the rare cross-host case where the lock backend hiccups.
 *
 * Username collisions: Pelican enforces unique usernames. We derive from
 * the user's name (or email local-part), retrying up to 3 times with a
 * random suffix on `username` 422 errors. `email` 422 errors trigger a
 * re-find-by-email + link (covers manual-creation-elsewhere races).
 */
final class EnsurePelicanAccountAction
{
    private const LOCK_TTL_SECONDS = 30;
    private const LOCK_WAIT_SECONDS = 10;
    private const USERNAME_MAX_RETRIES = 3;

    public function __construct(
        private readonly PelicanApplicationService $pelican,
    ) {}

    /**
     * @throws RequestException
     */
    public function execute(User $user, string $source = 'unknown'): void
    {
        // The pelican_user_id column IS the cache: short-circuit on every
        // call after the first successful link. No Redis memo needed.
        if ($user->pelican_user_id !== null) {
            return;
        }

        if ($user->email === null || $user->email === '') {
            throw new \RuntimeException("User #{$user->id} has no email — cannot link to Pelican.");
        }

        Cache::lock("pelican-link:{$user->id}", self::LOCK_TTL_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, function () use ($user, $source): void {
                // Re-check inside the lock — another worker may have linked
                // while we were waiting on the lock.
                $user->refresh();
                if ($user->pelican_user_id !== null) {
                    return;
                }

                $this->linkOrCreate($user, $source);
            });
    }

    /**
     * @throws RequestException
     */
    private function linkOrCreate(User $user, string $source): void
    {
        $email = strtolower(trim($user->email));

        $existing = $this->pelican->findUserByEmail($email);
        if ($existing !== null) {
            $user->forceFill(['pelican_user_id' => $existing->id])->save();
            Log::info('Pelican account linked (existing match)', [
                'user_id' => $user->id,
                'pelican_user_id' => $existing->id,
                'source' => $source,
            ]);
            return;
        }

        $this->createWithUsernameRetry($user, $email, $source);
    }

    /**
     * @throws RequestException
     */
    private function createWithUsernameRetry(User $user, string $email, string $source): void
    {
        $baseUsername = $this->deriveUsername($user, $email);
        $username = $baseUsername;

        for ($attempt = 0; $attempt <= self::USERNAME_MAX_RETRIES; $attempt++) {
            try {
                $created = $this->pelican->createUser($email, $username, $user->name ?: $username);
                $user->forceFill(['pelican_user_id' => $created->id])->save();
                Log::info('Pelican account created', [
                    'user_id' => $user->id,
                    'pelican_user_id' => $created->id,
                    'username' => $created->username,
                    'source' => $source,
                ]);
                return;
            } catch (RequestException $e) {
                $field = $this->extractValidationField($e);

                if ($field === 'email') {
                    // Race condition: another caller created the Pelican
                    // user between our findByEmail and our createUser. Re-
                    // find and link instead of failing.
                    $existing = $this->pelican->findUserByEmail($email);
                    if ($existing !== null) {
                        $user->forceFill(['pelican_user_id' => $existing->id])->save();
                        Log::info('Pelican account linked after email race', [
                            'user_id' => $user->id,
                            'pelican_user_id' => $existing->id,
                            'source' => $source,
                        ]);
                        return;
                    }
                    throw $e;
                }

                if ($field === 'username' && $attempt < self::USERNAME_MAX_RETRIES) {
                    $username = $baseUsername.'_'.Str::lower(Str::random(6));
                    continue;
                }

                throw $e;
            }
        }

        Log::warning('Pelican account creation gave up after username retries', [
            'user_id' => $user->id,
            'base_username' => $baseUsername,
            'source' => $source,
        ]);
        throw new \RuntimeException(
            "Failed to create Pelican account for user #{$user->id} after ".self::USERNAME_MAX_RETRIES.' username retries.'
        );
    }

    /**
     * Pelican usernames must be alphanumeric + underscore/dash, 3-191 chars.
     * Derive from the user's name (slugged) or the email local-part. Empty
     * or too-short results fall back to a random `user_xxxxxxxx` so we
     * always satisfy the regex.
     */
    private function deriveUsername(User $user, string $email): string
    {
        $candidate = Str::slug((string) ($user->name ?: Str::before($email, '@')), '_');

        if (strlen($candidate) < 3) {
            $candidate = 'user_'.Str::lower(Str::random(8));
        }

        return Str::limit($candidate, 180, '');
    }

    /**
     * Pelican validation errors come back as:
     *   { "errors": [ { "source": { "field": "email" }, ... } ] }
     * Returns the first failing field name we recognise, or null.
     */
    private function extractValidationField(RequestException $e): ?string
    {
        $response = $e->response;
        if ($response === null || $response->status() !== 422) {
            return null;
        }

        $fields = (array) $response->json('errors.*.source.field');
        foreach ($fields as $field) {
            if (in_array($field, ['email', 'username'], true)) {
                return $field;
            }
        }

        // Some Pelican versions report under "meta.source_field" or in the
        // human-readable detail string — fall back to a substring sniff.
        $detail = strtolower((string) $response->json('errors.0.detail'));
        if (str_contains($detail, 'email')) {
            return 'email';
        }
        if (str_contains($detail, 'username')) {
            return 'username';
        }

        return null;
    }
}
