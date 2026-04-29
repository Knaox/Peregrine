<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the lifecycle of the "Activer la lecture DB locale" backfill job.
 *
 * One row per backfill run, ordered chronologically. The Filament page
 * polls the most recent row to render the progress UI :
 *  - `state=running`    → "Backfill en cours…" + spinner
 *  - `state=completed`  → "Lecture DB locale active depuis <date>"
 *  - `state=failed`     → red badge + error excerpt + "Réessayer" button
 *
 * Distinct from `pelican_backfill_progress` (per-resource cursor for the
 * resumable artisan command) — this one is global, append-only and
 * captures the full report as JSON for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mirror_backfill_progress', function (Blueprint $table) {
            $table->id();
            $table->string('state', 16)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['state', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mirror_backfill_progress');
    }
};
