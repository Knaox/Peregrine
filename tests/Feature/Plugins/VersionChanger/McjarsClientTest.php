<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\VersionChanger;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Plugins\VersionChanger\Models\VersionChangerConfig;
use Plugins\VersionChanger\Services\McjarsClient;
use Plugins\VersionChanger\Services\McjarsResponseNormaliser;
use Plugins\VersionChanger\Services\VersionChangerSettingsService;
use Tests\TestCase;

/**
 * Pins the MCJars HTTP + cache contract :
 *  - first call hits the network and caches the result
 *  - subsequent calls within TTL use the cache
 *  - misses on hash lookup are negative-cached
 *  - the API key, when set, is sent as Bearer Authorization
 */
class McjarsClientTest extends TestCase
{
    use ActivatesVersionChangerPlugin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->bootVersionChangerPlugin();

        parent::setUp();

        Cache::flush();
    }

    public function test_list_types_caches_after_first_call(): void
    {
        Http::fake([
            'mcjars.app/api/v2/types' => Http::response([
                'success' => true,
                'types' => [
                    'recommended' => [
                        'PAPER' => [
                            'name' => 'Paper', 'icon' => 'x', 'color' => '#444',
                            'homepage' => 'https://papermc.io', 'deprecated' => false,
                            'experimental' => false, 'description' => null,
                            'builds' => 5000, 'versions' => ['minecraft' => 60, 'project' => 0],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = $this->client();

        $first = $client->listTypes();
        $this->assertSame('paper', $first['recommended'][0]['slug']);
        $this->assertSame('Paper', $first['recommended'][0]['name']);

        $second = $client->listTypes();
        $this->assertSame($first, $second);

        Http::assertSentCount(1);
    }

    public function test_lookup_by_hash_negative_caches_a_miss(): void
    {
        Http::fake([
            'mcjars.app/api/v2/build' => Http::response([
                'success' => false, 'errors' => ['build not found'],
            ], 404),
        ]);

        $client = $this->client();
        $hash = str_repeat('a', 128);

        $this->assertNull($client->lookupByHash($hash));
        $this->assertNull($client->lookupByHash($hash));
        Http::assertSentCount(1);
    }

    public function test_lookup_by_hash_returns_normalised_build_on_hit(): void
    {
        $hash = str_repeat('a', 128);
        Http::fake([
            'mcjars.app/api/v2/build' => Http::response([
                'success' => true,
                'build' => [
                    'type' => 'PAPER',
                    'versionId' => '1.21',
                    'buildNumber' => 130,
                ],
            ], 200),
        ]);

        $client = $this->client();
        $entry = $client->lookupByHash($hash);

        $this->assertNotNull($entry);
        $this->assertSame('PAPER', $entry['type']);
        $this->assertSame('1.21', $entry['version']);
        $this->assertSame(130, $entry['build_number']);
    }

    public function test_invalid_hash_is_refused_before_network(): void
    {
        Http::fake();

        $client = $this->client();

        $this->assertNull($client->lookupByHash('not-a-hex-hash'));
        $this->assertNull($client->lookupByHash(''));
        $this->assertNull($client->lookupByHash(str_repeat('z', 128)));
        Http::assertNothingSent();
    }

    public function test_api_key_is_sent_as_bearer_when_configured(): void
    {
        VersionChangerConfig::singleton()->update(['mcjars_api_key' => 'sk-test-key']);

        Http::fake([
            'mcjars.app/api/v2/types' => Http::response([
                'success' => true, 'types' => [],
            ], 200),
        ]);

        $this->client()->listTypes();

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer sk-test-key');
        });
    }

    private function client(): McjarsClient
    {
        return new McjarsClient(new VersionChangerSettingsService, new McjarsResponseNormaliser);
    }
}
