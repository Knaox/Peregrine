<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ServerConfigurationResource\Pages;
use App\Filament\Resources\ServerConfigurationResource\ServerConfigurationFormSchema;
use App\Filament\Resources\ServerConfigurationResource\ServerConfigurationTable;
use App\Models\ServerConfiguration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Technical server configurations — admin-owned. Each configuration captures
 * the Pelican-side recipe (egg, node selection, runtime toggles, env mapping,
 * resource limits) that gets fed to `ProvisionServerJob` when a customer
 * checks out.
 *
 * Form schema and table configuration live in sibling classes under
 * `ServerConfigurationResource/` (FormSchema + Table) — keeps this Resource
 * focused on Filament wiring (model, navigation, page routing).
 */
class ServerConfigurationResource extends Resource
{
    protected static ?string $model = ServerConfiguration::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = \App\Filament\Clusters\Shops::class;

    public static function getNavigationLabel(): string
    {
        return __('admin/server_configurations.resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin/server_configurations.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/server_configurations.resource.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            ServerConfigurationFormSchema::tabs(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ServerConfigurationTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServerConfigurations::route('/'),
            'create' => Pages\CreateServerConfiguration::route('/create'),
            'edit' => Pages\EditServerConfiguration::route('/{record}/edit'),
        ];
    }
}
