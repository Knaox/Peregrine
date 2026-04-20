<?php

namespace App\Console\Commands;

use App\Services\PluginManager;
use Illuminate\Console\Command;

class PluginActivateCommand extends Command
{
    protected $signature = 'plugin:activate {plugin_id : The plugin ID to activate}';

    protected $description = 'Activate a plugin (runs migrations and registers ServiceProvider)';

    public function handle(PluginManager $pluginManager): int
    {
        $pluginId = $this->argument('plugin_id');

        try {
            $this->info("Activating plugin: {$pluginId}...");
            $pluginManager->activate($pluginId);
            $this->info("Plugin '{$pluginId}' activated successfully.");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
