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

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.pelican_backups.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.pelican_backups.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.pelican_backups.plural');
    }

    /**
     * Hidden from the sidebar — backups don't need a dedicated admin page.
     * The data still lands in `pelican_backups` for support audit; query the
     * table directly when needed. Re-enable by returning the original
     * `pelican_webhook_enabled` check if a future need surfaces.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-archive-box')
            ->emptyStateHeading(__('admin.resources.pelican_backups.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.logs'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPelicanBackups::route('/'),
        ];
    }
}
