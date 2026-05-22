<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Permissions;

use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;
use ReflectionMethod;
use Throwable;

/**
 * Registers the `easyconfig` subuser permission group in the Invitations
 * PermissionRegistry, using the established 3-guard pattern so the plugin works
 * standalone (owners/admins) and gains subuser gating only when Invitations is
 * active:
 *   1. class_exists  — Invitations PSR-4 is registered only when it's active.
 *   2. reflection    — only forward `availableForServer` if that build accepts it.
 *   3. try/catch     — never crash the host on an Invitations contract change.
 */
final class PermissionContributor
{
    private const REGISTRY = '\\Plugins\\Invitations\\Services\\PermissionRegistry';

    public function __construct(private readonly TemplateRegistry $registry) {}

    public function register(): void
    {
        if (! class_exists(self::REGISTRY)) {
            return;
        }

        try {
            $registry = (self::REGISTRY)::getInstance();

            $supportsFilter = false;
            foreach ((new ReflectionMethod($registry, 'registerGroup'))->getParameters() as $parameter) {
                if ($parameter->getName() === 'availableForServer') {
                    $supportsFilter = true;
                    break;
                }
            }

            $args = [
                'groupKey' => 'easyconfig',
                'groupLabel' => ['en' => 'Game configuration', 'fr' => 'Configuration du jeu'],
                'permissions' => [
                    'easyconfig.read' => ['en' => 'View game configuration', 'fr' => 'Consulter la configuration du jeu'],
                    'easyconfig.write' => ['en' => 'Edit game configuration', 'fr' => 'Modifier la configuration du jeu'],
                ],
            ];

            if ($supportsFilter) {
                $args['availableForServer'] = fn (Server $server): bool => $this->eggHasTemplate($server);
            }

            $registry->registerGroup(...$args);
        } catch (Throwable $e) {
            Log::info('easy-configuration: Invitations integration skipped after a runtime check failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function eggHasTemplate(Server $server): bool
    {
        try {
            return $this->registry->forEgg((int) $server->egg_id)->isNotEmpty();
        } catch (Throwable) {
            return false;
        }
    }
}
