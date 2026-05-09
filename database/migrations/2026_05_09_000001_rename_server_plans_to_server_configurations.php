<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Foundation : rename `server_plans` → `server_configurations`.
 *
 * Atomic on MySQL/Postgres ; SQLite emulates rename via temp table copy.
 * No data loss : ids and FKs preserved. Subsequent migrations drop the
 * commercial columns and rename the FK column on `servers`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('server_plans', 'server_configurations');
    }

    public function down(): void
    {
        Schema::rename('server_configurations', 'server_plans');
    }
};
