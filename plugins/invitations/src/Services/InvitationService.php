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
     * @param array<int, string> $permissions
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
            throw new \RuntimeException('Invitation not found or expired.');
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw new \RuntimeException('Email does not match the invitation.');
        }

        return DB::transaction(function () use ($invitation, $user) {
            // Step 1: Ensure user has a Pelican account. Synchronous on
            // purpose — Step 2 (subuser creation) needs `pelican_user_id`
            // immediately. The action handles existing-Pelican-user lookup,
            // username collisions, and parallel-call locking.
            app(EnsurePelicanAccountAction::class)->execute($user, 'invitation');

            // Step 2: Create subuser on Pelican
            $invitation->loadMissing('server');
            $serverIdentifier = $invitation->server->identifier;

            $this->subuserService->createSubuser(
                $serverIdentifier,
                $user->email,
                $invitation->permissions ?? [],
            );

            // Step 3: Create access record in Peregrine DB (pivot table)
            DB::table('server_user')->updateOrInsert(
                ['user_id' => $user->id, 'server_id' => $invitation->server_id],
                [
                    'role' => 'subuser',
                    'permissions' => json_encode($invitation->permissions ?? []),
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
