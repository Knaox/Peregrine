<?php

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Models\PelicanProcessedEvent;
use App\Services\Bridge\PelicanEventClassifier;
use App\Services\Bridge\PelicanWebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives outgoing webhooks from Pelican Panel.
 *
 * Pelican fires events on Eloquent model changes (create / update / delete)
 * and on a few custom events like `App\Events\Server\Installed`. The
 * receiver mirrors those changes into the local Peregrine DB.
 *
 * Receiver works in ALL bridge modes (Disabled / Shop+Stripe / Paymenter)
 * — only the `pelican_webhook_enabled` toggle gates it.
 *
 * Hard rule : respond fast. Pelican does NOT retry on failure. We :
 *  1. Compute idempotency hash + dedupe via `pelican_processed_events`
 *  2. Classify the event via PelicanEventClassifier → PelicanEventKind
 *  3. Dispatch the matching sync job via PelicanWebhookDispatcher
 *  4. Always respond 200 (Pelican won't retry — bad responses just lose data)
 */
class PelicanWebhookController extends Controller
{
    public function __construct(
        private readonly PelicanEventClassifier $classifier,
        private readonly PelicanWebhookDispatcher $dispatcher,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $request->attributes->get('pelican.event');

        if (! is_array($payload)) {
            return response()->json(['error' => 'no_event'], 500);
        }

        $eventType = (string) $request->header('X-Webhook-Event', '');
        if ($eventType === '') {
            $eventType = $this->extractEventType($payload);
        }

        $data = $this->extractData($payload);
        $modelId = (int) ($data['id'] ?? 0);

        if ($eventType === '' || $modelId === 0) {
            Log::warning('Pelican webhook: missing event type or model id', [
                'event_type' => $eventType,
                'model_id' => $modelId,
                'keys' => array_keys($payload),
            ]);
            return response()->json(['received' => true, 'skipped' => 'missing_fields'], 200);
        }

        $hash = $this->computeIdempotencyHash($eventType, $modelId, $data, $request->getContent());

        if (PelicanProcessedEvent::where('idempotency_hash', $hash)->exists()) {
            return response()->json(['received' => true, 'idempotent' => true], 200);
        }

        if (config('app.debug')) {
            Log::debug('Pelican webhook payload received', [
                'event_type' => $eventType,
                'model_id' => $modelId,
                'data_keys' => array_keys($data),
                'data' => $data,
            ]);
        }

        $kind = $this->classifier->classify($eventType);
        $responseStatus = 200;
        $errorMessage = null;
        $payloadSummary = null;

        try {
            $payloadSummary = $this->dispatcher->dispatchByKind($kind, $eventType, $modelId, $data);
        } catch (\Throwable $e) {
            $errorMessage = Str::limit($e->getMessage(), 900);
            Log::error('Pelican webhook handler failed', [
                'event_type' => $eventType,
                'event_kind' => $kind->value,
                'model_id' => $modelId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            // Stay 200 — Pelican won't retry, the polling reconciliation
            // job will re-sync this server on the next tick.
        }

        PelicanProcessedEvent::create([
            'idempotency_hash' => $hash,
            'event_type' => $eventType,
            'pelican_model_id' => $modelId,
            'payload_summary' => $payloadSummary,
            'response_status' => $responseStatus,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);

        return response()->json(['received' => true], $responseStatus);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventType(array $payload): string
    {
        return (string) ($payload['event'] ?? $payload['event_type'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractData(array $payload): array
    {
        $data = $payload['payload'] ?? $payload['data'] ?? $payload['model'] ?? null;

        if (is_array($data)) {
            return $data;
        }

        // Custom Pelican events (e.g. App\Events\Server\Installed) ship the
        // model nested under its kind key instead of `data`/`model`.
        foreach (['server', 'user', 'node'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        if (isset($payload['id'])) {
            return $payload;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function computeIdempotencyHash(string $eventType, int $modelId, array $data, string $rawBody): string
    {
        $updatedAt = (string) ($data['updated_at'] ?? '');
        $bodyDigest = hash('sha256', $rawBody);

        return hash('sha256', $eventType.'|'.$modelId.'|'.$updatedAt.'|'.$bodyDigest);
    }
}
