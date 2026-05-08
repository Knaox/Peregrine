<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Filament\Resources;

use App\Models\Egg;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Plugins\MinecraftModpackInstaller\Filament\Resources\ModpackConfigResource\Pages;
use Plugins\MinecraftModpackInstaller\Models\ModpackConfig;
use Plugins\MinecraftModpackInstaller\Services\EggImporter;

/**
 * Singleton admin page for the Minecraft Modpack Installer plugin. Mirrors
 * the structure of `Plugins\ArkModsInstaller\Filament\Resources\ArkModsConfigResource`
 * so the admin gets the same UX across both plugins (eligibility, page
 * route/label, sort defaults, paging, cache TTL).
 *
 * Hidden from the admin sidebar — the only way in is the "Configure" button
 * on `/admin/plugins`, which links to the resource via the plugin's
 * `manage_url`.
 */
class ModpackConfigResource extends Resource
{
    protected static ?string $model = ModpackConfig::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $modelLabel = 'Modpack configuration';

    protected static ?string $pluralModelLabel = 'Modpack configuration';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (bool) $user->is_admin;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('minecraft-modpack-installer::admin.eligibility.section'))
                ->description(__('minecraft-modpack-installer::admin.eligibility.description'))
                ->schema([
                    Select::make('egg_ids')
                        ->label(__('minecraft-modpack-installer::admin.fields.egg_ids.label'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => Egg::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->helperText(__('minecraft-modpack-installer::admin.fields.egg_ids.help')),
                ]),

            Section::make(__('minecraft-modpack-installer::admin.curseforge.section'))
                ->description(__('minecraft-modpack-installer::admin.curseforge.description'))
                ->schema([
                    TextInput::make('curseforge_api_key')
                        ->label(__('minecraft-modpack-installer::admin.curseforge.api_key.label'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->placeholder(__('minecraft-modpack-installer::admin.curseforge.api_key.placeholder')),
                ]),

            Section::make(__('minecraft-modpack-installer::admin.providers.section'))
                ->schema([
                    Select::make('default_provider')
                        ->label(__('minecraft-modpack-installer::admin.fields.default_provider.label'))
                        ->required()
                        ->options([
                            'modrinth' => __('minecraft-modpack-installer::admin.providers.modrinth'),
                            'curseforge' => __('minecraft-modpack-installer::admin.providers.curseforge'),
                            'atlauncher' => __('minecraft-modpack-installer::admin.providers.atlauncher'),
                            'ftb' => __('minecraft-modpack-installer::admin.providers.ftb'),
                            'technic' => __('minecraft-modpack-installer::admin.providers.technic'),
                            'voidswrath' => __('minecraft-modpack-installer::admin.providers.voidswrath'),
                        ])
                        ->default('modrinth'),

                    Select::make('default_sort')
                        ->label(__('minecraft-modpack-installer::admin.fields.default_sort.label'))
                        ->required()
                        ->options([
                            'relevance' => __('minecraft-modpack-installer::admin.sort.relevance'),
                            'downloads' => __('minecraft-modpack-installer::admin.sort.downloads'),
                            'updated' => __('minecraft-modpack-installer::admin.sort.updated'),
                            'newest' => __('minecraft-modpack-installer::admin.sort.newest'),
                        ])
                        ->default('relevance')
                        ->helperText(__('minecraft-modpack-installer::admin.fields.default_sort.help')),
                ])
                ->columns(2),

            Section::make(__('minecraft-modpack-installer::admin.display.section'))
                ->schema([
                    TextInput::make('page_label')
                        ->label(__('minecraft-modpack-installer::admin.fields.page_label.label'))
                        ->maxLength(255)
                        ->placeholder('Modpacks')
                        ->helperText(__('minecraft-modpack-installer::admin.fields.page_label.help')),

                    TextInput::make('page_route')
                        ->label(__('minecraft-modpack-installer::admin.fields.page_route.label'))
                        ->default('/modpacks')
                        ->required()
                        ->maxLength(64)
                        ->regex('/^\/[a-z0-9\-]+$/')
                        ->helperText(__('minecraft-modpack-installer::admin.fields.page_route.help')),

                    Select::make('modpacks_per_page')
                        ->label(__('minecraft-modpack-installer::admin.fields.modpacks_per_page.label'))
                        ->required()
                        ->options([10 => '10', 25 => '25', 50 => '50'])
                        ->default(25)
                        ->helperText(__('minecraft-modpack-installer::admin.fields.modpacks_per_page.help')),
                ])
                ->columns(3),

            Section::make(__('minecraft-modpack-installer::admin.behavior.section'))
                ->schema([
                    TextInput::make('install_timeout_minutes')
                        ->label(__('minecraft-modpack-installer::admin.fields.install_timeout_minutes.label'))
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(180)
                        ->default(30)
                        ->required()
                        ->helperText(__('minecraft-modpack-installer::admin.fields.install_timeout_minutes.help')),

                    TextInput::make('cache_ttl_seconds')
                        ->label(__('minecraft-modpack-installer::admin.fields.cache_ttl_seconds.label'))
                        ->numeric()
                        ->minValue(60)
                        ->maxValue(86400)
                        ->default(3600)
                        ->required()
                        ->helperText(__('minecraft-modpack-installer::admin.fields.cache_ttl_seconds.help')),
                ])
                ->columns(2),

            // ── Java compatibility ────────────────────────────────────────
            // Every field here is OPTIONAL. Leave blank to keep the bundled
            // plugin defaults from `config/java-compatibility.php`. Filling
            // a field overrides it: the rules list is replaced wholesale,
            // the images map is merged per-key, and the default Java is
            // a single value override.
            Section::make(__('minecraft-modpack-installer::admin.java.section'))
                ->description(__('minecraft-modpack-installer::admin.java.description'))
                ->schema([
                    Select::make('default_java')
                        ->label(__('minecraft-modpack-installer::admin.java.default_java.label'))
                        ->options([
                            8 => 'Java 8',
                            11 => 'Java 11',
                            16 => 'Java 16',
                            17 => 'Java 17',
                            21 => 'Java 21',
                        ])
                        ->placeholder(__('minecraft-modpack-installer::admin.java.default_java.placeholder'))
                        ->helperText(__('minecraft-modpack-installer::admin.java.default_java.help')),

                    KeyValue::make('java_images')
                        ->label(__('minecraft-modpack-installer::admin.java.images.label'))
                        ->keyLabel(__('minecraft-modpack-installer::admin.java.images.key_label'))
                        ->valueLabel(__('minecraft-modpack-installer::admin.java.images.value_label'))
                        ->reorderable(false)
                        ->addActionLabel(__('minecraft-modpack-installer::admin.java.images.add'))
                        ->helperText(__('minecraft-modpack-installer::admin.java.images.help')),

                    Repeater::make('java_rules')
                        ->label(__('minecraft-modpack-installer::admin.java.rules.label'))
                        ->helperText(__('minecraft-modpack-installer::admin.java.rules.help'))
                        ->addActionLabel(__('minecraft-modpack-installer::admin.java.rules.add'))
                        ->reorderableWithDragAndDrop()
                        ->columns(4)
                        ->schema([
                            Select::make('loader')
                                ->label(__('minecraft-modpack-installer::admin.java.rules.fields.loader'))
                                ->options([
                                    'forge' => 'Forge',
                                    'neoforge' => 'NeoForge',
                                    'fabric' => 'Fabric',
                                    'quilt' => 'Quilt',
                                ])
                                ->placeholder(__('minecraft-modpack-installer::admin.java.rules.fields.loader_any'))
                                ->native(false),

                            TextInput::make('mc_min')
                                ->label(__('minecraft-modpack-installer::admin.java.rules.fields.mc_min'))
                                ->placeholder('1.18')
                                ->regex('/^\d+\.\d+(\.\d+)?$/'),

                            TextInput::make('mc_max')
                                ->label(__('minecraft-modpack-installer::admin.java.rules.fields.mc_max'))
                                ->placeholder('1.20.4')
                                ->regex('/^\d+\.\d+(\.\d+)?$/'),

                            Select::make('java')
                                ->label(__('minecraft-modpack-installer::admin.java.rules.fields.java'))
                                ->options([
                                    8 => '8',
                                    11 => '11',
                                    16 => '16',
                                    17 => '17',
                                    21 => '21',
                                ])
                                ->required(),
                        ]),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([])->paginated(false);
    }

    /**
     * Header action shared by the Edit page — pushes the bundled installer
     * egg into Pelican. Idempotent (UUID match → in-place update). Also
     * exposed via the `modpacks:import-egg` artisan command.
     */
    public static function importEggAction(): Action
    {
        return Action::make('importEgg')
            ->label(__('minecraft-modpack-installer::admin.actions.import_egg.label'))
            ->tooltip(__('minecraft-modpack-installer::admin.actions.import_egg.tooltip'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->requiresConfirmation()
            ->action(function (): void {
                try {
                    $eggId = app(EggImporter::class)->ensureImported(force: true);
                    Notification::make()
                        ->title(__('minecraft-modpack-installer::admin.notifications.egg_imported', ['id' => $eggId]))
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title(__('minecraft-modpack-installer::admin.notifications.egg_import_failed', ['reason' => $e->getMessage()]))
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModpackConfigs::route('/'),
            'edit' => Pages\EditModpackConfig::route('/{record}/edit'),
        ];
    }
}
