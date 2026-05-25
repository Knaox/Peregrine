<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Tests\Feature;

use Plugins\PeregrinePhpmyadmin\Services\PmaTokenStore;
use Plugins\PeregrinePhpmyadmin\Tests\TestCase;

class PmaTokenStoreTest extends TestCase
{
    public function test_put_then_pull_returns_payload_exactly_once(): void
    {
        $store = app(PmaTokenStore::class);
        $store->put('tok', ['username' => 'u', 'password' => 'p'], 30);

        $payload = $store->pull('tok');
        $this->assertSame('u', $payload['username'] ?? null);

        // One-shot: a replay resolves to null.
        $this->assertNull($store->pull('tok'));
    }

    public function test_token_expires_after_its_ttl(): void
    {
        $store = app(PmaTokenStore::class);
        $store->put('tok', ['x' => 1], 30);

        $this->travel(40)->seconds();

        $this->assertNull($store->pull('tok'));
    }

    public function test_empty_token_is_rejected(): void
    {
        $this->assertNull(app(PmaTokenStore::class)->pull(''));
    }
}
