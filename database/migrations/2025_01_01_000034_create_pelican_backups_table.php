<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of Pelican's `backups` table. Populated by the
 * SyncBackupFromPelicanWebhookJob and the hourly reconciliation when the
 * Pelican mass-update bypasses the webhook (PruneOrphanedBackupsCommand).
 *
 * Pelican's password / sensitive fields never reach this table — Backup
 * has no $hidden columns to begin with, but we whitelist the fields we
 * persist explicitly in the job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelican_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelican_backup_id')->unique();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('uuid', 36)->index();
            $table->string('name');
            $table->string('disk')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('pelican_created_at')->nullable();
            $table->timestamp('pelican_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['server_id', 'pelican_created_at']);
            $table->index('is_successful');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelican_backups');
    }
};
