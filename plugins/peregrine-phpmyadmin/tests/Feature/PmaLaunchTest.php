<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Plugins\PeregrinePhpmyadmin\Tests\TestCase;

class PmaLaunchTest extends TestCase
{
    private function fakeCredentials(): void
    {
        Http::fake(function (ClientRequest $request) {
            if (str_contains($request->url(), '/databases')) {
                return Http::response(['data' => [['attributes' => [
                    'id' => 'db-1',
                    'name' => 's1_db',
                    'username' => 'u1_abc',
                    'host' => ['address' => 'db.host.test', 'port' => 3306],
                    'relationships' => ['password' => ['attributes' => ['password' => 'SECRET']]],
                ]]]], 200);
            }

            return Http::response([], 204);
        });
    }

    private function url(int $serverId): string
    {
        return "/api/plugins/peregrine-phpmyadmin/servers/{$serverId}/databases/db-1/launch";
    }

    public function test_owner_gets_a_signon_url_and_an_audit_row(): void
    {
        $this->configurePlugin(['enabled' => true, 'pma_url' => 'https://pma.test', 'auto_select_db' => true]);
        $this->fakeCredentials();

        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)->postJson($this->url($server->id));

        $response->assertOk()->assertJsonStructure(['url']);
        $url = (string) $response->json('url');
        $this->assertStringContainsString('https://pma.test/?signon_token=', $url);
        $this->assertStringContainsString('db=s1_db', $url);
        // No server index configured → no ?server param (keeps phpMyAdmin's default server).
        $this->assertStringNotContainsString('&server=', $url);

        $this->assertDatabaseHas('pma_launch_logs', [
            'server_id' => $server->id,
            'database_id' => 'db-1',
            'event' => 'launch',
        ]);
    }

    public function test_launch_is_forbidden_when_disabled(): void
    {
        $this->configurePlugin(['enabled' => false, 'pma_url' => 'https://pma.test']);

        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $this->actingAs($owner)->postJson($this->url($server->id))->assertStatus(403);
    }

    public function test_a_stranger_cannot_launch(): void
    {
        $this->configurePlugin(['enabled' => true, 'pma_url' => 'https://pma.test']);
        $this->fakeCredentials();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $this->actingAs($stranger)->postJson($this->url($server->id))->assertStatus(403);
    }

    public function test_launch_targets_the_configured_signon_server(): void
    {
        $this->configurePlugin(['enabled' => true, 'pma_url' => 'https://pma.test', 'pma_server_index' => 2]);
        $this->fakeCredentials();

        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $url = (string) $this->actingAs($owner)->postJson($this->url($server->id))->assertOk()->json('url');

        $this->assertStringContainsString('&server=2', $url);
    }

    public function test_manual_login_mode_opens_phpmyadmin_without_a_token(): void
    {
        $this->configurePlugin(['enabled' => true, 'pma_url' => 'https://pma.test', 'auto_login' => false]);
        $this->fakeCredentials();

        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $url = (string) $this->actingAs($owner)->postJson($this->url($server->id))->assertOk()->json('url');

        $this->assertStringNotContainsString('signon_token', $url);
        $this->assertStringContainsString('db=s1_db', $url);
    }
}
