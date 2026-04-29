<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PelicanAllocationResource\Pages;
use App\Models\Pelican\Allocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PelicanAllocationResource extends Resource
{
    protected static ?string $model = Allocation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'Pelican Mirror';

    protected static ?int $navigationSort = 42;

    protected static ?string $navigationLabel = 'Allocations';

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
                Tables\Columns\TextColumn::make('node.name')->label('Node')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('server.name')->label('Server')->placeholder('— (free)'),
                Tables\Columns\TextColumn::make('ip')->searchable(),
                Tables\Columns\TextColumn::make('port')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('ip_alias')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_locked')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('server_id')->label('Assigned')->nullable(),
                Tables\Filters\TernaryFilter::make('is_locked'),
            ])
            ->defaultSort('port', 'asc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPelicanAllocations::route('/'),
        ];
    }
}
