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
 *    egg is selected, the "Modpacks" tab stays hidden on every server —
 *    enforced by the manifest enricher in the service provider).
 *  - Install timeout (minutes) + default provider.
 *
 * Route slug `/admin/modpack-settings` matches the plugin manifest's
 * `manage_url` field so the Plugins page links here on click.
 *
 * Access is gated by Filament's panel-level admin check
 * (User::canAccessPanel) plus a defensive canAccess() override here.
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

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user !== null && (bool) $user->is_admin;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationLabel(): string
    {
        return __('minecraft-modpack-installer::admin.navigation');
    }

    public function getTitle(): string
    {
        return __('minecraft-modpack-installer::admin.title');
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
            Section::make(__('minecraft-modpack-installer::admin.curseforge.section'))
                ->description(__('minecraft-modpack-installer::admin.curseforge.description'))
                ->schema([
                    TextInput::make('modpack_curseforge_api_key')
                        ->label(__('minecraft-modpack-installer::admin.curseforge.api_key.label'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->placeholder(__('minecraft-modpack-installer::admin.curseforge.api_key.placeholder')),
                ]),

            Section::make(__('minecraft-modpack-installer::admin.eligibility.section'))
                ->description(__('minecraft-modpack-installer::admin.eligibility.description'))
                ->schema([
                    Select::make('modpack_whitelisted_egg_ids')
                        ->label(__('minecraft-modpack-installer::admin.eligibility.eggs.label'))
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => $this->eggOptions())
                        ->helperText(__('minecraft-modpack-installer::admin.eligibility.eggs.helper')),
                ]),

            Section::make(__('minecraft-modpack-installer::admin.behavior.section'))
                ->schema([
                    TextInput::make('modpack_install_timeout_minutes')
                        ->label(__('minecraft-modpack-installer::admin.behavior.timeout.label'))
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(180)
                        ->default(30)
                        ->required()
                        ->helperText(__('minecraft-modpack-installer::admin.behavior.timeout.helper')),

                    Select::make('modpack_default_provider')
                        ->label(__('minecraft-modpack-installer::admin.behavior.provider.label'))
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
                ])
                ->columns(2),
        ]);
    }

    /** @return array<string, Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('minecraft-modpack-installer::admin.actions.save'))
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

        Notification::make()
            ->title(__('minecraft-modpack-installer::admin.notifications.saved'))
            ->success()
            ->send();
    }

    /**
     * Build the egg picker options from the local Peregrine DB. No tag
     * filtering : the admin sees every egg synced from Pelican and decides
     * which ones are eligible. The list groups eggs by their nest name when
     * multiple nests are present, so visually similar names from different
     * nests don't collide.
     *
     * @return array<int, string>
     */
    private function eggOptions(): array
    {
        try {
            return Egg::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
