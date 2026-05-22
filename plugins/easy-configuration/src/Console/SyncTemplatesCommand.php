<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Console;

use Illuminate\Console\Command;
use Plugins\EasyConfiguration\Services\Templates\TemplateRegistry;

/**
 * Rebuilds the template cache from the on-disk JSON files. Useful after
 * dropping shared templates into storage or in CI/install hooks; the admin API
 * also re-syncs on every mutation.
 */
final class SyncTemplatesCommand extends Command
{
    protected $signature = 'easy-config:sync-templates';

    protected $description = 'Rebuild the Easy Configuration template cache from the on-disk JSON files.';

    public function handle(TemplateRegistry $registry): int
    {
        $registry->rebuild();
        $this->info('Easy Configuration templates synced.');

        return self::SUCCESS;
    }
}
