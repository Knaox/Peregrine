<?php

namespace Tests\Feature\Auth;

use App\Actions\Pelican\EnsurePelicanAccountAction;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Pins the "OAuth/login/register must NOT block on Pelican availability"
 * contract. The path that exposed the bug : QUEUE_CONNECTION=sync (the
 * default in fresh installs) makes `dispatch()` run the handler inline.
 * If the Pelican API is unreachable, the action throws and the
 * exception bubbles up to the user-facing controller, surfacing as
 * "Shop OAuth doesn't link" or "registration failed" — even though the
 * auth state itself is fine.
 *
 * `dispatchSafely()` swallows + logs the dispatch-time failure so the
 * user-facing flow keeps moving ; the daily `auth:relink-orphans`
 * sweep is the final net for users whose Pelican link never landed.
 */
class LinkPelicanAccountJobDispatchSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Force sync queue so dispatch runs the handler inline — this is
        // the configuration that originally surfaced the bug.
        config(['queue.default' => 'sync']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dispatch_safely_swallows_pelican_action_failures(): void
    {
        $user = User::factory()->create();

        // EnsurePelicanAccountAction is `final` so we can't mock it directly;
        // instead we mock its underlying PelicanApplicationService dependency
        // and bind it into the container. The action will then call into the
        // throwing service when handle() runs.
        $pelican = Mockery::mock(PelicanApplicationService::class);
        $pelican->shouldReceive('findUserByEmail')->andThrow(new \RuntimeException('Pelican unreachable'));
        $pelican->shouldReceive('listUsers')->andThrow(new \RuntimeException('Pelican unreachable'));
        $pelican->shouldReceive('createUser')->andThrow(new \RuntimeException('Pelican unreachable'));
        $this->app->instance(PelicanApplicationService::class, $pelican);

        // Must not throw — the user-facing caller relies on this.
        LinkPelicanAccountJob::dispatchSafely($user->id, 'test-source');

        // Sanity : a regular dispatch under the same conditions DOES throw.
        $this->expectException(\RuntimeException::class);
        LinkPelicanAccountJob::dispatch($user->id, 'test-source-2');
    }
}
