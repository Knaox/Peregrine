<?php

declare(strict_types=1);

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the original KeyValue settings (4 keys in the `settings` table) with
 * a singleton Eloquent row at `modpack_configs.id = 1`. Mirrors the pattern
 * used by `ark-mods-installer` so the admin UI can be a real Filament
 * Resource backed by a model rather than a synthetic Page with public
 * properties wired by hand.
 *
 * The migration also imports the four legacy keys (curseforge api key,
 * whitelist, timeout, default provider) so existing installs don't lose
 * their config — idempotent: skipped when the row already exists or when
 * the legacy `settings` table isn't present.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modpack_configs', function (Blueprint $table): void {
            $table->id();
            $table->json('egg_ids');
            $table->longText('curseforge_api_key')->nullable();
            $table->string('default_provider', 32)->default('modrinth');
            $table->string('default_sort', 32)->default('relevance');
            $table->string('page_label', 255)->nullable();
            $table->string('page_route', 64)->default('/modpacks');
            $table->unsignedSmallInteger('modpacks_per_page')->default(12);
            $table->unsignedSmallInteger('install_timeout_minutes')->default(30);
            $table->unsignedInteger('cache_ttl_seconds')->default(3600);
            $table->timestamps();
        });

        $this->migrateLegacySettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('modpack_configs');
    }

    /**
     * Best-effort: pull the four legacy keys from the SettingsService and
     * seed the singleton row. We deliberately don't decrypt the CurseForge
     * key here — it stays in its encrypted form in `curseforge_api_key`,
     * matching the new model's `encrypted` cast which round-trips through
     * the same Crypt::encryptString/decryptString pair.
     */
    private function migrateLegacySettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        try {
            $settings = app(SettingsService::class);
        } catch (\Throwable) {
            return;
        }

        $rawKey = $settings->get('modpack_curseforge_api_key');
        $whitelist = $settings->get('modpack_whitelisted_egg_ids', '[]');
        $timeout = $settings->get('modpack_install_timeout_minutes', 30);
        $provider = $settings->get('modpack_default_provider', 'modrinth');

        $eggIds = is_string($whitelist) ? json_decode($whitelist, true) : $whitelist;
        if (! is_array($eggIds)) {
            $eggIds = [];
        }
        $eggIds = array_values(array_filter(
            array_map('intval', $eggIds),
            static fn (int $id): bool => $id > 0,
        ));

        $apiKeyForModel = null;
        if (is_string($rawKey) && $rawKey !== '') {
            // Legacy column stored Crypt::encryptString output; decrypt
            // here so the Eloquent `encrypted` cast can re-encrypt on
            // insert (the cast adds metadata of its own).
            try {
                $apiKeyForModel = \Illuminate\Support\Facades\Crypt::decryptString($rawKey);
            } catch (\Throwable) {
                $apiKeyForModel = null;
            }
        }

        \Plugins\MinecraftModpackInstaller\Models\ModpackConfig::query()->updateOrCreate(
            ['id' => 1],
            [
                'egg_ids' => $eggIds,
                'curseforge_api_key' => $apiKeyForModel,
                'default_provider' => is_string($provider) ? $provider : 'modrinth',
                'default_sort' => 'relevance',
                'page_label' => null,
                'page_route' => '/modpacks',
                'modpacks_per_page' => 12,
                'install_timeout_minutes' => max(5, min(180, (int) ($timeout ?: 30))),
                'cache_ttl_seconds' => 3600,
            ],
        );

        // Cache busts so the manifest enricher and ModpackSettingsService
        // pick up the migrated values immediately.
        try {
            \Illuminate\Support\Facades\Cache::forget('modpack_settings.whitelisted_egg_ids');
        } catch (\Throwable) {
            // best-effort
        }
    }
};
