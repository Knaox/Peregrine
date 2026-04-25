<?php

namespace Tests\Feature\Bridge;

use App\Actions\Pelican\EnsurePelicanAccountAction;
use App\Jobs\ProvisionServerJob;
use App\Models\Egg;
use App\Models\Node;
use App\Models\ServerPlan;
use App\Models\User;
use App\Services\Bridge\EnvironmentResolver;
use App\Services\Bridge\PortAllocator;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks the safety-net behaviour added to ProvisionServerJob: when the
 * job is dispatched directly (bypassing the LinkPelicanAccountJob →
 * ProvisionServerJob chain wired from the Stripe webhook), the job
 * must auto-link the user to Pelican instead of crashing on the previous
 * null-pelican-id guard.
 */
class ProvisionServerJobAutoLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_links_user_when_pelican_user_id_is_null(): void
    {
        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.admin_api_key', 'test-key');

        $user = User::factory()->create([
            'email' => 'autolinked@example.com',
            'pelican_user_id' => null,
        ]);

        $egg = Egg::create([
            'pelican_egg_id' => 42,
            'name' => 'minecraft',
            'description' => '',
            'docker_image' => 'pelican/yolks:java_17',
            'startup' => 'java -jar server.jar',
        ]);

        $node = Node::create([
            'pelican_node_id' => 9,
            'name' => 'Test Node',
            'fqdn' => 'node.test',
            'scheme' => 'https',
            'memory' => 1024,
            'disk' => 5000,
        ]);

        // node_id set → isReadyToProvision() returns true and we reach the
        // safety-net line.
        $plan = ServerPlan::create([
            'name' => 'Test Plan',
            'shop_plan_id' => 'shop-test-plan',
            'shop_plan_slug' => 'test-plan',
            'shop_plan_type' => 'recurring',
            'price_cents' => 1000,
            'currency' => 'usd',
            'interval' => 'month',
            'is_active' => true,
            'egg_id' => $egg->id,
            'node_id' => $node->id,
        ]);

        // Pelican already has a user with this email (e.g. created by a
        // sysadmin manually). The action will link, no POST needed.
        // All other Pelican endpoints return an empty 200 to keep the rest
        // of the job from blowing up before we can assert.
        Http::fake([
            'pelican.test/api/application/users?*' => Http::response([
                'data' => [[
                    'object' => 'user',
                    'attributes' => [
                        'id' => 999,
                        'email' => 'autolinked@example.com',
                        'username' => 'auto',
                        'name' => 'Autolinked',
                        'root_admin' => false,
                        'created_at' => '2026-01-01T00:00:00Z',
                    ],
                ]],
            ], 200),
            'pelican.test/*' => Http::response([], 500), // anything else fails
        ]);

        $job = new ProvisionServerJob(
            planId: $plan->id,
            userId: $user->id,
            idempotencyKey: 'test-idempotency-1',
        );

        try {
            $job->handle(
                app(PortAllocator::class),
                app(EnvironmentResolver::class),
                app(PelicanApplicationService::class),
                app(EnsurePelicanAccountAction::class),
            );
        } catch (\Throwable) {
            // Expected — downstream Pelican calls will fail, but the
            // safety-net link runs FIRST. We're only asserting the link.
        }

        $this->assertSame(
            999,
            $user->fresh()->pelican_user_id,
            'Safety net failed to auto-link the user before the rest of the job ran.'
        );
    }
}
