<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Permissions;

use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Throwable;

/**
 * Adds the Easy Configuration permissions to the Invitations "Overview Page"
 * group. The editor lives on the server overview/home page, so its permissions
 * belong alongside the other overview-page permissions rather than in a group of
 * their own. Uses the established guard pattern so the plugin works standalone
 * (owners/admins) and only contributes to the picker when Invitations is active:
 *   1. class_exists  — Invitations PSR-4 is registered only when it's active.
 *   2. reflection    — only forward `advanced` if that Invitations build accepts it.
 *   3. try/catch     — never crash the host on an Invitations contract change.
 *
 * Registering with an existing group key MERGES our permissions into it. We pass
 * the exact label core uses so the result is correct whichever provider boots
 * first (the merge keeps the first registration's label/filter), and we
 * deliberately pass NO per-server filter: the overview group is always shown, so
 * our permissions ride along with it — and a filter set here while creating the
 * group first would wrongly hide the whole overview group for template-less eggs.
 */
final class PermissionContributor
{
    private const REGISTRY = '\\Plugins\\Invitations\\Services\\PermissionRegistry';

    public function register(): void
    {
        if (! class_exists(self::REGISTRY)) {
            return;
        }

        try {
            $registry = (self::REGISTRY)::getInstance();

            $supportsAdvanced = false;
            foreach ((new ReflectionMethod($registry, 'registerGroup'))->getParameters() as $parameter) {
                if ($parameter->getName() === 'advanced') {
                    $supportsAdvanced = true;
                }
            }

            $args = [
                'groupKey' => 'overview',
                'groupLabel' => ['en' => 'Overview Page', 'fr' => 'Page Vue d\'ensemble'],
                'permissions' => [
                    'easyconfig.read' => ['en' => 'View game configuration', 'fr' => 'Consulter la configuration du jeu'],
                    'easyconfig.write' => ['en' => 'Edit game configuration', 'fr' => 'Modifier la configuration du jeu'],
                    'easyconfig.copy' => ['en' => 'Copy configuration to other servers', 'fr' => 'Copier la configuration vers d\'autres serveurs'],
                    'easyconfig.boost' => ['en' => 'Configure value boosts', 'fr' => 'Configurer les boosts de valeurs'],
                ],
            ];

            if ($supportsAdvanced) {
                $args['advanced'] = ['easyconfig.copy', 'easyconfig.boost'];
            }

            $registry->registerGroup(...$args);
        } catch (Throwable $e) {
            Log::info('easy-configuration: Invitations integration skipped after a runtime check failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
