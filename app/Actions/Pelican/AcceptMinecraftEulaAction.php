<?php

declare(strict_types=1);

namespace App\Actions\Pelican;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;

/**
 * Write `eula=true` to a Minecraft server's eula.txt and power-cycle it, so a
 * player can clear the "you need to agree to the EULA" boot failure straight
 * from the console without opening the file manager.
 *
 * The power-cycle is a hard kill → wait-offline → start (not a soft restart) —
 * the server has usually already crashed on the EULA check, so we confirm it's
 * fully down before starting it cleanly again.
 */
final readonly class AcceptMinecraftEulaAction
{
    public function __construct(
        private PelicanFileService $files,
        private RestartServerCleanlyAction $restart,
    ) {}

    public function __invoke(Server $server): void
    {
        $this->files->writeFile($server->identifier, '/eula.txt', "eula=true\n");
        ($this->restart)($server);
    }
}
