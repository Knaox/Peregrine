<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Tests;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Plugin;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase as BaseTestCase;

/**
 * Base case for the plugin's feature tests: boots the plugin, points Pelican
 * at a fake host, and offers helpers to configure the plugin settings and to
 * build an owned server (there is no Server factory in the app).
 */
abstract class TestCase extends BaseTestCase
{
    use ActivatesPmaPlugin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->bootPmaPlugin();

        parent::setUp();

        config([
            'panel.installed' => true,
            'panel.pelican.url' => 'https://pelican.test',
            'panel.pelican.admin_api_key' => 'admin-key',
            'panel.pelican.client_api_key' => 'client-key',
        ]);

        Cache::flush();
    }

    /**
     * Persist plugin settings (creating the plugins row on demand). The shared
     * secret is encrypted at rest, exactly as the settings page stores it.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function configurePlugin(array $settings): void
    {
        if (array_key_exists('shared_secret', $settings)) {
            $settings['shared_secret'] = Crypt::encryptString((string) $settings['shared_secret']);
        }

        $plugin = Plugin::firstOrCreate(
            ['plugin_id' => 'peregrine-phpmyadmin'],
            ['is_active' => true, 'version' => '1.0.0', 'settings' => []],
        );

        $plugin->update(['settings' => array_merge($plugin->settings ?? [], $settings)]);
    }

    protected function makeServer(int $ownerId): Server
    {
        $nest = Nest::create(['pelican_nest_id' => random_int(1, 999999), 'name' => 'N']);
        $egg = Egg::create([
            'pelican_egg_id' => random_int(1, 999999),
            'nest_id' => $nest->id,
            'name' => 'E',
            'docker_image' => 'img',
            'startup' => 'java',
        ]);

        return Server::create([
            'user_id' => $ownerId,
            'pelican_server_id' => 4242,
            'identifier' => 'srv-uuid',
            'name' => 'mc',
            'status' => 'running',
            'egg_id' => $egg->id,
        ]);
    }

    protected function asOwner(Server $server, User $user): void
    {
        DB::table('server_user')->insert([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'permissions' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
