<?php

namespace App\Filament\Pages;

use App\Actions\Settings\TestSmtpConfigAction;
use App\Filament\Pages\Settings\SettingsFormSchema;
use App\Services\Settings\SettingsPersister;
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

    // General — runtime app timezone (IANA identifier, e.g. Europe/Paris)
    public string $app_timezone = 'UTC';

    // Network
    /** @var array<int, string> */
    public array $trusted_proxies = [];

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

        // Pelican URL + API keys live in the `settings` table now.
        // URL is plaintext, API keys are encrypted — we never display the
        // stored API keys (admin types a new one to rotate; empty keeps the
        // current encrypted value).
        $this->pelican_url = (string) ($settings->get('pelican_url') ?? config('panel.pelican.url', ''));
        $this->pelican_admin_api_key = '';
        $this->pelican_client_api_key = '';

        // app_debug / app_timezone / trusted_proxies are now DB-backed
        // (table `settings`) so they survive a Docker stack redeploy. The
        // .env values are kept as bootstrap fallback for the very first
        // boot before an admin has saved the page once.
        $this->app_debug = ($settings->get('app_debug', config('app.debug') ? 'true' : 'false')) === 'true';
        $this->app_timezone = (string) $settings->get('app_timezone', config('app.timezone', 'UTC'));

        $proxiesRaw = (string) $settings->get('trusted_proxies', env('TRUSTED_PROXIES', '*'));
        $this->trusted_proxies = $proxiesRaw === '*' || $proxiesRaw === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $proxiesRaw))));

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
            'app_timezone' => $this->app_timezone,
            'trusted_proxies' => $this->trusted_proxies,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        // Authentication section moved to the dedicated AuthSettings page.
        // All configurable fields are grouped into top-level tabs for clarity.
        return $schema->schema(SettingsFormSchema::tabs());
    }

    public function save(): void
    {
        $persister = new SettingsPersister(
            app(SettingsService::class),
            app(SetupService::class),
        );
        $persister->persist($this->form->getState());

        Notification::make()->title('Settings saved')
            ->body('Your settings have been updated successfully.')
            ->success()->send();
    }

    public function testSmtp(): void
    {
        $userEmail = (string) (auth()->user()?->email ?? '');
        $result = (new TestSmtpConfigAction())->execute($userEmail);

        $notification = Notification::make()
            ->title($result['ok'] ? 'Test email sent' : 'SMTP test failed')
            ->body($result['message']);

        $result['ok'] ? $notification->success()->send() : $notification->danger()->send();
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
