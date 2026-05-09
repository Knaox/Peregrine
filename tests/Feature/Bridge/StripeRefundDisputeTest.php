<?php

declare(strict_types=1);

namespace Tests\Feature\Bridge;

use App\Jobs\SuspendServerJob;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks the inbound Stripe `charge.refunded` and `charge.dispute.created`
 * handlers added in Phase 4 :
 *
 *  - Refund → SuspendServerJob with scheduleDeletion=true (admin can
 *    cancel via Filament if it was a partial refund).
 *  - Dispute → SuspendServerJob with scheduleDeletion=false (server
 *    stays suspended pending the dispute outcome).
 *
 * Both flows look up the Server by `payment_intent_id` extracted from
 * Stripe's `data.object.payment_intent`.
 */
class StripeRefundDisputeTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_refund_dispute_test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['bridge.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_charge_refunded_dispatches_suspend_with_scheduled_deletion(): void
    {
        Bus::fake();

        $server = $this->seedServer(paymentIntent: 'pi_refund_001');

        $event = $this->signedEvent([
            'id' => 'evt_'.Str::random(24),
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_test_001',
                'payment_intent' => 'pi_refund_001',
                'amount_refunded' => 1500,
            ]],
        ]);

        $event->assertStatus(200);
        Bus::assertDispatched(SuspendServerJob::class, function (SuspendServerJob $job) use ($server): bool {
            return $job->stripeSubscriptionId === $server->stripe_subscription_id
                && $job->scheduleDeletion === true;
        });
    }

    public function test_charge_dispute_created_dispatches_suspend_without_scheduled_deletion(): void
    {
        Bus::fake();

        $server = $this->seedServer(paymentIntent: 'pi_dispute_001');

        $response = $this->signedEvent([
            'id' => 'evt_'.Str::random(24),
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_test_001',
                'payment_intent' => 'pi_dispute_001',
                'status' => 'warning_needs_response',
            ]],
        ]);

        $response->assertStatus(200);
        Bus::assertDispatched(SuspendServerJob::class, function (SuspendServerJob $job): bool {
            return $job->scheduleDeletion === false;
        });
    }

    public function test_charge_refunded_skipped_when_no_payment_intent(): void
    {
        Bus::fake();

        $response = $this->signedEvent([
            'id' => 'evt_'.Str::random(24),
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'id' => 'ch_no_pi',
                'amount_refunded' => 100,
            ]],
        ]);

        $response->assertStatus(200);
        Bus::assertNothingDispatched();
    }

    public function test_charge_dispute_skipped_when_server_not_found(): void
    {
        Bus::fake();

        $response = $this->signedEvent([
            'id' => 'evt_'.Str::random(24),
            'type' => 'charge.dispute.created',
            'data' => ['object' => [
                'id' => 'dp_orphan',
                'payment_intent' => 'pi_orphan',
                'status' => 'lost',
            ]],
        ]);

        $response->assertStatus(200);
        Bus::assertNothingDispatched();
    }

    private function seedServer(string $paymentIntent): Server
    {
        $user = User::factory()->create();
        return Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => mt_rand(1000, 9999),
            'identifier' => substr(Str::random(8), 0, 8),
            'name' => 'srv-'.Str::random(4),
            'status' => 'active',
            'payment_intent_id' => $paymentIntent,
            'stripe_subscription_id' => 'sub_'.Str::random(8),
        ]);
    }

    private function signedEvent(array $event): \Illuminate\Testing\TestResponse
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
}
