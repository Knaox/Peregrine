<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\Shop;
use App\Services\SettingsService;

/**
 * Functional replacement for the legacy `BridgeModeService` / `BridgeMode`
 * enum. The "mode" concept (Disabled / ShopStripe / Paymenter) is gone —
 * every integration is now opt-in independently :
 *
 *   - Stripe webhooks fire the moment `bridge_stripe_webhook_secret` is set.
 *   - Pelican webhooks are ALWAYS active (gated only by their own
 *     `pelican_webhook_enabled` toggle on `/admin/pelican-webhook-settings`).
 *   - Multi-shop is driven by rows in the `shops` table — `hasActiveShop()`
 *     becomes the truth source instead of a global mode setting.
 *
 * Callers ask "is X configured ?" rather than "is the bridge in mode Y ?".
 * Cheap reads — `SettingsService::get` is already cached and the active-shop
 * existence check hits an indexed column.
 */
final class IntegrationStatusService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Stripe inbound webhooks are wired the moment the signing secret is
     * configured. This is the canonical replacement for the old
     * `BridgeMode::isShopStripe()` gate used by listeners + Filament.
     */
    public function hasStripeConfigured(): bool
    {
        return $this->settingHasValue('bridge_stripe_webhook_secret');
    }

    /**
     * Stripe API secret is required for outbound calls (creating Customer
     * Portal sessions, fetching invoice URLs in receipt emails). Distinct
     * from the inbound webhook secret — admin can configure one without
     * the other.
     */
    public function hasStripeApiKey(): bool
    {
        return $this->settingHasValue('bridge_stripe_api_secret');
    }

    /**
     * True when at least one `Shop` row exists with status='active'. Used by
     * Filament gates that want to know "is the multi-shop surface in use ?"
     * — replaces the legacy `BridgeMode::isShopStripe()` semantic.
     */
    public function hasActiveShop(): bool
    {
        try {
            return Shop::query()->where('status', 'active')->exists();
        } catch (\Throwable) {
            // Table may not exist yet during fresh install / migrations.
            return false;
        }
    }

    /**
     * Aggregate "is anything wired up ?" check. Used by the dashboard
     * widgets that want a single boolean for "show the integrations
     * panel / hide it".
     */
    public function hasAnyIntegration(): bool
    {
        return $this->hasStripeConfigured() || $this->hasActiveShop();
    }

    private function settingHasValue(string $key): bool
    {
        $value = $this->settings->get($key, '');
        return is_string($value) && $value !== '';
    }
}
