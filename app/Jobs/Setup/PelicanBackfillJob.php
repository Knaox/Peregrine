<?php

namespace App\Jobs\Setup;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Queue wrapper around `php artisan pelican:backfill-mirrors`. Used by
 * the Setup Wizard's BackfillStep so the wizard UI stays responsive
 * while the (potentially long) backfill runs in the background.
 */
class PelicanBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function handle(): void
    {
        Artisan::call('pelican:backfill-mirrors', ['--resume' => true]);
    }
}
