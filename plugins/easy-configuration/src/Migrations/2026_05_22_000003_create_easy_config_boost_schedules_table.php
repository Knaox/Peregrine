<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled "boosts": a temporary multiplication of selected numeric config
 * values over a date window. The scheduler dispatches ApplyBoostJob at
 * start_at and EndBoostJob at end_at; both stop the server, rewrite the files
 * and restart it. `parameters` snapshots, per boosted key, the user max_cap and
 * (once applied) the original baseline + the boosted value written.
 *
 * Run order: after the templates/copy tables. Rollback drops this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easy_config_boost_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('template_id');
            $table->decimal('multiplier', 8, 3);
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('status', 16)->default('pending'); // pending|active|completed|cancelled|failed
            $table->json('parameters'); // [{ file_id, section, key, max_cap?, original_value?, boosted_value? }]
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_at']);
            $table->index(['status', 'end_at']);
            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easy_config_boost_schedules');
    }
};
