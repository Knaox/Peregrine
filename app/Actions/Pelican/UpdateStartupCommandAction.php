<?php

declare(strict_types=1);

namespace App\Actions\Pelican;

use App\Events\AdminActionPerformed;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanStartupClient;
use Illuminate\Validation\ValidationException;

/**
 * Switch a server's startup command to one of the egg-defined named
 * commands (Pelican beta26+ "multiple startup commands" feature).
 *
 * The chosen name is validated STRICTLY against the egg's command map —
 * free text never reaches Pelican (mirrors Pelican's own client behaviour:
 * raw startup editing was refused upstream for security). A server whose
 * current command was admin-customized (absent from the egg map) can still
 * switch TO a named command; until then the UI shows it read-only.
 */
final readonly class UpdateStartupCommandAction
{
    public function __construct(
        private PelicanApplicationService $pelican,
        private PelicanStartupClient $startupClient,
    ) {}

    /**
     * @return array{name: string, startup: string}
     *
     * @throws ValidationException when the name doesn't match an egg command
     */
    public function __invoke(User $actor, Server $server, string $commandName, ?string $ip = null, string $userAgent = ''): array
    {
        if ($server->pelican_server_id === null) {
            throw ValidationException::withMessages(['name' => 'This server is not provisioned yet.']);
        }

        $container = $this->pelican->getServerContainer($server->pelican_server_id);
        $options = $this->startupClient->getEggStartupOptions((int) $container['egg']);

        if (! array_key_exists($commandName, $options)) {
            throw ValidationException::withMessages(['name' => 'Unknown startup command for this egg.']);
        }

        $this->startupClient->updateServerStartupCommand(
            $server->pelican_server_id,
            $options[$commandName],
            $container,
        );

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $actor,
            action: 'server.startup.command',
            server: $server,
            payload: ['name' => $commandName, 'command' => mb_substr($options[$commandName], 0, 500)],
            ip: $ip,
            userAgent: $userAgent,
        );

        return ['name' => $commandName, 'startup' => $options[$commandName]];
    }
}
