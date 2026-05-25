<?php

declare(strict_types=1);

namespace Tests\Unit\Network;

use App\Services\Network\CloudflareDnsResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareDnsResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The resolver caches successful lookups ; flush so each test starts
        // from a clean slate and the send-count assertions stay accurate.
        Cache::flush();
    }

    public function test_returns_ip_literal_without_querying(): void
    {
        Http::fake();

        $this->assertSame('203.0.113.5', (new CloudflareDnsResolver)->resolve('203.0.113.5'));
        $this->assertSame('2606:4700::1111', (new CloudflareDnsResolver)->resolve('2606:4700::1111'));

        Http::assertNothingSent();
    }

    public function test_resolves_first_a_record(): void
    {
        Http::fake([
            'cloudflare-dns.com/*' => Http::response([
                'Status' => 0,
                'Answer' => [
                    ['name' => 'node.example.com', 'type' => 5, 'data' => 'cname.example.com'],
                    ['name' => 'node.example.com', 'type' => 1, 'TTL' => 300, 'data' => '198.51.100.20'],
                ],
            ], 200),
        ]);

        $this->assertSame('198.51.100.20', (new CloudflareDnsResolver)->resolve('node.example.com'));
    }

    public function test_returns_null_when_no_a_record(): void
    {
        Http::fake([
            'cloudflare-dns.com/*' => Http::response([
                'Status' => 0,
                'Answer' => [
                    ['name' => 'node.example.com', 'type' => 5, 'data' => 'cname.example.com'],
                ],
            ], 200),
        ]);

        $this->assertNull((new CloudflareDnsResolver)->resolve('node.example.com'));
    }

    public function test_returns_null_on_http_error(): void
    {
        Http::fake(['cloudflare-dns.com/*' => Http::response('', 500)]);

        $this->assertNull((new CloudflareDnsResolver)->resolve('node.example.com'));
    }

    public function test_returns_null_for_empty_input(): void
    {
        Http::fake();

        $this->assertNull((new CloudflareDnsResolver)->resolve('   '));

        Http::assertNothingSent();
    }

    public function test_caches_successful_resolution(): void
    {
        Http::fake([
            'cloudflare-dns.com/*' => Http::response([
                'Status' => 0,
                'Answer' => [['type' => 1, 'data' => '198.51.100.20']],
            ], 200),
        ]);

        $resolver = new CloudflareDnsResolver;
        $this->assertSame('198.51.100.20', $resolver->resolve('node.example.com'));
        $this->assertSame('198.51.100.20', $resolver->resolve('node.example.com'));

        Http::assertSentCount(1);
    }
}
