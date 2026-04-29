<?php

namespace App\Filament\Pages;

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
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.plugins';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.plugins.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.plugins.title');
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
        // an `official` flag derived either from the registry entry or from
        // the plugin's own author field (covers the "marketplace
        // unreachable" case).
        try {
            $registry = app(MarketplaceService::class)->listWithStatus();
            $registryById = collect($registry)->keyBy('id');

            foreach ($installed as &$p) {
                $entry = $registryById->get($p['id']);
                $p['latest_version'] = $entry['version'] ?? null;
                $p['update_available'] = $entry !== null
                    && !empty($entry['update_available']);
                $p['official'] = !empty($entry['official'])
                    || ($p['author'] ?? null) === 'Peregrine Team';
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

    public function updatePlugin(string $id): void
    {
        try {
            app(MarketplaceService::class)->update($id);

            Notification::make()
                ->title('Plugin updated')
                ->body("'{$id}' has been upgraded to the latest version.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Update failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
        $this->loadMarketplace();
    }

    public function loadMarketplace(): void
    {
        if (! config('panel.marketplace.enabled', true)) {
            return;
        }

        try {
            $marketplace = app(MarketplaceService::class);
            $this->marketplacePlugins = $marketplace->listWithStatus();
        } catch (\Throwable) {
            $this->marketplacePlugins = [];
        }
    }

    public function refreshMarketplace(): void
    {
        try {
            app(MarketplaceService::class)->clearCache();
        } catch (\Throwable) {
            // noop — cache store unavailable is non-fatal.
        }

        $this->loadMarketplace();

        Notification::make()
            ->title('Marketplace refreshed')
            ->body(count($this->marketplacePlugins) . ' plugin(s) listed.')
            ->success()
            ->send();
    }

    public function activatePlugin(string $id): void
    {
        try {
            app(PluginManager::class)->activate($id);

            Notification::make()
                ->title('Plugin activated')
                ->body("'{$id}' has been activated successfully.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Activation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
    }

    public function uninstallPlugin(string $id): void
    {
        try {
            app(PluginManager::class)->uninstall($id);

            Notification::make()
                ->title('Plugin uninstalled')
                ->body("'{$id}' has been removed.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Uninstall failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
        $this->loadMarketplace();
    }

    public function deactivatePlugin(string $id): void
    {
        app(PluginManager::class)->deactivate($id);

        Notification::make()
            ->title('Plugin deactivated')
            ->body("'{$id}' has been deactivated.")
            ->success()
            ->send();

        $this->loadPlugins();
    }

    public function openSettings(string $id): void
    {
        $this->settingsPluginId = $id;
        $plugin = Plugin::where('plugin_id', $id)->first();
        $this->settingsData = $plugin?->settings ?? [];

        // Fill defaults from schema
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
            ->title('Settings saved')
            ->success()
            ->send();

        $this->settingsPluginId = null;
        $this->dispatch('close-modal', id: 'plugin-settings');
    }

    public function installFromMarketplace(string $id): void
    {
        try {
            app(MarketplaceService::class)->install($id);

            Notification::make()
                ->title('Plugin installed')
                ->body("'{$id}' has been downloaded and installed.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Installation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
        $this->loadMarketplace();
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

            $component = match ($type) {
                'number' => TextInput::make("settingsData.{$key}")->label($label)->numeric(),
                'toggle' => Toggle::make("settingsData.{$key}")->label($label),
                'select' => Select::make("settingsData.{$key}")->label($label)->options($field['options'] ?? []),
                'textarea' => Textarea::make("settingsData.{$key}")->label($label)->rows(3),
                default => TextInput::make("settingsData.{$key}")->label($label),
            };

            $fields[] = $component;
        }

        return $fields;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema($this->getSettingsFields());
    }
}
