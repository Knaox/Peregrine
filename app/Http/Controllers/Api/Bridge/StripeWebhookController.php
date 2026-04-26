<?php

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Models\StripeProcessedEvent;
use App\Services\Bridge\Stripe\StripeEventHandlers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Event;

/**
 * Receives webhook events from Stripe (the signed payload was already
 * validated by VerifyStripeSignature middleware — we get a parsed
 * Stripe\Event in the request attributes).
 *
 * Hard rule from Stripe : we have less than 5 seconds to respond. The
 * controller's only job is to dispatch background work, never to call
 * Pelican synchronously. All retry/error logic lives in the queued jobs.
 *
 * Event-specific routing and business lookups live in
 * `App\Services\Bridge\Stripe\StripeEventHandlers` — this controller
 * stays focused on idempotency, response shaping, and audit logging.
 *
 * Response codes :
 *   200 — event accepted (dispatched OR already processed OR explicitly skipped)
 *   422 — event valid but data missing (logged + 200-style behavior to avoid endless retries)
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        /** @var Event|null $event */
        $event = $request->attributes->get('stripe.event');

        if ($event === null) {
            // Should never happen — middleware always sets this on success.
            return response()->json(['error' => 'no_event'], 500);
        }

        // Idempotency : Stripe may re-deliver the same event up to 3 days.
        // We persist event_id in stripe_processed_events; if hit, no-op.
        if (StripeProcessedEvent::where('event_id', $event->id)->exists()) {
            return response()->json(['received' => true, 'idempotent' => true], 200);
        }

        $responseStatus = 200;
        $errorMessage = null;
        $payloadSummary = null;

        try {
            $payloadSummary = match ($event->type) {
                'checkout.session.completed' => StripeEventHandlers::handleCheckoutCompleted($event),
                'customer.subscription.updated' => StripeEventHandlers::handleSubscriptionUpdated($event),
                'customer.subscription.deleted' => StripeEventHandlers::handleSubscriptionDeleted($event),
                'customer.subscription.trial_will_end' => StripeEventHandlers::handleTrialWillEnd($event),
                'invoice.paid' => StripeEventHandlers::handleInvoicePaid($event),
                'invoice.payment_failed' => StripeEventHandlers::handlePaymentFailed($event),
                default => StripeEventHandlers::handleUnsupported($event),
            };
        } catch (\Throwable $e) {
            $errorMessage = Str::limit($e->getMessage(), 900);
            Log::error('Stripe webhook handler failed', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            // Still record + return 200 so Stripe stops retrying. The error
            // is preserved in stripe_processed_events for admin investigation.
            $responseStatus = 200;
        }

        StripeProcessedEvent::create([
            'event_id' => $event->id,
            'event_type' => $event->type,
            'payload_summary' => $payloadSummary,
            'response_status' => $responseStatus,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);

        return response()->json(['received' => true], $responseStatus);
    }
}
