<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

final class DeveloperSection
{
    public static function make(): Section
    {
        return Section::make('Developer')
            ->description('Low-level toggles. Leave debug mode OFF in production — when enabled, full PHP stack traces are exposed to clients on errors.')
            ->icon('heroicon-o-bug-ant')
            ->schema([
                Toggle::make('app_debug')
                    ->label('Enable debug mode (APP_DEBUG)')
                    ->helperText('Stored in DB (table `settings`) — survives a Docker stack rebuild. Applies on the next request, no container restart needed.'),
            ])->columns(1);
    }
}
