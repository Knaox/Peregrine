<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links each server to its local mirrored Node and stores the FULL Pelican
 * uuid, so node health can be probed per server directly against Wings
 * (Wings routes are keyed by the full uuid, not the short identifier).
 *
 * Both columns are nullable and hydrated in two ways:
 *   - at provisioning time (ProvisionServerJob knows node + uuid from the
 *     create response),
 *   - lazily on first display via ResolveServerNodeAction for servers that
 *     existed before this migration (one Application-API call, persisted).
 *
 * node_id uses nullOnDelete: deleting a node simply unlinks its servers —
 * the link re-resolves automatically if the server still exists in Pelican.
 *
 * Rollback drops the FK + both columns; no data loss beyond the cached link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->foreignId('node_id')
                ->nullable()
                ->after('egg_id')
                ->constrained('nodes')
                ->nullOnDelete();
            $table->string('pelican_uuid')->nullable()->after('identifier');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('node_id');
            $table->dropColumn('pelican_uuid');
        });
    }
};
