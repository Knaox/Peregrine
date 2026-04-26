<?php

namespace Plugins\EggConfigEditor\Filament\Resources;

use App\Models\Egg;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Plugins\EggConfigEditor\Filament\Resources\EggConfigFileResource\Pages;
use Plugins\EggConfigEditor\Models\EggConfigFile;
use BackedEnum;

/**
 * Filament admin page for the egg config editor.
 *
 * Hidden from the sidebar — entered via the "Configure" button on the
 * `/admin/plugins` row (the plugin's `manage_url`).
 *
 * Form contract :
 *   - `egg_ids` is a multi-select (one config-file row may target several
 *     eggs that share the same file path).
 *   - `file_path` + `file_type` describe where to read/write the file.
 *   - All curation (labels, types, constraints, hidden parameters) lives in
 *     the plugin's i18n dictionary — never declared per-row.
 */
class EggConfigFileResource extends Resource
{
    protected static ?string $model = EggConfigFile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $modelLabel = 'Config file';

    protected static ?string $pluralModelLabel = 'Config files';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('File')
                ->description('Pick one or more eggs that all use this config file at the same path. Whatever the file contains, the plugin auto-detects parameters and translates known keys via the i18n dictionary.')
                ->schema([
                    Select::make('egg_ids')
                        ->label('Eggs')
                        ->multiple()
                        ->options(fn () => Egg::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Select every egg that uses this file at the path below. Common case : the same INI is reused across modded variants of one game.'),
                    TagsInput::make('file_paths')
                        ->label('File paths (try in order)')
                        ->placeholder('/server.properties')
                        ->helperText('One absolute path per tag. The plugin tries each in order and uses the first that exists on the server. Useful for multi-OS games (e.g. ARK ships either under LinuxServer/ or WindowsServer/). Press Enter after each path.')
                        ->required()
                        ->reorderable(),
                    Select::make('file_type')
                        ->label('File format')
                        ->options([
                            'properties' => '.properties (Minecraft & friends)',
                            'ini' => '.ini (ARK, Palworld, Valheim, …)',
                            'json' => '.json (modpacks, custom configs)',
                        ])
                        ->required()
                        ->live(),
                    TagsInput::make('sections')
                        ->label('INI sections to expose (whitelist)')
                        ->placeholder('ServerSettings')
                        ->helperText('Only for .ini files. Type each section name as it appears in the file (without the [brackets]). Leave empty to expose every section. Example for ARK: ServerSettings, MessageOfTheDay, SessionSettings — anything not listed (e.g. [ScalabilityGroups]) stays hidden and untouched on save.')
                        ->visible(fn ($get) => $get('file_type') === 'ini')
                        ->reorderable(),
                    Toggle::make('enabled')
                        ->label('Enabled')
                        ->default(true)
                        ->helperText('Disable to hide this file from players without losing the entry.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('egg_ids')
                    ->label('Eggs')
                    ->formatStateUsing(function ($state): string {
                        $ids = is_array($state) ? $state : [];
                        if ($ids === []) return '—';
                        $names = Egg::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
                        return implode(', ', $names);
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('file_paths')
                    ->label('Paths')
                    ->formatStateUsing(function ($state): string {
                        $paths = is_array($state) ? $state : [];
                        if ($paths === []) return '—';
                        if (count($paths) === 1) return (string) $paths[0];
                        return $paths[0] . ' (+' . (count($paths) - 1) . ')';
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('file_type')->label('Type')->badge(),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('id');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEggConfigFiles::route('/'),
            'create' => Pages\CreateEggConfigFile::route('/create'),
            'edit' => Pages\EditEggConfigFile::route('/{record}/edit'),
        ];
    }
}
