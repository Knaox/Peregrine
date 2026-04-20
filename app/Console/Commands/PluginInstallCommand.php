<?php

namespace App\Console\Commands;

use App\Services\MarketplaceService;
use Illuminate\Console\Command;

class PluginInstallCommand extends Command
{
    protected $signature = 'plugin:install {plugin_id : The plugin ID to install from the marketplace}';

    protected $description = 'Download and install a plugin from the marketplace';

    public function handle(MarketplaceService $marketplace): int
    {
        $pluginId = $this->argument('plugin_id');

        try {
            $this->info("Installing plugin: {$pluginId}...");
            $marketplace->install($pluginId);
            $this->info("Plugin '{$pluginId}' installed successfully.");
            $this->line("Activate it with: php artisan plugin:activate {$pluginId}");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
