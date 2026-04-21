<?php

namespace App\Filament\Pages;

use App\Filament\Pages\AuthSettings\AuthSettingsFormSchema;
use App\Services\Auth\AuthProviderRegistry;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Admin-facing page for auth & security configuration.
 *
 * Writes to the `settings` table via SettingsService — no .env, no redeploy
 * required when rotating provider keys. Secrets are envelope-encrypted via
 * AuthProviderRegistry.
 *
 * Plan §S8: when an admin disables a provider that currently has exclusive
 * users (no password, no other linked identity), the save is blocked until
 * the `acknowledge_disable_risk` flag is ticked. Prevents silent lock-outs.
 */
class AuthSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 50;

    protected static ?string $title = 'Auth & Security';

    protected string $view = 'filament.pages.auth-settings';

    public bool $auth_local_enabled = true;
    public bool $auth_local_registration_enabled = true;
    public bool $auth_2fa_enabled = true;
    public bool $auth_2fa_required_admins = false;
    public bool $auth_shop_enabled = false;
    public ?string $auth_shop_client_id = '';
    public ?string $auth_shop_client_secret = '';
    public ?string $auth_shop_authorize_url = '';
    public ?string $auth_shop_token_url = '';
    public ?string $auth_shop_user_url = '';
    public ?string $auth_shop_register_url = '';
    /** @var array<int, string>|null */
    public ?array $auth_shop_logo_path = [];
    public bool $auth_providers_google_enabled = false;
    public ?string $auth_providers_google_client_id = '';
    public ?string $auth_providers_google_client_secret = '';
    public bool $auth_providers_discord_enabled = false;
    public ?string $auth_providers_discord_client_id = '';
    public ?string $auth_providers_discord_client_secret = '';
    public bool $auth_providers_linkedin_enabled = false;
    public ?string $auth_providers_linkedin_client_id = '';
    public ?string $auth_providers_linkedin_client_secret = '';
    public bool $acknowledge_disable_risk = false;

    public function mount(): void
    {
        $settings = app(SettingsService::class);
        $registry = app(AuthProviderRegistry::class);

        $this->auth_local_enabled = $settings->get('auth_local_enabled', 'true') === 'true';
        $this->auth_local_registration_enabled = $settings->get('auth_local_registration_enabled', 'true') === 'true';
        $this->auth_2fa_enabled = $settings->get('auth_2fa_enabled', 'true') === 'true';
        $this->auth_2fa_required_admins = $settings->get('auth_2fa_required_admins', 'false') === 'true';

        $shop = $registry->shopConfig();
        $this->auth_shop_enabled = $settings->get('auth_shop_enabled', 'false') === 'true';
        $this->auth_shop_client_id = (string) ($shop['client_id'] ?? '');
        $this->auth_shop_client_secret = '';
        $this->auth_shop_authorize_url = (string) ($shop['authorize_url'] ?? '');
        $this->auth_shop_token_url = (string) ($shop['token_url'] ?? '');
        $this->auth_shop_user_url = (string) ($shop['user_url'] ?? '');
        $this->auth_shop_register_url = (string) ($shop['register_url'] ?? '');
        $logoPath = (string) ($shop['logo_path'] ?? '');
        $this->auth_shop_logo_path = ($logoPath !== '' && ! str_starts_with($logoPath, '/')) ? [$logoPath] : [];

        $providers = $registry->decodeProviders();
        foreach (['google', 'discord', 'linkedin'] as $id) {
            $this->{"auth_providers_{$id}_enabled"} = (bool) ($providers[$id]['enabled'] ?? false);
            $this->{"auth_providers_{$id}_client_id"} = (string) ($providers[$id]['client_id'] ?? '');
            $this->{"auth_providers_{$id}_client_secret"} = '';
        }

        $this->form->fill($this->currentFormState());
    }

    public function form(Schema $schema): Schema
    {
        $registry = app(AuthProviderRegistry::class);
        $shop = $registry->shopConfig();
        $shopRedirect = (string) ($shop['redirect_uri'] ?? $this->defaultRedirect('shop'));

        $providers = $registry->decodeProviders();

        return $schema->schema([
            AuthSettingsFormSchema::general(),
            AuthSettingsFormSchema::shop($shopRedirect),
            AuthSettingsFormSchema::socialProvider(
                'google', 'Google', 'heroicon-o-globe-alt',
                (string) ($providers['google']['redirect_uri'] ?? $this->defaultRedirect('google')),
            ),
            AuthSettingsFormSchema::socialProvider(
                'discord', 'Discord', 'heroicon-o-chat-bubble-left-right',
                (string) ($providers['discord']['redirect_uri'] ?? $this->defaultRedirect('discord')),
            ),
            AuthSettingsFormSchema::socialProvider(
                'linkedin', 'LinkedIn', 'heroicon-o-briefcase',
                (string) ($providers['linkedin']['redirect_uri'] ?? $this->defaultRedirect('linkedin')),
            ),
            AuthSettingsFormSchema::twoFactor(),
            AuthSettingsFormSchema::safety(),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        $registry = app(AuthProviderRegistry::class);

        // 2FA enforcement self-lockout guard (kept from étape B).
        if (($data['auth_2fa_required_admins'] ?? false) === true) {
            $user = auth()->user();
            if ($user !== null && $user->is_admin && ! $user->hasTwoFactor()) {
                Notification::make()
                    ->title('Set up 2FA first')
                    ->body('Enable 2FA on your own admin account before forcing it for all admins.')
                    ->danger()
                    ->send();

                return;
            }
        }

        // S8 disable guardrail — block if a provider is being turned off and
        // exclusive users remain, unless the admin explicitly acknowledges.
        $ack = (bool) ($data['acknowledge_disable_risk'] ?? false);
        foreach (['shop', 'google', 'discord', 'linkedin'] as $pid) {
            $wasEnabled = $this->wasPreviouslyEnabled($pid);
            $willBeEnabled = $pid === 'shop'
                ? (bool) ($data['auth_shop_enabled'] ?? false)
                : (bool) ($data["auth_providers_{$pid}_enabled"] ?? false);

            if ($wasEnabled && ! $willBeEnabled) {
                $exclusive = $registry->providerHasExclusiveUsers($pid);
                if ($exclusive > 0 && ! $ack) {
                    Notification::make()
                        ->title("Disabling {$pid} would lock out {$exclusive} user(s)")
                        ->body('Tick "I understand the risk" under Safety, then save again.')
                        ->danger()
                        ->send();

                    return;
                }
            }
        }

        $settings->set('auth_local_enabled', ($data['auth_local_enabled'] ?? true) ? 'true' : 'false');
        $settings->set('auth_local_registration_enabled', ($data['auth_local_registration_enabled'] ?? true) ? 'true' : 'false');
        $settings->set('auth_2fa_enabled', ($data['auth_2fa_enabled'] ?? true) ? 'true' : 'false');
        $settings->set('auth_2fa_required_admins', ($data['auth_2fa_required_admins'] ?? false) ? 'true' : 'false');

        $this->persistShop($data, $registry);
        $this->persistProviders($data, $registry);

        Notification::make()->title('Auth settings saved')->success()->send();

        // Reset the acknowledgement + secrets inputs so they don't stick.
        $this->acknowledge_disable_risk = false;
        $this->auth_shop_client_secret = '';
        foreach (['google', 'discord', 'linkedin'] as $id) {
            $this->{"auth_providers_{$id}_client_secret"} = '';
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistShop(array $data, AuthProviderRegistry $registry): void
    {
        $existing = $registry->shopConfig();
        $existing['client_id'] = (string) ($data['auth_shop_client_id'] ?? '');
        $existing['authorize_url'] = (string) ($data['auth_shop_authorize_url'] ?? '');
        $existing['token_url'] = (string) ($data['auth_shop_token_url'] ?? '');
        $existing['user_url'] = (string) ($data['auth_shop_user_url'] ?? '');
        $existing['register_url'] = (string) ($data['auth_shop_register_url'] ?? '');
        $existing['redirect_uri'] = $existing['redirect_uri'] ?? $this->defaultRedirect('shop');

        // FileUpload returns an array (or cleared null) — extract first path.
        $logoValue = $data['auth_shop_logo_path'] ?? null;
        $logoPath = is_array($logoValue) ? (array_values($logoValue)[0] ?? null) : $logoValue;
        $existing['logo_path'] = $logoPath ?: '';

        app(SettingsService::class)->set('auth_shop_config', json_encode($existing, JSON_THROW_ON_ERROR));
        app(SettingsService::class)->set('auth_shop_enabled', ($data['auth_shop_enabled'] ?? false) ? 'true' : 'false');

        $typed = (string) ($data['auth_shop_client_secret'] ?? '');
        if ($typed !== '') {
            $registry->storeShopClientSecret($typed);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistProviders(array $data, AuthProviderRegistry $registry): void
    {
        $existing = $registry->decodeProviders();

        foreach (['google', 'discord', 'linkedin'] as $id) {
            $existing[$id] ??= [];
            $existing[$id]['enabled'] = (bool) ($data["auth_providers_{$id}_enabled"] ?? false);
            $existing[$id]['client_id'] = (string) ($data["auth_providers_{$id}_client_id"] ?? '');
            $existing[$id]['redirect_uri'] = $existing[$id]['redirect_uri'] ?? $this->defaultRedirect($id);
        }

        app(SettingsService::class)->set('auth_providers', json_encode($existing, JSON_THROW_ON_ERROR));

        foreach (['google', 'discord', 'linkedin'] as $id) {
            $typed = (string) ($data["auth_providers_{$id}_client_secret"] ?? '');
            if ($typed !== '') {
                $registry->storeProviderClientSecret($id, $typed);
            }
        }
    }

    private function wasPreviouslyEnabled(string $providerId): bool
    {
        if ($providerId === 'shop') {
            return app(SettingsService::class)->get('auth_shop_enabled', 'false') === 'true';
        }

        $providers = app(AuthProviderRegistry::class)->decodeProviders();

        return (bool) ($providers[$providerId]['enabled'] ?? false);
    }

    private function defaultRedirect(string $provider): string
    {
        return rtrim((string) config('app.url', ''), '/')."/api/auth/social/{$provider}/callback";
    }

    /**
     * @return array<string, mixed>
     */
    private function currentFormState(): array
    {
        return [
            'auth_local_enabled' => $this->auth_local_enabled,
            'auth_local_registration_enabled' => $this->auth_local_registration_enabled,
            'auth_2fa_enabled' => $this->auth_2fa_enabled,
            'auth_2fa_required_admins' => $this->auth_2fa_required_admins,
            'auth_shop_enabled' => $this->auth_shop_enabled,
            'auth_shop_client_id' => $this->auth_shop_client_id,
            'auth_shop_authorize_url' => $this->auth_shop_authorize_url,
            'auth_shop_token_url' => $this->auth_shop_token_url,
            'auth_shop_user_url' => $this->auth_shop_user_url,
            'auth_shop_register_url' => $this->auth_shop_register_url,
            'auth_shop_client_secret' => '',
            'auth_shop_logo_path' => $this->auth_shop_logo_path,
            'auth_providers_google_enabled' => $this->auth_providers_google_enabled,
            'auth_providers_google_client_id' => $this->auth_providers_google_client_id,
            'auth_providers_google_client_secret' => '',
            'auth_providers_discord_enabled' => $this->auth_providers_discord_enabled,
            'auth_providers_discord_client_id' => $this->auth_providers_discord_client_id,
            'auth_providers_discord_client_secret' => '',
            'auth_providers_linkedin_enabled' => $this->auth_providers_linkedin_enabled,
            'auth_providers_linkedin_client_id' => $this->auth_providers_linkedin_client_id,
            'auth_providers_linkedin_client_secret' => '',
            'acknowledge_disable_risk' => false,
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Settings')->submit('save'),
        ];
    }
}
