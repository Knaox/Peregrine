<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PelicanServerTransferResource\Pages;
use App\Models\Pelican\ServerTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PelicanServerTransferResource extends Resource
{
    protected static ?string $model = ServerTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 43;

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.pelican_server_transfers.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.pelican_server_transfers.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.pelican_server_transfers.plural');
    }

    /**
     * Hidden from the sidebar — server transfers are exceptional events
     * surfaced through Pelican's own UI when needed. Local mirror retained
     * for support audit.
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
                Tables\Columns\TextColumn::make('server.name')->label('Server')->searchable(),
                Tables\Columns\TextColumn::make('old_node')->label('From node')->placeholder('—'),
                Tables\Columns\TextColumn::make('new_node')->label('To node')->placeholder('—'),
                Tables\Columns\IconColumn::make('successful')
                    ->boolean()
                    ->placeholder('—')
                    ->tooltip(fn ($state) => $state === null ? 'In progress' : null),
                Tables\Columns\IconColumn::make('archived')->boolean(),
                Tables\Columns\TextColumn::make('pelican_created_at')->label('Started')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('successful'),
                Tables\Filters\TernaryFilter::make('archived'),
            ])
            ->defaultSort('pelican_created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->emptyStateHeading(__('admin.resources.pelican_server_transfers.plural'))
            ->emptyStateDescription(__('admin.common.empty_states.logs'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPelicanServerTransfers::route('/'),
        ];
    }
}
