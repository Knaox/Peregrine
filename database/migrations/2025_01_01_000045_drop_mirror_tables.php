<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops every Pelican mirror table left over from the rolled-back
 * "Lecture DB locale" feature. The SPA reads `/network /databases
 * /backups /sub-users` straight from Pelican's API now, so these
 * tables had no readers anymore — keeping them lying around just
 * inflated the schema and confused operators.
 *
 * Tables dropped :
 *   - pelican_allocations          (mirror of Pelican allocations)
 *   - pelican_backups              (mirror of per-server backups)
 *   - pelican_databases            (mirror of per-server databases)
 *   - pelican_database_hosts       (mirror of database hosts)
 *   - pelican_server_transfers     (mirror of in-flight transfers)
 *   - mirror_backfill_progress     (lifecycle of EnableLocalDbReadJob)
 *   - invitations_pelican_subusers (plugin-owned subuser mirror)
 *
 * Tables kept (NOT dropped) :
 *   - pelican_processed_events  (idempotency ledger for the receiver)
 *   - pelican_backfill_progress (used by `pelican:backfill-mirrors`
 *     command which still syncs the four CORE tables — users, nodes,
 *     eggs, servers)
 *
 * Idempotent : `dropIfExists` is a no-op when the table is already
 * gone (fresh install where the create_* migrations were removed).
 *
 * The down() migration intentionally cannot recreate the schema —
 * the create migrations were also deleted. Restoring the feature
 * means re-creating the tables from scratch in a future PR.
 */
return new class extends Migration
{
    private const TABLES = [
        'invitations_pelican_subusers',
        'mirror_backfill_progress',
        'pelican_server_transfers',
        'pelican_allocations',
        'pelican_databases',
        'pelican_database_hosts',
        'pelican_backups',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        // Drop the settings row that drove the rolled-back "Lecture DB
        // locale" toggle. Idempotent — no-op when the row is already gone
        // (fresh install or already cleaned up).
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'mirror_reads_enabled')->delete();
        }
    }

    public function down(): void
    {
        // No-op : the create migrations were removed in the same change.
        // Rolling forward would land on a clean schema without these
        // tables, which is the intended end state.
    }
};
