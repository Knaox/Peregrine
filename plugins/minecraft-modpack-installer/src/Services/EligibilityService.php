<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use App\Models\Server;

class EligibilityService
{
    public function __construct(private readonly ModpackSettingsService $settings) {}

    public function isEligible(Server $server): bool
    {
        $whitelist = $this->settings->whitelistedEggIds();
        if ($whitelist === []) {
            return false;
        }
        if ($server->egg_id === null) {
            return false;
        }

        return in_array((int) $server->egg_id, $whitelist, true);
    }

    public function reason(Server $server): ?string
    {
        return $this->isEligible($server) ? null : 'egg_not_whitelisted';
    }
}
