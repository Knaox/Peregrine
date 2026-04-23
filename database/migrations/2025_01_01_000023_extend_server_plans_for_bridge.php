<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étend `server_plans` pour supporter les plans poussés par le Shop via API.
 *
 * Le Shop pousse les champs business + Pelican specs (mirror lecture-seule
 * côté Peregrine). L'admin Peregrine ajoute ensuite la config technique
 * (egg, node, docker_image, port_count, env_var_mapping, toggles avancés).
 *
 * Breaking changes contrôlés :
 *  - `stripe_price_id` passe NULLABLE (le Shop pousse un plan AVANT d'avoir
 *    cliqué "Sync to Stripe" — donc Stripe Price ID arrive plus tard)
 *  - `egg_id` / `nest_id` / `node_id` passent NULLABLE (un plan fresh-from-Shop
 *    n'a pas encore d'egg/node configuré côté Peregrine)
 *
 * La FK cascadeOnDelete devient nullOnDelete pour rester cohérent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop puis recréer les FK avec nullOnDelete (Laravel ne sait pas les
        // modifier in-place sur SQLite/MySQL via Blueprint).
        Schema::table('server_plans', function (Blueprint $table) {
            $table->dropForeign(['egg_id']);
            $table->dropForeign(['nest_id']);
            $table->dropForeign(['node_id']);
        });

        Schema::table('server_plans', function (Blueprint $table) {
            // Passer les colonnes existantes en nullable
            $table->string('stripe_price_id')->nullable()->change();
            $table->unsignedBigInteger('egg_id')->nullable()->change();
            $table->unsignedBigInteger('nest_id')->nullable()->change();
            $table->unsignedBigInteger('ram')->nullable()->change();
            $table->unsignedBigInteger('cpu')->nullable()->change();
            $table->unsignedBigInteger('disk')->nullable()->change();
            $table->unsignedBigInteger('node_id')->nullable()->change();

            // Ajouter les colonnes Mirror Shop (business)
            $table->unsignedBigInteger('shop_plan_id')->nullable()->unique()->after('id');
            $table->string('shop_plan_slug')->nullable()->after('shop_plan_id');
            $table->string('shop_plan_type')->nullable()->after('shop_plan_slug');
            $table->text('description')->nullable()->after('name');
            $table->unsignedBigInteger('price_cents')->nullable()->after('description');
            $table->string('currency', 3)->nullable()->after('price_cents');
            $table->string('interval')->nullable()->after('currency');
            $table->unsignedInteger('interval_count')->nullable()->after('interval');
            $table->boolean('has_trial')->default(false)->after('interval_count');
            $table->string('trial_interval')->nullable()->after('has_trial');
            $table->unsignedInteger('trial_interval_count')->nullable()->after('trial_interval');

            // Ajouter colonnes Pelican specs (Shop)
            $table->unsignedInteger('swap_mb')->default(0)->after('disk');
            $table->unsignedInteger('io_weight')->default(500)->after('swap_mb');
            $table->string('cpu_pinning')->nullable()->after('io_weight');

            // Ajouter colonnes Config Peregrine (admin)
            $table->unsignedBigInteger('default_node_id')->nullable()->after('node_id');
            $table->json('allowed_node_ids')->nullable()->after('default_node_id');
            $table->boolean('auto_deploy')->default(false)->after('allowed_node_ids');
            $table->string('docker_image')->nullable()->after('auto_deploy');
            $table->unsignedInteger('port_count')->default(1)->after('docker_image');
            $table->json('env_var_mapping')->nullable()->after('port_count');
            $table->boolean('enable_oom_killer')->default(true)->after('env_var_mapping');
            $table->boolean('start_on_completion')->default(true)->after('enable_oom_killer');
            $table->boolean('skip_install_script')->default(false)->after('start_on_completion');
            $table->boolean('dedicated_ip')->default(false)->after('skip_install_script');
            $table->unsignedInteger('feature_limits_databases')->default(0)->after('dedicated_ip');
            $table->unsignedInteger('feature_limits_backups')->default(3)->after('feature_limits_databases');
            $table->unsignedInteger('feature_limits_allocations')->default(1)->after('feature_limits_backups');

            // Custom fields Stripe Checkout (config par plan, source Shop)
            $table->json('checkout_custom_fields')->nullable()->after('feature_limits_allocations');

            // Tracking sync
            $table->timestamp('last_shop_synced_at')->nullable()->after('updated_at');

            // Recréer les FK avec nullOnDelete au lieu de cascadeOnDelete
            $table->foreign('egg_id')->references('id')->on('eggs')->nullOnDelete();
            $table->foreign('nest_id')->references('id')->on('nests')->nullOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->nullOnDelete();
            $table->foreign('default_node_id')->references('id')->on('nodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('server_plans', function (Blueprint $table) {
            $table->dropForeign(['egg_id']);
            $table->dropForeign(['nest_id']);
            $table->dropForeign(['node_id']);
            $table->dropForeign(['default_node_id']);

            $table->dropColumn([
                'shop_plan_id',
                'shop_plan_slug',
                'shop_plan_type',
                'description',
                'price_cents',
                'currency',
                'interval',
                'interval_count',
                'has_trial',
                'trial_interval',
                'trial_interval_count',
                'swap_mb',
                'io_weight',
                'cpu_pinning',
                'default_node_id',
                'allowed_node_ids',
                'auto_deploy',
                'docker_image',
                'port_count',
                'env_var_mapping',
                'enable_oom_killer',
                'start_on_completion',
                'skip_install_script',
                'dedicated_ip',
                'feature_limits_databases',
                'feature_limits_backups',
                'feature_limits_allocations',
                'checkout_custom_fields',
                'last_shop_synced_at',
            ]);

            // Restaurer FK cascadeOnDelete originales
            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
            $table->foreign('nest_id')->references('id')->on('nests')->cascadeOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });
    }
};
