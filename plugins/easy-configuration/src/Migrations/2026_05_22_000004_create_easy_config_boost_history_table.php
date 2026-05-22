<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive of finished boosts (completed | cancelled | failed). A boost is
 * copied here when it ends so the active table stays small and the server's
 * boost tab can show a history with the original + boosted values that were
 * applied. Run order: after the boost schedules table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easy_config_boost_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('template_id');
            $table->decimal('multiplier', 8, 3);
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('final_status', 16); // completed|cancelled|failed
            $table->json('parameters');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easy_config_boost_history');
    }
};
