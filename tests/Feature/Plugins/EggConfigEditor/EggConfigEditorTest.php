<?php

namespace Tests\Feature\Plugins\EggConfigEditor;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Plugin;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanFileService;
use App\Services\Plugin\ManifestEnricherRegistry;
use App\Services\Plugin\PluginBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Plugins\EggConfigEditor\EggConfigEditorServiceProvider;
use Plugins\EggConfigEditor\Models\EggConfigFile;
use Tests\TestCase;

/**
 * Pins the v1.0.0 contract for the egg-config-editor plugin :
 *
 *   1. Authorization — when the `invitations` plugin is active, dedicated
 *      eggconfig.read / eggconfig.write permissions gate access; without
 *      it, falls back to file.read / file.update.
 *   2. Boolean override — toggleNonBooleanKey flips a key in the per-egg
 *      `non_boolean_keys` JSON column; readConfig respects the override
 *      and reports it back to the frontend via `boolean_overridden`.
 *   3. Manifest enricher — `requires_egg_ids` is dynamically populated on
 *      the home section so /api/plugins reflects the eggs that actually
 *      have configs declared in DB.
 */
class EggConfigEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Plugin tables and provider must boot BEFORE RefreshDatabase runs
        // its migrate:fresh, otherwise the plugin migrations aren't picked
        // up. afterApplicationCreated runs during parent::setUp(), before
        // setUpTraits() (which is what triggers the migration).
        $this->afterApplicationCreated(function (): void {
            config(['panel.installed' => true]);

            $loader = require base_path('vendor/autoload.php');
            $loader->addPsr4('Plugins\\EggConfigEditor\\', base_path('plugins/egg-config-editor/src/'));
            $loader->addPsr4('Plugins\\Invitations\\', base_path('plugins/invitations/src/'));

            $this->app->register(EggConfigEditorServiceProvider::class);
            // Register the Invitations provider too so its routes are
            // wired and tests can hit /api/plugins/invitations/... — the
            // PermissionRegistry singleton is shared so registrations
            // from EggConfigEditor land in it regardless of order.
            $this->app->register(\Plugins\Invitations\InvitationsServiceProvider::class);
        });

        parent::setUp();

        // RefreshDatabase only runs migrations registered before its trait
        // setup fires; plugin migrations registered through the provider's
        // boot() are picked up on the SECOND test in the same process but
        // not the first. Run them explicitly so every test starts on the
        // same footing.
        Artisan::call('migrate', [
            '--path' => 'plugins/egg-config-editor/src/Migrations',
            '--realpath' => false,
        ]);

        // Ensure each test starts with a clean enricher registry + cache.
        // Singletons persist across tests in the same PHP process.
        ManifestEnricherRegistry::getInstance()->reset();
        Cache::forget(EggConfigEditorServiceProvider::APPLICABLE_EGGS_CACHE_KEY);

        // Re-register the enricher (provider boot ran before reset).
        $this->app->register(EggConfigEditorServiceProvider::class, force: true);

        // Seed a default Nest + Egg(id=1) so our makeServer() helper has
        // a valid FK target. Tests that need a different egg can override.
        $this->seedDefaultEgg();
    }

    private function seedDefaultEgg(): void
    {
        if (Egg::query()->where('id', 1)->exists()) {
            return;
        }
        $nest = Nest::create([
            'pelican_nest_id' => 1,
            'name' => 'Test',
            'description' => 'Test nest',
        ]);
        Egg::create([
            'pelican_egg_id' => 1,
            'nest_id' => $nest->id,
            'name' => 'Test Egg',
            'description' => 'Test',
            'docker_image' => 'test:latest',
            'startup' => 'echo',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================================
    //  Authorization
    // ============================================================

    public function test_owner_can_read_and_write_without_any_plugin_permission(): void
    {
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);

        $this->mockPelicanFileService();

        $this->actingAs($owner)
            ->getJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($owner)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}", [
                'values' => ['max-players' => 50],
            ])
            ->assertOk();
    }

    public function test_admin_can_read_and_write_any_server(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);

        $this->mockPelicanFileService();

        $this->actingAs($admin)
            ->getJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs")
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}", [
                'values' => ['max-players' => 99],
            ])
            ->assertOk();
    }

    public function test_subuser_with_eggconfig_read_can_read_but_not_write_when_invitations_active(): void
    {
        $this->activateInvitations();

        $owner = User::factory()->create();
        $sub = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);
        $this->grantSubuser($sub, $server, ['eggconfig.read']);

        $this->mockPelicanFileService();

        $this->actingAs($sub)
            ->getJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}")
            ->assertOk();

        $this->actingAs($sub)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}", [
                'values' => ['max-players' => 50],
            ])
            ->assertForbidden();
    }

    public function test_subuser_with_eggconfig_write_can_read_and_write_when_invitations_active(): void
    {
        $this->activateInvitations();

        $owner = User::factory()->create();
        $sub = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);
        $this->grantSubuser($sub, $server, ['eggconfig.write']);

        $this->mockPelicanFileService();

        $this->actingAs($sub)
            ->getJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}")
            ->assertOk();

        $this->actingAs($sub)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}", [
                'values' => ['max-players' => 50],
            ])
            ->assertOk();
    }

    public function test_subuser_without_any_eggconfig_perm_is_denied_when_invitations_active(): void
    {
        $this->activateInvitations();

        $owner = User::factory()->create();
        $sub = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);
        // Grant only the legacy file.read — should NOT be honored when
        // invitations is active and the dedicated eggconfig keys are missing.
        $this->grantSubuser($sub, $server, ['file.read', 'file.update']);

        $this->mockPelicanFileService();

        $this->actingAs($sub)
            ->getJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}")
            ->assertForbidden();

        $this->actingAs($sub)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}", [
                'values' => ['max-players' => 50],
            ])
            ->assertForbidden();
    }

    public function test_subuser_falls_back_to_file_perms_when_invitations_not_active(): void
    {
        // Invitations is NOT activated — controller falls back to Pelican
        // file-manager parity. Subuser with file.update can read + write.
        $owner = User::factory()->create();
        $sub = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);
        $this->grantSubuser($sub, $server, ['file.read', 'file.update']);

        $this->mockPelicanFileService();

        $this->actingAs($sub)
            ->getJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}")
            ->assertOk();

        $this->actingAs($sub)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}", [
                'values' => ['max-players' => 50],
            ])
            ->assertOk();
    }

    // ============================================================
    //  Boolean override
    // ============================================================

    public function test_toggle_adds_then_removes_key_in_non_boolean_keys(): void
    {
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);

        $this->mockPelicanFileService();

        // First toggle → key added (override active).
        $this->actingAs($owner)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}/non-boolean-keys/toggle", [
                'key' => 'pvp',
            ])
            ->assertOk()
            ->assertJsonPath('data.overridden', true)
            ->assertJsonPath('data.non_boolean_keys', ['pvp']);

        // Second toggle → key removed (override gone).
        $this->actingAs($owner)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}/non-boolean-keys/toggle", [
                'key' => 'pvp',
            ])
            ->assertOk()
            ->assertJsonPath('data.overridden', false)
            ->assertJsonPath('data.non_boolean_keys', []);

        $this->assertNull($config->fresh()->non_boolean_keys);
    }

    public function test_read_config_returns_text_type_for_overridden_boolean_keys(): void
    {
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id, [
            'file_type' => 'properties',
            'non_boolean_keys' => ['pvp'],
        ]);

        // Mock Pelican file content : two boolean-looking keys.
        $this->mockPelicanFileService(content: "pvp=true\nhardcore=false\n");

        $response = $this->actingAs($owner)
            ->getJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}")
            ->assertOk();

        $params = collect($response->json('data.parameters'));
        $pvp = $params->firstWhere('config_key', 'pvp');
        $hardcore = $params->firstWhere('config_key', 'hardcore');

        $this->assertSame('text', $pvp['inferred_type']);
        $this->assertTrue($pvp['boolean_overridden']);
        $this->assertSame('boolean', $hardcore['inferred_type']);
        $this->assertFalse($hardcore['boolean_overridden']);
    }

    public function test_toggle_requires_write_permission_for_subuser(): void
    {
        $this->activateInvitations();

        $owner = User::factory()->create();
        $sub = User::factory()->create();
        $server = $this->makeServer($owner);
        $config = $this->makeConfigFile($server->egg_id);
        $this->grantSubuser($sub, $server, ['eggconfig.read']);

        $this->actingAs($sub)
            ->postJson("/api/plugins/egg-config-editor/servers/{$server->id}/configs/{$config->id}/non-boolean-keys/toggle", [
                'key' => 'pvp',
            ])
            ->assertForbidden();
    }

    // ============================================================
    //  Manifest enricher
    // ============================================================

    public function test_enricher_injects_requires_egg_ids_from_db(): void
    {
        // Create a plugin row so PluginBootstrap surfaces it.
        Plugin::create(['plugin_id' => 'egg-config-editor', 'is_active' => true, 'version' => '1.0.0']);

        // Two distinct egg ids covered by config files.
        EggConfigFile::create([
            'egg_ids' => [11, 22],
            'file_paths' => ['/server.properties'],
            'file_type' => 'properties',
            'enabled' => true,
        ]);
        EggConfigFile::create([
            'egg_ids' => [22, 33],
            'file_paths' => ['/other.ini'],
            'file_type' => 'ini',
            'enabled' => true,
        ]);

        $manifests = app(PluginBootstrap::class)->getActiveManifests();
        $manifest = collect($manifests)->firstWhere('id', 'egg-config-editor');

        $this->assertNotNull($manifest, 'Plugin manifest missing from getActiveManifests');
        $section = $manifest['server_home_sections'][0] ?? null;
        $this->assertNotNull($section);
        $this->assertSame('egg-config-editor', $section['id']);
        $this->assertEqualsCanonicalizing([11, 22, 33], $section['requires_egg_ids']);
    }

    public function test_enricher_returns_empty_egg_list_when_no_config_files(): void
    {
        Plugin::create(['plugin_id' => 'egg-config-editor', 'is_active' => true, 'version' => '1.0.0']);

        // No EggConfigFile rows.
        $manifests = app(PluginBootstrap::class)->getActiveManifests();
        $manifest = collect($manifests)->firstWhere('id', 'egg-config-editor');

        $this->assertNotNull($manifest);
        $section = $manifest['server_home_sections'][0] ?? null;
        $this->assertSame([], $section['requires_egg_ids']);
    }

    public function test_observer_busts_cache_when_config_file_changes(): void
    {
        Plugin::create(['plugin_id' => 'egg-config-editor', 'is_active' => true, 'version' => '1.0.0']);

        // Prime the cache : empty list.
        $manifests = app(PluginBootstrap::class)->getActiveManifests();
        $section = collect($manifests)->firstWhere('id', 'egg-config-editor')['server_home_sections'][0];
        $this->assertSame([], $section['requires_egg_ids']);

        // Add a config file → observer must bust the cache so the next
        // call sees the new egg_id, not the stale empty list.
        EggConfigFile::create([
            'egg_ids' => [42],
            'file_paths' => ['/server.properties'],
            'file_type' => 'properties',
            'enabled' => true,
        ]);

        $manifests = app(PluginBootstrap::class)->getActiveManifests();
        $section = collect($manifests)->firstWhere('id', 'egg-config-editor')['server_home_sections'][0];
        $this->assertEqualsCanonicalizing([42], $section['requires_egg_ids']);
    }

    // ============================================================
    //  Per-server permission filtering (eggconfig.* visibility)
    // ============================================================

    public function test_eggconfig_permissions_hidden_when_no_config_file_covers_egg(): void
    {
        $this->activateInvitations();

        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        // No EggConfigFile rows — Config Editor has nothing to expose for
        // this server's egg, so the eggconfig group must not appear.

        $response = $this->actingAs($owner)
            ->getJson("/api/plugins/invitations/servers/{$server->identifier}/permissions");

        $response->assertOk();
        $groupKeys = collect($response->json('data'))->pluck('group')->all();
        $this->assertNotContains('eggconfig', $groupKeys);
        // Native Pelican groups are always visible regardless.
        $this->assertContains('control', $groupKeys);
        $this->assertContains('file', $groupKeys);
    }

    public function test_eggconfig_permissions_visible_when_config_file_covers_egg(): void
    {
        $this->activateInvitations();

        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $this->makeConfigFile($server->egg_id);

        $response = $this->actingAs($owner)
            ->getJson("/api/plugins/invitations/servers/{$server->identifier}/permissions");

        $response->assertOk();
        $groups = collect($response->json('data'));
        $eggconfig = $groups->firstWhere('group', 'eggconfig');
        $this->assertNotNull($eggconfig, 'eggconfig group missing from picker for a covered egg');
        $permKeys = collect($eggconfig['permissions'])->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['eggconfig.read', 'eggconfig.write'], $permKeys);
    }

    public function test_eggconfig_permissions_hidden_when_config_file_is_disabled(): void
    {
        $this->activateInvitations();

        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        // Row exists but disabled — same outcome as no row at all.
        $this->makeConfigFile($server->egg_id, ['enabled' => false]);

        $response = $this->actingAs($owner)
            ->getJson("/api/plugins/invitations/servers/{$server->identifier}/permissions");

        $response->assertOk();
        $groupKeys = collect($response->json('data'))->pluck('group')->all();
        $this->assertNotContains('eggconfig', $groupKeys);
    }

    public function test_eggconfig_permissions_hidden_when_config_file_targets_other_egg(): void
    {
        $this->activateInvitations();

        // Server has egg_id=1, config file covers a DIFFERENT egg.
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $this->makeConfigFile(99, ['egg_ids' => [99]]);

        $response = $this->actingAs($owner)
            ->getJson("/api/plugins/invitations/servers/{$server->identifier}/permissions");

        $response->assertOk();
        $groupKeys = collect($response->json('data'))->pluck('group')->all();
        $this->assertNotContains('eggconfig', $groupKeys);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    private function activateInvitations(): void
    {
        Plugin::create(['plugin_id' => 'invitations', 'is_active' => true, 'version' => '1.0.0']);
    }

    private function makeServer(User $owner, array $overrides = []): Server
    {
        $server = Server::create(array_merge([
            'user_id' => $owner->id,
            'pelican_server_id' => random_int(1, 1_000_000),
            'identifier' => bin2hex(random_bytes(4)),
            'name' => 'Test server',
            'status' => 'active',
            'egg_id' => 1,
        ], $overrides));

        // The accessibleBy scope checks the server_user pivot, so the
        // owner must be registered there to see their own server (mirrors
        // ServerSync::syncFromPelican() and InvitationService::accept()).
        $server->accessUsers()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'permissions' => null],
        ]);

        return $server;
    }

    private function makeConfigFile(int $eggId, array $overrides = []): EggConfigFile
    {
        return EggConfigFile::create(array_merge([
            'egg_ids' => [$eggId],
            'file_paths' => ['/server.properties'],
            'file_type' => 'properties',
            'enabled' => true,
        ], $overrides));
    }

    /**
     * Insert the user as a subuser of the server with the given perms.
     * Mirrors what InvitationService does at accept-time, without going
     * through the invitations plugin (these tests don't need the full
     * invitation flow — just the pivot row for hasServerPermission).
     *
     * @param  array<int, string>  $permissions
     */
    private function grantSubuser(User $user, Server $server, array $permissions): void
    {
        DB::table('server_user')->insert([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'role' => 'subuser',
            'permissions' => json_encode($permissions),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Bind a fake PelicanFileService to the container so the controller
     * never tries to hit the network. Reads return the canned content,
     * writes are no-ops.
     */
    private function mockPelicanFileService(string $content = "max-players=20\n"): void
    {
        $mock = Mockery::mock(PelicanFileService::class);
        $mock->shouldReceive('getFileContent')->andReturn($content);
        $mock->shouldReceive('writeFile')->andReturnNull();
        $this->app->instance(PelicanFileService::class, $mock);
    }
}
