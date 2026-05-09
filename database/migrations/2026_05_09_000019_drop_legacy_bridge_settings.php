<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase C — drop the legacy `bridge_mode` setting and its siblings.
 *
 * After the multi-shop refactor, integrations are opt-in independently :
 *  - Stripe webhook is wired the moment `bridge_stripe_webhook_secret` is set.
 *  - Pelican webhooks are governed by `pelican_webhook_enabled` /
 *    `pelican_webhook_token` (independent page).
 *  - Multi-shop is driven by rows in the `shops` table.
 *
 * The keys removed below have NO consumer left in the codebase :
 *  - `bridge_mode`              : was the radio backing `BridgeModeService`.
 *  - `bridge_enabled`           : legacy boolean kept in sync with bridge_mode
 *                                 for back-compat ; nothing reads it now.
 *  - `bridge_shop_url`          : legacy field on the now-removed
 *                                 `/admin/bridge-settings` page ; never read
 *                                 outside that page.
 *  - `bridge_pelican_webhook_token` : was the legacy fallback for the
 *                                 Pelican webhook token before
 *                                 2025_01_01_000033 extracted it to
 *                                 `pelican_webhook_token`.
 *
 * Down() is a no-op — these settings have no canonical default and
 * restoring them serves no purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', [
            'bridge_mode',
            'bridge_enabled',
            'bridge_shop_url',
            'bridge_pelican_webhook_token',
        ])->delete();
    }

    public function down(): void
    {
        // No-op : these keys are dead, no canonical value to restore.
    }
};
