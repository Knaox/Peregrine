<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\MinecraftPluginsInstaller;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Plugins\MinecraftPluginsInstaller\Services\LoaderIconCache;
use Tests\TestCase;

/**
 * Twin of the mods-installer test — pins the same contract for the
 * plugins-installer's LoaderIconCache. Kept independent so a drift in
 * either implementation can never silently regress.
 */
class LoaderIconCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $loader = require base_path('vendor/autoload.php');
        $loader->addPsr4(
            'Plugins\\MinecraftPluginsInstaller\\',
            base_path('plugins/minecraft-plugins-installer/src/'),
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
        $bytes = "\x89PNGcached-purpur";
        Http::fake([
            'https://s3.mcjars.app/icons/purpur.png' => Http::response($bytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $cache = new LoaderIconCache;
        $cache->get('purpur');
        Http::assertSentCount(1);

        $second = $cache->get('purpur');
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
