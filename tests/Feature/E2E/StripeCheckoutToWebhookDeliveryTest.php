<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Actions\Shops\AuthorizeConfigurationForShopAction;
use App\Actions\Shops\GenerateShopApiKeyAction;
use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Jobs\ProvisionServerJob;
use App\Jobs\Webhooks\DispatchWebhookDeliveryJob;
use App\Models\ServerConfiguration;
use App\Models\Shop;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Webhooks\StandardWebhookSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end smoke test of the multi-shop platform :
 *
 *   1. Seed Shop + ApiKey + Configuration + WebhookEndpoint subscribed
 *      to configuration.updated.
 *   2. Simulate a Stripe `checkout.session.completed` webhook with full
 *      metadata. Expect the chain LinkPelicanAccountJob → ProvisionServerJob.
 *   3. Mutate the configuration. Expect a WebhookEvent emitted + a
 *      WebhookDelivery dispatched (Bus::fake to keep it offline).
 *   4. Verify the would-be outbound payload signature is valid using
 *      our own StandardWebhookSigner — the same code path the SDK
 *      verifier will run on the receiver side.
 *
 * Covers Phases 1-5 in a single scenario : data layer, multi-shop
 * scoping, Bridge intake, observer, fan-out, signature.
 */
class StripeCheckoutToWebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_e2e_test_only';

    protected function setUp(): void
    {
        parent::setUp();
        config(['bridge.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_full_flow_from_checkout_to_outbound_webhook(): void
    {
        Bus::fake();

        // --- 1. Seed
        $shop = Shop::factory()->create(['name' => 'E2E Shop']);
        (new GenerateShopApiKeyAction())($shop, label: 'E2E', abilities: ['configurations:read']);

        $config = ServerConfiguration::create([
            'internal_name' => 'cfg-e2e-mc',
            'name_template' => '{user.username}-{configuration.internal_name}',
            'ram' => 4096, 'cpu' => 200, 'disk' => 20480,
        ]);
        (new AuthorizeConfigurationForShopAction())($shop, $config, shopExternalId: 'plan-mc-medium');

        $endpoint = WebhookEndpoint::factory()
            ->for($shop)
            ->subscribedTo(['configuration.updated'])
            ->create(['url' => 'https://shop.test/webhooks/peregrine']);

        // --- 2. Stripe checkout.session.completed
        $event = $this->checkoutEvent(configurationId: $config->id, shopId: $shop->id, email: 'buyer@example.com');
        $this->signedStripePost($event)->assertStatus(200);

        Bus::assertChained([
            LinkPelicanAccountJob::class,
            function (ProvisionServerJob $job) use ($config): bool {
                return $job->serverConfigurationId === $config->id
                    && $job->externalOrderId !== null;
            },
        ]);

        // --- 3. Mutate the configuration → observer fires → fan-out
        $config->update(['ram' => 8192]);

        $this->assertSame(1, WebhookEvent::where('event_type', 'configuration.updated')->count());
        $delivery = WebhookDelivery::latest('id')->first();
        $this->assertNotNull($delivery);
        $this->assertSame($endpoint->id, $delivery->webhook_endpoint_id);
        Bus::assertDispatched(DispatchWebhookDeliveryJob::class);

        // --- 4. Signature roundtrip (sender + verifier agree)
        $signer = new StandardWebhookSigner();
        $body = '{"x":"y"}';
        $ts = (string) time();
        $id = (string) Str::uuid();
        $sig = $signer->sign($id, $ts, $body, $endpoint->fresh()->signing_secret);
        $this->assertTrue($signer->verify($id, $ts, $body, $endpoint->fresh()->signing_secret, $sig));
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

    private function checkoutEvent(int $configurationId, int $shopId, string $email): array
    {
        return [
            'id' => 'evt_'.Str::random(24),
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_e2e_'.Str::random(8),
                'object' => 'checkout.session',
                'payment_intent' => 'pi_e2e_'.Str::random(8),
                'subscription' => 'sub_e2e_'.Str::random(8),
                'customer' => 'cus_e2e_'.Str::random(8),
                'customer_details' => ['email' => $email, 'name' => 'E2E Buyer'],
                'metadata' => [
                    'peregrine_configuration_id' => (string) $configurationId,
                    'peregrine_shop_id' => (string) $shopId,
                    'peregrine_user_email' => $email,
                    'peregrine_external_order_id' => 'ord-e2e-'.Str::random(8),
                ],
            ]],
        ];
    }
}
