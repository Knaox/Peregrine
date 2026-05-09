<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookDeliveryAttempt;
use App\Webhooks\StandardWebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends one signed POST to the target endpoint. Each invocation is one
 * attempt ; on failure, the queue worker re-runs the job with backoff
 * up to `endpoint.max_retries`. After exhaustion the delivery is marked
 * `expired` and `consecutive_failures` is bumped on the endpoint so the
 * Filament dashboard can surface flaky targets.
 *
 * Spatie's webhook-server package was installed (Phase 3) but its
 * built-in Signer interface only sees the body — Standard Webhooks
 * needs id + timestamp pre-computed for the signed content. We
 * therefore use Laravel's HTTP client directly with our own
 * `StandardWebhookSigner` and let the job retry policy handle async
 * back-off.
 */
class DispatchWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // we control retries via $delivery->attempt_count

    public function __construct(public readonly int $deliveryId) {}

    public function handle(StandardWebhookSigner $signer): void
    {
        $delivery = WebhookDelivery::with(['endpoint', 'event'])->find($this->deliveryId);
        if ($delivery === null || $delivery->status === 'success' || $delivery->status === 'expired') {
            return;
        }
        $endpoint = $delivery->endpoint;
        $event = $delivery->event;
        if ($endpoint === null || $event === null || ! $endpoint->isActive()) {
            return;
        }

        $body = (string) json_encode([
            'type' => $event->event_type,
            'id' => $event->idempotency_key,
            'timestamp' => $event->emitted_at?->toIso8601String(),
            'data' => $event->payload,
        ]);
        $timestamp = (string) time();
        $signature = $signer->sign($event->idempotency_key, $timestamp, $body, (string) $endpoint->signing_secret);

        $attemptNumber = $delivery->attempt_count + 1;
        $now = now();
        $delivery->forceFill([
            'attempt_count' => $attemptNumber,
            'first_attempted_at' => $delivery->first_attempted_at ?? $now,
            'last_attempted_at' => $now,
        ])->save();

        $start = microtime(true);
        try {
            $response = Http::timeout($endpoint->timeout_seconds)
                ->withHeaders([
                    'webhook-id' => $event->idempotency_key,
                    'webhook-timestamp' => $timestamp,
                    'webhook-signature' => $signature,
                    'content-type' => 'application/json',
                    'user-agent' => 'Peregrine-Webhooks/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
            $latency = (int) ((microtime(true) - $start) * 1000);

            $this->persistAttempt(
                $delivery,
                $attemptNumber,
                $body,
                $response->status(),
                $response->headers(),
                $response->body(),
                $latency,
                null,
                null,
            );

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => 'success',
                    'last_status_code' => $response->status(),
                    'last_error_message' => null,
                    'next_retry_at' => null,
                ])->save();
                $endpoint->forceFill([
                    'consecutive_failures' => 0,
                    'last_delivery_at' => $now,
                    'last_error' => null,
                ])->save();
                return;
            }

            $this->handleFailure(
                $delivery,
                $endpoint,
                $attemptNumber,
                $response->status(),
                Str::limit('HTTP '.$response->status().': '.$response->body(), 500),
            );
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);
            $this->persistAttempt(
                $delivery,
                $attemptNumber,
                $body,
                null,
                null,
                null,
                $latency,
                'transport_error',
                Str::limit($e->getMessage(), 500),
            );
            $this->handleFailure(
                $delivery,
                $endpoint,
                $attemptNumber,
                null,
                Str::limit($e->getMessage(), 500),
            );
        }
    }

    /**
     * @param  array<string, array<int, string>>|null  $responseHeaders
     */
    private function persistAttempt(
        WebhookDelivery $delivery,
        int $attemptNumber,
        string $body,
        ?int $status,
        ?array $responseHeaders,
        ?string $responseBody,
        ?int $latencyMs,
        ?string $errorType,
        ?string $errorMessage,
    ): void {
        WebhookDeliveryAttempt::create([
            'webhook_delivery_id' => $delivery->id,
            'attempt_number' => $attemptNumber,
            'request_body' => $body,
            'http_status_code' => $status,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody === null ? null : Str::limit($responseBody, 8000, ''),
            'response_time_ms' => $latencyMs,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'attempted_at' => now(),
        ]);
    }

    private function handleFailure(
        WebhookDelivery $delivery,
        \App\Models\WebhookEndpoint $endpoint,
        int $attemptNumber,
        ?int $statusCode,
        string $errorMessage,
    ): void {
        $endpoint->forceFill([
            'consecutive_failures' => $endpoint->consecutive_failures + 1,
            'last_delivery_at' => now(),
            'last_error' => $errorMessage,
        ])->save();

        if ($attemptNumber >= $endpoint->max_retries) {
            $delivery->forceFill([
                'status' => 'expired',
                'last_status_code' => $statusCode,
                'last_error_message' => $errorMessage,
                'next_retry_at' => null,
            ])->save();
            Log::warning('Webhook delivery expired after max retries', [
                'delivery_id' => $delivery->id,
                'endpoint_id' => $endpoint->id,
            ]);
            return;
        }

        // Exponential backoff : 60s, 300s, 900s, 1800s, 3600s, capped.
        $backoffs = [60, 300, 900, 1800, 3600];
        $delaySeconds = $backoffs[min($attemptNumber - 1, count($backoffs) - 1)];

        $delivery->forceFill([
            'status' => 'failed',
            'last_status_code' => $statusCode,
            'last_error_message' => $errorMessage,
            'next_retry_at' => now()->addSeconds($delaySeconds),
        ])->save();

        // Re-dispatch the same job after the delay.
        self::dispatch($delivery->id)->delay(now()->addSeconds($delaySeconds));
    }
}
