<?php

namespace App\Console\Commands;

use App\Services\PluginManager;
use Illuminate\Console\Command;

class PluginDeactivateCommand extends Command
{
    protected $signature = 'plugin:deactivate {plugin_id : The plugin ID to deactivate}';

    protected $description = 'Deactivate a plugin (tables remain intact)';

    public function handle(PluginManager $pluginManager): int
    {
        $pluginId = $this->argument('plugin_id');
        $pluginManager->deactivate($pluginId);
        $this->info("Plugin '{$pluginId}' deactivated.");

        return self::SUCCESS;
    }
}
