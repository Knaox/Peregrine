<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Tests\Feature;

use Plugins\PeregrinePhpmyadmin\Services\PmaTokenStore;
use Plugins\PeregrinePhpmyadmin\Tests\TestCase;

class PmaRedeemTest extends TestCase
{
    private const URL = '/api/plugins/peregrine-phpmyadmin/redeem';

    private function seedToken(string $token = 'redeem-tok'): void
    {
        app(PmaTokenStore::class)->put($token, [
            'username' => 'u', 'password' => 'p', 'host' => 'h', 'port' => 3306, 'database' => 'd', 'user_id' => 1,
        ], 60);
    }

    public function test_valid_secret_and_token_returns_credentials(): void
    {
        $this->configurePlugin(['shared_secret' => 'SECRET']);
        $this->seedToken();

        $this->postJson(self::URL, ['token' => 'redeem-tok'], ['X-Plugin-Secret' => 'SECRET'])
            ->assertOk()
            ->assertJson(['username' => 'u', 'password' => 'p', 'host' => 'h', 'database' => 'd']);
    }

    public function test_a_replayed_token_is_rejected(): void
    {
        $this->configurePlugin(['shared_secret' => 'SECRET']);
        $this->seedToken();

        $this->postJson(self::URL, ['token' => 'redeem-tok'], ['X-Plugin-Secret' => 'SECRET'])->assertOk();
        $this->postJson(self::URL, ['token' => 'redeem-tok'], ['X-Plugin-Secret' => 'SECRET'])->assertStatus(404);
    }

    public function test_wrong_shared_secret_is_forbidden(): void
    {
        $this->configurePlugin(['shared_secret' => 'SECRET']);
        $this->seedToken();

        $this->postJson(self::URL, ['token' => 'redeem-tok'], ['X-Plugin-Secret' => 'WRONG'])->assertStatus(403);
    }

    public function test_missing_secret_fails_closed_even_when_unconfigured(): void
    {
        // No shared secret configured at all → every request denied.
        $this->seedToken();

        $this->postJson(self::URL, ['token' => 'redeem-tok'])->assertStatus(403);
    }

    public function test_missing_token_with_valid_secret_is_not_found(): void
    {
        $this->configurePlugin(['shared_secret' => 'SECRET']);

        $this->postJson(self::URL, ['token' => 'nope'], ['X-Plugin-Secret' => 'SECRET'])->assertStatus(404);
    }

    public function test_ip_allowlist_blocks_a_disallowed_address(): void
    {
        $this->configurePlugin(['shared_secret' => 'SECRET', 'ip_allowlist' => "10.0.0.0/8\n203.0.113.5"]);
        $this->seedToken();

        // The test request originates from 127.0.0.1, outside the allowlist.
        $this->postJson(self::URL, ['token' => 'redeem-tok'], ['X-Plugin-Secret' => 'SECRET'])->assertStatus(403);
    }
}
