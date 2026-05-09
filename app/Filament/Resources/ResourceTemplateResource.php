<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ResourceTemplateResource\Pages;
use App\Filament\Resources\ResourceTemplateResource\ResourceTemplateFormSchema;
use App\Filament\Resources\ResourceTemplateResource\ResourceTemplateTable;
use App\Models\ResourceTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Resource templates centralise the Pelican spec tuple (RAM, CPU, disk,
 * swap, I/O weight, cpu_pinning) under a marketing-friendly name
 * (e.g. "Medium-Medium", "Performance-Large"). Multiple
 * `ServerConfiguration` rows can share the same template — editing
 * the template updates every configuration bound to it (and triggers
 * `configuration.updated` outbound webhooks for every shop).
 *
 * Form / Table / Pages are split into siblings to honour the 300-line
 * file rule.
 */
class ResourceTemplateResource extends Resource
{
    protected static ?string $model = ResourceTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Servers';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/resource_templates.resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('admin/resource_templates.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resource_templates.resource.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ResourceTemplateFormSchema::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResourceTemplateTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResourceTemplates::route('/'),
            'create' => Pages\CreateResourceTemplate::route('/create'),
            'edit' => Pages\EditResourceTemplate::route('/{record}/edit'),
        ];
    }
}
