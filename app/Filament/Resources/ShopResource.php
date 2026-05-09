<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ShopResource\Pages;
use App\Filament\Resources\ShopResource\RelationManagers\ApiKeysRelationManager;
use App\Filament\Resources\ShopResource\RelationManagers\ServerConfigurationsRelationManager;
use App\Filament\Resources\ShopResource\ShopFormSchema;
use App\Filament\Resources\ShopResource\ShopTable;
use App\Models\Shop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Admin Filament resource for `Shop` rows. The form / table / pages /
 * relation managers live in sibling classes under `ShopResource/` to
 * honour the 300-LoC-per-file rule.
 *
 * Navigation lives in the "Integrations" group with the configurations
 * resource — these are the two pieces an admin manages together when
 * onboarding a new third-party shop.
 */
class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = \App\Filament\Clusters\Shops::class;

    public static function getNavigationLabel(): string
    {
        return __('admin/shops.resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin/shops.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/shops.resource.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(ShopFormSchema::fields());
    }

    public static function table(Table $table): Table
    {
        return ShopTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ApiKeysRelationManager::class,
            ServerConfigurationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShops::route('/'),
            'create' => Pages\CreateShop::route('/create'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
        ];
    }
}
