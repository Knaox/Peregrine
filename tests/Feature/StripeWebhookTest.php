<?php

namespace Tests\Feature;

use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Jobs\ProvisionServerJob;
use App\Models\ServerPlan;
use App\Models\StripeProcessedEvent;
use App\Models\User;
use App\Services\Bridge\Stripe\StripeSessionFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks the Stripe webhook contract end-to-end :
 *  - Signature validation via the official SDK (we sign manually with the
 *    same algo Stripe uses : HMAC-SHA256 of "{timestamp}.{payload}")
 *  - Idempotency by event.id (no double-dispatch on Stripe re-deliveries)
 *  - Lookup ServerPlan by stripe_price_id, skip + 200 when unmapped
 *  - Dispatch ProvisionServerJob with payment_intent as idempotency_key
 *  - Custom fields → server_name override
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_local_only_ignore';

    protected function setUp(): void
    {
        parent::setUp();
        config(['bridge.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_dispatches_provision_job_on_checkout_session_completed_with_valid_signature(): void
    {
        Bus::fake();

        $plan = $this->seedPlan(stripePriceId: 'price_test_123');
        $event = $this->makeCheckoutCompletedEvent(
            priceId: 'price_test_123',
            email: 'buyer@example.com',
            paymentIntent: 'pi_test_001',
            subscription: 'sub_test_001',
            customer: 'cus_test_001',
            serverName: 'MyMinecraftServer',
        );

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        // The webhook dispatches a chain so ProvisionServerJob never runs
        // until LinkPelicanAccountJob has linked the user to Pelican. Assert
        // the chain head + check the chained ProvisionServerJob payload.
        Bus::assertChained([
            LinkPelicanAccountJob::class,
            function (ProvisionServerJob $job) use ($plan): bool {
                return $job->planId === $plan->id
                    && $job->idempotencyKey === 'stripe-pi-pi_test_001'
                    && $job->serverNameOverride === 'MyMinecraftServer'
                    && $job->stripeSubscriptionId === 'sub_test_001'
                    && $job->paymentIntentId === 'pi_test_001';
            },
        ]);

        // Buyer user was created and has the customer ID stored.
        $user = User::where('email', 'buyer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('cus_test_001', $user->stripe_customer_id);

        // Idempotency ledger has the event recorded.
        $this->assertDatabaseHas('stripe_processed_events', [
            'event_id' => $event['id'],
            'event_type' => 'checkout.session.completed',
            'response_status' => 200,
        ]);
    }

    public function test_rejects_invalid_signature(): void
    {
        Bus::fake();

        $event = $this->makeCheckoutCompletedEvent(priceId: 'price_test_x', email: 'x@x.com');
        $payload = json_encode($event);
        $ts = time();

        $response = $this->call(
            'POST', '/api/stripe/webhook',
            content: $payload,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1=deadbeef",
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $response->assertStatus(401);
        Bus::assertNothingDispatched();
        $this->assertDatabaseCount('stripe_processed_events', 0);
    }

    public function test_rejects_expired_timestamp(): void
    {
        Bus::fake();

        $event = $this->makeCheckoutCompletedEvent(priceId: 'price_test_x', email: 'x@x.com');
        $payload = json_encode($event);
        // 10 minutes in the past, Stripe SDK tolerance is 300s by default
        $expiredTs = time() - 600;
        $sig = "t={$expiredTs},v1=" . hash_hmac('sha256', "{$expiredTs}.{$payload}", self::WEBHOOK_SECRET);

        $response = $this->call(
            'POST', '/api/stripe/webhook',
            content: $payload,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $sig,
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        $response->assertStatus(401);
        Bus::assertNothingDispatched();
    }

    public function test_skips_already_processed_event(): void
    {
        Bus::fake();

        $event = $this->makeCheckoutCompletedEvent(priceId: 'price_test_y', email: 'y@y.com');

        // Pre-mark this event as processed
        StripeProcessedEvent::create([
            'event_id' => $event['id'],
            'event_type' => $event['type'],
            'response_status' => 200,
            'processed_at' => now()->subMinute(),
        ]);

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        $response->assertJson(['received' => true, 'idempotent' => true]);
        Bus::assertNothingDispatched();
        // Still only one row in the ledger (no duplicate).
        $this->assertDatabaseCount('stripe_processed_events', 1);
    }

    public function test_falls_back_to_api_expand_when_line_items_absent_from_payload(): void
    {
        Bus::fake();

        // Real Stripe webhook payloads NEVER inline line_items — the handler
        // must call back the API with expand[]=line_items. Stub the fetcher
        // so we can assert it gets called with the session id and have it
        // return the price the rest of the handler will resolve.
        $plan = $this->seedPlan(stripePriceId: 'price_expanded_001');
        $event = $this->makeCheckoutCompletedEvent(
            priceId: 'price_expanded_001',
            email: 'expand@example.com',
            paymentIntent: 'pi_expand_001',
            includeLineItems: false,
        );
        $sessionId = $event['data']['object']['id'];

        $fetcher = new class extends StripeSessionFetcher {
            public ?string $calledWith = null;
            public function fetchFirstLineItemPriceId(string $sessionId): ?string
            {
                $this->calledWith = $sessionId;
                return 'price_expanded_001';
            }
        };
        $this->app->instance(StripeSessionFetcher::class, $fetcher);

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        $this->assertSame($sessionId, $fetcher->calledWith);
        Bus::assertChained([
            LinkPelicanAccountJob::class,
            function (ProvisionServerJob $job) use ($plan): bool {
                return $job->planId === $plan->id
                    && $job->idempotencyKey === 'stripe-pi-pi_expand_001';
            },
        ]);
    }

    public function test_returns_200_when_line_items_absent_and_api_expand_fails(): void
    {
        Bus::fake();

        $event = $this->makeCheckoutCompletedEvent(
            priceId: 'price_irrelevant',
            email: 'expand-fail@example.com',
            includeLineItems: false,
        );

        // Fetcher returns null (network error / auth failure / missing secret).
        $fetcher = new class extends StripeSessionFetcher {
            public function fetchFirstLineItemPriceId(string $sessionId): ?string
            {
                return null;
            }
        };
        $this->app->instance(StripeSessionFetcher::class, $fetcher);

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        Bus::assertNothingDispatched();
        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertSame('no_price_id', $log->payload_summary['skipped'] ?? null);
    }

    public function test_idempotency_key_falls_back_to_subscription_when_no_payment_intent(): void
    {
        Bus::fake();

        $plan = $this->seedPlan(stripePriceId: 'price_sub_idem');
        // Subscription Checkout : no payment_intent (Stripe uses setup_intent
        // for card auth in subscription mode). The webhook is then re-delivered
        // by the Dashboard with a NEW event.id. Without subscription_id as a
        // stable fallback, every redeliver would create a duplicate Server.
        $event = $this->makeCheckoutCompletedEvent(
            priceId: 'price_sub_idem',
            email: 'sub@example.com',
            paymentIntent: '',
            subscription: 'sub_stable_001',
        );

        $this->signedStripePost($event)->assertStatus(200);

        Bus::assertChained([
            LinkPelicanAccountJob::class,
            function (ProvisionServerJob $job) use ($plan): bool {
                return $job->planId === $plan->id
                    && $job->idempotencyKey === 'stripe-sub-sub_stable_001';
            },
        ]);
    }

    public function test_idempotency_key_falls_back_to_session_id_when_no_payment_intent_or_subscription(): void
    {
        Bus::fake();

        $plan = $this->seedPlan(stripePriceId: 'price_session_idem');
        $event = $this->makeCheckoutCompletedEvent(
            priceId: 'price_session_idem',
            email: 'session@example.com',
            paymentIntent: '',
            subscription: null,
        );
        $sessionId = $event['data']['object']['id'];

        $this->signedStripePost($event)->assertStatus(200);

        Bus::assertChained([
            LinkPelicanAccountJob::class,
            function (ProvisionServerJob $job) use ($plan, $sessionId): bool {
                return $job->planId === $plan->id
                    && $job->idempotencyKey === 'stripe-cs-'.$sessionId;
            },
        ]);
    }

    public function test_returns_200_when_stripe_price_id_unknown(): void
    {
        Bus::fake();

        // No plan seeded for this price_id
        $event = $this->makeCheckoutCompletedEvent(
            priceId: 'price_does_not_exist',
            email: 'noplan@example.com',
        );

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        Bus::assertNothingDispatched();

        // Recorded as processed with skipped summary
        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertNotNull($log);
        $this->assertSame('unknown_price_id', $log->payload_summary['skipped'] ?? null);
    }

    public function test_handles_missing_custom_fields_with_default_server_name(): void
    {
        Bus::fake();

        $plan = $this->seedPlan(stripePriceId: 'price_test_nameless');
        $event = $this->makeCheckoutCompletedEvent(
            priceId: 'price_test_nameless',
            email: 'noname@example.com',
            paymentIntent: 'pi_test_nameless',
            customFields: [], // no server_name custom field
        );

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        Bus::assertChained([
            LinkPelicanAccountJob::class,
            function (ProvisionServerJob $job): bool {
                // No name override → ProvisionServerJob will generate a default
                return $job->serverNameOverride === null;
            },
        ]);
    }

    public function test_returns_503_when_secret_not_configured(): void
    {
        Bus::fake();
        config(['bridge.stripe.webhook_secret' => '']);

        $event = $this->makeCheckoutCompletedEvent(priceId: 'price_x', email: 'x@x.com');
        $response = $this->signedStripePost($event);

        $response->assertStatus(503);
        Bus::assertNothingDispatched();
    }

    public function test_unsupported_event_type_is_acknowledged_without_dispatch(): void
    {
        Bus::fake();

        $event = [
            'id' => 'evt_'.Str::random(24),
            'type' => 'charge.refunded',
            'data' => ['object' => ['id' => 'ch_test']],
        ];

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        Bus::assertNothingDispatched();
        $this->assertDatabaseHas('stripe_processed_events', [
            'event_id' => $event['id'],
            'event_type' => 'charge.refunded',
        ]);
    }

    private function signedStripePost(array $event): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($event);
        $ts = time();
        $sig = "t={$ts},v1=" . hash_hmac('sha256', "{$ts}.{$payload}", self::WEBHOOK_SECRET);

        return $this->call(
            'POST', '/api/stripe/webhook',
            content: $payload,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $sig,
                'HTTP_ACCEPT' => 'application/json',
            ],
        );
    }

    private function seedPlan(string $stripePriceId): ServerPlan
    {
        $nest = \App\Models\Nest::create(['pelican_nest_id' => 100, 'name' => 'TestNest', 'description' => 't']);
        $egg = \App\Models\Egg::create([
            'pelican_egg_id' => 200, 'nest_id' => $nest->id, 'name' => 'TestEgg',
            'description' => 't', 'docker_image' => 'test:latest', 'startup' => 'echo hi',
        ]);
        $node = \App\Models\Node::create([
            'pelican_node_id' => 300, 'name' => 'TestNode', 'fqdn' => 'test.test',
            'scheme' => 'https', 'memory' => 1000, 'disk' => 1000,
        ]);
        return ServerPlan::create([
            'name' => 'Test Plan',
            'stripe_price_id' => $stripePriceId,
            'egg_id' => $egg->id,
            'nest_id' => $nest->id,
            'node_id' => $node->id,
            'ram' => 1024, 'cpu' => 100, 'disk' => 5000,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $customFields
     */
    private function makeCheckoutCompletedEvent(
        string $priceId,
        string $email,
        ?string $paymentIntent = 'pi_default',
        ?string $subscription = 'sub_default',
        ?string $customer = 'cus_default',
        ?string $serverName = null,
        ?array $customFields = null,
        bool $includeLineItems = true,
    ): array {
        if ($customFields === null && $serverName !== null) {
            $customFields = [[
                'key' => 'server_name',
                'text' => ['value' => $serverName],
            ]];
        }
        $sessionObject = [
            'id' => 'cs_test_'.Str::random(20),
            'object' => 'checkout.session',
            'payment_intent' => $paymentIntent,
            'subscription' => $subscription,
            'customer' => $customer,
            'customer_email' => $email,
            'customer_details' => ['email' => $email, 'name' => 'Test Buyer'],
            'custom_fields' => $customFields ?? [],
        ];
        if ($includeLineItems) {
            $sessionObject['line_items'] = [
                'data' => [[
                    'id' => 'li_test',
                    'price' => ['id' => $priceId],
                ]],
            ];
        }
        return [
            'id' => 'evt_'.Str::random(24),
            'type' => 'checkout.session.completed',
            'data' => ['object' => $sessionObject],
        ];
    }
}
