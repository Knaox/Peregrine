<?php

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncUserFromPelicanWebhookJob;
use App\Models\PelicanProcessedEvent;
use App\Services\Bridge\BridgeModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives outgoing webhooks from Pelican Panel (Bridge Paymenter mode).
 *
 * Pelican fires events on Eloquent model changes (create / update / delete)
 * and on a few custom events like `App\Events\Server\Installed`. Our job
 * here is to mirror those changes into the local Peregrine DB so the
 * customer dashboard reflects what Paymenter / Pelican has done.
 *
 * Hard rule : respond fast (Pelican does NOT retry on failure). Controller
 * only computes idempotency, dispatches the queued job, and records the
 * event. No synchronous Pelican calls.
 *
 * Idempotency strategy : Pelican does not provide an event_id. We derive
 *   sha256(event_type | model_id | updated_at | body_hash)
 * and check that against `pelican_processed_events`. Same physical event
 * re-emitted (e.g. admin re-saves the webhook config) is deduplicated.
 *
 * Response codes :
 *   200 — event accepted (dispatched OR already processed OR explicitly skipped)
 *   422 — payload missing critical fields (still 200 effectively to avoid
 *         the admin chasing ghost retries — Pelican won't retry anyway)
 */
class PelicanWebhookController extends Controller
{
    public function __construct(
        private readonly BridgeModeService $bridgeMode,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $request->attributes->get('pelican.event');

        if (! is_array($payload)) {
            return response()->json(['error' => 'no_event'], 500);
        }

        // Pelican fills X-Webhook-Event with the {{event}} template — that's
        // the most reliable source. Fall back to the body for resilience.
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

        // Debug-only : full payload dump for diagnosing field shape mismatches
        // (e.g. `egg_id` vs `egg` vs nested relation). Gated on APP_DEBUG so
        // production logs stay clean.
        if (config('app.debug')) {
            Log::debug('Pelican webhook payload received', [
                'event_type' => $eventType,
                'model_id' => $modelId,
                'data_keys' => array_keys($data),
                'data' => $data,
            ]);
        }

        $responseStatus = 200;
        $errorMessage = null;
        $payloadSummary = null;

        try {
            $payloadSummary = $this->dispatchByEventType($eventType, $modelId, $data);
        } catch (\Throwable $e) {
            $errorMessage = Str::limit($e->getMessage(), 900);
            Log::error('Pelican webhook handler failed', [
                'event_type' => $eventType,
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function dispatchByEventType(string $eventType, int $modelId, array $data): ?array
    {
        return match (true) {
            $this->isServerEvent($eventType) => $this->dispatchServerSync($eventType, $modelId, $data),
            $this->isUserCreatedEvent($eventType) => $this->dispatchUserSyncIfAllowed($modelId),
            default => $this->ignored($eventType),
        };
    }

    private function isServerEvent(string $eventType): bool
    {
        // Pelican's webhook UI uses two formats interchangeably :
        //   short form (UI label)  : "created: Server", "updated: Server",
        //                            "deleted: Server", "event: Server\Installed"
        //   long form (legacy/raw) : "eloquent.created: App\Models\Server",
        //                            "App\Events\Server\Installed"
        // We normalise spacing + double-escaped backslashes, then match on
        // both shapes.
        $normalized = str_replace([' ', '\\\\'], ['', '\\'], $eventType);

        return str_contains($normalized, 'App\\Models\\Server')
            || str_contains($normalized, 'App\\Events\\Server\\')
            || str_ends_with($normalized, ':Server')
            || str_starts_with($normalized, 'event:Server\\');
    }

    private function isUserCreatedEvent(string $eventType): bool
    {
        $normalized = str_replace([' ', '\\\\'], ['', '\\'], $eventType);

        return $normalized === 'created:User'
            || str_contains($normalized, 'eloquent.created:App\\Models\\User');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchServerSync(string $eventType, int $modelId, array $data): array
    {
        SyncServerFromPelicanWebhookJob::dispatch(
            eventType: $eventType,
            pelicanServerId: $modelId,
            payloadSnapshot: $data,
        );

        return [
            'dispatched' => 'SyncServerFromPelicanWebhookJob',
            'pelican_server_id' => $modelId,
            'event_type' => $eventType,
        ];
    }

    /**
     * Skip user-created events in shop_stripe mode — users are created by
     * the Stripe / OAuth flow, not Pelican. Letting Pelican create users
     * out of band would produce ghost accounts with no Shop link. Updates
     * are still allowed via the dedicated `eloquent.updated:User` path
     * (handled by SyncServerFromPelicanWebhookJob's owner backfill).
     *
     * @return array<string, mixed>
     */
    private function dispatchUserSyncIfAllowed(int $modelId): array
    {
        if ($this->bridgeMode->isShopStripe()) {
            return [
                'ignored' => 'user_creation_disabled_in_shop_stripe_mode',
                'pelican_user_id' => $modelId,
            ];
        }

        SyncUserFromPelicanWebhookJob::dispatch(pelicanUserId: $modelId);

        return [
            'dispatched' => 'SyncUserFromPelicanWebhookJob',
            'pelican_user_id' => $modelId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ignored(string $eventType): array
    {
        return ['ignored' => 'unsupported_event_type', 'type' => $eventType];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventType(array $payload): string
    {
        // Pelican's webhook payload uses the `event` key by convention.
        // Defensive fallbacks for variant payload schemas.
        return (string) ($payload['event'] ?? $payload['event_type'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractData(array $payload): array
    {
        // Pelican Eloquent webhooks ship the model under `payload`. Older /
        // custom payloads use `data` or `model`. If none of those, fall back
        // to the root payload itself (the model fields are top-level).
        $data = $payload['payload'] ?? $payload['data'] ?? $payload['model'] ?? null;

        if (is_array($data)) {
            return $data;
        }

        // Last resort: the raw payload IS the model snapshot (no envelope).
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
