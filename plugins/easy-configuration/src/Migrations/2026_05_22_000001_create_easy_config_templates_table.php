<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache / index of the on-disk template JSON files.
 *
 * Templates themselves live as JSON under `storage/app/easy-config/templates/`
 * (forkable, shareable). This table is a fast read-model rebuilt by the
 * TemplateRegistry from those files: it powers the admin listing and the
 * "which templates target egg X" lookup driving the server overview section.
 * No game-config VALUES are ever stored here — a template is a pure render
 * schema; values always live in the real file on the server.
 *
 * Run order: first migration of the plugin (foundational). Rollback drops the
 * cache table only; the source JSON files on disk are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easy_config_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_id')->unique();   // the JSON "id" (e.g. minecraft-vanilla)
            $table->string('version', 32)->default('1.0.0');
            $table->json('name');                       // {fr, en}
            $table->json('description')->nullable();    // {fr, en}
            $table->string('author')->nullable();
            $table->json('target_eggs');                // [int] local egg ids
            $table->boolean('boost_enabled')->default(false);
            $table->json('boost_blacklist')->nullable(); // [string] excluded parameter keys
            $table->unsignedSmallInteger('file_count')->default(0);
            $table->string('source_path');              // relative path under the templates disk
            $table->string('checksum', 64)->nullable(); // sha1 of file content, for cheap rebuild
            $table->boolean('is_valid')->default(true);
            $table->text('last_error')->nullable();     // schema validation error, if invalid
            $table->timestamps();

            $table->index('is_valid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easy_config_templates');
    }
};
