<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResourceTemplateResource;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Form schema for `ResourceTemplateResource`. Two sections :
 *   - Identity     : the marketing-friendly name (UNIQUE)
 *   - Resources    : RAM / CPU / disk / swap / I/O weight / CPU pinning
 *
 * The fields mirror what used to live inline on `ServerConfiguration`
 * — admins editing a template see exactly the same shape they were
 * used to before the extraction.
 */
final class ResourceTemplateFormSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin/resource_templates.sections.identity'))
                ->icon('heroicon-o-identification')
                ->schema(self::identityFields())
                ->columnSpanFull(),

            Section::make(__('admin/resource_templates.sections.resources'))
                ->icon('heroicon-o-cpu-chip')
                ->schema(self::resourceFields())
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    /** @return array<int, mixed> */
    private static function identityFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin/resource_templates.fields.name'))
                ->helperText(__('admin/resource_templates.helpers.name'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private static function resourceFields(): array
    {
        return [
            TextInput::make('ram')
                ->label(__('admin/resource_templates.fields.ram'))
                ->numeric()->minValue(0)->required()->suffix('MB'),

            TextInput::make('cpu')
                ->label(__('admin/resource_templates.fields.cpu'))
                ->numeric()->minValue(0)->required()->suffix('%'),

            TextInput::make('disk')
                ->label(__('admin/resource_templates.fields.disk'))
                ->numeric()->minValue(0)->required()->suffix('MB'),

            TextInput::make('swap_mb')
                ->label(__('admin/resource_templates.fields.swap'))
                ->numeric()->minValue(-1)->default(0)->suffix('MB'),

            TextInput::make('io_weight')
                ->label(__('admin/resource_templates.fields.io_weight'))
                ->numeric()->minValue(10)->maxValue(1000)->default(500)
                ->helperText(__('admin/resource_templates.helpers.io_weight')),

            TextInput::make('cpu_pinning')
                ->label(__('admin/resource_templates.fields.cpu_pinning'))
                ->placeholder('e.g. 0-3')
                ->helperText(__('admin/resource_templates.helpers.cpu_pinning'))
                ->maxLength(64),
        ];
    }
}
