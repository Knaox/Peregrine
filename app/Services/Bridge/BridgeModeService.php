<?php

namespace App\Services\Bridge;

use App\Enums\BridgeMode;
use App\Services\SettingsService;

/**
 * Reads the active Bridge mode from settings, with legacy fallback.
 *
 * The canonical source is `settings.bridge_mode` (string value of the
 * BridgeMode enum). For installations that haven't been migrated yet, we
 * fall back to the legacy `bridge_enabled` boolean → ShopStripe/Disabled.
 *
 * Cached via the underlying SettingsService cache (1h TTL) — same envelope
 * as every other setting read in the codebase. The migration that backfills
 * `bridge_mode` runs once and removes the need for the fallback path on
 * production installs after the next deploy.
 */
class BridgeModeService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function current(): BridgeMode
    {
        $stored = $this->settings->get('bridge_mode');

        if ($stored !== null && $stored !== '') {
            $mode = BridgeMode::tryFrom((string) $stored);
            if ($mode !== null) {
                return $mode;
            }
        }

        // Legacy fallback: pre-migration installs only have `bridge_enabled`.
        $legacyEnabled = $this->settings->get('bridge_enabled', 'false') === 'true';

        return $legacyEnabled ? BridgeMode::ShopStripe : BridgeMode::Disabled;
    }

    public function isShopStripe(): bool
    {
        return $this->current()->isShopStripe();
    }

    public function isPaymenter(): bool
    {
        return $this->current()->isPaymenter();
    }

    public function isActive(): bool
    {
        return $this->current()->isActive();
    }
}
