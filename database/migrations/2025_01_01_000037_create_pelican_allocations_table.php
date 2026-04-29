<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of Pelican's `allocations` table (network ports per node).
 * server_id is nullable: free allocations exist on a node without being
 * assigned to a specific server. Reconciliation hourly catches the
 * mass-update bypass cases (TransferServerService, ServerDeletionService,
 * BuildModificationService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelican_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelican_allocation_id')->unique();
            $table->foreignId('node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained('servers')->nullOnDelete();
            $table->string('ip', 45);
            $table->unsignedSmallInteger('port');
            $table->string('ip_alias')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('pelican_created_at')->nullable();
            $table->timestamp('pelican_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['server_id', 'port']);
            $table->index(['node_id', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelican_allocations');
    }
};
