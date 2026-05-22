<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit ledger for the "copy configuration to other servers" feature. One row
 * per (batch, target server): the CopyConfigJob writes a row as it finishes
 * each target so the UI can poll a batch and report a per-server success/failure
 * recap. Partial failures are kept (no distributed rollback) and surfaced here.
 *
 * Run order: after the templates table. Rollback drops the ledger only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easy_config_copy_log', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('source_server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('target_server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('status', 16);          // success | failed
            $table->json('files')->nullable();      // [{ id, params }] copied
            $table->unsignedInteger('params_count')->default(0);
            $table->boolean('copied_boosts')->default(false);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easy_config_copy_log');
    }
};
