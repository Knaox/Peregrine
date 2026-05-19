<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\MinecraftModsInstaller;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Plugins\MinecraftModsInstaller\Services\LoaderIconCache;
use Tests\TestCase;

/**
 * Pins the LoaderIconCache contract for the mods plugin :
 *  - hostile slugs never reach mcjars,
 *  - first hit fetches + caches,
 *  - subsequent hits never re-call mcjars,
 *  - misses are negative-cached so 404 storms don't escalate,
 *  - non-image payloads are refused.
 *
 * The PSR-4 autoload for plugin sources is normally registered by
 * `App\Services\Plugin\PluginBootstrap` at boot, but only for plugins
 * marked `is_active = true` in the DB. Tests boot without that row,
 * so we register the mapping manually in setUp.
 */
class LoaderIconCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $loader = require base_path('vendor/autoload.php');
        $loader->addPsr4(
            'Plugins\\MinecraftModsInstaller\\',
            base_path('plugins/minecraft-mods-installer/src/'),
        );

        Cache::flush();
    }

    public function test_rejects_invalid_slugs_without_calling_mcjars(): void
    {
        Http::fake();

        $cache = new LoaderIconCache;

        $this->assertNull($cache->get('../etc/passwd'));
        $this->assertNull($cache->get('PAPER'));
        $this->assertNull($cache->get(''));
        $this->assertNull($cache->get(str_repeat('a', 33)));
        $this->assertNull($cache->get('paper.png'));

        Http::assertNothingSent();
    }

    public function test_fetches_and_caches_a_fresh_icon(): void
    {
        $bytes = "\x89PNG\r\n\x1a\nfake-paper-bytes";
        Http::fake([
            'https://s3.mcjars.app/icons/paper.png' => Http::response($bytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $cache = new LoaderIconCache;
        $blob = $cache->get('paper');

        $this->assertNotNull($blob);
        $this->assertSame($bytes, $blob['bytes']);
        $this->assertSame('image/png', $blob['content_type']);
        Http::assertSentCount(1);
    }

    public function test_serves_from_cache_without_re_calling_mcjars(): void
    {
        $bytes = "\x89PNGcached-forge";
        Http::fake([
            'https://s3.mcjars.app/icons/forge.png' => Http::response($bytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $cache = new LoaderIconCache;
        $cache->get('forge');
        Http::assertSentCount(1);

        $second = $cache->get('forge');
        $this->assertNotNull($second);
        $this->assertSame($bytes, $second['bytes']);
        Http::assertSentCount(1);
    }

    public function test_negative_caches_a_404_and_does_not_re_fetch(): void
    {
        Http::fake([
            'https://s3.mcjars.app/icons/nope.png' => Http::response('', 404),
        ]);

        $cache = new LoaderIconCache;
        $this->assertNull($cache->get('nope'));
        Http::assertSentCount(1);

        $this->assertNull($cache->get('nope'));
        Http::assertSentCount(1);
    }

    public function test_rejects_non_image_content_type_responses(): void
    {
        Http::fake([
            'https://s3.mcjars.app/icons/spoof.png' => Http::response('<html>boom</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $cache = new LoaderIconCache;

        $this->assertNull($cache->get('spoof'));
    }
}
