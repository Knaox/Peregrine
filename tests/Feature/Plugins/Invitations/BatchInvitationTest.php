<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\Invitations;

use App\Jobs\SendPluginMail;
use App\Models\Egg;
use App\Models\Nest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Plugins\Invitations\Mail\ServerInvitationMail;
use Plugins\Invitations\Models\Invitation;
use Plugins\Invitations\Services\InvitationService;
use Tests\TestCase;

/**
 * Covers the multi-server invitation batch: createBatch (one email, one row per
 * server, shared batch_id) and accept (accept-all with per-server partial
 * failure). Also guards the single-server path against regression.
 */
class BatchInvitationTest extends TestCase
{
    use ActivatesInvitationsPlugin;
    use RefreshDatabase;

    private int $eggId = 0;

    protected function setUp(): void
    {
        $this->bootInvitationsPlugin();

        parent::setUp();

        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.client_api_key', 'test-client-key');
    }

    private function egg(): int
    {
        if ($this->eggId === 0) {
            $nest = Nest::create(['pelican_nest_id' => mt_rand(1, 999999), 'name' => 'N']);
            $egg = Egg::create(['pelican_egg_id' => mt_rand(1, 999999), 'nest_id' => $nest->id, 'name' => 'E', 'docker_image' => 't:1', 'startup' => 'echo']);
            $this->eggId = $egg->id;
        }

        return $this->eggId;
    }

    private function makeServer(string $identifier, int $ownerId): Server
    {
        return Server::create([
            'user_id' => $ownerId,
            'pelican_server_id' => mt_rand(100, 9999999),
            'identifier' => $identifier,
            'name' => 'srv-'.$identifier,
            'status' => 'active',
            'egg_id' => $this->egg(),
        ]);
    }

    private function invitee(): User
    {
        $user = User::factory()->create(['email' => 'invitee@example.com']);
        $user->forceFill(['pelican_user_id' => 4242])->save(); // skip EnsurePelicanAccount

        return $user;
    }

    private function service(): InvitationService
    {
        return app(InvitationService::class);
    }

    private function makeBatchRow(string $token, string $batchId, bool $leader, int $serverId, int $inviterId): void
    {
        Invitation::create([
            'token' => hash('sha256', $token),
            'batch_id' => $batchId,
            'is_batch_leader' => $leader,
            'email' => 'invitee@example.com',
            'server_id' => $serverId,
            'permissions' => ['control.console'],
            'inviter_user_id' => $inviterId,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_single_server_invite_stays_backward_compatible(): void
    {
        Bus::fake([SendPluginMail::class]);
        $inviter = User::factory()->create();
        $server = $this->makeServer('aaa', $inviter->id);

        $result = $this->service()->createBatch([$server->id], $inviter, 'New@Example.com', ['control.console']);

        $this->assertCount(1, $result['invitations']);
        $this->assertSame(1, Invitation::count());
        $this->assertNull(Invitation::first()->batch_id);
        $this->assertSame('new@example.com', Invitation::first()->email);
        Bus::assertDispatchedTimes(SendPluginMail::class, 1);
    }

    public function test_batch_creates_one_row_per_server_and_a_single_email(): void
    {
        Bus::fake([SendPluginMail::class]);
        $inviter = User::factory()->create();
        $ids = [
            $this->makeServer('aaa', $inviter->id)->id,
            $this->makeServer('bbb', $inviter->id)->id,
            $this->makeServer('ccc', $inviter->id)->id,
        ];

        $result = $this->service()->createBatch($ids, $inviter, 'team@example.com', ['user.read', 'control.console']);

        $this->assertCount(3, $result['invitations']);
        $this->assertSame(3, Invitation::count());
        $this->assertCount(1, Invitation::pluck('batch_id')->unique());
        $this->assertNotNull(Invitation::first()->batch_id);
        $this->assertSame(1, Invitation::where('is_batch_leader', true)->count());
        Bus::assertDispatchedTimes(SendPluginMail::class, 1);
    }

    public function test_batch_skips_servers_with_an_active_invite(): void
    {
        Bus::fake([SendPluginMail::class]);
        $inviter = User::factory()->create();
        $a = $this->makeServer('aaa', $inviter->id);
        $b = $this->makeServer('bbb', $inviter->id);

        Invitation::create([
            'token' => hash('sha256', 'pre'), 'email' => 'dup@example.com', 'server_id' => $b->id,
            'permissions' => ['user.read'], 'inviter_user_id' => $inviter->id, 'expires_at' => now()->addDays(7),
        ]);

        $result = $this->service()->createBatch([$a->id, $b->id], $inviter, 'dup@example.com', ['control.console']);

        $this->assertSame([$b->id], $result['skipped']);
        $this->assertSame(1, Invitation::where('server_id', $a->id)->count());
        $this->assertSame(1, Invitation::where('server_id', $b->id)->count());
    }

    public function test_accept_grants_access_on_every_server_in_the_batch(): void
    {
        Http::fake(['pelican.test/api/client/servers/*/users' => Http::response(['attributes' => ['uuid' => 'u-1']], 200)]);

        $inviter = User::factory()->create();
        $invitee = $this->invitee();
        $a = $this->makeServer('aaa', $inviter->id);
        $b = $this->makeServer('bbb', $inviter->id);

        $batchId = (string) Str::uuid();
        $this->makeBatchRow('leader-token', $batchId, true, $a->id, $inviter->id);
        $this->makeBatchRow('sibling-token', $batchId, false, $b->id, $inviter->id);

        $result = $this->service()->accept('leader-token', $invitee);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $result['accepted']);
        $this->assertSame([], $result['failed']);
        $this->assertSame(0, Invitation::whereNull('accepted_at')->count());
        $this->assertSame(2, DB::table('server_user')->where('user_id', $invitee->id)->where('role', 'subuser')->count());
    }

    public function test_accept_partial_failure_leaves_failed_server_pending(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/servers/bbb/')) {
                return Http::response(['error' => 'boom'], 500);
            }

            return Http::response(['attributes' => ['uuid' => 'u-1']], 200);
        });

        $inviter = User::factory()->create();
        $invitee = $this->invitee();
        $a = $this->makeServer('aaa', $inviter->id);
        $b = $this->makeServer('bbb', $inviter->id);

        $batchId = (string) Str::uuid();
        $this->makeBatchRow('leader-token', $batchId, true, $a->id, $inviter->id);
        $this->makeBatchRow('sibling-token', $batchId, false, $b->id, $inviter->id);

        $result = $this->service()->accept('leader-token', $invitee);

        $this->assertSame([$a->id], $result['accepted']);
        $this->assertSame([$b->id], $result['failed']);
        $this->assertNotNull(Invitation::where('server_id', $a->id)->first()->accepted_at);
        $this->assertNull(Invitation::where('server_id', $b->id)->first()->accepted_at);
        $this->assertSame(1, DB::table('server_user')->where('user_id', $invitee->id)->count());
    }

    public function test_invitation_email_renders_single_and_multi_server(): void
    {
        $inviter = User::factory()->create(['name' => 'Alice']);
        $a = $this->makeServer('aaa', $inviter->id);
        $b = $this->makeServer('bbb', $inviter->id);

        // N = 1 → single-server body; every placeholder resolved.
        $single = Invitation::create([
            'token' => hash('sha256', 'one'), 'email' => 'x@example.com', 'server_id' => $a->id,
            'permissions' => ['control.console'], 'inviter_user_id' => $inviter->id, 'expires_at' => now()->addDays(7),
        ]);
        $htmlSingle = (new ServerInvitationMail($single->id, 'tok', 'en'))->render();
        $this->assertStringContainsString('srv-aaa', $htmlSingle);
        $this->assertStringNotContainsString('{servers_list}', $htmlSingle);
        $this->assertStringNotContainsString('{server_name}', $htmlSingle);

        // N > 1 → batch body lists every server behind the single accept link.
        $batchId = (string) Str::uuid();
        $leader = Invitation::create([
            'token' => hash('sha256', 'two'), 'batch_id' => $batchId, 'is_batch_leader' => true,
            'email' => 'y@example.com', 'server_id' => $a->id, 'permissions' => ['control.console'],
            'inviter_user_id' => $inviter->id, 'expires_at' => now()->addDays(7),
        ]);
        Invitation::create([
            'token' => hash('sha256', 'three'), 'batch_id' => $batchId, 'is_batch_leader' => false,
            'email' => 'y@example.com', 'server_id' => $b->id, 'permissions' => ['control.console'],
            'inviter_user_id' => $inviter->id, 'expires_at' => now()->addDays(7),
        ]);
        $htmlMulti = (new ServerInvitationMail($leader->id, 'tok2', 'en'))->render();
        $this->assertStringContainsString('srv-aaa', $htmlMulti);
        $this->assertStringContainsString('srv-bbb', $htmlMulti);
        $this->assertStringNotContainsString('{servers_list}', $htmlMulti);
    }
}
