<?php

namespace App\Services\Admin;

use App\Models\AdminActionLog;

class AdminAuditService
{
    /**
     * Persist an admin action entry. Called by the LogAdminAction listener;
     * controllers never touch this directly (plan §S6 — dispatch events, let
     * the listener write, so failed requests produce no row).
     *
     * @param  array<string, mixed>  $payload
     */
    public function log(
        int $adminId,
        string $action,
        ?int $targetUserId,
        ?int $targetServerId,
        array $payload,
        ?string $ip,
        string $userAgent,
    ): AdminActionLog {
        return AdminActionLog::create([
            'admin_id' => $adminId,
            'target_user_id' => $targetUserId,
            'target_server_id' => $targetServerId,
            'action' => $action,
            'payload' => $payload,
            'ip' => $ip ?? '0.0.0.0',
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'created_at' => now(),
        ]);
    }
}
