<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WebhookDeliveryResource\Pages;
use App\Filament\Resources\WebhookDeliveryResource\WebhookDeliveryTable;
use App\Models\WebhookDelivery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Read-only audit page for webhook deliveries. Each row aggregates the
 * retry attempts of one (event, endpoint) pair. Replay actions live in
 * the table — no create/update/delete from the admin UI ; the engine is
 * the only writer.
 */
class WebhookDeliveryResource extends Resource
{
    protected static ?string $model = WebhookDelivery::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/webhook_deliveries.resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin/webhook_deliveries.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/webhook_deliveries.resource.plural');
    }

    public static function table(Table $table): Table
    {
        return WebhookDeliveryTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookDeliveries::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
