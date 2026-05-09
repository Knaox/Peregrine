<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of the resource-template extraction.
 *
 * 1. Adds `resource_template_id` (nullable FK) to `server_configurations`.
 * 2. Back-fills the column : each distinct tuple of inline specs becomes
 *    a `resource_templates` row, and every configuration sharing that
 *    tuple gets pointed at the new template id. Auto-named when no
 *    natural label is available (`auto-{counter}`).
 * 3. Drops the inline spec columns from `server_configurations`.
 *
 * `down()` does NOT recreate the inline data perfectly (only the
 * columns) — this is intentional : a rollback after data loss expects
 * an admin restore from backup.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('server_configurations', function (Blueprint $table) {
            $table->foreignId('resource_template_id')
                ->nullable()
                ->after('cpu_pinning')
                ->constrained('resource_templates')
                ->nullOnDelete();
        });

        $this->backfillTemplates();

        Schema::table('server_configurations', function (Blueprint $table) {
            // Drop the inline specs once every row points at a template.
            $table->dropColumn(['ram', 'cpu', 'disk', 'swap_mb', 'io_weight', 'cpu_pinning']);
        });
    }

    public function down(): void
    {
        Schema::table('server_configurations', function (Blueprint $table) {
            // Recreate the columns nullable so a rollback doesn't fail —
            // values are lost (admin restores from a backup if needed).
            $table->unsignedInteger('ram')->nullable();
            $table->unsignedInteger('cpu')->nullable();
            $table->unsignedInteger('disk')->nullable();
            $table->unsignedInteger('swap_mb')->default(0);
            $table->unsignedSmallInteger('io_weight')->default(500);
            $table->string('cpu_pinning', 64)->nullable();
        });

        Schema::table('server_configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resource_template_id');
        });
    }

    /**
     * Walk every existing configuration, group by (ram, cpu, disk,
     * swap_mb, io_weight, cpu_pinning), insert a single template row per
     * group, and update every member of the group with the new id.
     */
    private function backfillTemplates(): void
    {
        $rows = DB::table('server_configurations')
            ->select('id', 'ram', 'cpu', 'disk', 'swap_mb', 'io_weight', 'cpu_pinning')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = json_encode([
                $row->ram, $row->cpu, $row->disk,
                $row->swap_mb, $row->io_weight, $row->cpu_pinning,
            ]);
            $groups[$key][] = $row;
        }

        $counter = 1;
        foreach ($groups as $signature => $members) {
            $first = $members[0];
            $name = $this->deriveTemplateName($first, $counter);
            $counter++;

            $templateId = DB::table('resource_templates')->insertGetId([
                'name' => $name,
                'ram' => $first->ram,
                'cpu' => $first->cpu,
                'disk' => $first->disk,
                'swap_mb' => $first->swap_mb ?? 0,
                'io_weight' => $first->io_weight ?? 500,
                'cpu_pinning' => $first->cpu_pinning,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids = array_map(fn ($m) => $m->id, $members);
            DB::table('server_configurations')
                ->whereIn('id', $ids)
                ->update(['resource_template_id' => $templateId]);
        }
    }

    /**
     * Build a human-readable template name from the spec tuple, falling
     * back to `auto-N` when not enough information is available.
     */
    private function deriveTemplateName(object $row, int $counter): string
    {
        $ram = (int) ($row->ram ?? 0);
        $disk = (int) ($row->disk ?? 0);

        if ($ram > 0 && $disk > 0) {
            $ramLabel = $ram >= 1024 ? round($ram / 1024).'GB' : $ram.'MB';
            $diskLabel = $disk >= 1024 ? round($disk / 1024).'GB' : $disk.'MB';
            $base = $ramLabel.'-RAM-'.$diskLabel.'-DISK';
        } else {
            $base = 'auto-'.$counter;
        }

        // Resolve unicity in case the natural name collides with another
        // backfilled group or a pre-existing row (paranoid guard — the
        // transaction in up() makes a real collision unlikely).
        $candidate = $base;
        $suffix = 2;
        while (DB::table('resource_templates')->where('name', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
};
