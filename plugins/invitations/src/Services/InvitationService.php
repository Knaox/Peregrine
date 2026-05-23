<?php

namespace Plugins\Invitations\Services;

use App\Actions\Pelican\EnsurePelicanAccountAction;
use App\Jobs\SendPluginMail;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\PluginManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Plugins\Invitations\Events\InvitationAccepted;
use Plugins\Invitations\Events\InvitationCreated;
use Plugins\Invitations\Events\InvitationRevoked;
use Plugins\Invitations\Mail\ServerInvitationMail;
use Plugins\Invitations\Models\Invitation;

class InvitationService
{
    public function __construct(
        private readonly PelicanSubuserService $subuserService,
        private readonly PelicanApplicationService $applicationService,
        private readonly PluginManager $pluginManager,
    ) {}

    /**
     * Create and send a server invitation.
     *
     * @param  array<int, string>  $permissions
     */
    public function create(
        Server $server,
        User $inviter,
        string $email,
        array $permissions,
        ?int $expiresInDays = null,
    ): Invitation {
        $email = strtolower(trim($email));
        $expiresInDays ??= (int) $this->pluginManager->getSetting('invitations', 'invitation_expiry_days', 7);

        $this->enforceRateLimit($inviter);
        $this->enforceCooldown($email, $server);

        // Generate token — store hashed, return plain
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        $invitation = Invitation::create([
            'token' => $hashedToken,
            'email' => $email,
            'server_id' => $server->id,
            'permissions' => $permissions,
            'inviter_user_id' => $inviter->id,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        event(new InvitationCreated($invitation));

        // Send email with the PLAIN token (not the hash)
        $this->send($invitation, $plainToken);

        return $invitation;
    }

    /**
     * Create a batch of invitations (one per server) that share a batch_id and
     * are authorized by a SINGLE email — the recipient accepts once and lands
     * on every server. The same permission set is applied to each server
     * (Pelican filters per-server natively at accept time).
     *
     * For a single server this delegates to create() so the mono-server path
     * stays byte-for-byte identical (token, event, response). Servers that
     * already have an active invite for this email are skipped (reported in
     * `skipped`) instead of aborting the whole batch.
     *
     * @param  array<int, int>  $serverIds
     * @param  array<int, string>  $permissions
     * @return array{invitations: array<int, Invitation>, skipped: array<int, int>}
     */
    public function createBatch(
        array $serverIds,
        User $inviter,
        string $email,
        array $permissions,
        ?int $expiresInDays = null,
    ): array {
        $email = strtolower(trim($email));
        $expiresInDays ??= (int) $this->pluginManager->getSetting('invitations', 'invitation_expiry_days', 7);
        $serverIds = array_values(array_unique(array_map('intval', $serverIds)));

        // Single target → reuse the existing, fully-tested single-invite path.
        if (count($serverIds) <= 1) {
            $server = Server::findOrFail($serverIds[0] ?? 0);

            return ['invitations' => [$this->create($server, $inviter, $email, $permissions, $expiresInDays)], 'skipped' => []];
        }

        $this->enforceRateLimit($inviter);

        $servers = Server::whereIn('id', $serverIds)->get()->keyBy('id');
        $batchId = (string) Str::uuid();

        /** @var array<int, Invitation> $created */
        $created = [];
        /** @var array<int, int> $skipped */
        $skipped = [];
        $leaderPlainToken = null;
        $leaderId = null;

        DB::transaction(function () use ($serverIds, $servers, $inviter, $email, $permissions, $expiresInDays, $batchId, &$created, &$skipped, &$leaderPlainToken, &$leaderId): void {
            foreach ($serverIds as $serverId) {
                $server = $servers->get($serverId);
                if (! $server || $this->hasActiveInvite($email, $server)) {
                    $skipped[] = $serverId;

                    continue;
                }

                $plainToken = Str::random(64);
                $isLeader = $leaderId === null;

                $invitation = Invitation::create([
                    'token' => hash('sha256', $plainToken),
                    'batch_id' => $batchId,
                    'is_batch_leader' => $isLeader,
                    'email' => $email,
                    'server_id' => $server->id,
                    'permissions' => $permissions,
                    'inviter_user_id' => $inviter->id,
                    'expires_at' => now()->addDays($expiresInDays),
                ]);

                event(new InvitationCreated($invitation));
                $created[] = $invitation;

                if ($isLeader) {
                    $leaderPlainToken = $plainToken;
                    $leaderId = $invitation->id;
                }
            }
        });

        // ONE email — the leader's token authorizes the whole batch.
        if ($leaderId !== null && $leaderPlainToken !== null) {
            $this->send(Invitation::findOrFail($leaderId), $leaderPlainToken);
        }

        return ['invitations' => $created, 'skipped' => $skipped];
    }

    /**
     * Send (or re-send) the invitation email.
     */
    public function send(Invitation $invitation, string $plainToken): void
    {
        // Dispatch via core Job — only primitives, no plugin classes serialized
        SendPluginMail::dispatch(
            $invitation->email,
            ServerInvitationMail::class,
            ['invitationId' => $invitation->id, 'plainToken' => $plainToken],
        );
    }

    /**
     * Accept an invitation. Verifies the token, links a Pelican account if
     * needed, then provisions + grants access for EVERY server the link
     * authorizes — a single row for a normal invite, or the whole batch for a
     * multi-server invite (one email, accept-all).
     *
     * Ordering is deliberate. Pelican is an external system and is NOT part of
     * the DB transaction: the per-server external work runs first (and on a
     * hard failure leaves that one server pending), then the local pivot +
     * `accepted_at` are committed atomically per invitation. A failure on one
     * server never rolls back the servers that already succeeded.
     *
     * The entry row is resolved WITHOUT the active() scope so a revoked or
     * already-accepted leader still lets the mailed link reach its pending
     * batch siblings.
     *
     * @return array{accepted: array<int, int>, failed: array<int, int>, first_server_id: ?int}
     */
    public function accept(string $plainToken, User $user): array
    {
        $hashedToken = hash('sha256', $plainToken);
        $entry = Invitation::where('token', $hashedToken)->first();

        if (! $entry) {
            throw new \RuntimeException(__('invitations::messages.errors.invitation_not_found'));
        }

        if (strtolower($user->email) !== strtolower($entry->email)) {
            throw new \RuntimeException(__('invitations::messages.errors.email_mismatch'));
        }

        // The invitations this link authorizes: the whole batch when batched,
        // otherwise just this row. Only still-active ones are (re)processed.
        $invitations = $entry->batch_id
            ? Invitation::active()->forBatch($entry->batch_id)->with('server')->get()
            : Invitation::active()->whereKey($entry->id)->with('server')->get();

        if ($invitations->isEmpty()) {
            throw new \RuntimeException(__('invitations::messages.errors.invitation_not_found'));
        }

        // Link the Pelican account once for the whole batch. Idempotent; the
        // action handles existing-user lookup, username collisions, locking.
        app(EnsurePelicanAccountAction::class)->execute($user, 'invitation');

        $accepted = [];
        $failed = [];

        foreach ($invitations as $invitation) {
            $server = $invitation->server;

            if (! $server || ! $server->identifier) {
                // Server deleted or never got a Pelican identifier — skip it.
                $failed[] = (int) $invitation->server_id;

                continue;
            }

            try {
                $this->acceptOne($invitation, $user, $server->identifier);
                $accepted[] = (int) $invitation->server_id;
            } catch (\Throwable $e) {
                // One server failing must not abort the rest of the batch — it
                // stays pending and is reported so the user can retry.
                report($e);
                $failed[] = (int) $invitation->server_id;
            }
        }

        if (empty($accepted)) {
            // Nothing landed — surface a hard error so the route returns 422 and
            // the invitation(s) stay pending for a retry.
            throw new \RuntimeException(__('invitations::messages.errors.accept_failed'));
        }

        return [
            'accepted' => $accepted,
            'failed' => $failed,
            'first_server_id' => $accepted[0],
        ];
    }

    /**
     * Provision a single server's Pelican subuser (external, OUTSIDE any DB
     * transaction) then grant local access + mark the invitation accepted
     * atomically. Extracted so accept() can loop a batch cleanly.
     */
    private function acceptOne(Invitation $invitation, User $user, string $serverIdentifier): void
    {
        $permissions = $invitation->permissions ?? [];

        // Ensure the Pelican subuser exists with the right permissions —
        // create, or update if already a subuser. Tolerates the "already a
        // subuser" conflict so a re-invite never gets stuck.
        $this->subuserService->syncSubuser($serverIdentifier, $user->email, $permissions);

        DB::transaction(function () use ($invitation, $user, $permissions): void {
            // Grant access in Peregrine's pivot — this, not Pelican, is what the
            // host's ServerPolicy / permissionsForUser read for access checks.
            DB::table('server_user')->updateOrInsert(
                ['user_id' => $user->id, 'server_id' => $invitation->server_id],
                [
                    'role' => 'subuser',
                    'permissions' => json_encode($permissions),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ]);
        });

        event(new InvitationAccepted($invitation, $user));
    }

    /**
     * Revoke an invitation.
     */
    public function revoke(Invitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
        event(new InvitationRevoked($invitation));
    }

    /**
     * Re-send an existing pending invitation to its original email.
     *
     * Use case : the recipient lost / never received the original mail
     * (spam folder, misclicked URL, mailbox down at the time, …) and
     * the inviter wants to trigger another delivery without going
     * through "revoke + create new" — which would lose the original
     * timestamp + permission set.
     *
     * Token rotation : the original plaintext token is gone (we only
     * keep its sha256 hash in DB), so we MUST mint a fresh token,
     * persist its new hash, and ship the new token in the new mail.
     * This is also a security upgrade — any old mail still sitting in
     * an intercepted mailbox is invalidated by the rotation. Mirrors
     * Laravel's password-reset behaviour.
     *
     * Expiration is reset to a fresh window so the recipient gets the
     * full configured expiry from the moment they receive the new
     * mail (otherwise an invitation resent on day 6 of a 7-day
     * window would be useful for ~1 day, defeating the resend).
     *
     * Throws when the invitation is no longer in a resendable state
     * (already accepted, already revoked) — the caller surfaces a
     * clean 422 to the operator.
     *
     * @throws \RuntimeException
     */
    public function resend(Invitation $invitation, ?int $expiresInDays = null): void
    {
        if ($invitation->accepted_at !== null) {
            throw new \RuntimeException(__('invitations::messages.errors.already_accepted'));
        }
        if ($invitation->revoked_at !== null) {
            throw new \RuntimeException(__('invitations::messages.errors.already_revoked'));
        }

        $expiresInDays ??= (int) $this->pluginManager->getSetting('invitations', 'invitation_expiry_days', 7);

        // Mint a fresh token + reset the expiry window. The previous
        // hashed token is overwritten — the URL inside any older mail
        // immediately stops working, which is the desired security
        // posture for a re-send.
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        $invitation->update([
            'token' => $hashedToken,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        // For a multi-server batch, keep the sibling invitations valid for the
        // same fresh window so the resent link still authorizes every server on
        // accept (accept() resolves the whole batch from this row's token).
        if ($invitation->batch_id) {
            Invitation::query()
                ->forBatch($invitation->batch_id)
                ->whereKeyNot($invitation->id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['expires_at' => now()->addDays($expiresInDays)]);
        }

        $this->send($invitation, $plainToken);
    }

    /**
     * Cleanup expired invitations older than 30 days.
     */
    public function cleanup(): int
    {
        return Invitation::query()
            ->where('expires_at', '<', now()->subDays(30))
            ->whereNull('accepted_at')
            ->delete();
    }

    /**
     * Enforce rate limit: max N invitations per hour per inviter.
     */
    private function enforceRateLimit(User $inviter): void
    {
        $maxPerHour = (int) $this->pluginManager->getSetting('invitations', 'max_invitations_per_hour', 10);

        $recentCount = Invitation::where('inviter_user_id', $inviter->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= $maxPerHour) {
            throw new \RuntimeException("Rate limit exceeded. Maximum {$maxPerHour} invitations per hour.");
        }
    }

    /**
     * Enforce cooldown: 1 active invitation per 24h per email per server.
     */
    private function enforceCooldown(string $email, Server $server): void
    {
        if ($this->hasActiveInvite($email, $server)) {
            throw new \RuntimeException('An active invitation for this email on this server already exists.');
        }
    }

    /**
     * Whether an active (un-accepted, un-revoked, un-expired) invitation for
     * this email on this server was created within the last 24h. Used both to
     * enforce the single-invite cooldown and to skip already-invited servers
     * in a batch without failing the whole batch.
     */
    private function hasActiveInvite(string $email, Server $server): bool
    {
        return Invitation::query()
            ->forEmail($email)
            ->where('server_id', $server->id)
            ->active()
            ->where('created_at', '>=', now()->subDay())
            ->exists();
    }
}
