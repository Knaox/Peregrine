<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\VersionChanger;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Plugins\VersionChanger\Jobs\ChangeVersionJob;
use Plugins\VersionChanger\Models\ChangeVersionLog;
use Tests\TestCase;

/**
 * Top-priority test : pins the three behavioural corrections that
 * make Version Changer safe to run on a production server.
 *
 *  1. **No user data ever deleted.** The only Wings `/files/delete`
 *     call the job emits is for `server.jar` + `libraries/`. Worlds,
 *     plugins, configs are untouched.
 *  2. **Docker image patched via fingerprint** when the optional
 *     Wings patch is live — re-empreinte → MCJars → java → image.
 *  3. **Docker image patched via MCJars-known Java** when no
 *     fingerprint feature is detected (fallback path).
 *
 * Both image paths end on the same PATCH `/api/application/servers/{id}/startup`
 * call — the egg, startup command and environment are preserved.
 */
class ChangeVersionJobTest extends TestCase
{
    use ActivatesVersionChangerPlugin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->bootVersionChangerPlugin();

        parent::setUp();

        config([
            'panel.installed' => true,
            'panel.pelican.url' => 'http://pelican.test',
            'panel.pelican.admin_api_key' => 'admin-key',
            'panel.pelican.client_api_key' => 'client-key',
        ]);

        Cache::flush();
    }

    public function test_pipeline_only_deletes_server_jar_and_libraries_directory(): void
    {
        $server = $this->makeServer();
        $log = $this->makeLog($server);

        $deleteCalls = [];
        $this->fakePelicanAndMcjars(
            extraOverrides: [
                'pelican.test/api/client/servers/'.$this->identifier.'/files/delete' => function ($request) use (&$deleteCalls) {
                    $deleteCalls[] = $request->data();

                    return Http::response(['data' => []], 204);
                },
            ],
        );

        $job = new ChangeVersionJob($log->id, $this->identifier, true);
        app()->call([$job, 'handle']);

        $this->assertNotEmpty($deleteCalls, 'wipe step must call /files/delete');
        $wipeCall = $deleteCalls[0];
        $this->assertSame('/', $wipeCall['root']);
        $this->assertSame(['server.jar', 'libraries'], $wipeCall['files']);

        foreach ($deleteCalls as $call) {
            foreach ((array) ($call['files'] ?? []) as $name) {
                $this->assertNotContains($name, ['world', 'worlds', 'plugins', 'config'],
                    'User data path leaked into a delete call.');
            }
        }
    }

    public function test_pipeline_patches_docker_image_via_fingerprint_path(): void
    {
        $server = $this->makeServer();
        $log = $this->makeLog($server);

        $patchCalls = [];
        $this->fakePelicanAndMcjars(
            beforeMcjarsBuilds: null,
            extraOverrides: [
                'pelican.test/api/application/servers/'.$server->pelican_server_id.'/startup' => function ($request) use (&$patchCalls) {
                    $patchCalls[] = ['method' => $request->method(), 'data' => $request->data()];

                    return Http::response(['attributes' => []], 200);
                },
            ],
            fingerprintProbe: 200,
            fingerprintHashes: ['server.jar' => $this->sha512('fingerprint-hit')],
            mcjarsHashLookup: ['type' => 'PAPER', 'versionId' => '1.21', 'buildNumber' => 130],
        );

        $job = new ChangeVersionJob($log->id, $this->identifier, true);
        app()->call([$job, 'handle']);

        $patches = array_filter($patchCalls, fn ($c) => $c['method'] === 'PATCH');
        $this->assertNotEmpty($patches, 'patch image step must call PATCH /servers/{id}/startup');
        $last = end($patches);
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $last['data']['image']);
        $this->assertSame(true, $last['data']['skip_scripts']);
        $this->assertArrayHasKey('egg', $last['data']);
        $this->assertArrayHasKey('startup', $last['data']);
        $this->assertArrayHasKey('environment', $last['data']);

        $log->refresh();
        $this->assertSame(ChangeVersionLog::STATUS_OK, $log->status);
    }

    public function test_pipeline_invalidates_sibling_plugin_runtime_caches_on_success(): void
    {
        $server = $this->makeServer();
        $log = $this->makeLog($server);

        // Sibling plugins (mods-installer, plugins-installer, hytale-mods)
        // would normally hold a stale 5 min cache of the OLD loader
        // banner after a version change. The job must blow those keys
        // away so the next /runtime call detects the freshly installed
        // server.jar instead of serving "Paper" for another 5 min.
        Cache::put('minecraft-mods-installer:runtime:'.$this->identifier, ['status' => 'ok'], 600);
        Cache::put('minecraft-plugins-installer:runtime:'.$this->identifier, ['status' => 'ok'], 600);
        Cache::put('hytale-mods-installer:runtime:'.$this->identifier, ['status' => 'ok'], 600);

        $this->fakePelicanAndMcjars();

        $job = new ChangeVersionJob($log->id, $this->identifier, true);
        app()->call([$job, 'handle']);

        $log->refresh();
        $this->assertSame(ChangeVersionLog::STATUS_OK, $log->status);
        $this->assertFalse(Cache::has('minecraft-mods-installer:runtime:'.$this->identifier));
        $this->assertFalse(Cache::has('minecraft-plugins-installer:runtime:'.$this->identifier));
        $this->assertFalse(Cache::has('hytale-mods-installer:runtime:'.$this->identifier));
    }

    public function test_pipeline_falls_back_to_mcjars_java_when_no_fingerprint(): void
    {
        $server = $this->makeServer();
        $log = $this->makeLog($server);

        $patchCalls = [];
        $this->fakePelicanAndMcjars(
            extraOverrides: [
                'pelican.test/api/application/servers/'.$server->pelican_server_id.'/startup' => function ($request) use (&$patchCalls) {
                    $patchCalls[] = ['method' => $request->method(), 'data' => $request->data()];

                    return Http::response(['attributes' => []], 200);
                },
            ],
            fingerprintProbe: 404,
        );

        $job = new ChangeVersionJob($log->id, $this->identifier, true);
        app()->call([$job, 'handle']);

        $patches = array_filter($patchCalls, fn ($c) => $c['method'] === 'PATCH');
        $this->assertNotEmpty($patches, 'image must still be patched in the fallback path');
        $last = end($patches);
        // Fallback uses java from the build's version index (set to 21 in fakes).
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $last['data']['image']);

        $log->refresh();
        $this->assertSame(ChangeVersionLog::STATUS_OK, $log->status);
    }

    // ---------------------------------------------------------------
    //  Test helpers
    // ---------------------------------------------------------------

    private string $identifier = 'srv-test1';

    private function makeServer(): Server
    {
        $user = User::factory()->create();

        return Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => 4242,
            'identifier' => $this->identifier,
            'name' => 'Test',
            'status' => 'active',
        ]);
    }

    private function makeLog(Server $server): ChangeVersionLog
    {
        return ChangeVersionLog::query()->create([
            'server_id' => $server->id,
            'target_type' => 'paper',
            'target_version' => '1.21',
            'target_build_id' => 181130,
            'target_build_number' => 130,
            'status' => ChangeVersionLog::STATUS_PENDING,
        ]);
    }

    private function sha512(string $material): string
    {
        return hash('sha512', $material);
    }

    /** @param array<string, callable|array<string, mixed>> $extraOverrides */
    private function fakePelicanAndMcjars(
        ?callable $beforeMcjarsBuilds = null,
        array $extraOverrides = [],
        int $fingerprintProbe = 404,
        array $fingerprintHashes = [],
        ?array $mcjarsHashLookup = null,
    ): void {
        $buildPayload = [
            'success' => true,
            'builds' => [[
                'id' => 181130,
                'name' => '#130',
                'buildNumber' => 130,
                'experimental' => false,
                'projectVersionId' => null,
                'jarUrl' => 'https://example.test/paper.jar',
                'zipUrl' => null,
                'installation' => [[['type' => 'download', 'url' => 'https://example.test/paper.jar', 'file' => 'server.jar', 'size' => 100]]],
                'versionId' => '1.21',
                'type' => 'PAPER',
            ]],
        ];

        $versionsPayload = [
            'success' => true,
            'builds' => ['1.21' => ['type' => 'RELEASE', 'supported' => true, 'java' => 21, 'builds' => 1, 'created' => null, 'latest' => ['id' => 181130]]],
        ];

        $hashResp = $mcjarsHashLookup === null
            ? Http::response(['success' => false, 'errors' => ['build not found']], 404)
            : Http::response(['success' => true, 'build' => $mcjarsHashLookup], 200);

        $fingerprintsHandler = function ($request) use ($fingerprintProbe, $fingerprintHashes) {
            $url = (string) $request->url();
            if (! str_contains($url, 'files[]=') && ! str_contains($url, 'files%5B%5D=')) {
                return Http::response([], $fingerprintProbe);
            }

            return Http::response(['fingerprints' => $fingerprintHashes], 200);
        };

        $fakes = [
            'mcjars.app/api/v2/builds/paper/1.21' => Http::response($buildPayload, 200),
            'mcjars.app/api/v2/builds/paper' => Http::response($versionsPayload, 200),
            'mcjars.app/api/v2/build' => $hashResp,
            'pelican.test/api/client/servers/'.$this->identifier.'/power' => Http::response([], 204),
            'pelican.test/api/client/servers/'.$this->identifier.'/resources' => Http::response(['attributes' => ['current_state' => 'offline']], 200),
            'pelican.test/api/client/servers/'.$this->identifier.'/files/delete' => Http::response([], 204),
            'pelican.test/api/client/servers/'.$this->identifier.'/files/pull' => Http::response([], 204),
            'pelican.test/api/client/servers/'.$this->identifier.'/files/write*' => Http::response([], 204),
            'pelican.test/api/client/servers/'.$this->identifier.'/startup/variable' => Http::response([], 204),
            'pelican.test/api/client/servers/'.$this->identifier.'/files/fingerprints*' => $fingerprintsHandler,
            'pelican.test/api/application/servers/4242*' => Http::response([
                'attributes' => [
                    'egg' => 1,
                    'container' => [
                        'image' => 'old-image',
                        'startup_command' => '{{SERVER_JARFILE}}',
                        'environment' => ['SERVER_JARFILE' => 'server.jar'],
                    ],
                ],
            ], 200),
        ];

        Http::fake(array_merge($fakes, $extraOverrides));

    }
}
