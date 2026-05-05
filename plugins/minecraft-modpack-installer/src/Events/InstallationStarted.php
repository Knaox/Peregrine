<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;

class InstallationStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly ModpackInstallation $installation) {}
}
