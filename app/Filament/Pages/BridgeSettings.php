<?php

namespace App\Filament\Pages;

use App\Enums\BridgeMode;
use App\Filament\Pages\BridgeSettings\BridgeSettingsFormSchema;
use App\Services\Bridge\BridgeModeService;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use UnitEnum;

/**
 * Admin page for Bridge configuration. Hosts BOTH bridge backends on a
 * single screen — only one can be active at a time:
 *
 *   - Shop + Stripe : Shop pushes plans via signed HTTP API, Stripe sends
 *     subscription events directly to Peregrine.
 *   - Paymenter     : Paymenter orchestrates everything (plans + emails +
 *     billing). Peregrine just mirrors Pelican state via Pelican's native
 *     outgoing webhooks. No plans page, no Bridge emails in this mode.
 *
 * The active mode is stored as the `bridge_mode` enum (Disabled/ShopStripe/
 * Paymenter). All secrets are encrypted via Crypt::encryptString in the
 * `settings` table; saving an empty secret field keeps the existing value.
 *
 * Form sections live in `BridgeSettings/BridgeSettingsFormSchema.php`,
 * HTML rendering helpers in `BridgeSettings/BridgeSettingsHtmlHelpers.php`
 * (sibling pattern matching Settings/Theme/AuthSettings — see CLAUDE.md
 * file size rule).
 */
class BridgeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 55;

    protected static ?string $title = 'Bridge';

    protected string $view = 'filament.pages.bridge-settings';

    public string $bridge_mode = 'disabled';

    public ?string $bridge_shop_url = '';

    public ?string $bridge_shop_shared_secret = '';

    public ?string $bridge_stripe_webhook_secret = '';

    public ?string $bridge_stripe_api_secret = '';

    public int $bridge_grace_period_days = 14;

    public ?string $bridge_pelican_webhook_token = '';

    public function mount(): void
    {
        $settings = app(SettingsService::class);
        $modeService = app(BridgeModeService::class);

        $this->bridge_mode = $modeService->current()->value;
        $this->bridge_shop_url = (string) $settings->get('bridge_shop_url', '');
        // Never display the stored secrets — admin types new ones to replace.
        $this->bridge_shop_shared_secret = '';
        $this->bridge_stripe_webhook_secret = '';
        $this->bridge_stripe_api_secret = '';
        $this->bridge_pelican_webhook_token = '';
        $this->bridge_grace_period_days = (int) $settings->get('bridge_grace_period_days', 14);

        $this->form->fill($this->currentFormState());
    }

    public function form(Schema $schema): Schema
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $bridgeApiDocsUrl = url('/docs/bridge-api');
        $paymenterDocsUrl = url('/docs/bridge-paymenter');
        $auditLogUrl = url('/admin/pelican-webhook-logs');

        return $schema->schema([
            BridgeSettingsFormSchema::modeSelector(),
            BridgeSettingsFormSchema::shopStripeSection($baseUrl, $bridgeApiDocsUrl),
            BridgeSettingsFormSchema::paymenterSection($baseUrl, $paymenterDocsUrl, $auditLogUrl),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $modeValue = (string) ($data['bridge_mode'] ?? BridgeMode::Disabled->value);
        $mode = BridgeMode::tryFrom($modeValue) ?? BridgeMode::Disabled;
        $settings->set('bridge_mode', $mode->value);

        // Backward-compat: keep the legacy `bridge_enabled` boolean in sync
        // for any caller that still reads it directly. Sprint 4 removes the
        // last call sites and this can be deleted then.
        $settings->set('bridge_enabled', $mode->isShopStripe() ? 'true' : 'false');

        $settings->set('bridge_shop_url', (string) ($data['bridge_shop_url'] ?? ''));

        $typedSecret = (string) ($data['bridge_shop_shared_secret'] ?? '');
        if ($typedSecret !== '') {
            $settings->set('bridge_shop_shared_secret', Crypt::encryptString($typedSecret));
        }

        $typedStripeSecret = (string) ($data['bridge_stripe_webhook_secret'] ?? '');
        if ($typedStripeSecret !== '') {
            $settings->set('bridge_stripe_webhook_secret', Crypt::encryptString($typedStripeSecret));
        }

        $typedStripeApiSecret = (string) ($data['bridge_stripe_api_secret'] ?? '');
        if ($typedStripeApiSecret !== '') {
            $settings->set('bridge_stripe_api_secret', Crypt::encryptString($typedStripeApiSecret));
        }

        $typedPelicanToken = (string) ($data['bridge_pelican_webhook_token'] ?? '');
        if ($typedPelicanToken !== '') {
            $settings->set('bridge_pelican_webhook_token', Crypt::encryptString($typedPelicanToken));
        }

        $settings->set('bridge_grace_period_days', (string) (int) ($data['bridge_grace_period_days'] ?? 14));

        Notification::make()->title('Bridge settings saved')->success()->send();

        // Don't keep the typed secrets in the form state.
        $this->bridge_shop_shared_secret = '';
        $this->bridge_stripe_webhook_secret = '';
        $this->bridge_stripe_api_secret = '';
        $this->bridge_pelican_webhook_token = '';
    }

    /**
     * @return array<string, mixed>
     */
    private function currentFormState(): array
    {
        return [
            'bridge_mode' => $this->bridge_mode,
            'bridge_shop_url' => $this->bridge_shop_url,
            'bridge_shop_shared_secret' => $this->bridge_shop_shared_secret,
            'bridge_stripe_webhook_secret' => $this->bridge_stripe_webhook_secret,
            'bridge_stripe_api_secret' => $this->bridge_stripe_api_secret,
            'bridge_grace_period_days' => $this->bridge_grace_period_days,
            'bridge_pelican_webhook_token' => $this->bridge_pelican_webhook_token,
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save Settings')->submit('save'),
        ];
    }
}
