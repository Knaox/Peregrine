<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modpack_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('modpack_id');
            $table->string('modpack_name');
            $table->string('modpack_slug')->nullable();
            $table->string('version_id');
            $table->string('version_label')->nullable();
            $table->text('icon_url')->nullable();
            $table->text('external_url')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('status_message')->nullable();
            $table->boolean('purge_files')->default(false);
            $table->unsignedTinyInteger('java_version')->nullable();
            $table->unsignedBigInteger('pelican_egg_snapshot_id')->nullable();
            $table->string('pelican_image_snapshot')->nullable();
            $table->text('pelican_startup_snapshot')->nullable();
            $table->string('pelican_jarfile_snapshot')->nullable();
            $table->json('pelican_environment_snapshot')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('server_id');
            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modpack_installations');
    }
};
