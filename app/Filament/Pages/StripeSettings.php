<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Pages\StripeSettings\StripeSettingsFormSchema;
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
 * Stripe integration settings — the SOLE config surface for Stripe
 * inbound webhooks + outbound calls + customer-facing URLs (billing
 * portal, resubscribe template) + grace period.
 *
 * Replaces the legacy `/admin/bridge-settings` page : the "BridgeMode"
 * radio is gone, Pelican webhooks are independent (`/admin/pelican-webhook-settings`),
 * multi-shop is in `/admin/shops`. This page sticks to Stripe.
 *
 * Encryption : `bridge_stripe_webhook_secret` and `bridge_stripe_api_secret`
 * are persisted via `Crypt::encryptString`. Empty input = keep existing
 * value (admin must type a fresh value to rotate). Non-secret fields
 * (URLs, grace period) are stored as plaintext.
 */
class StripeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 55;

    protected string $view = 'filament.pages.stripe-settings';

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/stripe_settings.page.navigation');
    }

    public function getTitle(): string
    {
        return __('admin/stripe_settings.page.title');
    }

    public ?string $bridge_stripe_webhook_secret = '';

    public ?string $bridge_stripe_api_secret = '';

    public ?string $bridge_stripe_billing_portal_url = '';

    public ?string $bridge_resubscribe_url = '';

    public int $bridge_grace_period_days = 14;

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        // Secrets are NEVER displayed — admin types a new value to rotate.
        $this->bridge_stripe_webhook_secret = '';
        $this->bridge_stripe_api_secret = '';

        $this->bridge_stripe_billing_portal_url = (string) $settings->get('bridge_stripe_billing_portal_url', '');
        $this->bridge_resubscribe_url = (string) $settings->get('bridge_resubscribe_url', '');
        $this->bridge_grace_period_days = (int) $settings->get('bridge_grace_period_days', 14);

        $this->form->fill([
            'bridge_stripe_webhook_secret' => $this->bridge_stripe_webhook_secret,
            'bridge_stripe_api_secret' => $this->bridge_stripe_api_secret,
            'bridge_stripe_billing_portal_url' => $this->bridge_stripe_billing_portal_url,
            'bridge_resubscribe_url' => $this->bridge_resubscribe_url,
            'bridge_grace_period_days' => $this->bridge_grace_period_days,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema(StripeSettingsFormSchema::sections());
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $typedWebhook = (string) ($data['bridge_stripe_webhook_secret'] ?? '');
        if ($typedWebhook !== '') {
            $settings->set('bridge_stripe_webhook_secret', Crypt::encryptString($typedWebhook));
        }

        $typedApi = (string) ($data['bridge_stripe_api_secret'] ?? '');
        if ($typedApi !== '') {
            $settings->set('bridge_stripe_api_secret', Crypt::encryptString($typedApi));
        }

        $settings->set('bridge_stripe_billing_portal_url', (string) ($data['bridge_stripe_billing_portal_url'] ?? ''));
        $settings->set('bridge_resubscribe_url', (string) ($data['bridge_resubscribe_url'] ?? ''));
        $settings->set('bridge_grace_period_days', (string) (int) ($data['bridge_grace_period_days'] ?? 14));

        Notification::make()
            ->title(__('admin/stripe_settings.notifications.saved'))
            ->success()
            ->send();

        // Don't keep typed secrets in the form state.
        $this->bridge_stripe_webhook_secret = '';
        $this->bridge_stripe_api_secret = '';
    }

    /** @return array<int, Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('admin/_shell.actions.save_settings'))
                ->submit('save'),
        ];
    }
}
