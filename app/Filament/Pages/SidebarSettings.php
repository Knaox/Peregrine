<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use App\Services\ThemeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class SidebarSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 92;

    protected static ?string $title = 'Server Sidebar';

    protected static ?string $navigationLabel = 'Server Sidebar';

    protected string $view = 'filament.pages.theme-settings';

    public ?string $position = 'left';

    public ?string $style = 'default';

    public bool $show_server_status = true;

    public bool $show_server_name = true;

    /** @var array<int, array{id: string, label_key: string, icon: string, enabled: bool, route_suffix: string}> */
    public array $entries = [];

    public function mount(): void
    {
        $config = app(ThemeService::class)->getSidebarConfig();

        $this->form->fill([
            'position' => $config['position'] ?? 'left',
            'style' => $config['style'] ?? 'default',
            'show_server_status' => $config['show_server_status'] ?? true,
            'show_server_name' => $config['show_server_name'] ?? true,
            'entries' => $config['entries'] ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Sidebar Layout')
                    ->description('Configure the server detail sidebar position and style.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Select::make('position')
                            ->label('Position')
                            ->options([
                                'left' => 'Left sidebar',
                                'top' => 'Top tabs',
                            ]),
                        Select::make('style')
                            ->label('Style')
                            ->options([
                                'default' => 'Default',
                                'compact' => 'Compact (icons only)',
                                'pills' => 'Pills',
                            ]),
                        Toggle::make('show_server_status')
                            ->label('Show server status dot'),
                        Toggle::make('show_server_name')
                            ->label('Show server name in header'),
                    ])->columns(2),

                Section::make('Sidebar Entries')
                    ->description('Configure which links appear in the server sidebar. Drag to reorder.')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Repeater::make('entries')
                            ->label('')
                            ->schema([
                                TextInput::make('id')
                                    ->label('ID')
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('label_key')
                                    ->label('i18n Key')
                                    ->helperText('Translation key (e.g. servers.detail.overview)'),
                                Select::make('icon')
                                    ->label('Icon')
                                    ->options([
                                        'home' => 'Home',
                                        'terminal' => 'Terminal',
                                        'folder' => 'Folder',
                                        'database' => 'Database',
                                        'archive' => 'Archive',
                                        'clock' => 'Clock',
                                        'globe' => 'Globe',
                                        'key' => 'Key',
                                        'settings' => 'Settings',
                                        'shield' => 'Shield',
                                        'users' => 'Users',
                                        'server' => 'Server',
                                        'link' => 'Link',
                                        'code' => 'Code',
                                        'cpu' => 'CPU',
                                        'hard-drive' => 'Hard Drive',
                                    ]),
                                TextInput::make('route_suffix')
                                    ->label('Route Suffix')
                                    ->helperText('Appended to /servers/{id} (e.g. /console)'),
                                Toggle::make('enabled')
                                    ->label('Enabled'),
                            ])
                            ->columns(5)
                            ->reorderable()
                            ->addable(false)
                            ->deletable(false),
                    ])->columns(1),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $entries = collect($data['entries'] ?? [])->map(function (array $entry, int $index): array {
            return [
                'id' => $entry['id'],
                'label_key' => $entry['label_key'],
                'icon' => $entry['icon'],
                'enabled' => (bool) $entry['enabled'],
                'route_suffix' => $entry['route_suffix'],
                'order' => $index,
            ];
        })->values()->all();

        $config = [
            'position' => $data['position'] ?? 'left',
            'style' => $data['style'] ?? 'default',
            'show_server_status' => (bool) ($data['show_server_status'] ?? true),
            'show_server_name' => (bool) ($data['show_server_name'] ?? true),
            'entries' => $entries,
        ];

        $settings->set('sidebar_server_config', json_encode($config));

        Notification::make()
            ->title('Sidebar settings saved')
            ->body('Server sidebar configuration has been updated.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Sidebar Settings')
                ->submit('save'),
        ];
    }
}
