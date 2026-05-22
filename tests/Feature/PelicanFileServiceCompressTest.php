<?php

namespace Tests\Feature;

use App\Services\Pelican\PelicanFileService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression cover for the compress 500: the service used to hardcode
 * `root => '/'`, so compressing anything inside a sub-directory made Pelican
 * look for the files at the filesystem root, fail, and surface as a 500.
 * The directory the selection lives in must reach Pelican untouched.
 */
class PelicanFileServiceCompressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.client_api_key', 'test-client-key');
    }

    public function test_compress_forwards_the_real_root_not_slash(): void
    {
        Http::fake([
            'pelican.test/api/client/servers/*/files/compress' => Http::response(['object' => 'file_object'], 200),
        ]);

        app(PelicanFileService::class)->compressFiles('srv-uuid', '/plugins/world', ['level.dat', 'session.lock']);

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_contains($req->url(), '/api/client/servers/srv-uuid/files/compress')
            && $req['root'] === '/plugins/world'
            && $req['files'] === ['level.dat', 'session.lock']);
    }

    public function test_compress_normalizes_empty_root_to_slash(): void
    {
        Http::fake([
            'pelican.test/api/client/servers/*/files/compress' => Http::response(['object' => 'file_object'], 200),
        ]);

        app(PelicanFileService::class)->compressFiles('srv-uuid', '', ['backup.zip']);

        Http::assertSent(fn ($req) => $req['root'] === '/');
    }
}
