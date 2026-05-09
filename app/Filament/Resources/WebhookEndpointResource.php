<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WebhookEndpointResource\Pages;
use App\Filament\Resources\WebhookEndpointResource\WebhookEndpointFormSchema;
use App\Filament\Resources\WebhookEndpointResource\WebhookEndpointTable;
use App\Models\WebhookEndpoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Webhook endpoints — Peregrine pushes signed events to the URL each
 * row defines. Owned by a `Shop`. Form / Table / Pages live in sibling
 * classes to honour the 300-LoC rule.
 */
class WebhookEndpointResource extends Resource
{
    protected static ?string $model = WebhookEndpoint::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/webhook_endpoints.resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin/webhook_endpoints.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/webhook_endpoints.resource.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(WebhookEndpointFormSchema::fields());
    }

    public static function table(Table $table): Table
    {
        return WebhookEndpointTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookEndpoints::route('/'),
            'create' => Pages\CreateWebhookEndpoint::route('/create'),
            'edit' => Pages\EditWebhookEndpoint::route('/{record}/edit'),
        ];
    }
}
