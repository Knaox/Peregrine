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
 * Covers the Minecraft console quick-fix endpoints: list Docker images,
 * switch the Java image, and accept the EULA. All Pelican traffic is faked.
 */
class ServerConsoleFixTest extends TestCase
{
    use RefreshDatabase;

    private const JAVA_17 = 'ghcr.io/pelican-eggs/yolks:java_17';

    private const JAVA_21 = 'ghcr.io/pelican-eggs/yolks:java_21';

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

            if (str_contains($url, '/api/application/eggs/')) {
                return Http::response(['attributes' => ['docker_images' => [
                    'Java 17' => self::JAVA_17,
                    'Java 21' => self::JAVA_21,
                ]]], 200);
            }

            if (str_contains($url, '/startup') && $request->method() === 'PATCH') {
                return Http::response(['attributes' => []], 200);
            }

            if (str_contains($url, '/api/application/servers/')) {
                return Http::response(['attributes' => [
                    'egg' => 555,
                    'container' => [
                        'image' => self::JAVA_17,
                        'startup_command' => 'java -jar {{SERVER_JARFILE}}',
                        'environment' => ['SERVER_JARFILE' => 'server.jar'],
                    ],
                ]], 200);
            }

            // Client API: report the server as already offline so the
            // kill → wait-offline → start sequence resolves immediately.
            if (str_contains($url, '/resources')) {
                return Http::response(['attributes' => ['current_state' => 'offline']], 200);
            }

            // Client API (files/write, power) — always 204.
            return Http::response([], 204);
        });
    }

    private function makeServer(int $ownerId): Server
    {
        $nest = Nest::create(['pelican_nest_id' => mt_rand(1, 999999), 'name' => 'N']);
        $egg = Egg::create([
            'pelican_egg_id' => 555,
            'nest_id' => $nest->id,
            'name' => 'Paper',
            'docker_image' => self::JAVA_17,
            'startup' => 'java -jar {{SERVER_JARFILE}}',
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

    public function test_docker_images_lists_egg_images_and_flags_current_and_recommended(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/docker-images?java=21");

        $response->assertOk();
        $response->assertJsonPath('data.current', self::JAVA_17);

        $images = collect($response->json('data.images'));
        $this->assertSame(2, $images->count());
        $this->assertTrue($images->firstWhere('image', self::JAVA_17)['is_current']);
        $this->assertTrue($images->firstWhere('image', self::JAVA_21)['is_recommended']);
        $this->assertFalse($images->firstWhere('image', self::JAVA_17)['is_recommended']);
    }

    public function test_apply_docker_image_patches_startup_and_restarts(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)
            ->postJson("/api/servers/{$server->id}/docker-image", ['image' => self::JAVA_21]);

        $response->assertOk()->assertJson(['success' => true, 'image' => self::JAVA_21]);

        Http::assertSent(fn (ClientRequest $r): bool => $r->method() === 'PATCH'
            && str_contains($r->url(), '/api/application/servers/4242/startup')
            && $r['image'] === self::JAVA_21
            && $r['egg'] === 555
            && $r['skip_scripts'] === true);

        // Hard power-cycle: kill, then start (not a soft restart).
        Http::assertSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/api/client/servers/srv-uuid/power')
            && $r['signal'] === 'kill');
        Http::assertSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/api/client/servers/srv-uuid/power')
            && $r['signal'] === 'start');
    }

    public function test_apply_docker_image_rejects_an_image_outside_the_allowed_set(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)
            ->postJson("/api/servers/{$server->id}/docker-image", ['image' => 'evil/image:latest']);

        $response->assertStatus(422);

        Http::assertNotSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/startup'));
        Http::assertNotSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/power'));
    }

    public function test_accept_eula_writes_the_file_and_restarts(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)->postJson("/api/servers/{$server->id}/accept-eula");

        $response->assertOk()->assertJson(['success' => true]);

        Http::assertSent(fn (ClientRequest $r): bool => $r->method() === 'POST'
            && str_contains($r->url(), '/api/client/servers/srv-uuid/files/write')
            && str_contains($r->url(), 'file=%2Feula.txt')
            && trim($r->body()) === 'eula=true');

        // Hard power-cycle: kill, then start.
        Http::assertSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/power') && $r['signal'] === 'kill');
        Http::assertSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/power') && $r['signal'] === 'start');
    }

    public function test_a_stranger_cannot_accept_the_eula(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $this->actingAs($stranger)
            ->postJson("/api/servers/{$server->id}/accept-eula")
            ->assertStatus(403);

        Http::assertNotSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/files/write'));
    }
}
