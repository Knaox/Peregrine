<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PelicanWebhookLogResource\Pages;
use App\Models\PelicanProcessedEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only audit page for Pelican outgoing webhooks Peregrine has accepted
 * (Bridge Paymenter mode). One row per event, with idempotency hash, model
 * id, response status, and any handler error.
 *
 * Hidden when Bridge mode is not Paymenter — these rows are only generated
 * by the Pelican webhook receiver.
 */
class PelicanWebhookLogResource extends Resource
{
    protected static ?string $model = PelicanProcessedEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 62;

    protected static ?string $navigationLabel = 'Pelican webhook logs';

    public static function shouldRegisterNavigation(): bool
    {
        return app(\App\Services\Bridge\BridgeModeService::class)->isPaymenter();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable()
                    ->label('When'),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('pelican_model_id')
                    ->label('Pelican ID')
                    ->numeric()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->placeholder('—')
                    ->tooltip(fn ($state): ?string => $state),
                Tables\Columns\TextColumn::make('idempotency_hash')
                    ->label('Hash')
                    ->limit(12)
                    ->tooltip(fn ($state): ?string => $state)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('processed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPelicanWebhookLogs::route('/'),
        ];
    }
}
