<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of Pelican's `database_hosts` table. Populated by the
 * SyncDatabaseHostFromPelicanWebhookJob. The host's password is never
 * mirrored — only metadata for the admin UI dropdown when creating a
 * database for a server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelican_database_hosts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelican_database_host_id')->unique();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port');
            $table->string('username');
            $table->unsignedInteger('max_databases')->default(0);
            $table->timestamp('pelican_created_at')->nullable();
            $table->timestamp('pelican_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelican_database_hosts');
    }
};
