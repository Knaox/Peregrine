<?php

declare(strict_types=1);

namespace Tests\Feature\Server;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the database password plumbing fixed in Partie 0: the list endpoint
 * never leaks the password, while credentials / create / rotate flatten
 * Pelican's nested `relationships.password.attributes.password` into a plain
 * top-level `password` the SPA can display. All Pelican traffic is faked.
 */
class ServerDatabaseCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const DB_ID = 'db-1';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.admin_api_key', 'test-admin-key');
        config()->set('panel.pelican.client_api_key', 'test-client-key');

        Cache::flush();
    }

    private function fakePelican(): void
    {
        Http::fake(function (ClientRequest $request) {
            $url = $request->url();
            $method = $request->method();

            // Rotate → new password.
            if ($method === 'POST' && str_contains($url, '/rotate-password')) {
                return Http::response(['attributes' => $this->dbAttributes('ROTATED-PASS')], 200);
            }

            // Create → password returned by Pelican on creation.
            if ($method === 'POST' && str_contains($url, '/databases')) {
                return Http::response(['attributes' => $this->dbAttributes('CREATED-PASS')], 201);
            }

            // List with ?include=password (credentials path) → carries password.
            if (str_contains($url, 'include=password')) {
                return Http::response(['data' => [['attributes' => $this->dbAttributes('SECRET-PASS')]]], 200);
            }

            // Plain list (index) → no password whatsoever.
            return Http::response(['data' => [['attributes' => $this->dbAttributes(null)]]], 200);
        });
    }

    /** @return array<string, mixed> */
    private function dbAttributes(?string $password): array
    {
        $attrs = [
            'id' => self::DB_ID,
            'name' => 's1_mydb',
            'username' => 'u1_abc',
            'connections_from' => '%',
            'max_connections' => 0,
            'host' => ['address' => 'db.host.test', 'port' => 3306],
        ];

        if ($password !== null) {
            $attrs['relationships'] = ['password' => ['attributes' => ['password' => $password]]];
        }

        return $attrs;
    }

    private function makeServer(int $ownerId): Server
    {
        $nest = Nest::create(['pelican_nest_id' => mt_rand(1, 999999), 'name' => 'N']);
        $egg = Egg::create([
            'pelican_egg_id' => 777,
            'nest_id' => $nest->id,
            'name' => 'Paper',
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

    private function asOwner(Server $server, User $user): void
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

    public function test_index_never_exposes_the_password(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/databases");

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data.0'));
    }

    public function test_credentials_flattens_the_nested_password(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)
            ->getJson("/api/servers/{$server->id}/databases/".self::DB_ID.'/credentials');

        $response->assertOk();
        $response->assertJsonPath('data.password', 'SECRET-PASS');
        $this->assertArrayNotHasKey('relationships', $response->json('data'));

        // The credentials path must ask Pelican for the password explicitly.
        Http::assertSent(fn (ClientRequest $r): bool => str_contains($r->url(), 'include=password'));
    }

    public function test_create_returns_the_plaintext_password(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)
            ->postJson("/api/servers/{$server->id}/databases", ['database' => 's1_mydb', 'remote' => '%']);

        $response->assertCreated();
        $response->assertJsonPath('data.password', 'CREATED-PASS');
    }

    public function test_rotate_returns_the_new_password(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)
            ->postJson("/api/servers/{$server->id}/databases/".self::DB_ID.'/rotate-password');

        $response->assertOk();
        $response->assertJsonPath('data.password', 'ROTATED-PASS');
    }

    public function test_a_stranger_cannot_read_credentials(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $this->actingAs($stranger)
            ->getJson("/api/servers/{$server->id}/databases/".self::DB_ID.'/credentials')
            ->assertStatus(403);
    }
}
