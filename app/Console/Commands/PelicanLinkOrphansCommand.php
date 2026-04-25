<?php

namespace App\Console\Commands;

use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Sweep users with `pelican_user_id = NULL` and dispatch a link job for
 * each. Runs daily at 04:00 as a final safety net for users created during
 * Pelican outages (any of the 5 entry points may have failed silently if
 * the queue couldn't reach Pelican). Idempotent — the action short-circuits
 * users already linked, and the job is unique-per-user so duplicate
 * dispatches collapse.
 */
final class PelicanLinkOrphansCommand extends Command
{
    protected $signature = 'pelican:link-orphans {--dry-run : List orphans without dispatching jobs}';

    protected $description = 'Find users without a Pelican account and dispatch link jobs.';

    public function handle(): int
    {
        $orphans = User::whereNull('pelican_user_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get(['id', 'email', 'name']);

        if ($orphans->isEmpty()) {
            $this->info('No orphan users — every Peregrine user is linked to Pelican.');
            return self::SUCCESS;
        }

        $this->info("Found {$orphans->count()} user(s) without a Pelican account:");
        foreach ($orphans as $user) {
            $this->line("  #{$user->id} {$user->email} ({$user->name})");
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: no jobs dispatched.');
            return self::SUCCESS;
        }

        foreach ($orphans as $user) {
            LinkPelicanAccountJob::dispatch($user->id, 'orphan-command');
        }

        $this->info("Dispatched {$orphans->count()} LinkPelicanAccountJob(s).");
        return self::SUCCESS;
    }
}
