<?php

namespace App\Listeners;

use App\Events\AdminActionPerformed;
use App\Services\Admin\AdminAuditService;

/**
 * Synchronous listener — the audit log write must happen inside the request
 * lifecycle so an exception raised after dispatch (unlikely, but possible)
 * still leaves a log entry. Queued listeners would defer and could lose the
 * write if the worker is down.
 *
 * Kept NOT ShouldQueue by design.
 */
class LogAdminAction
{
    public function __construct(
        private readonly AdminAuditService $audit,
    ) {}

    public function handle(AdminActionPerformed $event): void
    {
        $this->audit->log(
            adminId: $event->admin->id,
            action: $event->action,
            targetUserId: $event->targetUser?->id,
            targetServerId: $event->targetServer?->id,
            payload: $event->payload,
            ip: $event->ip,
            userAgent: $event->userAgent,
        );
    }
}
