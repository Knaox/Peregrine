<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HandlesPluginActions;
use App\Filament\Pages\Concerns\HandlesPluginUpload;
use App\Models\Plugin;
use App\Services\MarketplaceService;
use App\Services\PluginManager;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class Plugins extends Page implements HasForms
{
    use HandlesPluginActions;
    use HandlesPluginUpload;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.plugins';

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/plugins.page.navigation');
    }

    public function getTitle(): string
    {
        return __('admin/plugins.page.title');
    }

    /** @var array<int, array<string, mixed>> */
    public array $plugins = [];

    /** @var array<int, array<string, mixed>> */
    public array $marketplacePlugins = [];

    public ?string $settingsPluginId = null;

    /** @var array<string, mixed> */
    public array $settingsData = [];

    public string $activeTab = 'installed';

    public function mount(): void
    {
        $this->loadPlugins();
        $this->loadMarketplace();
    }

    public function loadPlugins(): void
    {
        $installed = app(PluginManager::class)->allWithStatus();

        // Annotate each installed plugin with marketplace upgrade info +
        // an `official` flag derived either from the registry entry or
        // from the plugin's own author field (covers the "marketplace
        // unreachable" case).
        try {
            $registry = app(MarketplaceService::class)->listWithStatus();
            $registryById = collect($registry)->keyBy('id');

            foreach ($installed as &$p) {
                $entry = $registryById->get($p['id']);
                $p['latest_version'] = $entry['version'] ?? null;
                $p['update_available'] = $entry !== null && ! empty($entry['update_available']);
                $p['official'] = ! empty($entry['official']) || ($p['author'] ?? null) === 'Peregrine Team';
            }
            unset($p);
        } catch (\Throwable) {
            foreach ($installed as &$p) {
                $p['official'] = ($p['author'] ?? null) === 'Peregrine Team';
            }
            unset($p);
        }

        $this->plugins = $installed;
    }

    public function loadMarketplace(): void
    {
        if (! config('panel.marketplace.enabled', true)) {
            return;
        }

        try {
            $this->marketplacePlugins = app(MarketplaceService::class)->listWithStatus();
        } catch (\Throwable) {
            $this->marketplacePlugins = [];
        }
    }

    /**
     * Aggregate counters used by the page header.
     *
     * @return array{installed: int, active: int, updates: int, marketplace: int}
     */
    public function getStats(): array
    {
        $installed = count($this->plugins);
        $active = collect($this->plugins)->where('is_active', true)->count();
        $updates = collect($this->plugins)->where('update_available', true)->count();
        $marketplace = count($this->marketplacePlugins);

        return compact('installed', 'active', 'updates', 'marketplace');
    }

    /**
     * Distinct category tags across the marketplace listing — used to
     * render the category filter chips on the marketplace tab.
     *
     * @return array<int, string>
     */
    public function getCategories(): array
    {
        return collect($this->marketplacePlugins)
            ->flatMap(fn (array $p) => $p['tags'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function openSettings(string $id): void
    {
        $this->settingsPluginId = $id;
        $plugin = Plugin::where('plugin_id', $id)->first();
        $this->settingsData = $plugin?->settings ?? [];

        // Fill defaults from schema so toggles + selects render in their
        // declared default state on first open.
        $manifest = app(PluginManager::class)->getManifest($id);
        $schema = $manifest['settings_schema'] ?? [];

        foreach ($schema as $field) {
            if (! isset($this->settingsData[$field['key']]) && isset($field['default'])) {
                $this->settingsData[$field['key']] = $field['default'];
            }
        }

        $this->dispatch('open-modal', id: 'plugin-settings');
    }

    public function saveSettings(): void
    {
        if (! $this->settingsPluginId) {
            return;
        }

        $plugin = Plugin::where('plugin_id', $this->settingsPluginId)->first();

        if ($plugin) {
            $plugin->update(['settings' => $this->settingsData]);
        }

        Notification::make()
            ->title(__('admin/plugins.notifications.settings_saved'))
            ->success()
            ->send();

        $this->settingsPluginId = null;
        $this->dispatch('close-modal', id: 'plugin-settings');
    }

    /**
     * Build dynamic Filament form fields from a plugin's settings_schema.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public function getSettingsFields(): array
    {
        if (! $this->settingsPluginId) {
            return [];
        }

        $manifest = app(PluginManager::class)->getManifest($this->settingsPluginId);
        $schema = $manifest['settings_schema'] ?? [];
        $fields = [];

        foreach ($schema as $field) {
            $key = $field['key'];
            $label = $field['label'] ?? $key;
            $type = $field['type'] ?? 'text';

            $fields[] = match ($type) {
                'number' => TextInput::make("settingsData.{$key}")->label($label)->numeric(),
                'toggle' => Toggle::make("settingsData.{$key}")->label($label),
                'select' => Select::make("settingsData.{$key}")->label($label)->options($field['options'] ?? []),
                'textarea' => Textarea::make("settingsData.{$key}")->label($label)->rows(3),
                default => TextInput::make("settingsData.{$key}")->label($label),
            };
        }

        return $fields;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema($this->getSettingsFields());
    }
}
