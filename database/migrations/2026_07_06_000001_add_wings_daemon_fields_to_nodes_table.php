<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Wings daemon connection details to the mirrored `nodes` rows so
 * Peregrine can probe node health directly against the Wings API
 * ({scheme}://{fqdn}:{daemon_listen}) without round-tripping through Pelican.
 *
 * - `scheme` / `daemon_listen` / `maintenance_mode` come from the standard
 *   `GET /api/application/nodes` payload (synced by InfrastructureSync,
 *   PelicanMirrorSyncer and the node webhook mirror job). Existing rows get
 *   safe defaults (https / 8080 / false) and converge on the next sync.
 * - `daemon_token_id` / `daemon_token` are NOT exposed by that endpoint
 *   (hidden on Pelican's Node model); they are lazily hydrated from
 *   `GET /api/application/nodes/{id}/configuration` the first time a health
 *   check needs them, and re-fetched once if Wings rejects them (token
 *   rotation self-healing). `daemon_token` is encrypted at rest via the
 *   model's `encrypted` cast, mirroring how Pelican stores it.
 *
 * Rollback drops the columns — nothing else references them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('scheme', 10)->default('https')->after('fqdn');
            $table->unsignedInteger('daemon_listen')->default(8080)->after('scheme');
            $table->string('daemon_token_id')->nullable()->after('daemon_listen');
            $table->text('daemon_token')->nullable()->after('daemon_token_id');
            $table->boolean('maintenance_mode')->default(false)->after('daemon_token');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'scheme',
                'daemon_listen',
                'daemon_token_id',
                'daemon_token',
                'maintenance_mode',
            ]);
        });
    }
};
