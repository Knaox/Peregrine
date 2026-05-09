<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resource templates centralise the Pelican resource specs (RAM, CPU,
 * disk, swap, I/O weight, cpu_pinning) that used to live inline on
 * `server_configurations`. Multiple configurations can share the same
 * template ; renaming the template's `name` ("Medium-Medium") propagates
 * everywhere automatically, and editing a spec value updates every
 * configuration bound to it in one shot.
 *
 * The next migration adds `resource_template_id` to `server_configurations`
 * and back-fills it from the existing inline values ; only after that
 * do we drop the inline columns.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('resource_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();

            // Pelican resource limits, mirroring the shape currently on
            // `server_configurations` so the back-fill is a 1:1 copy.
            $table->unsignedInteger('ram')->nullable();
            $table->unsignedInteger('cpu')->nullable();
            $table->unsignedInteger('disk')->nullable();
            $table->unsignedInteger('swap_mb')->default(0);
            $table->unsignedSmallInteger('io_weight')->default(500);
            $table->string('cpu_pinning', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_templates');
    }
};
