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
     * Accept an invitation: verify token, create Pelican user if needed, create subuser.
     */
    public function accept(string $plainToken, User $user): Invitation
    {
        $hashedToken = hash('sha256', $plainToken);
        $invitation = Invitation::active()->where('token', $hashedToken)->first();

        if (! $invitation) {
            throw new \RuntimeException(__('invitations::messages.errors.invitation_not_found'));
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw new \RuntimeException(__('invitations::messages.errors.email_mismatch'));
        }

        return DB::transaction(function () use ($invitation, $user) {
            // Step 1: Ensure user has a Pelican account. Synchronous on
            // purpose — Step 2 (subuser creation) needs `pelican_user_id`
            // immediately. The action handles existing-Pelican-user lookup,
            // username collisions, and parallel-call locking.
            app(EnsurePelicanAccountAction::class)->execute($user, 'invitation');

            // Step 2: Create the subuser on Pelican — or UPDATE it if the user
            // is already a subuser of this server (re-invitation, or a leftover
            // from a previous grant). Calling createSubuser blindly would 422 on
            // the duplicate and roll back the whole accept, leaving the
            // invitation stuck "pending" with stale permissions.
            $invitation->loadMissing('server');
            $serverIdentifier = $invitation->server->identifier;
            $permissions = $invitation->permissions ?? [];

            $existing = collect($this->subuserService->listSubusers($serverIdentifier))
                ->first(fn (array $s): bool => isset($s['email'])
                    && strtolower((string) $s['email']) === strtolower($user->email));

            if (is_array($existing) && ! empty($existing['uuid'])) {
                $this->subuserService->updateSubuser($serverIdentifier, (string) $existing['uuid'], $permissions);
            } else {
                $this->subuserService->createSubuser($serverIdentifier, $user->email, $permissions);
            }

            // Step 3: Create access record in Peregrine DB (pivot table)
            DB::table('server_user')->updateOrInsert(
                ['user_id' => $user->id, 'server_id' => $invitation->server_id],
                [
                    'role' => 'subuser',
                    'permissions' => json_encode($permissions),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            // Step 4: Mark invitation as accepted
            $invitation->update([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->id,
            ]);

            event(new InvitationAccepted($invitation, $user));

            return $invitation;
        });
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
        $exists = Invitation::query()
            ->forEmail($email)
            ->where('server_id', $server->id)
            ->active()
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($exists) {
            throw new \RuntimeException('An active invitation for this email on this server already exists.');
        }
    }
}
