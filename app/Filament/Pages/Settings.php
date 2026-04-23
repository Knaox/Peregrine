<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Settings\SettingsFormSchema;
use App\Services\SettingsService;
use App\Services\SetupService;
use Illuminate\Support\Facades\Mail;
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
    public ?array $logo_url_light = [];
    /** @var array<int, string>|null */
    public ?array $favicon_url = [];
    public bool $show_app_name = true;
    public ?string $logo_height = '40';
    public string $default_locale = 'en';
    /** @var array<int, array<string, mixed>> */
    public array $header_links = [];

    // Pelican
    public ?string $pelican_url = '';
    public ?string $pelican_admin_api_key = '';
    public ?string $pelican_client_api_key = '';

    // Authentication moved to the dedicated AuthSettings page — manages the
    // full multi-provider config (2FA enforcement, Shop, Google, Discord,
    // LinkedIn) via the settings table rather than .env. Nothing auth-related
    // remains here.

    // Bridge moved to the dedicated BridgeSettings page (HMAC secret +
    // toggle managed there). The Stripe webhook secret will move there too
    // when the P3 webhook handler is implemented.

    // Developer
    public bool $app_debug = false;

    // SMTP
    public ?string $mail_mailer = 'smtp';
    public ?string $mail_host = '';
    public ?string $mail_port = '587';
    public ?string $mail_encryption = 'tls';
    public ?string $mail_username = '';
    public ?string $mail_password = '';
    public ?string $mail_from_address = '';
    public ?string $mail_from_name = '';

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->app_name = $settings->get('app_name', config('app.name'));
        $this->show_app_name = $settings->get('show_app_name', 'true') === 'true';
        $this->logo_height = $settings->get('logo_height', '40');
        $this->default_locale = (string) $settings->get('default_locale', 'en');

        $logoPath = $settings->get('app_logo_path', '');
        $logoLightPath = $settings->get('app_logo_path_light', '');
        $faviconPath = $settings->get('app_favicon_path', '');
        $logoForForm = ($logoPath && ! str_starts_with($logoPath, '/')) ? [$logoPath] : [];
        $logoLightForForm = ($logoLightPath && ! str_starts_with($logoLightPath, '/')) ? [$logoLightPath] : [];
        $faviconForForm = ($faviconPath && ! str_starts_with($faviconPath, '/')) ? [$faviconPath] : [];

        $this->header_links = json_decode($settings->get('header_links', '[]') ?? '[]', true) ?: [];

        $this->pelican_url = config('services.pelican.url', '');
        $this->pelican_admin_api_key = config('services.pelican.admin_api_key', '');
        $this->pelican_client_api_key = config('panel.client_api_key', '');

        $this->app_debug = (bool) config('app.debug', false);

        $this->mail_mailer = config('mail.default', 'smtp');
        $this->mail_host = config('mail.mailers.smtp.host', '');
        $this->mail_port = (string) config('mail.mailers.smtp.port', '587');
        $this->mail_encryption = env('MAIL_ENCRYPTION', 'tls');
        $this->mail_username = config('mail.mailers.smtp.username', '');
        $this->mail_password = config('mail.mailers.smtp.password', '');
        $this->mail_from_address = config('mail.from.address', '');
        $this->mail_from_name = config('mail.from.name', '');

        $this->form->fill([
            'app_name' => $this->app_name,
            'show_app_name' => $this->show_app_name,
            'logo_height' => $this->logo_height,
            'default_locale' => $this->default_locale,
            'logo_url' => $logoForForm,
            'logo_url_light' => $logoLightForForm,
            'favicon_url' => $faviconForForm,
            'header_links' => $this->header_links,
            'pelican_url' => $this->pelican_url,
            'pelican_admin_api_key' => $this->pelican_admin_api_key,
            'pelican_client_api_key' => $this->pelican_client_api_key,
            'mail_mailer' => $this->mail_mailer,
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_encryption' => $this->mail_encryption,
            'mail_username' => $this->mail_username,
            'mail_password' => $this->mail_password,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
            'app_debug' => $this->app_debug,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        // Authentication section moved to the dedicated AuthSettings page.
        return $schema->schema([
            SettingsFormSchema::appearance(),
            SettingsFormSchema::pelican(),
            SettingsFormSchema::smtp(),
            SettingsFormSchema::developer(),
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
        $defaultLocale = in_array($data['default_locale'] ?? 'en', ['en', 'fr'], true)
            ? $data['default_locale']
            : 'en';
        $settings->set('default_locale', $defaultLocale);
        $settings->set('header_links', json_encode($data['header_links'] ?? []));

        $logoValue = $data['logo_url'] ?? null;
        if ($logoValue) {
            $path = is_array($logoValue) ? (array_values($logoValue)[0] ?? null) : $logoValue;
            if ($path) {
                $settings->set('app_logo_path', $path);
            }
        }

        // Light-mode logo: optional; empty/cleared value persists as '' so the
        // frontend falls back to the main logo.
        $logoLightValue = $data['logo_url_light'] ?? null;
        $logoLightPath = is_array($logoLightValue) ? (array_values($logoLightValue)[0] ?? null) : $logoLightValue;
        $settings->set('app_logo_path_light', $logoLightPath ?: '');

        $faviconValue = $data['favicon_url'] ?? null;
        if ($faviconValue) {
            $path = is_array($faviconValue) ? (array_values($faviconValue)[0] ?? null) : $faviconValue;
            if ($path) {
                $settings->set('app_favicon_path', $path);
            }
        }

        // Pelican → .env
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
        // Auth config is owned by the dedicated AuthSettings page.
        // Bridge config is owned by the dedicated BridgeSettings page.
        // Both stay out of .env so admins can change them without a redeploy.

        // SMTP → .env
        $envValues['MAIL_MAILER'] = $data['mail_mailer'] ?? 'smtp';
        if (($data['mail_mailer'] ?? 'smtp') === 'smtp') {
            $envValues['MAIL_HOST'] = $data['mail_host'] ?? '';
            $envValues['MAIL_PORT'] = $data['mail_port'] ?? '587';
            $envValues['MAIL_ENCRYPTION'] = $data['mail_encryption'] ?? 'tls';
            $envValues['MAIL_USERNAME'] = $data['mail_username'] ?? '';
            if (! empty($data['mail_password'])) {
                $envValues['MAIL_PASSWORD'] = $data['mail_password'];
            }
        }
        $envValues['MAIL_FROM_ADDRESS'] = $data['mail_from_address'] ?? '';
        $envValues['MAIL_FROM_NAME'] = $data['mail_from_name'] ?? config('app.name', 'Peregrine');

        // Developer → .env
        $envValues['APP_DEBUG'] = ($data['app_debug'] ?? false) ? 'true' : 'false';

        if (! empty($envValues)) {
            $setup->writeEnv($envValues);
        }

        $settings->clearCache();

        Notification::make()->title('Settings saved')
            ->body('Your settings have been updated successfully.')
            ->success()->send();
    }

    public function testSmtp(): void
    {
        $userEmail = auth()->user()?->email;

        if (! $userEmail) {
            Notification::make()->title('No email')->body('Cannot determine your email.')->danger()->send();

            return;
        }

        try {
            Mail::raw(
                'This is a test email from ' . config('app.name', 'Peregrine') . ".\n\nIf you received this, your SMTP is working.\n\nSent at: " . now()->toDateTimeString(),
                function ($message) use ($userEmail): void {
                    $message->to($userEmail)->subject(config('app.name', 'Peregrine') . ' — SMTP Test');
                },
            );

            Notification::make()
                ->title('Test email sent')
                ->body("A test email was sent to {$userEmail}.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('SMTP test failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Settings')->submit('save'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testSmtp')
                ->label('Test SMTP')
                ->color('gray')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->modalHeading('Send test email')
                ->modalDescription('A test email will be sent to your admin email address.')
                ->modalSubmitActionLabel('Send test')
                ->action(fn () => $this->testSmtp()),
        ];
    }
}
