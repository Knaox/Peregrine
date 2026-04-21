<?php

namespace App\Events;

use App\Models\Server;
use App\Models\User;

/**
 * Emitted AFTER an admin successfully performs a sensitive action against a
 * server they don't own. Consumed by LogAdminAction to write a row in
 * admin_action_logs. Never fire on a failed/throwing request — dispatching
 * from a middleware terminate() is forbidden by plan §S6.
 */
class AdminActionPerformed
{
    public function __construct(
        public readonly User $admin,
        public readonly string $action,
        public readonly ?User $targetUser,
        public readonly ?Server $targetServer,
        /** @var array<string, mixed> */
        public readonly array $payload,
        public readonly ?string $ip,
        public readonly string $userAgent,
    ) {}

    /**
     * Helper: dispatch the event only if the acting user is an admin AND the
     * target server belongs to someone else. Keeps controllers terse — each
     * mutating endpoint reduces to a single-line call after success.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function dispatchIfCrossUser(
        User $admin,
        string $action,
        Server $server,
        array $payload = [],
        ?string $ip = null,
        string $userAgent = '',
    ): void {
        if (! $admin->is_admin) {
            return;
        }

        if ($server->user_id === $admin->id) {
            return;
        }

        event(new self(
            admin: $admin,
            action: $action,
            targetUser: $server->user,
            targetServer: $server,
            payload: self::scrub($payload),
            ip: $ip,
            userAgent: $userAgent,
        ));
    }

    /**
     * Strip secrets from a payload before it lands in the audit table. Keeps
     * accidental credentials (current_password on a profile edit, api token
     * rotations...) from being written to a durable log.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function scrub(array $payload): array
    {
        $sensitive = ['password', 'current_password', 'new_password', 'token', 'api_token', 'secret', 'client_secret'];

        foreach ($sensitive as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
