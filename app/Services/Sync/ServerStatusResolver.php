<?php

namespace App\Services\Sync;

use App\Services\Pelican\DTOs\PelicanServer;

/**
 * Pure mapping logic between Pelican's server lifecycle (installing /
 * install_failed / null / running / suspended) and Peregrine's local
 * status enum (provisioning / provisioning_failed / active / suspended).
 *
 * Extracted from SyncServerFromPelicanWebhookJob to keep that job under
 * the 300-line plafond CLAUDE.md and to make these mappings testable
 * in isolation (no Eloquent, no DI, pure functions).
 */
final class ServerStatusResolver
{
    /**
     * Compute the install-related status delta Pelican is allowed to apply
     * on a Shop-owned server. Returns null when no transition should happen.
     *
     * Allowed transitions :
     *   provisioning → active                (install finished)
     *   provisioning → provisioning_failed   (install errored)
     *
     * @param  array<string, mixed>  $payload
     */
    public static function resolveInstallStatus(?PelicanServer $apiSnapshot, string $previousStatus, array $payload): ?string
    {
        if ($previousStatus !== 'provisioning') {
            return null;
        }

        if ($apiSnapshot !== null) {
            if ($apiSnapshot->installFailed()) {
                return 'provisioning_failed';
            }
            if ($apiSnapshot->isInstalling()) {
                return null;
            }
            return 'active';
        }

        $payloadStatus = $payload['status'] ?? null;
        return match ($payloadStatus) {
            'install_failed', 'reinstall_failed' => 'provisioning_failed',
            'installing' => null,
            null, '' => 'active',
            default => null,
        };
    }

    /**
     * Translate the `isSuspended` flag from the Pelican Application API
     * response into our local `servers.status` enum. Used only on the
     * full-upsert path (Paymenter / admin-imported), where Pelican is the
     * source of truth for the lifecycle status.
     */
    public static function mapStatusFromApi(bool $isSuspended): string
    {
        return $isSuspended ? 'suspended' : 'active';
    }

    /**
     * Translate Pelican's server status field into our local enum. Used on
     * the full-upsert path only (Paymenter / admin-imported).
     *
     * @param  array<string, mixed>  $data
     */
    public static function mapPelicanStatus(array $data): string
    {
        $isSuspended = (bool) ($data['suspended'] ?? false);
        $status = $data['status'] ?? null;

        if ($isSuspended || $status === 'suspended') {
            return 'suspended';
        }

        return match ($status) {
            'installing' => 'provisioning',
            'install_failed', 'reinstall_failed' => 'provisioning_failed',
            null, '' => 'active',
            default => (string) $status,
        };
    }
}
