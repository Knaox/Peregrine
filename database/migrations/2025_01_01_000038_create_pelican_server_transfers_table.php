<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of Pelican's `server_transfers` table — used to surface a
 * "transfer in progress" badge on the server detail page in Peregrine
 * without polling Pelican every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelican_server_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelican_server_transfer_id')->unique();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->boolean('successful')->nullable();
            $table->unsignedBigInteger('old_node')->nullable();
            $table->unsignedBigInteger('new_node')->nullable();
            $table->unsignedBigInteger('old_allocation')->nullable();
            $table->unsignedBigInteger('new_allocation')->nullable();
            $table->json('old_additional_allocations')->nullable();
            $table->json('new_additional_allocations')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamp('pelican_created_at')->nullable();
            $table->timestamp('pelican_updated_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelican_server_transfers');
    }
};
