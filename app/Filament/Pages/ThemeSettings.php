<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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

    private const DEFAULTS = [
        'theme_primary' => '#e11d48',
        'theme_primary_hover' => '#f43f5e',
        'theme_danger' => '#ef4444',
        'theme_warning' => '#f59e0b',
        'theme_success' => '#10b981',
        'theme_info' => '#3b82f6',
        'theme_background' => '#0c0a14',
        'theme_surface' => '#16131e',
        'theme_surface_hover' => '#1e1a2a',
        'theme_surface_elevated' => '#1a1724',
        'theme_border' => '#2a2535',
        'theme_border_hover' => '#3a3445',
        'theme_text_primary' => '#f1f0f5',
        'theme_text_secondary' => '#8b849e',
        'theme_text_muted' => '#5a5370',
        'theme_radius' => '0.75rem',
        'theme_font' => 'Inter',
        'theme_custom_css' => '',
    ];

    // Properties for all theme keys
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

    public function mount(): void
    {
        $settings = app(SettingsService::class);
        $values = [];

        foreach (self::DEFAULTS as $key => $default) {
            $values[$key] = $settings->get($key, $default);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Brand Colors')
                    ->description('Primary colors used throughout the player panel.')
                    ->icon('heroicon-o-swatch')
                    ->schema([
                        ColorPicker::make('theme_primary')
                            ->label('Primary')
                            ->helperText('Main accent color (buttons, links, active states).'),
                        ColorPicker::make('theme_primary_hover')
                            ->label('Primary Hover'),
                        ColorPicker::make('theme_danger')
                            ->label('Danger'),
                        ColorPicker::make('theme_warning')
                            ->label('Warning'),
                        ColorPicker::make('theme_success')
                            ->label('Success'),
                        ColorPicker::make('theme_info')
                            ->label('Info / Accent'),
                    ])->columns(3),

                Section::make('Background & Surfaces')
                    ->description('Dark mode background, cards, elevated surfaces, and hover states.')
                    ->icon('heroicon-o-square-3-stack-3d')
                    ->schema([
                        ColorPicker::make('theme_background')
                            ->label('Background')
                            ->helperText('Main page background.'),
                        ColorPicker::make('theme_surface')
                            ->label('Surface')
                            ->helperText('Cards, sidebar, panels.'),
                        ColorPicker::make('theme_surface_hover')
                            ->label('Surface Hover')
                            ->helperText('Hovered cards, inputs.'),
                        ColorPicker::make('theme_surface_elevated')
                            ->label('Surface Elevated')
                            ->helperText('Dropdowns, modals.'),
                    ])->columns(2),

                Section::make('Borders')
                    ->description('Border colors for cards, inputs, and dividers. Use rgba() for transparency.')
                    ->icon('heroicon-o-stop')
                    ->schema([
                        TextInput::make('theme_border')
                            ->label('Border')
                            ->helperText('Default border (e.g. rgba(148, 163, 184, 0.08) or #hex).'),
                        TextInput::make('theme_border_hover')
                            ->label('Border Hover')
                            ->helperText('Hover/active border.'),
                    ])->columns(2),

                Section::make('Text Colors')
                    ->description('Text hierarchy: primary, secondary, muted.')
                    ->icon('heroicon-o-language')
                    ->schema([
                        ColorPicker::make('theme_text_primary')
                            ->label('Text Primary'),
                        ColorPicker::make('theme_text_secondary')
                            ->label('Text Secondary'),
                        ColorPicker::make('theme_text_muted')
                            ->label('Text Muted'),
                    ])->columns(3),

                Section::make('Typography & Shape')
                    ->description('Font family and border radius.')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        Select::make('theme_font')
                            ->label('Font Family')
                            ->options([
                                'Inter' => 'Inter',
                                'Plus Jakarta Sans' => 'Plus Jakarta Sans',
                                'Space Grotesk' => 'Space Grotesk',
                                'Outfit' => 'Outfit',
                                'system-ui' => 'System Default',
                            ]),
                        Select::make('theme_radius')
                            ->label('Border Radius')
                            ->options([
                                '0' => 'None',
                                '0.25rem' => 'Small',
                                '0.375rem' => 'Medium',
                                '0.75rem' => 'Large (default)',
                                '1rem' => 'Extra Large',
                                '1.5rem' => '2XL',
                                '9999px' => 'Full (pill)',
                            ]),
                    ])->columns(2),

                Section::make('Custom CSS')
                    ->description('Inject custom CSS into the player panel. Use CSS variables (var(--color-*)) for theme compatibility.')
                    ->icon('heroicon-o-code-bracket')
                    ->schema([
                        Textarea::make('theme_custom_css')
                            ->label('Custom CSS')
                            ->rows(6)
                            ->placeholder('/* Your custom CSS here */'),
                    ])->columns(1),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        foreach (array_keys(self::DEFAULTS) as $key) {
            $settings->set($key, $data[$key] ?? null);
        }

        Notification::make()
            ->title('Theme saved')
            ->body('Theme settings have been updated. Refresh the player panel to see changes.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Theme')
                ->submit('save'),
            Action::make('reset')
                ->label('Reset to Defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->resetToDefaults();
                }),
        ];
    }

    private function resetToDefaults(): void
    {
        $settings = app(SettingsService::class);

        foreach (self::DEFAULTS as $key => $value) {
            $settings->set($key, $value);
        }

        $this->form->fill(self::DEFAULTS);

        Notification::make()
            ->title('Theme reset')
            ->body('Theme has been reset to default values.')
            ->success()
            ->send();
    }
}
