<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of Pelican's `databases` table — metadata only. The plaintext
 * password is NEVER stored here (Pelican holds it encrypted; we fetch
 * via Client API on demand when the user clicks "Show password").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelican_databases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pelican_database_id')->unique();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('pelican_database_host_id')
                ->nullable()
                ->constrained('pelican_database_hosts')
                ->nullOnDelete();
            $table->string('database');
            $table->string('username');
            $table->string('remote')->default('%');
            $table->unsignedInteger('max_connections')->default(0);
            $table->timestamp('pelican_created_at')->nullable();
            $table->timestamp('pelican_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['server_id', 'pelican_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelican_databases');
    }
};
