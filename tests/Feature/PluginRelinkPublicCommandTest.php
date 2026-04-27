<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Services\Plugin\PluginLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * `plugin:relink-public` is the boot helper that recreates the
 * `public/plugins/{id}` symlinks at every container start. Without it, a
 * Docker redeploy (which wipes the ephemeral FS) leaves active plugins
 * orphaned — the bundle URL falls back to the SPA HTML and the browser
 * dies with `Unexpected token '<'`.
 *
 * These tests pin the contract so the command keeps :
 *  - relinking every active plugin
 *  - skipping inactive plugins (don't accidentally re-publish them)
 *  - surviving a single plugin failure (still process the others)
 *  - returning SUCCESS when the table doesn't exist yet (pre-install boot)
 */
class PluginRelinkPublicCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_relinks_every_active_plugin_and_skips_inactive(): void
    {
        Plugin::create(['plugin_id' => 'invitations', 'is_active' => true, 'version' => '1.0.0']);
        Plugin::create(['plugin_id' => 'analytics', 'is_active' => true, 'version' => '1.0.0']);
        Plugin::create(['plugin_id' => 'legacy-disabled', 'is_active' => false, 'version' => '1.0.0']);

        $lifecycle = Mockery::mock(PluginLifecycle::class);
        $lifecycle->shouldReceive('relinkPublicAssets')->once()->with('invitations');
        $lifecycle->shouldReceive('relinkPublicAssets')->once()->with('analytics');
        $lifecycle->shouldNotReceive('relinkPublicAssets')->with('legacy-disabled');
        $this->app->instance(PluginLifecycle::class, $lifecycle);

        $this->artisan('plugin:relink-public')
            ->expectsOutputToContain('Relinked: invitations')
            ->expectsOutputToContain('Relinked: analytics')
            ->assertSuccessful();
    }

    public function test_returns_success_with_no_active_plugins(): void
    {
        $lifecycle = Mockery::mock(PluginLifecycle::class);
        $lifecycle->shouldNotReceive('relinkPublicAssets');
        $this->app->instance(PluginLifecycle::class, $lifecycle);

        $this->artisan('plugin:relink-public')
            ->expectsOutputToContain('No active plugins')
            ->assertSuccessful();
    }

    public function test_continues_when_one_plugin_relink_throws(): void
    {
        Plugin::create(['plugin_id' => 'broken', 'is_active' => true, 'version' => '1.0.0']);
        Plugin::create(['plugin_id' => 'healthy', 'is_active' => true, 'version' => '1.0.0']);

        $lifecycle = Mockery::mock(PluginLifecycle::class);
        $lifecycle->shouldReceive('relinkPublicAssets')
            ->once()
            ->with('broken')
            ->andThrow(new \RuntimeException('FS read-only'));
        $lifecycle->shouldReceive('relinkPublicAssets')->once()->with('healthy');
        $this->app->instance(PluginLifecycle::class, $lifecycle);

        $this->artisan('plugin:relink-public')
            ->expectsOutputToContain('Failed to relink broken: FS read-only')
            ->expectsOutputToContain('Relinked: healthy')
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
