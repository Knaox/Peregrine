<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Events;

use App\Models\Server;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UninstallationCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Server $server) {}
}
