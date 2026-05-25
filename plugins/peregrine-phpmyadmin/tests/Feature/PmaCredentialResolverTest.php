<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Plugins\PeregrinePhpmyadmin\Services\PmaCredentialResolver;
use Plugins\PeregrinePhpmyadmin\Tests\TestCase;

class PmaCredentialResolverTest extends TestCase
{
    /** @param array<string, mixed> $attributes */
    private function fakeDatabases(array $attributes): void
    {
        Http::fake(function (ClientRequest $request) use ($attributes) {
            if (str_contains($request->url(), '/databases')) {
                return Http::response(['data' => [['attributes' => $attributes]]], 200);
            }

            return Http::response([], 404);
        });
    }

    public function test_resolves_password_from_pelican_relationships_shape(): void
    {
        $this->fakeDatabases([
            'id' => 'db-1',
            'name' => 's1_db',
            'username' => 'u1_abc',
            'host' => ['address' => 'db.host.test', 'port' => 3307],
            'relationships' => ['password' => ['attributes' => ['password' => 'SECRET']]],
        ]);

        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);

        $creds = app(PmaCredentialResolver::class)->resolve($server, 'db-1');

        $this->assertSame('SECRET', $creds['password']);
        $this->assertSame('db.host.test', $creds['host']);
        $this->assertSame(3307, $creds['port']);
        $this->assertSame('s1_db', $creds['database']);
    }

    public function test_resolves_password_from_inline_shape(): void
    {
        $this->fakeDatabases([
            'id' => 'db-1', 'name' => 's1_db', 'username' => 'u', 'password' => 'INLINE',
            'host' => ['address' => 'h', 'port' => 3306],
        ]);

        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);

        $this->assertSame('INLINE', app(PmaCredentialResolver::class)->resolve($server, 'db-1')['password']);
    }

    public function test_returns_null_when_database_is_absent(): void
    {
        $this->fakeDatabases(['id' => 'other', 'name' => 'x', 'username' => 'u', 'password' => 'p', 'host' => []]);

        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);

        $this->assertNull(app(PmaCredentialResolver::class)->resolve($server, 'db-1'));
    }
}
