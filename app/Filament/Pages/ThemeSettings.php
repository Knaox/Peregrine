<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Filament\Pages\Theme\ThemeFormSchema;
use App\Services\SettingsService;
use App\Services\ThemeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class ThemeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Theme';

    protected static ?string $navigationLabel = 'Theme';

    protected string $view = 'filament.pages.theme-settings';

    // Theme color properties
    public ?string $theme_primary = '';
    public ?string $theme_primary_hover = '';
    public ?string $theme_danger = '';
    public ?string $theme_warning = '';
    public ?string $theme_success = '';
    public ?string $theme_info = '';
    public ?string $theme_background = '';
    public ?string $theme_surface = '';
    public ?string $theme_surface_hover = '';
    public ?string $theme_surface_elevated = '';
    public ?string $theme_border = '';
    public ?string $theme_border_hover = '';
    public ?string $theme_text_primary = '';
    public ?string $theme_text_secondary = '';
    public ?string $theme_text_muted = '';
    public ?string $theme_radius = '';
    public ?string $theme_font = '';
    public ?string $theme_custom_css = '';

    // Card config properties
    public bool $show_egg_icon = true;
    public bool $show_egg_name = true;
    public bool $show_plan_name = true;
    public bool $show_status_badge = true;
    public bool $show_stats_bars = true;
    public bool $show_quick_actions = true;
    public bool $show_ip_port = false;
    public bool $show_uptime = false;
    public ?string $card_style = 'glass';
    public ?string $sort_default = 'name';
    public ?string $group_by = 'none';
    public ?int $columns_desktop = 3;
    public ?int $columns_tablet = 2;
    public ?int $columns_mobile = 1;

    // Sidebar config properties
    public ?string $sidebar_position = 'left';
    public ?string $sidebar_style = 'default';
    public bool $show_server_status = true;
    public bool $show_server_name = true;
    /** @var array<int, array<string, mixed>> */
    public array $entries = [];

    public function mount(): void
    {
        $settings = app(SettingsService::class);
        $themeService = app(ThemeService::class);

        $values = [];
        foreach (ThemeDefaults::COLORS as $key => $default) {
            $values[$key] = $settings->get($key, $default);
        }

        $card = $themeService->getCardConfig();
        $cols = $card['columns'] ?? [];
        $values += [
            'show_egg_icon' => $card['show_egg_icon'] ?? true,
            'show_egg_name' => $card['show_egg_name'] ?? true,
            'show_plan_name' => $card['show_plan_name'] ?? true,
            'show_status_badge' => $card['show_status_badge'] ?? true,
            'show_stats_bars' => $card['show_stats_bars'] ?? true,
            'show_quick_actions' => $card['show_quick_actions'] ?? true,
            'show_ip_port' => $card['show_ip_port'] ?? false,
            'show_uptime' => $card['show_uptime'] ?? false,
            'card_style' => $card['card_style'] ?? 'glass',
            'sort_default' => $card['sort_default'] ?? 'name',
            'group_by' => $card['group_by'] ?? 'none',
            'columns_desktop' => $cols['desktop'] ?? 3,
            'columns_tablet' => $cols['tablet'] ?? 2,
            'columns_mobile' => $cols['mobile'] ?? 1,
        ];

        $sidebar = $themeService->getSidebarConfig();
        $values += [
            'sidebar_position' => $sidebar['position'] ?? 'left',
            'sidebar_style' => $sidebar['style'] ?? 'default',
            'show_server_status' => $sidebar['show_server_status'] ?? true,
            'show_server_name' => $sidebar['show_server_name'] ?? true,
            'entries' => $sidebar['entries'] ?? [],
        ];

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            ...ThemeFormSchema::colorSections(),
            ThemeFormSchema::cardSection(),
            ThemeFormSchema::sidebarSection(),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        foreach (array_keys(ThemeDefaults::COLORS) as $key) {
            $settings->set($key, $data[$key] ?? null);
        }

        $settings->set('card_server_config', json_encode($this->buildCardConfig($data)));
        $settings->set('sidebar_server_config', json_encode($this->buildSidebarConfig($data)));
        $settings->clearCache();
        app(\App\Services\ThemeService::class)->clearCache();

        Notification::make()->title('Theme saved')
            ->body('All theme, card and sidebar settings have been updated.')
            ->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Theme')->submit('save'),
            Action::make('reset')->label('Reset to Defaults')->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->resetToDefaults()),
        ];
    }

    private function resetToDefaults(): void
    {
        $settings = app(SettingsService::class);

        foreach (ThemeDefaults::COLORS as $key => $value) {
            $settings->set($key, $value);
        }
        $settings->set('card_server_config', json_encode(ThemeDefaults::CARD_CONFIG));
        $settings->set('sidebar_server_config', json_encode(ThemeDefaults::SIDEBAR_CONFIG));
        $settings->clearCache();
        $this->mount();

        Notification::make()->title('All settings reset')
            ->body('Theme, card and sidebar settings have been reset to defaults.')
            ->success()->send();
    }

    private function buildCardConfig(array $data): array
    {
        return [
            'layout' => 'grid',
            'columns' => [
                'desktop' => (int) ($data['columns_desktop'] ?? 3),
                'tablet' => (int) ($data['columns_tablet'] ?? 2),
                'mobile' => (int) ($data['columns_mobile'] ?? 1),
            ],
            'show_egg_icon' => (bool) ($data['show_egg_icon'] ?? true),
            'show_egg_name' => (bool) ($data['show_egg_name'] ?? true),
            'show_plan_name' => (bool) ($data['show_plan_name'] ?? true),
            'show_status_badge' => (bool) ($data['show_status_badge'] ?? true),
            'show_stats_bars' => (bool) ($data['show_stats_bars'] ?? true),
            'show_quick_actions' => (bool) ($data['show_quick_actions'] ?? true),
            'show_ip_port' => (bool) ($data['show_ip_port'] ?? false),
            'show_uptime' => (bool) ($data['show_uptime'] ?? false),
            'card_style' => $data['card_style'] ?? 'glass',
            'sort_default' => $data['sort_default'] ?? 'name',
            'group_by' => $data['group_by'] ?? 'none',
        ];
    }

    private function buildSidebarConfig(array $data): array
    {
        $entries = collect($data['entries'] ?? [])->map(fn (array $e, int $i) => [
            'id' => $e['id'], 'label_key' => $e['label_key'], 'icon' => $e['icon'],
            'enabled' => (bool) $e['enabled'], 'route_suffix' => $e['route_suffix'], 'order' => $i,
        ])->values()->all();

        return [
            'position' => $data['sidebar_position'] ?? 'left',
            'style' => $data['sidebar_style'] ?? 'default',
            'show_server_status' => (bool) ($data['show_server_status'] ?? true),
            'show_server_name' => (bool) ($data['show_server_name'] ?? true),
            'entries' => $entries,
        ];
    }
}
