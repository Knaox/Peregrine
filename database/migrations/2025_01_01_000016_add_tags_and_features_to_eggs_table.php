<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Egg model's $fillable + $casts have always declared `tags` and
     * `features` as JSON arrays (Pelican ships both on its egg resource),
     * but the initial create_eggs_table migration never created the
     * underlying columns. SyncService::syncEggs() then fails with:
     *   SQLSTATE[42S22] Unknown column 'tags' in 'field list'
     * and the cascade breaks the Server sync too.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table): void {
            if (! Schema::hasColumn('eggs', 'tags')) {
                $table->json('tags')->nullable()->after('description');
            }
            if (! Schema::hasColumn('eggs', 'features')) {
                $table->json('features')->nullable()->after('tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table): void {
            if (Schema::hasColumn('eggs', 'features')) {
                $table->dropColumn('features');
            }
            if (Schema::hasColumn('eggs', 'tags')) {
                $table->dropColumn('tags');
            }
        });
    }
};
