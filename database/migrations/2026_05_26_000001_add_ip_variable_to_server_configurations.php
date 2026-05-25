<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "IP variable" feature to `server_configurations`.
 *
 * Intent : let an admin push the provisioned server's public IP into a chosen
 * egg environment variable, without typing the IP by hand. Three new columns
 * drive it :
 *   - ip_variable_enabled : master toggle for the feature.
 *   - ip_variable_name    : the egg `env_variable` that receives the IP
 *                           (e.g. SERVER_IP). Picked from the egg's variable
 *                           list in the Filament form (no free typing).
 *   - ip_variable_source  : where the IP comes from —
 *       'node_fqdn'        → resolve the node's FQDN to an A record
 *       'allocation_alias' → resolve the default allocation's ip_alias
 *     Both go through a Cloudflare DoH lookup
 *     (App\Services\Network\CloudflareDnsResolver) at provisioning time.
 *
 * Run order : after `server_configurations` exists (renamed from
 * `server_plans` in 2025_01_01_000023_extend_server_plans_for_bridge). Purely
 * additive — safe to run on a live table.
 *
 * Rollback : drops the three columns. No data migration needed — the feature
 * is opt-in and existing rows default to disabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_configurations', function (Blueprint $table): void {
            $table->boolean('ip_variable_enabled')->default(false)->after('env_var_mapping');
            $table->string('ip_variable_name')->nullable()->after('ip_variable_enabled');
            $table->string('ip_variable_source')->nullable()->after('ip_variable_name');
        });
    }

    public function down(): void
    {
        Schema::table('server_configurations', function (Blueprint $table): void {
            $table->dropColumn(['ip_variable_enabled', 'ip_variable_name', 'ip_variable_source']);
        });
    }
};
