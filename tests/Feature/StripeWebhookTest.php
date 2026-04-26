<?php

namespace Tests\Feature;

use App\Events\Bridge\PaymentConfirmed;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Jobs\ProvisionServerJob;
use App\Listeners\Bridge\SendPaymentConfirmedNotification;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Models\StripeProcessedEvent;
use App\Models\User;
use App\Notifications\Bridge\PaymentConfirmedNotification;
use App\Notifications\Bridge\TrialWillEndNotification;
use App\Services\Bridge\Stripe\StripeSessionFetcher;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
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

    public function test_payment_confirmed_listener_skips_when_amount_is_zero(): void
    {
        // Trial checkouts fire PaymentConfirmed with amountCents=0. The listener
        // must short-circuit so the customer doesn't get a "thanks for your €0
        // payment" receipt. Stripe will fire a real invoice.payment_succeeded
        // when the trial converts to a paid charge.
        Notification::fake();
        app(SettingsService::class)->set('bridge_mode', 'shop_stripe');

        $user = User::factory()->create();
        $plan = $this->seedPlan(stripePriceId: 'price_trial_zero');

        (new SendPaymentConfirmedNotification())->handle(new PaymentConfirmed(
            user: $user,
            plan: $plan,
            amountCents: 0,
            currency: 'eur',
            invoiceId: null,
        ));

        Notification::assertNothingSent();
    }

    public function test_payment_confirmed_listener_sends_when_amount_is_positive(): void
    {
        // Counter-test: a real paid checkout (amount > 0) still gets the receipt.
        Notification::fake();
        app(SettingsService::class)->set('bridge_mode', 'shop_stripe');

        $user = User::factory()->create();
        $plan = $this->seedPlan(stripePriceId: 'price_paid_positive');

        (new SendPaymentConfirmedNotification())->handle(new PaymentConfirmed(
            user: $user,
            plan: $plan,
            amountCents: 999,
            currency: 'eur',
            invoiceId: 'in_test_001',
        ));

        Notification::assertSentTo($user, PaymentConfirmedNotification::class);
    }

    public function test_trial_will_end_dispatches_user_email(): void
    {
        Notification::fake();
        app(SettingsService::class)->set('bridge_mode', 'shop_stripe');

        $user = User::factory()->create();
        $this->seedServerWithSubscription($user, 'sub_trial_001');

        $trialEndTs = time() + (3 * 86400);
        $event = $this->makeTrialWillEndEvent('sub_trial_001', $trialEndTs);

        $response = $this->signedStripePost($event);

        $response->assertStatus(200);
        Notification::assertSentTo($user, TrialWillEndNotification::class);

        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertSame('TrialWillEnd', $log->payload_summary['dispatched'] ?? null);
        $this->assertSame('sub_trial_001', $log->payload_summary['subscription_id'] ?? null);
    }

    public function test_trial_will_end_skipped_in_paymenter_mode(): void
    {
        Notification::fake();
        app(SettingsService::class)->set('bridge_mode', 'paymenter');

        $user = User::factory()->create();
        $this->seedServerWithSubscription($user, 'sub_trial_paymenter');

        $event = $this->makeTrialWillEndEvent('sub_trial_paymenter', time() + (3 * 86400));
        $this->signedStripePost($event)->assertStatus(200);

        // Listener gates on isShopStripe() — Paymenter mode = silent.
        Notification::assertNothingSent();
    }

    public function test_trial_will_end_skipped_when_server_not_found(): void
    {
        Notification::fake();
        app(SettingsService::class)->set('bridge_mode', 'shop_stripe');

        // No server seeded for this subscription id.
        $event = $this->makeTrialWillEndEvent('sub_unknown_trial', time() + (3 * 86400));
        $this->signedStripePost($event)->assertStatus(200);

        Notification::assertNothingSent();
        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertSame('server_not_found', $log->payload_summary['skipped'] ?? null);
    }

    public function test_invoice_paid_resets_provisioning_error_when_present(): void
    {
        $user = User::factory()->create();
        $server = $this->seedServerWithSubscription($user, 'sub_renewal_001');
        $server->forceFill(['provisioning_error' => 'transient Pelican 503'])->save();

        $event = $this->makeInvoicePaidEvent('sub_renewal_001', 'in_renew_001', amountPaid: 999);
        $this->signedStripePost($event)->assertStatus(200);

        $server->refresh();
        $this->assertNull($server->provisioning_error);

        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertTrue($log->payload_summary['audited'] ?? false);
        $this->assertTrue($log->payload_summary['provisioning_error_cleared'] ?? false);
    }

    public function test_invoice_paid_audits_without_changes_when_no_provisioning_error(): void
    {
        $user = User::factory()->create();
        $this->seedServerWithSubscription($user, 'sub_clean_renewal');

        $event = $this->makeInvoicePaidEvent('sub_clean_renewal', 'in_clean_001', amountPaid: 1500);
        $this->signedStripePost($event)->assertStatus(200);

        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertTrue($log->payload_summary['audited'] ?? false);
        $this->assertFalse($log->payload_summary['provisioning_error_cleared'] ?? true);
    }

    public function test_invoice_paid_skipped_when_server_not_found(): void
    {
        $event = $this->makeInvoicePaidEvent('sub_orphan_invoice', 'in_orphan_001', amountPaid: 500);
        $this->signedStripePost($event)->assertStatus(200);

        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertSame('server_not_found', $log->payload_summary['skipped'] ?? null);
    }

    public function test_invoice_paid_skipped_when_no_subscription_on_invoice(): void
    {
        // One-shot invoice (no subscription field) — nothing to reconcile.
        $event = [
            'id' => 'evt_'.Str::random(24),
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_oneshot',
                'amount_paid' => 1000,
                // no 'subscription' key
            ]],
        ];
        $this->signedStripePost($event)->assertStatus(200);

        $log = StripeProcessedEvent::where('event_id', $event['id'])->first();
        $this->assertSame('no_subscription_on_invoice', $log->payload_summary['skipped'] ?? null);
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

    private function makeTrialWillEndEvent(string $subscriptionId, int $trialEndTs): array
    {
        return [
            'id' => 'evt_'.Str::random(24),
            'type' => 'customer.subscription.trial_will_end',
            'data' => ['object' => [
                'id' => $subscriptionId,
                'object' => 'subscription',
                'status' => 'trialing',
                'trial_end' => $trialEndTs,
            ]],
        ];
    }

    private function makeInvoicePaidEvent(string $subscriptionId, string $invoiceId, int $amountPaid): array
    {
        return [
            'id' => 'evt_'.Str::random(24),
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => $invoiceId,
                'object' => 'invoice',
                'subscription' => $subscriptionId,
                'amount_paid' => $amountPaid,
                'currency' => 'eur',
            ]],
        ];
    }

    private function seedServerWithSubscription(User $user, string $subscriptionId): Server
    {
        $plan = $this->seedPlan(stripePriceId: 'price_'.Str::random(8));

        return Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => mt_rand(1000, 9999),
            'identifier' => substr(Str::random(8), 0, 8),
            'name' => 'srv-'.Str::random(4),
            'status' => 'active',
            'egg_id' => $plan->egg_id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $subscriptionId,
        ]);
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
