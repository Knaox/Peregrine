<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use App\Services\ThemeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class CardSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 91;

    protected static ?string $title = 'Server Cards';

    protected static ?string $navigationLabel = 'Server Cards';

    protected string $view = 'filament.pages.theme-settings';

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

    public function mount(): void
    {
        $config = app(ThemeService::class)->getCardConfig();

        $columns = $config['columns'] ?? [];

        $this->form->fill([
            'show_egg_icon' => $config['show_egg_icon'] ?? true,
            'show_egg_name' => $config['show_egg_name'] ?? true,
            'show_plan_name' => $config['show_plan_name'] ?? true,
            'show_status_badge' => $config['show_status_badge'] ?? true,
            'show_stats_bars' => $config['show_stats_bars'] ?? true,
            'show_quick_actions' => $config['show_quick_actions'] ?? true,
            'show_ip_port' => $config['show_ip_port'] ?? false,
            'show_uptime' => $config['show_uptime'] ?? false,
            'card_style' => $config['card_style'] ?? 'glass',
            'sort_default' => $config['sort_default'] ?? 'name',
            'group_by' => $config['group_by'] ?? 'none',
            'columns_desktop' => $columns['desktop'] ?? 3,
            'columns_tablet' => $columns['tablet'] ?? 2,
            'columns_mobile' => $columns['mobile'] ?? 1,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Card Content')
                    ->description('Choose what information to display on each server card.')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Toggle::make('show_egg_icon')
                            ->label('Show egg icon/banner'),
                        Toggle::make('show_egg_name')
                            ->label('Show egg name'),
                        Toggle::make('show_plan_name')
                            ->label('Show plan name'),
                        Toggle::make('show_status_badge')
                            ->label('Show status badge'),
                        Toggle::make('show_stats_bars')
                            ->label('Show stats bars (CPU/RAM/Disk)'),
                        Toggle::make('show_quick_actions')
                            ->label('Show power quick actions'),
                        Toggle::make('show_ip_port')
                            ->label('Show IP:port'),
                        Toggle::make('show_uptime')
                            ->label('Show uptime'),
                    ])->columns(2),

                Section::make('Card Appearance')
                    ->description('Visual style and layout of server cards.')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Select::make('card_style')
                            ->label('Card Style')
                            ->options([
                                'default' => 'Default (border)',
                                'elevated' => 'Elevated (shadow)',
                                'glass' => 'Glass (glassmorphism)',
                                'minimal' => 'Minimal (no border)',
                            ]),
                        Select::make('sort_default')
                            ->label('Default Sort')
                            ->options([
                                'name' => 'Name',
                                'status' => 'Status',
                                'created_at' => 'Date Created',
                                'egg' => 'Egg Type',
                            ]),
                        Select::make('group_by')
                            ->label('Group By')
                            ->options([
                                'none' => 'No Grouping',
                                'egg' => 'Egg Type',
                                'status' => 'Status',
                                'plan' => 'Plan',
                            ]),
                    ])->columns(3),

                Section::make('Grid Layout')
                    ->description('Number of columns per screen size.')
                    ->icon('heroicon-o-view-columns')
                    ->schema([
                        Select::make('columns_desktop')
                            ->label('Desktop Columns')
                            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4']),
                        Select::make('columns_tablet')
                            ->label('Tablet Columns')
                            ->options([1 => '1', 2 => '2', 3 => '3']),
                        Select::make('columns_mobile')
                            ->label('Mobile Columns')
                            ->options([1 => '1', 2 => '2']),
                    ])->columns(3),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $config = [
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

        $settings->set('card_server_config', json_encode($config));

        Notification::make()
            ->title('Card settings saved')
            ->body('Server card configuration has been updated.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Card Settings')
                ->submit('save'),
        ];
    }
}
