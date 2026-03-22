<?php

namespace App\Filament\Widgets;

use App\Models\Egg;
use App\Models\Server;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
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
            Stat::make('Synced Eggs', Egg::count())
                ->description('From Pelican')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),
        ];
    }
}
