<?php

namespace App\Filament\Widgets;

use App\Models\Egg;
use App\Models\Server;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        // Pending queue jobs — surfaced here because a stale worker silently
        // breaks every webhook-driven flow (Bridge provisioning, Stripe
        // lifecycle, Pelican mirror). Admin sees this number ticking up =
        // worker is dead = restart it. 0 jobs is the green nominal state.
        $pendingJobs = DB::table('jobs')->count();
        $queueColor = $pendingJobs === 0 ? 'success' : ($pendingJobs > 20 ? 'danger' : 'warning');
        $queueDescription = $pendingJobs === 0
            ? 'Queue worker healthy'
            : "Worker may be down — start with 'php artisan queue:work'";

        return [
            Stat::make('Total Users', User::count())
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
            Stat::make('Total Servers', Server::count())
                ->description('All servers')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('primary'),
            Stat::make('Active Servers', Server::whereIn('status', ['running', 'active'])->count())
                ->description('Running or active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Pending Jobs', $pendingJobs)
                ->description($queueDescription)
                ->descriptionIcon($pendingJobs === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($queueColor),
            Stat::make('Synced Eggs', Egg::count())
                ->description('From Pelican')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),
        ];
    }
}
