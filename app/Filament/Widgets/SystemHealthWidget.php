<?php

namespace App\Filament\Widgets;

use App\Enums\BridgeMode;
use App\Models\SyncLog;
use App\Services\Bridge\BridgeModeService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Surface a quick health check on the dashboard so admins notice silent
 * failures (dead worker, stale Pelican sync, broken cache, missing bridge
 * config) without having to dig into individual settings pages.
 */
class SystemHealthWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return __('admin.widgets.system_health.title');
    }

    protected function getStats(): array
    {
        $now = now();

        // Queue worker — 0 jobs is healthy; jobs piling up usually means the
        // worker is dead. We use the same thresholds as StatsOverview to keep
        // the signals consistent.
        $jobs = DB::table('jobs')->count();
        $workerColor = match (true) {
            $jobs === 0 => 'success',
            $jobs <= 50 => 'info',
            $jobs <= 200 => 'warning',
            default => 'danger',
        };
        $workerLabel = $jobs === 0
            ? __('admin.widgets.system_health.healthy')
            : "{$jobs} queued";

        // Last Pelican sync — check the latest successful run from sync_logs.
        $lastSync = SyncLog::query()
            ->whereIn('type', ['servers', 'users', 'eggs', 'nodes'])
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->first();

        $syncColor = 'gray';
        $syncLabel = __('admin.widgets.system_health.never');
        if ($lastSync && $lastSync->completed_at) {
            $minutes = $lastSync->completed_at->diffInMinutes($now);
            $syncLabel = $lastSync->completed_at->diffForHumans();
            $syncColor = match (true) {
                $minutes <= 10 => 'success',
                $minutes <= 60 => 'info',
                $minutes <= 240 => 'warning',
                default => 'danger',
            };
        }

        // Bridge mode — translate to a colored badge.
        $bridgeMode = app(BridgeModeService::class)->current();
        [$bridgeLabel, $bridgeColor] = match ($bridgeMode) {
            BridgeMode::ShopStripe => [__('admin.badges.bridge_mode.shop_stripe'), 'success'],
            BridgeMode::Paymenter => [__('admin.badges.bridge_mode.paymenter'), 'info'],
            default => [__('admin.badges.bridge_mode.disabled'), 'gray'],
        };

        // Cache — try a tiny round-trip to detect a broken store.
        try {
            $key = 'system_health.ping';
            Cache::put($key, true, 5);
            $cacheOk = Cache::get($key) === true;
            Cache::forget($key);
        } catch (\Throwable) {
            $cacheOk = false;
        }
        $cacheLabel = $cacheOk
            ? __('admin.widgets.system_health.healthy')
            : __('admin.widgets.system_health.down');
        $cacheColor = $cacheOk ? 'success' : 'danger';

        return [
            Stat::make(__('admin.widgets.system_health.queue_worker'), $workerLabel)
                ->descriptionIcon($jobs === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->color($workerColor),
            Stat::make(__('admin.widgets.system_health.last_sync'), $syncLabel)
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color($syncColor),
            Stat::make(__('admin.widgets.system_health.bridge_mode'), $bridgeLabel)
                ->descriptionIcon('heroicon-o-link')
                ->color($bridgeColor),
            Stat::make(__('admin.widgets.system_health.cache'), $cacheLabel)
                ->descriptionIcon($cacheOk ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->color($cacheColor),
        ];
    }
}
