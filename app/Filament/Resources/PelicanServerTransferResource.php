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

    protected static string|UnitEnum|null $navigationGroup = 'Pelican Mirror';

    protected static ?int $navigationSort = 43;

    protected static ?string $navigationLabel = 'Server transfers';

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
                Tables\Columns\TextColumn::make('server.name')->label('Server')->searchable(),
                Tables\Columns\TextColumn::make('old_node')->label('From node')->placeholder('—'),
                Tables\Columns\TextColumn::make('new_node')->label('To node')->placeholder('—'),
                Tables\Columns\IconColumn::make('successful')->boolean()->placeholder('⏳'),
                Tables\Columns\IconColumn::make('archived')->boolean(),
                Tables\Columns\TextColumn::make('pelican_created_at')->label('Started')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('successful'),
                Tables\Filters\TernaryFilter::make('archived'),
            ])
            ->defaultSort('pelican_created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPelicanServerTransfers::route('/'),
        ];
    }
}
