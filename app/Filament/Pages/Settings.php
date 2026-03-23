<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Settings\SettingsFormSchema;
use App\Services\SettingsService;
use App\Services\SetupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
    /** @var array<int, string>|null */
    public ?array $logo_url = [];
    /** @var array<int, string>|null */
    public ?array $favicon_url = [];
    public bool $show_app_name = true;
    public ?string $logo_height = '40';
    /** @var array<int, array<string, mixed>> */
    public array $header_links = [];

    // Pelican
    public ?string $pelican_url = '';
    public ?string $pelican_admin_api_key = '';
    public ?string $pelican_client_api_key = '';

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
        $this->show_app_name = $settings->get('show_app_name', 'true') === 'true';
        $this->logo_height = $settings->get('logo_height', '40');

        $logoPath = $settings->get('app_logo_path', '');
        $faviconPath = $settings->get('app_favicon_path', '');
        $logoForForm = ($logoPath && ! str_starts_with($logoPath, '/')) ? [$logoPath] : [];
        $faviconForForm = ($faviconPath && ! str_starts_with($faviconPath, '/')) ? [$faviconPath] : [];

        $this->header_links = json_decode($settings->get('header_links', '[]') ?? '[]', true) ?: [];

        $this->pelican_url = config('services.pelican.url', '');
        $this->pelican_admin_api_key = config('services.pelican.admin_api_key', '');
        $this->pelican_client_api_key = config('panel.client_api_key', '');
        $this->auth_mode = config('auth.mode', 'local');
        $this->oauth_client_id = config('services.oauth.client_id', '');
        $this->oauth_client_secret = config('services.oauth.client_secret', '');
        $this->oauth_redirect_url = config('services.oauth.redirect_url', '');
        $this->bridge_enabled = (bool) config('services.bridge.enabled', false);
        $this->stripe_webhook_secret = config('services.stripe.webhook_secret', '');

        $this->form->fill([
            'app_name' => $this->app_name,
            'show_app_name' => $this->show_app_name,
            'logo_height' => $this->logo_height,
            'logo_url' => $logoForForm,
            'favicon_url' => $faviconForForm,
            'header_links' => $this->header_links,
            'pelican_url' => $this->pelican_url,
            'pelican_admin_api_key' => $this->pelican_admin_api_key,
            'pelican_client_api_key' => $this->pelican_client_api_key,
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
        return $schema->schema([
            SettingsFormSchema::appearance(),
            SettingsFormSchema::pelican(),
            SettingsFormSchema::authentication(),
            SettingsFormSchema::bridge(),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        $setup = app(SetupService::class);

        // Appearance → DB
        $settings->set('app_name', $data['app_name'] ?? null);
        $settings->set('show_app_name', ($data['show_app_name'] ?? true) ? 'true' : 'false');
        $settings->set('logo_height', $data['logo_height'] ?? '40');
        $settings->set('header_links', json_encode($data['header_links'] ?? []));

        $logoValue = $data['logo_url'] ?? null;
        if ($logoValue) {
            $path = is_array($logoValue) ? (array_values($logoValue)[0] ?? null) : $logoValue;
            if ($path) {
                $settings->set('app_logo_path', $path);
            }
        }

        $faviconValue = $data['favicon_url'] ?? null;
        if ($faviconValue) {
            $path = is_array($faviconValue) ? (array_values($faviconValue)[0] ?? null) : $faviconValue;
            if ($path) {
                $settings->set('app_favicon_path', $path);
            }
        }

        // Pelican, Auth, Bridge → .env
        $envValues = [];
        if (isset($data['pelican_url'])) {
            $envValues['PELICAN_URL'] = $data['pelican_url'];
        }
        if (isset($data['pelican_admin_api_key']) && $data['pelican_admin_api_key'] !== '') {
            $envValues['PELICAN_ADMIN_API_KEY'] = $data['pelican_admin_api_key'];
        }
        if (isset($data['pelican_client_api_key']) && $data['pelican_client_api_key'] !== '') {
            $envValues['PELICAN_CLIENT_API_KEY'] = $data['pelican_client_api_key'];
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

        $settings->clearCache();

        Notification::make()->title('Settings saved')
            ->body('Your settings have been updated successfully.')
            ->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Settings')->submit('save'),
        ];
    }
}
