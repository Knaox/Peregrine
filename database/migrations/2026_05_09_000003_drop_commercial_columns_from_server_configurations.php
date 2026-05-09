<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Foundation : drop the 16 commercial columns from
 * `server_configurations`. Peregrine is a server-management platform ; the
 * commerce concepts (price, currency, billing cycle, trial, marketing
 * description, Stripe price id) belong in the external shop and Stripe.
 *
 * Lookup of a configuration from a Stripe webhook is now driven by Stripe
 * metadata (`peregrine_configuration_id`) — the `stripe_price_id` column is
 * therefore obsolete. Visibility per shop will be carried by the
 * `shop_server_configuration` pivot in Phase 2 ; the `is_active` global flag
 * is dropped here.
 *
 * Run order : MUST come AFTER the `add_internal_name_and_name_template`
 * migration, which back-fills `internal_name` from the legacy `name` before
 * we drop it.
 */
return new class extends Migration
{
    /**
     * Columns to drop — kept here as a const for the down() reverse path.
     *
     * @var list<string>
     */
    private const COMMERCIAL_COLUMNS = [
        'name',
        'description',
        'shop_plan_id',
        'shop_plan_slug',
        'shop_plan_type',
        'price_cents',
        'currency',
        'interval',
        'interval_count',
        'has_trial',
        'trial_interval',
        'trial_interval_count',
        'stripe_price_id',
        'is_active',
        'checkout_custom_fields',
        'last_shop_synced_at',
    ];

    public function up(): void
    {
        // Drop the legacy unique indexes first — SQLite's emulated
        // dropColumn fails the post-drop integrity check otherwise (an index
        // still references a column that's about to disappear). Two unique
        // indexes were declared in earlier migrations :
        //  - `shop_plan_id` (2025_01_01_000023_extend_server_plans_for_bridge)
        //  - `stripe_price_id` (2025_01_01_000005_create_server_plans_table)
        // SQLite preserves index names across table renames, so they're still
        // prefixed with `server_plans_…`. MySQL renames them to follow the
        // new table prefix — we try both candidates.
        $indexCandidates = [
            'shop_plan_id' => ['server_plans_shop_plan_id_unique', 'server_configurations_shop_plan_id_unique'],
            'stripe_price_id' => ['server_plans_stripe_price_id_unique', 'server_configurations_stripe_price_id_unique'],
        ];
        foreach ($indexCandidates as $column => $names) {
            if (! Schema::hasColumn('server_configurations', $column)) {
                continue;
            }
            foreach ($names as $indexName) {
                try {
                    Schema::table('server_configurations', function (Blueprint $table) use ($indexName) {
                        $table->dropUnique($indexName);
                    });
                    break;
                } catch (\Throwable) {
                    // Try the next candidate. If neither match, the dropColumn
                    // below will fail loudly with an explicit error.
                }
            }
        }

        Schema::table('server_configurations', function (Blueprint $table) {
            foreach (self::COMMERCIAL_COLUMNS as $column) {
                if (Schema::hasColumn('server_configurations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('server_configurations', function (Blueprint $table) {
            // Recreate as nullable so existing rows survive the rollback —
            // commercial data itself is not restored (that's expected ; the
            // shop owns it).
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('shop_plan_id')->nullable()->unique();
            $table->string('shop_plan_slug')->nullable();
            $table->string('shop_plan_type')->nullable();
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('interval')->nullable();
            $table->unsignedInteger('interval_count')->nullable();
            $table->boolean('has_trial')->default(false);
            $table->string('trial_interval')->nullable();
            $table->unsignedInteger('trial_interval_count')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('checkout_custom_fields')->nullable();
            $table->timestamp('last_shop_synced_at')->nullable();
        });
    }
};
