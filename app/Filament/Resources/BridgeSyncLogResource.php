<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BridgeSyncLogResource\Pages;
use App\Models\BridgeSyncLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only audit page for inbound Bridge calls from the Shop. Each row
 * captures one HTTP exchange (signature outcome, payload, response). Hidden
 * when Bridge is disabled (matches ServerPlanResource gating).
 */
class BridgeSyncLogResource extends Resource
{
    protected static ?string $model = BridgeSyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Bridge sync logs';

    public static function shouldRegisterNavigation(): bool
    {
        return app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attempted_at')
                    ->dateTime()
                    ->sortable()
                    ->label('When'),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'upsert' ? 'info' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shop_plan_id')
                    ->label('Shop ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('serverPlan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('response_status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        $state >= 200 && $state < 300 => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('signature_valid')
                    ->label('HMAC')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options(['upsert' => 'Upsert', 'delete' => 'Delete']),
                Tables\Filters\TernaryFilter::make('signature_valid')->label('Signature valid'),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('attempted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBridgeSyncLogs::route('/'),
        ];
    }
}
