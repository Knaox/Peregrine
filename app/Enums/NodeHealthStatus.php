<?php

namespace App\Enums;

/**
 * Outcome of a Wings node / server-on-node reachability probe.
 *
 * The first five cases describe the node itself; the two `Server*` cases
 * mean "the node answers, but THIS server is in trouble on it" — including
 * the degraded-Wings pattern where /api/system still answers while every
 * server operation returns generic HTTP 500s (seen on LXC/Proxmox hosts).
 */
enum NodeHealthStatus: string
{
    case Healthy = 'healthy';

    /** Reachable but slower than NodeHealthService::SLOW_THRESHOLD_MS. */
    case Degraded = 'degraded';

    /** Connection refused / timeout / HTTP 5xx on /api/system. */
    case Unreachable = 'unreachable';

    /** Wings rejects the daemon token even after re-hydration from Pelican. */
    case AuthFailed = 'auth_failed';

    /** Node flagged maintenance_mode in Pelican. */
    case Maintenance = 'maintenance';

    /** Wings answers but this server is unknown / unreachable on it. */
    case ServerUnreachable = 'server_unreachable';

    /** Wings answers but operations on this server fail (HTTP 5xx). */
    case ServerErrors = 'server_errors';

    /** Node link or daemon credentials missing — nothing could be probed. */
    case Unknown = 'unknown';

    public function isProblem(): bool
    {
        return $this !== self::Healthy;
    }

    /**
     * Severity bucket shared by the admin badge and the player banner:
     * ok | warning | critical.
     */
    public function severity(): string
    {
        return match ($this) {
            self::Healthy => 'ok',
            self::Degraded, self::Maintenance, self::Unknown => 'warning',
            self::Unreachable,
            self::AuthFailed,
            self::ServerUnreachable,
            self::ServerErrors => 'critical',
        };
    }
}
