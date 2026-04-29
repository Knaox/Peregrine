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
        $pendingJobs = DB::table('jobs')->count();

        // Soft thresholds: 0 = green, 1-50 = info (queue is doing work),
        // 51-200 = warning (might be slow), 201+ = danger (likely a stuck worker).
        // The previous 20-job danger threshold was too aggressive — a healthy
        // panel can briefly burst above 20 during a Pelican sync.
        $queueColor = match (true) {
            $pendingJobs === 0 => 'success',
            $pendingJobs <= 50 => 'info',
            $pendingJobs <= 200 => 'warning',
            default => 'danger',
        };

        $queueDescription = $pendingJobs === 0
            ? __('admin.widgets.system_health.queue_worker') . ' — ' . __('admin.widgets.system_health.healthy')
            : "{$pendingJobs} jobs queued";

        return [
            Stat::make(__('admin.widgets.stats.users'), User::count())
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            Stat::make(__('admin.widgets.stats.servers'), Server::count())
                ->descriptionIcon('heroicon-o-server-stack')
                ->color('primary'),
            Stat::make(__('admin.widgets.stats.active_servers'), Server::whereIn('status', ['running', 'active'])->count())
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make(__('admin.widgets.stats.pending_jobs'), $pendingJobs)
                ->description($queueDescription)
                ->descriptionIcon($pendingJobs === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->color($queueColor),
            Stat::make(__('admin.widgets.stats.eggs'), Egg::count())
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),
        ];
    }
}
