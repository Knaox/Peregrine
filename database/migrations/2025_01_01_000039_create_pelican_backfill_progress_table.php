<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track progress of `pelican:backfill-mirrors` so the command is resumable
 * after interruption (network blip, queue worker crash, etc.).
 *
 * One row per resource type — `last_processed_id` lets the command resume
 * exactly where it stopped. `completed_at` set means the resource is fully
 * synced; null means in-progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelican_backfill_progress', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 64)->unique();
            $table->unsignedBigInteger('last_processed_id')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelican_backfill_progress');
    }
};
