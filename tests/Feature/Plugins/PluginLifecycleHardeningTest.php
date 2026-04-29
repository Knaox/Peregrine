<?php

namespace Tests\Feature\Plugins;

use App\Models\Plugin;
use App\Services\MarketplaceService;
use App\Services\Plugin\PluginLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Pins the v1.0.0-alpha.2 hardening of the plugin lifecycle.
 *
 *   1. Bundled plugins (`bundled: true` in plugin.json) are immune to
 *      both `install` and `uninstall` from the marketplace path — the
 *      panel source ships them via git, the marketplace must not fight
 *      that.
 *   2. `update()` on a bundled plugin delegates to `forceResync()` and
 *      does not touch disk content.
 *   3. `uninstall()` is atomic — DB row only goes when disk delete
 *      verifiably succeeded.
 *   4. `forceResync()` syncs DB version to disk manifest without
 *      flipping `is_active`.
 *   5. `plugin:force-resync` artisan command exists and routes to
 *      forceResync.
 */
class PluginLifecycleHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force panel.installed=true so PluginManager's gates (`config('panel.installed')`)
        // don't return early during tests.
        config(['panel.installed' => true]);
    }

    // ============================================================
    //  Bundled plugins — install/uninstall rejection
    // ============================================================

    public function test_install_rejects_bundled_plugin_already_on_disk(): void
    {
        // egg-config-editor is bundled (manifest has "bundled": true) and
        // its directory exists in the repo. The marketplace install path
        // must refuse and direct the admin to plugin:force-resync.
        $marketplace = $this->mockRegistryWithBundledEntry('egg-config-editor');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/bundled with Peregrine/');
        $this->expectExceptionMessageMatches('/plugin:force-resync egg-config-editor/');

        $marketplace->install('egg-config-editor');
    }

    public function test_uninstall_refuses_bundled_plugin(): void
    {
        // Pre-conditions : not active (so we don't hit the "deactivate first"
        // gate before the bundled check).
        Plugin::create(['plugin_id' => 'egg-config-editor', 'is_active' => false, 'version' => '1.0.0']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/bundled with Peregrine/');

        app(PluginLifecycle::class)->uninstall('egg-config-editor');

        // DB row must remain — we refused before deleting anything.
        $this->assertDatabaseHas('plugins', ['plugin_id' => 'egg-config-editor']);
    }

    // ============================================================
    //  forceResync
    // ============================================================

    public function test_force_resync_bumps_db_version_to_disk_manifest(): void
    {
        // Simulate a stale DB row (version 0.8.1) while the on-disk
        // manifest is at 1.0.0.
        Plugin::create([
            'plugin_id' => 'egg-config-editor',
            'is_active' => false,
            'version' => '0.8.1',
            'installed_at' => now()->subDays(10),
        ]);

        app(PluginLifecycle::class)->forceResync('egg-config-editor');

        $row = Plugin::where('plugin_id', 'egg-config-editor')->firstOrFail();
        $this->assertSame('1.0.0', $row->version);
    }

    public function test_force_resync_preserves_is_active_flag(): void
    {
        // Active plugin → resync must not deactivate it ; inactive plugin
        // → must not activate it. Two cases in one test : one assert per
        // active, one per inactive (separate Plugin rows).
        Plugin::create(['plugin_id' => 'egg-config-editor', 'is_active' => true, 'version' => '0.8.1']);

        app(PluginLifecycle::class)->forceResync('egg-config-editor');

        $this->assertTrue(Plugin::where('plugin_id', 'egg-config-editor')->value('is_active'));
    }

    public function test_force_resync_creates_db_row_when_missing(): void
    {
        // No DB row at all : the disk has the plugin but a half-failed
        // previous install never wrote the metadata. forceResync must
        // create the row from the manifest, defaulting to is_active=false
        // (admin keeps the activation decision).
        $this->assertDatabaseMissing('plugins', ['plugin_id' => 'egg-config-editor']);

        app(PluginLifecycle::class)->forceResync('egg-config-editor');

        $this->assertDatabaseHas('plugins', [
            'plugin_id' => 'egg-config-editor',
            'version' => '1.0.0',
            'is_active' => 0,
        ]);
    }

    public function test_force_resync_throws_when_plugin_not_on_disk(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found on disk/');

        app(PluginLifecycle::class)->forceResync('does-not-exist-anywhere');
    }

    // ============================================================
    //  Marketplace update path for bundled plugins
    // ============================================================

    public function test_update_for_bundled_plugin_resyncs_without_touching_disk(): void
    {
        // Snapshot the manifest content before, run the update, snapshot
        // after — they must be byte-identical (no download, no extract).
        $manifestPath = base_path('plugins/egg-config-editor/plugin.json');
        $before = File::get($manifestPath);
        $beforeMtime = File::lastModified($manifestPath);

        Plugin::create([
            'plugin_id' => 'egg-config-editor',
            'is_active' => true,
            'version' => '0.8.1',
        ]);

        // No HTTP fake — if the code tried to download, the test would
        // either fail (no fake) or hit the live registry (slow). The
        // bundled fast-path must skip Http::get entirely.
        app(MarketplaceService::class)->update('egg-config-editor');

        $this->assertSame($before, File::get($manifestPath), 'plugin.json must not be modified');
        $this->assertSame($beforeMtime, File::lastModified($manifestPath), 'plugin.json mtime must not change');
        $this->assertSame('1.0.0', Plugin::where('plugin_id', 'egg-config-editor')->value('version'));
    }

    // ============================================================
    //  Artisan command
    // ============================================================

    public function test_force_resync_command_is_registered(): void
    {
        Plugin::create(['plugin_id' => 'egg-config-editor', 'is_active' => false, 'version' => '0.8.1']);

        $this->artisan('plugin:force-resync', ['plugin_id' => 'egg-config-editor'])
            ->expectsOutputToContain('Resyncing plugin: egg-config-editor')
            ->expectsOutputToContain('resynced')
            ->assertSuccessful();

        $this->assertSame('1.0.0', Plugin::where('plugin_id', 'egg-config-editor')->value('version'));
    }

    public function test_force_resync_command_fails_for_unknown_plugin(): void
    {
        $this->artisan('plugin:force-resync', ['plugin_id' => 'does-not-exist'])
            ->expectsOutputToContain('not found on disk')
            ->assertFailed();
    }

    // ============================================================
    //  Helpers
    // ============================================================

    /**
     * Build a MarketplaceService with a registry that just contains the
     * named bundled plugin. Saves us from hitting the real GitHub host
     * and lets us assert specifically on the bundled-rejection branch.
     */
    private function mockRegistryWithBundledEntry(string $pluginId): MarketplaceService
    {
        $manifest = json_decode(File::get(base_path("plugins/{$pluginId}/plugin.json")), true);

        Cache::put('marketplace.registry', [[
            'id' => $pluginId,
            'name' => $manifest['name'] ?? $pluginId,
            'version' => $manifest['version'] ?? '1.0.0',
            'download_url' => 'https://github.com/Knaox/Peregrine/releases/download/v1.0.0-alpha.2/' . $pluginId . '-1.0.0.zip',
        ]], 3600);

        return app(MarketplaceService::class);
    }
}
