<?php

namespace Plugins\Invitations\Services;

/**
 * Registry of permissions available for server invitations.
 *
 * Core Pelican permissions are registered at boot. Other plugins can
 * register their own permission groups by calling registerGroup() in
 * their ServiceProvider's boot() method:
 *
 *   app(PermissionRegistry::class)->registerGroup(
 *       groupKey: 'mods',
 *       groupLabel: ['en' => 'Mod Management', 'fr' => 'Gestion des mods'],
 *       permissions: [
 *           'mods.install' => ['en' => 'Install mods', 'fr' => 'Installer des mods'],
 *           'mods.remove'  => ['en' => 'Remove mods', 'fr' => 'Supprimer des mods'],
 *       ],
 *   );
 */
class PermissionRegistry
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (! self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /** @var array<string, array{label: array<string, string>, permissions: array<string, array<string, string>>}> */
    private array $groups = [];

    /**
     * Register a permission group.
     *
     * @param string $groupKey Unique group key (e.g., 'control', 'file', 'mods')
     * @param array<string, string> $groupLabel Labels per locale (['en' => '...', 'fr' => '...'])
     * @param array<string, array<string, string>> $permissions Map of permission key => labels per locale
     */
    public function registerGroup(
        string $groupKey,
        array $groupLabel,
        array $permissions,
    ): void {
        if (isset($this->groups[$groupKey])) {
            // Merge permissions into existing group
            $this->groups[$groupKey]['permissions'] = array_merge(
                $this->groups[$groupKey]['permissions'],
                $permissions,
            );

            return;
        }

        $this->groups[$groupKey] = [
            'label' => $groupLabel,
            'permissions' => $permissions,
        ];
    }

    /**
     * Get all registered permission groups.
     *
     * @return array<string, array{label: array<string, string>, permissions: array<string, array<string, string>>}>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Get all permission groups formatted for API response.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(string $locale = 'en'): array
    {
        $result = [];

        foreach ($this->groups as $key => $group) {
            $permissions = [];

            foreach ($group['permissions'] as $permKey => $labels) {
                $permissions[] = [
                    'key' => $permKey,
                    'label' => $labels[$locale] ?? $labels['en'] ?? $permKey,
                ];
            }

            $result[] = [
                'group' => $key,
                'label' => $group['label'][$locale] ?? $group['label']['en'] ?? $key,
                'permissions' => $permissions,
            ];
        }

        return $result;
    }

    /**
     * Register all native Pelican subuser permissions.
     */
    public function registerPelicanDefaults(): void
    {
        // Peregrine-specific permissions for the Overview page
        $this->registerGroup('overview', [
            'en' => 'Overview Page',
            'fr' => 'Page Vue d\'ensemble',
        ], [
            'overview.read' => ['en' => 'View overview page', 'fr' => 'Voir la page vue d\'ensemble'],
            'overview.stats' => ['en' => 'View resource stats (CPU, RAM, Disk)', 'fr' => 'Voir les statistiques (CPU, RAM, Disque)'],
            'overview.server-info' => ['en' => 'View server configuration', 'fr' => 'Voir la configuration du serveur'],
        ]);

        $this->registerGroup('control', [
            'en' => 'Server Control',
            'fr' => 'Contrôle du serveur',
        ], [
            'control.console' => ['en' => 'View console', 'fr' => 'Voir la console'],
            'control.start' => ['en' => 'Start server', 'fr' => 'Démarrer le serveur'],
            'control.stop' => ['en' => 'Stop server', 'fr' => 'Arrêter le serveur'],
            'control.restart' => ['en' => 'Restart server', 'fr' => 'Redémarrer le serveur'],
        ]);

        $this->registerGroup('user', [
            'en' => 'User Management',
            'fr' => 'Gestion des utilisateurs',
        ], [
            'user.create' => ['en' => 'Create subusers', 'fr' => 'Créer des sous-utilisateurs'],
            'user.read' => ['en' => 'View subusers', 'fr' => 'Voir les sous-utilisateurs'],
            'user.update' => ['en' => 'Update subusers', 'fr' => 'Modifier les sous-utilisateurs'],
            'user.delete' => ['en' => 'Delete subusers', 'fr' => 'Supprimer les sous-utilisateurs'],
        ]);

        $this->registerGroup('file', [
            'en' => 'File Management',
            'fr' => 'Gestion des fichiers',
        ], [
            'file.create' => ['en' => 'Create files', 'fr' => 'Créer des fichiers'],
            'file.read' => ['en' => 'List files', 'fr' => 'Lister les fichiers'],
            'file.read-content' => ['en' => 'Read file content', 'fr' => 'Lire le contenu des fichiers'],
            'file.update' => ['en' => 'Edit files', 'fr' => 'Modifier des fichiers'],
            'file.delete' => ['en' => 'Delete files', 'fr' => 'Supprimer des fichiers'],
            'file.archive' => ['en' => 'Compress/extract archives', 'fr' => 'Compresser/extraire des archives'],
            'file.sftp' => ['en' => 'SFTP access', 'fr' => 'Accès SFTP'],
        ]);

        $this->registerGroup('backup', [
            'en' => 'Backups',
            'fr' => 'Sauvegardes',
        ], [
            'backup.create' => ['en' => 'Create backups', 'fr' => 'Créer des sauvegardes'],
            'backup.read' => ['en' => 'View backups', 'fr' => 'Voir les sauvegardes'],
            'backup.delete' => ['en' => 'Delete backups', 'fr' => 'Supprimer des sauvegardes'],
            'backup.download' => ['en' => 'Download backups', 'fr' => 'Télécharger des sauvegardes'],
            'backup.restore' => ['en' => 'Restore backups', 'fr' => 'Restaurer des sauvegardes'],
        ]);

        $this->registerGroup('database', [
            'en' => 'Databases',
            'fr' => 'Bases de données',
        ], [
            'database.create' => ['en' => 'Create databases', 'fr' => 'Créer des bases de données'],
            'database.read' => ['en' => 'View databases', 'fr' => 'Voir les bases de données'],
            'database.update' => ['en' => 'Rotate passwords', 'fr' => 'Changer les mots de passe'],
            'database.delete' => ['en' => 'Delete databases', 'fr' => 'Supprimer des bases de données'],
            'database.view_password' => ['en' => 'View passwords', 'fr' => 'Voir les mots de passe'],
        ]);

        $this->registerGroup('schedule', [
            'en' => 'Schedules',
            'fr' => 'Planificateur',
        ], [
            'schedule.create' => ['en' => 'Create schedules', 'fr' => 'Créer des planifications'],
            'schedule.read' => ['en' => 'View schedules', 'fr' => 'Voir les planifications'],
            'schedule.update' => ['en' => 'Edit schedules', 'fr' => 'Modifier les planifications'],
            'schedule.delete' => ['en' => 'Delete schedules', 'fr' => 'Supprimer les planifications'],
        ]);

        $this->registerGroup('allocation', [
            'en' => 'Network',
            'fr' => 'Réseau',
        ], [
            'allocation.read' => ['en' => 'View allocations', 'fr' => 'Voir les allocations'],
            'allocation.create' => ['en' => 'Add allocations', 'fr' => 'Ajouter des allocations'],
            'allocation.update' => ['en' => 'Edit allocations', 'fr' => 'Modifier les allocations'],
            'allocation.delete' => ['en' => 'Delete allocations', 'fr' => 'Supprimer les allocations'],
        ]);

        $this->registerGroup('startup', [
            'en' => 'Startup',
            'fr' => 'Démarrage',
        ], [
            'startup.read' => ['en' => 'View startup variables', 'fr' => 'Voir les variables de démarrage'],
            'startup.update' => ['en' => 'Edit startup variables', 'fr' => 'Modifier les variables de démarrage'],
        ]);

        $this->registerGroup('settings', [
            'en' => 'Settings',
            'fr' => 'Paramètres',
        ], [
            'settings.rename' => ['en' => 'Rename server', 'fr' => 'Renommer le serveur'],
            'settings.reinstall' => ['en' => 'Reinstall server', 'fr' => 'Réinstaller le serveur'],
        ]);
    }
}
