<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror parity fix for the "Réseau" page : Pelican Application API returns
 * an allocation record without telling you which one is the server's default
 * (the "Principal" badge in the UI) — that flag lives on the Server's
 * `attributes.allocation` field, not on the allocation itself.
 *
 * Without this column, the local DB read path forces `is_default = false`
 * for every row, so the badge never shows when the user toggles "Lecture
 * DB locale" on. We mirror the server's `default_allocation_id` into a
 * column on the allocation row and keep it in sync via :
 *  - AllocationMirrorBackfiller (second pass after listing allocations)
 *  - SyncServerFromPelicanWebhookJob (fires `updated: Server` whenever the
 *    primary changes)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pelican_allocations')) {
            return;
        }

        Schema::table('pelican_allocations', function (Blueprint $table): void {
            if (! Schema::hasColumn('pelican_allocations', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_locked')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pelican_allocations')) {
            return;
        }

        Schema::table('pelican_allocations', function (Blueprint $table): void {
            if (Schema::hasColumn('pelican_allocations', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });
    }
};
