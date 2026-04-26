<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Egg Config Editor — minimal 2-table schema.
 *
 * Two concepts only :
 *
 *   `egg_config_files`  — one row per (egg, file path) pair the admin wants
 *                         to expose to players. Holds the file location and
 *                         its format hint (.properties / .ini / .json).
 *
 *   `egg_config_rules`  — one row per parameter inside that file the admin
 *                         wants to surface to players. Anything NOT in this
 *                         table stays invisible (and therefore protected) —
 *                         even if the underlying file has 200 keys, players
 *                         only see the ones the admin curated.
 *
 * Intentional simplifications vs the source plugin :
 *   - No section_rules table (INI sections are handled by the parser as flat
 *     keys with a `section.key` notation if needed).
 *   - No per-product/plan rule overrides (one set of rules per file, period).
 *   - No `egg_ids` JSON for sharing one file across multiple eggs (one egg
 *     per file row; admin duplicates if needed — clicker than thinker).
 *   - No env-variable bridging on rules (config files only).
 *   - No custom true/false values (parser auto-detects the convention used).
 *   - No force_type override (admin picks the right input_type from the
 *     small enum below).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_config_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('egg_id')
                ->constrained('eggs')
                ->cascadeOnDelete();
            $table->string('file_path', 500);
            // Restricted enum on purpose — only the 3 formats supported by
            // ConfigParserService at v0.1. Adding a value here without
            // updating the parser would 500 on read.
            $table->enum('file_type', ['properties', 'ini', 'json']);
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Same egg + same path twice = config conflict, block it at the
            // schema layer rather than discovering it at runtime.
            $table->unique(['egg_id', 'file_path'], 'egg_config_files_egg_path_unique');
        });

        Schema::create('egg_config_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('egg_config_file_id')
                ->constrained('egg_config_files')
                ->cascadeOnDelete();
            // The exact key as it appears in the file (e.g. `max-players`,
            // `ServerPVE`, or `ServerSettings.MaxPlayers` for nested INI).
            $table->string('config_key', 200);
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            // Drives the React input rendered to the player.
            $table->enum('input_type', ['text', 'number', 'boolean', 'select']);
            $table->string('default_value', 1000)->nullable();
            // Numeric constraints (only honoured for input_type === number).
            $table->double('min_value')->nullable();
            $table->double('max_value')->nullable();
            $table->double('step')->nullable();
            // For input_type === select : JSON array of {value, label}.
            $table->json('options')->nullable();
            $table->boolean('hidden')->default(false);
            $table->boolean('readonly')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['egg_config_file_id', 'config_key'], 'egg_config_rules_file_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_config_rules');
        Schema::dropIfExists('egg_config_files');
    }
};
