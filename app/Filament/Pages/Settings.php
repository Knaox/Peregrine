<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use App\Services\SetupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Settings';

    protected string $view = 'filament.pages.settings';

    // Appearance
    public ?string $app_name = '';

    public ?string $logo_url = '';

    public ?string $favicon_url = '';

    // Pelican
    public ?string $pelican_url = '';

    public ?string $pelican_admin_api_key = '';

    // Authentication
    public ?string $auth_mode = 'local';

    public ?string $oauth_client_id = '';

    public ?string $oauth_client_secret = '';

    public ?string $oauth_redirect_url = '';

    // Bridge
    public bool $bridge_enabled = false;

    public ?string $stripe_webhook_secret = '';

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->app_name = $settings->get('app_name', config('app.name'));
        $this->logo_url = $settings->get('logo_url', '');
        $this->favicon_url = $settings->get('favicon_url', '');

        $this->pelican_url = config('services.pelican.url', '');
        $this->pelican_admin_api_key = config('services.pelican.admin_api_key', '');

        $this->auth_mode = config('auth.mode', 'local');
        $this->oauth_client_id = config('services.oauth.client_id', '');
        $this->oauth_client_secret = config('services.oauth.client_secret', '');
        $this->oauth_redirect_url = config('services.oauth.redirect_url', '');

        $this->bridge_enabled = (bool) config('services.bridge.enabled', false);
        $this->stripe_webhook_secret = config('services.stripe.webhook_secret', '');

        $this->form->fill([
            'app_name' => $this->app_name,
            'logo_url' => $this->logo_url,
            'favicon_url' => $this->favicon_url,
            'pelican_url' => $this->pelican_url,
            'pelican_admin_api_key' => $this->pelican_admin_api_key,
            'auth_mode' => $this->auth_mode,
            'oauth_client_id' => $this->oauth_client_id,
            'oauth_client_secret' => $this->oauth_client_secret,
            'oauth_redirect_url' => $this->oauth_redirect_url,
            'bridge_enabled' => $this->bridge_enabled,
            'stripe_webhook_secret' => $this->stripe_webhook_secret,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Appearance')
                    ->description('Customize the look and feel of your panel.')
                    ->icon('heroicon-o-paint-brush')
                    ->schema([
                        TextInput::make('app_name')
                            ->label('Application Name')
                            ->placeholder('Peregrine')
                            ->maxLength(255),
                        TextInput::make('logo_url')
                            ->label('Logo URL')
                            ->placeholder('/images/logo.svg')
                            ->maxLength(255),
                        TextInput::make('favicon_url')
                            ->label('Favicon URL')
                            ->placeholder('/images/favicon.svg')
                            ->maxLength(255),
                    ])->columns(1),

                Section::make('Pelican')
                    ->description('Configure the connection to your Pelican panel.')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        TextInput::make('pelican_url')
                            ->label('Pelican URL')
                            ->placeholder('https://panel.example.com')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('pelican_admin_api_key')
                            ->label('Admin API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])->columns(1),

                Section::make('Authentication')
                    ->description('Configure how users authenticate.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Radio::make('auth_mode')
                            ->label('Authentication Mode')
                            ->options([
                                'local' => 'Local (email & password)',
                                'oauth' => 'OAuth (SSO)',
                            ])
                            ->default('local')
                            ->live(),
                        TextInput::make('oauth_client_id')
                            ->label('OAuth Client ID')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('auth_mode') === 'oauth'),
                        TextInput::make('oauth_client_secret')
                            ->label('OAuth Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('auth_mode') === 'oauth'),
                        TextInput::make('oauth_redirect_url')
                            ->label('OAuth Redirect URL')
                            ->url()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('auth_mode') === 'oauth'),
                    ])->columns(1),

                Section::make('Bridge')
                    ->description('Configure the bridge between Pelican and Stripe.')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Toggle::make('bridge_enabled')
                            ->label('Enable Bridge')
                            ->live(),
                        TextInput::make('stripe_webhook_secret')
                            ->label('Stripe Webhook Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('bridge_enabled')),
                    ])->columns(1),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(SettingsService::class);
        $setup = app(SetupService::class);

        // Save appearance settings to the database
        $settings->set('app_name', $data['app_name'] ?? null);
        $settings->set('logo_url', $data['logo_url'] ?? null);
        $settings->set('favicon_url', $data['favicon_url'] ?? null);

        // Save Pelican, auth, and bridge settings to .env
        $envValues = [];

        if (isset($data['pelican_url'])) {
            $envValues['PELICAN_URL'] = $data['pelican_url'];
        }

        if (isset($data['pelican_admin_api_key']) && $data['pelican_admin_api_key'] !== '') {
            $envValues['PELICAN_ADMIN_API_KEY'] = $data['pelican_admin_api_key'];
        }

        $envValues['AUTH_MODE'] = $data['auth_mode'] ?? 'local';

        if (($data['auth_mode'] ?? 'local') === 'oauth') {
            $envValues['OAUTH_CLIENT_ID'] = $data['oauth_client_id'] ?? '';
            $envValues['OAUTH_CLIENT_SECRET'] = $data['oauth_client_secret'] ?? '';
            $envValues['OAUTH_REDIRECT_URL'] = $data['oauth_redirect_url'] ?? '';
        }

        $envValues['BRIDGE_ENABLED'] = ($data['bridge_enabled'] ?? false) ? 'true' : 'false';

        if (! empty($data['bridge_enabled']) && ! empty($data['stripe_webhook_secret'])) {
            $envValues['STRIPE_WEBHOOK_SECRET'] = $data['stripe_webhook_secret'];
        }

        if (! empty($envValues)) {
            $setup->writeEnv($envValues);
        }

        Notification::make()
            ->title('Settings saved')
            ->body('Your settings have been updated successfully.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}
