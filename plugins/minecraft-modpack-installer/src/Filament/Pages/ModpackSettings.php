<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Filament\Pages;

use App\Models\Egg;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService;

/**
 * Admin settings page for the Modpack Installer plugin. Three knobs :
 *
 *  - CurseForge API key (encrypted at rest via ModpackSettingsService).
 *  - Whitelist of egg IDs allowed to install modpacks (until at least one
 *    egg is selected, the "Modpacks" tab stays hidden on every server).
 *  - Install timeout (minutes) + default provider.
 *
 * Route slug `/admin/modpack-settings` matches the plugin manifest's
 * `manage_url` field so the Plugins page links here on click.
 */
class ModpackSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 70;

    protected string $view = 'plugins.minecraft-modpack-installer::filament.pages.modpack-settings';

    protected static ?string $slug = 'modpack-settings';

    public ?string $modpack_curseforge_api_key = '';

    /** @var list<int> */
    public array $modpack_whitelisted_egg_ids = [];

    public int $modpack_install_timeout_minutes = 30;

    public string $modpack_default_provider = 'modrinth';

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.modpack_settings.navigation', [], app()->getLocale())
            ?: 'Modpack Installer';
    }

    public function getTitle(): string
    {
        return __('admin.pages.modpack_settings.title', [], app()->getLocale())
            ?: 'Modpack Installer';
    }

    public function mount(): void
    {
        $settings = app(ModpackSettingsService::class);

        // Never expose the stored CurseForge key — admin types a new one to replace.
        $this->modpack_curseforge_api_key = '';
        $this->modpack_whitelisted_egg_ids = $settings->whitelistedEggIds();
        $this->modpack_install_timeout_minutes = $settings->installTimeoutMinutes();
        $this->modpack_default_provider = $settings->defaultProvider()->value;

        $this->form->fill([
            'modpack_curseforge_api_key' => '',
            'modpack_whitelisted_egg_ids' => $this->modpack_whitelisted_egg_ids,
            'modpack_install_timeout_minutes' => $this->modpack_install_timeout_minutes,
            'modpack_default_provider' => $this->modpack_default_provider,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('CurseForge')
                ->description('Required to enable the CurseForge provider. Get a key at console.curseforge.com.')
                ->schema([
                    TextInput::make('modpack_curseforge_api_key')
                        ->label('CurseForge API key')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->placeholder('Leave blank to keep the existing key'),
                ]),

            Section::make('Eligible servers')
                ->description('Pick the eggs allowed to install modpacks. Until at least one is selected, the Modpacks tab stays hidden on every server.')
                ->schema([
                    Select::make('modpack_whitelisted_egg_ids')
                        ->label('Allowed eggs')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => $this->eggOptions())
                        ->helperText('The list shows eggs tagged "minecraft" first, then everything else when no Minecraft-tagged egg exists.'),
                ]),

            Section::make('Behavior')
                ->schema([
                    TextInput::make('modpack_install_timeout_minutes')
                        ->label('Install timeout (minutes)')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(180)
                        ->default(30)
                        ->required()
                        ->helperText('Beyond this duration without progress, the install is auto-marked failed by the reconciler cron.'),

                    Select::make('modpack_default_provider')
                        ->label('Default provider')
                        ->required()
                        ->options([
                            'modrinth' => 'Modrinth',
                            'curseforge' => 'CurseForge',
                            'atlauncher' => 'ATLauncher',
                            'ftb' => 'Feed The Beast',
                            'technic' => 'Technic',
                            'voidswrath' => 'VoidsWrath',
                        ])
                        ->default('modrinth'),
                ])
                ->columns(2),
        ]);
    }

    /** @return array<string, Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(ModpackSettingsService::class);

        $newKey = (string) ($data['modpack_curseforge_api_key'] ?? '');
        if ($newKey !== '') {
            $settings->setCurseforgeApiKey($newKey);
        }

        $whitelist = $data['modpack_whitelisted_egg_ids'] ?? [];
        if (! is_array($whitelist)) {
            $whitelist = [];
        }
        $settings->setWhitelistedEggIds(array_map('intval', $whitelist));

        $settings->setInstallTimeoutMinutes((int) ($data['modpack_install_timeout_minutes'] ?? 30));

        $providerValue = (string) ($data['modpack_default_provider'] ?? 'modrinth');
        $providerEnum = ModpackProvider::tryFrom($providerValue) ?? ModpackProvider::Modrinth;
        $settings->setDefaultProvider($providerEnum);

        // Don't keep the typed CurseForge key in the form state after save.
        $this->modpack_curseforge_api_key = '';
        $this->form->fill([
            'modpack_curseforge_api_key' => '',
            'modpack_whitelisted_egg_ids' => $settings->whitelistedEggIds(),
            'modpack_install_timeout_minutes' => $settings->installTimeoutMinutes(),
            'modpack_default_provider' => $settings->defaultProvider()->value,
        ]);

        Notification::make()->title('Settings saved')->success()->send();
    }

    /**
     * Build the egg picker options. Filter to Minecraft-tagged eggs first;
     * if none have the tag, fall back to the full list so the admin still
     * has something to choose from on a fresh install where eggs haven't
     * been re-tagged yet.
     *
     * @return array<int, string>
     */
    private function eggOptions(): array
    {
        try {
            $minecraftEggs = Egg::query()
                ->whereJsonContains('tags', 'minecraft')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();

            if (! empty($minecraftEggs)) {
                return $minecraftEggs;
            }

            return Egg::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
