<?php

namespace App\Console\Commands;

use App\Services\MarketplaceService;
use Illuminate\Console\Command;

class PluginUpdateCommand extends Command
{
    protected $signature = 'plugin:update {plugin_id : The plugin ID to update}';

    protected $description = 'Update a plugin to the latest version from the marketplace';

    public function handle(MarketplaceService $marketplace): int
    {
        $pluginId = $this->argument('plugin_id');

        try {
            $this->info("Updating plugin: {$pluginId}...");
            $marketplace->update($pluginId);
            $this->info("Plugin '{$pluginId}' updated successfully.");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
