<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds recurrence to scheduled boosts: a boost may repeat `daily`, `weekly` or
 * `monthly`. When a recurring boost completes, the scheduler re-arms the next
 * occurrence (same window shifted by one interval) until `recurrence_until`
 * (null = indefinitely, until the user cancels the series).
 *
 * Run order: after the boost schedules table. Rollback drops the two columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('easy_config_boost_schedules', function (Blueprint $table): void {
            $table->string('recurrence', 16)->nullable()->after('end_at'); // null|daily|weekly|monthly
            $table->timestamp('recurrence_until')->nullable()->after('recurrence'); // null = indefinite
        });
    }

    public function down(): void
    {
        Schema::table('easy_config_boost_schedules', function (Blueprint $table): void {
            $table->dropColumn(['recurrence', 'recurrence_until']);
        });
    }
};
