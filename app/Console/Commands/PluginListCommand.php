<?php

namespace App\Console\Commands;

use App\Services\PluginManager;
use Illuminate\Console\Command;

class PluginListCommand extends Command
{
    protected $signature = 'plugin:list';

    protected $description = 'List all discovered plugins and their status';

    public function handle(PluginManager $pluginManager): int
    {
        $plugins = $pluginManager->allWithStatus();

        if (empty($plugins)) {
            $this->info('No plugins found in plugins/ directory.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($plugins as $plugin) {
            $status = match (true) {
                $plugin['is_active'] => '<fg=green>Active</>',
                $plugin['is_installed'] => '<fg=yellow>Inactive</>',
                default => '<fg=gray>Not installed</>',
            };

            $rows[] = [
                $plugin['id'],
                $plugin['name'],
                $plugin['version'],
                $plugin['author'],
                $status,
            ];
        }

        $this->table(['ID', 'Name', 'Version', 'Author', 'Status'], $rows);

        return self::SUCCESS;
    }
}
