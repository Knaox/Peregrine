<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerPlanResource\Pages;
use App\Filament\Resources\ServerPlanResource\ServerPlanFormSchema;
use App\Filament\Resources\ServerPlanResource\ServerPlanTable;
use App\Models\ServerPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Server plans pushed by the Shop via Bridge API. The page is hidden when
 * Bridge is not in Shop+Stripe mode (cf. shouldRegisterNavigation).
 *
 * UX rule : the form is split in two — Section 1 displays Shop-owned fields
 * read-only (name, billing, RAM/CPU/disk promised to the customer), Section 2
 * is the Peregrine-only technical config (egg, node, docker, port mapping,
 * env mapping, runtime toggles).
 *
 * Form schema and table configuration live in sibling classes under
 * `ServerPlanResource/` (FormSchema + Table) — keeps this Resource focused
 * on Filament wiring (model, navigation, page routing).
 */
class ServerPlanResource extends Resource
{
    protected static ?string $model = ServerPlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Servers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Plans';

    public static function shouldRegisterNavigation(): bool
    {
        // Plans are only meaningful in Shop+Stripe mode (Shop pushes them).
        // In Paymenter mode, Paymenter manages the catalogue itself.
        return app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            ServerPlanFormSchema::shopMirrorSection(),
            ServerPlanFormSchema::peregrineConfigSection(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ServerPlanTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServerPlans::route('/'),
            'edit' => Pages\EditServerPlan::route('/{record}/edit'),
        ];
    }
}
