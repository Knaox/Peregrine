<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PelicanBackupResource\Pages;
use App\Models\Pelican\Backup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only Filament page for the Phase 2 pelican_backups mirror table.
 * Useful for support: "show me all backups for server X" without ssh into prod.
 */
class PelicanBackupResource extends Resource
{
    protected static ?string $model = Backup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Pelican Mirror';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationLabel = 'Backups';

    public static function shouldRegisterNavigation(): bool
    {
        $value = (string) app(\App\Services\SettingsService::class)
            ->get('pelican_webhook_enabled', 'false');
        return $value === 'true' || $value === '1';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('server.name')->label('Server')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->limit(40),
                Tables\Columns\IconColumn::make('is_successful')->label('OK')->boolean(),
                Tables\Columns\IconColumn::make('is_locked')->label('Locked')->boolean(),
                Tables\Columns\TextColumn::make('bytes')->label('Size')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('pelican_created_at')->label('Pelican created')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_successful'),
                Tables\Filters\TernaryFilter::make('is_locked'),
            ])
            ->defaultSort('pelican_created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPelicanBackups::route('/'),
        ];
    }
}
