<?php

namespace App\Filament\Pages;

use App\Filament\Pages\AuthSettings\AuthSettingsFormSchema;
use App\Filament\Pages\AuthSettings\AuthSettingsPersister;
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

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.auth-settings';

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.auth_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('admin.pages.auth_settings.title');
    }

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
    public ?string $auth_shop_redirect_uri = '';
    public ?string $auth_shop_register_url = '';
    /** @var array<int, string>|null */
    public ?array $auth_shop_logo_path = [];
    public bool $auth_paymenter_enabled = false;
    public ?string $auth_paymenter_base_url = '';
    public ?string $auth_paymenter_client_id = '';
    public ?string $auth_paymenter_client_secret = '';
    public ?string $auth_paymenter_redirect_uri = '';
    public ?string $auth_paymenter_register_url = '';
    /** @var array<int, string>|null */
    public ?array $auth_paymenter_logo_path = [];
    public bool $auth_providers_google_enabled = false;
    public ?string $auth_providers_google_client_id = '';
    public ?string $auth_providers_google_client_secret = '';
    public ?string $auth_providers_google_redirect_uri = '';
    public bool $auth_providers_discord_enabled = false;
    public ?string $auth_providers_discord_client_id = '';
    public ?string $auth_providers_discord_client_secret = '';
    public ?string $auth_providers_discord_redirect_uri = '';
    public bool $auth_providers_linkedin_enabled = false;
    public ?string $auth_providers_linkedin_client_id = '';
    public ?string $auth_providers_linkedin_client_secret = '';
    public ?string $auth_providers_linkedin_redirect_uri = '';
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
        $this->auth_shop_redirect_uri = (string) ($shop['redirect_uri'] ?? '');
        $this->auth_shop_register_url = (string) ($shop['register_url'] ?? '');
        $logoPath = (string) ($shop['logo_path'] ?? '');
        $this->auth_shop_logo_path = ($logoPath !== '' && ! str_starts_with($logoPath, '/')) ? [$logoPath] : [];

        $paymenter = $registry->paymenterConfig();
        $this->auth_paymenter_enabled = $settings->get('auth_paymenter_enabled', 'false') === 'true';
        $this->auth_paymenter_base_url = (string) ($paymenter['base_url'] ?? '');
        $this->auth_paymenter_client_id = (string) ($paymenter['client_id'] ?? '');
        $this->auth_paymenter_client_secret = '';
        $this->auth_paymenter_redirect_uri = (string) ($paymenter['redirect_uri'] ?? '');
        $this->auth_paymenter_register_url = (string) ($paymenter['register_url'] ?? '');
        $paymenterLogoPath = (string) ($paymenter['logo_path'] ?? '');
        $this->auth_paymenter_logo_path = ($paymenterLogoPath !== '' && ! str_starts_with($paymenterLogoPath, '/')) ? [$paymenterLogoPath] : [];

        $providers = $registry->decodeProviders();
        foreach (['google', 'discord', 'linkedin'] as $id) {
            $this->{"auth_providers_{$id}_enabled"} = (bool) ($providers[$id]['enabled'] ?? false);
            $this->{"auth_providers_{$id}_client_id"} = (string) ($providers[$id]['client_id'] ?? '');
            $this->{"auth_providers_{$id}_client_secret"} = '';
            $this->{"auth_providers_{$id}_redirect_uri"} = (string) ($providers[$id]['redirect_uri'] ?? '');
        }

        $this->form->fill($this->currentFormState());
    }

    public function form(Schema $schema): Schema
    {
        // Each tab gets the APP_URL-derived default redirect URI as the
        // placeholder + reset target. The stored value (if any) overrides
        // it via $this->auth_*_redirect_uri loaded in mount().
        return $schema->schema(AuthSettingsFormSchema::tabs(
            shopRedirect: $this->defaultRedirect('shop'),
            paymenterRedirect: $this->defaultRedirect('paymenter'),
            googleRedirect: $this->defaultRedirect('google'),
            discordRedirect: $this->defaultRedirect('discord'),
            linkedinRedirect: $this->defaultRedirect('linkedin'),
        ));
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
                    ->title(__('admin.notifications.auth_2fa_setup_first_title'))
                    ->body(__('admin.notifications.auth_2fa_setup_first_body'))
                    ->danger()
                    ->send();

                return;
            }
        }

        // Mutual exclusivity guard: only ONE canonical IdP can be active at a
        // time (Shop OR Paymenter, never both). Block early so we never write
        // an inconsistent state.
        $shopWillBeEnabled = (bool) ($data['auth_shop_enabled'] ?? false);
        $paymenterWillBeEnabled = (bool) ($data['auth_paymenter_enabled'] ?? false);
        if ($shopWillBeEnabled && $paymenterWillBeEnabled) {
            Notification::make()
                ->title(__('admin.notifications.auth_only_one_idp_title'))
                ->body(__('admin.notifications.auth_only_one_idp_body'))
                ->danger()
                ->send();

            return;
        }

        // S8 disable guardrail — block if a provider is being turned off and
        // exclusive users remain, unless the admin explicitly acknowledges.
        $ack = (bool) ($data['acknowledge_disable_risk'] ?? false);
        foreach (['shop', 'paymenter', 'google', 'discord', 'linkedin'] as $pid) {
            $wasEnabled = AuthSettingsPersister::wasPreviouslyEnabled($pid);
            $willBeEnabled = match ($pid) {
                'shop' => $shopWillBeEnabled,
                'paymenter' => $paymenterWillBeEnabled,
                default => (bool) ($data["auth_providers_{$pid}_enabled"] ?? false),
            };

            if ($wasEnabled && ! $willBeEnabled) {
                $exclusive = $registry->providerHasExclusiveUsers($pid);
                if ($exclusive > 0 && ! $ack) {
                    Notification::make()
                        ->title(__('admin.notifications.auth_disable_lockout_title', ['provider' => $pid, 'count' => $exclusive]))
                        ->body(__('admin.notifications.auth_disable_lockout_body'))
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

        AuthSettingsPersister::persistShop($data, $registry, $this->defaultRedirect('shop'));
        AuthSettingsPersister::persistPaymenter($data, $registry, $this->defaultRedirect('paymenter'));
        AuthSettingsPersister::persistSocialProviders($data, $registry, [
            'google' => $this->defaultRedirect('google'),
            'discord' => $this->defaultRedirect('discord'),
            'linkedin' => $this->defaultRedirect('linkedin'),
        ]);

        Notification::make()->title(__('admin.notifications.auth_settings_saved'))->success()->send();

        // Reset the acknowledgement + secrets inputs so they don't stick.
        $this->acknowledge_disable_risk = false;
        $this->auth_shop_client_secret = '';
        $this->auth_paymenter_client_secret = '';
        foreach (['google', 'discord', 'linkedin'] as $id) {
            $this->{"auth_providers_{$id}_client_secret"} = '';
        }
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
            'auth_shop_redirect_uri' => $this->auth_shop_redirect_uri,
            'auth_shop_register_url' => $this->auth_shop_register_url,
            'auth_shop_client_secret' => '',
            'auth_shop_logo_path' => $this->auth_shop_logo_path,
            'auth_paymenter_enabled' => $this->auth_paymenter_enabled,
            'auth_paymenter_base_url' => $this->auth_paymenter_base_url,
            'auth_paymenter_client_id' => $this->auth_paymenter_client_id,
            'auth_paymenter_client_secret' => '',
            'auth_paymenter_redirect_uri' => $this->auth_paymenter_redirect_uri,
            'auth_paymenter_register_url' => $this->auth_paymenter_register_url,
            'auth_paymenter_logo_path' => $this->auth_paymenter_logo_path,
            'auth_providers_google_enabled' => $this->auth_providers_google_enabled,
            'auth_providers_google_client_id' => $this->auth_providers_google_client_id,
            'auth_providers_google_client_secret' => '',
            'auth_providers_google_redirect_uri' => $this->auth_providers_google_redirect_uri,
            'auth_providers_discord_enabled' => $this->auth_providers_discord_enabled,
            'auth_providers_discord_client_id' => $this->auth_providers_discord_client_id,
            'auth_providers_discord_client_secret' => '',
            'auth_providers_discord_redirect_uri' => $this->auth_providers_discord_redirect_uri,
            'auth_providers_linkedin_enabled' => $this->auth_providers_linkedin_enabled,
            'auth_providers_linkedin_client_id' => $this->auth_providers_linkedin_client_id,
            'auth_providers_linkedin_client_secret' => '',
            'auth_providers_linkedin_redirect_uri' => $this->auth_providers_linkedin_redirect_uri,
            'acknowledge_disable_risk' => false,
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label(__('admin.actions.save_settings'))->submit('save'),
        ];
    }
}
