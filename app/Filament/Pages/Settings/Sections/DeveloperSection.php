<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

final class DeveloperSection
{
    public static function make(): Section
    {
        return Section::make(__('admin/settings.form.developer.section'))
            ->description(__('admin/settings.form.developer.description'))
            ->icon('heroicon-o-bug-ant')
            ->schema([
                Toggle::make('app_debug')
                    ->label(__('admin/settings.form.developer.debug'))
                    ->helperText(__('admin/settings.form.developer.debug_helper')),
            ])->columns(1);
    }
}
