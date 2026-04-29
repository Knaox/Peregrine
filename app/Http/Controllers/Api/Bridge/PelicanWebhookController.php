<?php

namespace App\Http\Controllers\Api\Bridge;

use App\Enums\PelicanEventKind;
use App\Http\Controllers\Controller;
use App\Jobs\Bridge\SyncAllocationFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncBackupFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncDatabaseFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncDatabaseHostFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncEggFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncNodeFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncServerTransferFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncUserFromPelicanWebhookJob;
use App\Models\PelicanProcessedEvent;
use App\Services\Bridge\BridgeModeService;
use App\Services\Bridge\PelicanEventClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives outgoing webhooks from Pelican Panel.
 *
 * Pelican fires events on Eloquent model changes (create / update / delete)
 * and on a few custom events like `App\Events\Server\Installed`. Our job
 * here is to mirror those changes into the local Peregrine DB so the
 * customer dashboard reflects what Paymenter / Pelican has done.
 *
 * Receiver works in ALL bridge modes (Disabled / Shop+Stripe / Paymenter):
 * the `pelican_webhook_enabled` toggle is the only gate.
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
 * Event classification: PelicanEventClassifier turns the raw event string
 * into a typed PelicanEventKind. The controller then dispatches via a
 * flat `match` per kind. Unknown kinds are recorded as `Ignored`.
 *
 * Response codes :
 *   200 — event accepted (dispatched OR already processed OR explicitly skipped)
 */
class PelicanWebhookController extends Controller
{
    public function __construct(
        private readonly BridgeModeService $bridgeMode,
        private readonly PelicanEventClassifier $classifier,
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
            $payloadSummary = $this->dispatchByKind($kind, $eventType, $modelId, $data);
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function dispatchByKind(PelicanEventKind $kind, string $eventType, int $modelId, array $data): ?array
    {
        return match (true) {
            $kind->isServer() => $this->dispatchServerSync($eventType, $modelId, $data),
            $kind === PelicanEventKind::UserCreated => $this->dispatchUserCreated($modelId),
            $kind === PelicanEventKind::UserUpdated, $kind === PelicanEventKind::UserDeleted
                => $this->dispatchUserMutation($kind, $modelId),
            $kind === PelicanEventKind::NodeCreated,
            $kind === PelicanEventKind::NodeUpdated,
            $kind === PelicanEventKind::NodeDeleted
                => $this->dispatchNodeSync($kind, $modelId),
            $kind === PelicanEventKind::EggCreated,
            $kind === PelicanEventKind::EggUpdated,
            $kind === PelicanEventKind::EggDeleted
                => $this->dispatchEggSync($kind, $modelId),
            $kind === PelicanEventKind::EggVariableCreated,
            $kind === PelicanEventKind::EggVariableUpdated,
            $kind === PelicanEventKind::EggVariableDeleted
                => $this->dispatchEggVariableSync($kind, $data, $eventType),
            $kind === PelicanEventKind::BackupCreated,
            $kind === PelicanEventKind::BackupUpdated,
            $kind === PelicanEventKind::BackupDeleted
                => $this->dispatchBackupSync($kind, $modelId, $data),
            $kind === PelicanEventKind::AllocationCreated,
            $kind === PelicanEventKind::AllocationUpdated,
            $kind === PelicanEventKind::AllocationDeleted
                => $this->dispatchAllocationSync($kind, $modelId, $data),
            $kind === PelicanEventKind::DatabaseCreated,
            $kind === PelicanEventKind::DatabaseUpdated,
            $kind === PelicanEventKind::DatabaseDeleted
                => $this->dispatchDatabaseSync($kind, $modelId, $data),
            $kind === PelicanEventKind::DatabaseHostCreated,
            $kind === PelicanEventKind::DatabaseHostUpdated,
            $kind === PelicanEventKind::DatabaseHostDeleted
                => $this->dispatchDatabaseHostSync($kind, $modelId, $data),
            $kind === PelicanEventKind::ServerTransferCreated,
            $kind === PelicanEventKind::ServerTransferUpdated,
            $kind === PelicanEventKind::ServerTransferDeleted
                => $this->dispatchServerTransferSync($kind, $modelId, $data),
            $kind === PelicanEventKind::SubuserAdded,
            $kind === PelicanEventKind::SubuserRemoved
                => $this->dispatchSubuserSync($kind, $modelId, $data),
            $kind === PelicanEventKind::Ignored => $this->ignored($eventType),
            default => $this->ignored($eventType),
        };
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
     * out of band would produce ghost accounts with no Shop link.
     *
     * @return array<string, mixed>
     */
    private function dispatchUserCreated(int $modelId): array
    {
        if ($this->bridgeMode->isShopStripe()) {
            return [
                'ignored' => 'user_creation_disabled_in_shop_stripe_mode',
                'pelican_user_id' => $modelId,
            ];
        }

        SyncUserFromPelicanWebhookJob::dispatch(
            pelicanUserId: $modelId,
            eventKind: PelicanEventKind::UserCreated,
        );

        return [
            'dispatched' => 'SyncUserFromPelicanWebhookJob',
            'pelican_user_id' => $modelId,
            'event_kind' => PelicanEventKind::UserCreated->value,
        ];
    }

    /**
     * Dispatches user update / delete mirroring. Runs in ALL bridge modes —
     * if Pelican changes a user (admin email change, deletion in panel), we
     * mirror locally regardless of whether Shop or Paymenter owns the user.
     *
     * @return array<string, mixed>
     */
    private function dispatchUserMutation(PelicanEventKind $kind, int $modelId): array
    {
        SyncUserFromPelicanWebhookJob::dispatch(
            pelicanUserId: $modelId,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncUserFromPelicanWebhookJob',
            'pelican_user_id' => $modelId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * Dispatches node create / update / delete mirroring. Runs in ALL
     * bridge modes — node infrastructure is mode-agnostic.
     *
     * @return array<string, mixed>
     */
    private function dispatchNodeSync(PelicanEventKind $kind, int $modelId): array
    {
        SyncNodeFromPelicanWebhookJob::dispatch(
            pelicanNodeId: $modelId,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncNodeFromPelicanWebhookJob',
            'pelican_node_id' => $modelId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * Dispatches egg create / update / delete mirroring. Runs in ALL modes.
     *
     * @return array<string, mixed>
     */
    private function dispatchEggSync(PelicanEventKind $kind, int $pelicanEggId): array
    {
        SyncEggFromPelicanWebhookJob::dispatch(
            pelicanEggId: $pelicanEggId,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncEggFromPelicanWebhookJob',
            'pelican_egg_id' => $pelicanEggId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * EggVariable mutations resync the PARENT egg (the variable lives inside
     * the egg payload returned by `getEgg`). The modelId in the payload is
     * the variable id, but we need the egg_id field instead.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchEggVariableSync(PelicanEventKind $kind, array $data, string $eventType): array
    {
        $pelicanEggId = (int) ($data['egg_id'] ?? 0);
        if ($pelicanEggId === 0) {
            return [
                'ignored' => 'egg_variable_missing_egg_id',
                'type' => $eventType,
            ];
        }

        SyncEggFromPelicanWebhookJob::dispatch(
            pelicanEggId: $pelicanEggId,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncEggFromPelicanWebhookJob',
            'pelican_egg_id' => $pelicanEggId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchBackupSync(PelicanEventKind $kind, int $pelicanBackupId, array $data): array
    {
        $pelicanServerId = (int) ($data['server_id'] ?? 0);
        if ($pelicanServerId === 0) {
            return ['ignored' => 'backup_missing_server_id', 'pelican_backup_id' => $pelicanBackupId];
        }

        SyncBackupFromPelicanWebhookJob::dispatch(
            pelicanBackupId: $pelicanBackupId,
            pelicanServerId: $pelicanServerId,
            payload: $data,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncBackupFromPelicanWebhookJob',
            'pelican_backup_id' => $pelicanBackupId,
            'pelican_server_id' => $pelicanServerId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchAllocationSync(PelicanEventKind $kind, int $pelicanAllocationId, array $data): array
    {
        SyncAllocationFromPelicanWebhookJob::dispatch(
            pelicanAllocationId: $pelicanAllocationId,
            payload: $data,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncAllocationFromPelicanWebhookJob',
            'pelican_allocation_id' => $pelicanAllocationId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchDatabaseSync(PelicanEventKind $kind, int $pelicanDatabaseId, array $data): array
    {
        $pelicanServerId = (int) ($data['server_id'] ?? 0);
        if ($pelicanServerId === 0) {
            return ['ignored' => 'database_missing_server_id', 'pelican_database_id' => $pelicanDatabaseId];
        }

        SyncDatabaseFromPelicanWebhookJob::dispatch(
            pelicanDatabaseId: $pelicanDatabaseId,
            pelicanServerId: $pelicanServerId,
            payload: $data,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncDatabaseFromPelicanWebhookJob',
            'pelican_database_id' => $pelicanDatabaseId,
            'pelican_server_id' => $pelicanServerId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchDatabaseHostSync(PelicanEventKind $kind, int $pelicanDatabaseHostId, array $data): array
    {
        SyncDatabaseHostFromPelicanWebhookJob::dispatch(
            pelicanDatabaseHostId: $pelicanDatabaseHostId,
            payload: $data,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncDatabaseHostFromPelicanWebhookJob',
            'pelican_database_host_id' => $pelicanDatabaseHostId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchServerTransferSync(PelicanEventKind $kind, int $pelicanTransferId, array $data): array
    {
        SyncServerTransferFromPelicanWebhookJob::dispatch(
            pelicanServerTransferId: $pelicanTransferId,
            payload: $data,
            eventKind: $kind,
        );

        return [
            'dispatched' => 'SyncServerTransferFromPelicanWebhookJob',
            'pelican_server_transfer_id' => $pelicanTransferId,
            'event_kind' => $kind->value,
        ];
    }

    /**
     * Subuser events fire a domain event for the invitations plugin to react.
     * Core does NOT persist subusers — the plugin owns that table.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dispatchSubuserSync(PelicanEventKind $kind, int $pelicanSubuserId, array $data): array
    {
        \App\Jobs\Bridge\DispatchSubuserSyncedJob::dispatch(
            eventKind: $kind,
            pelicanSubuserId: $pelicanSubuserId,
            payload: $data,
        );

        return [
            'dispatched' => 'DispatchSubuserSyncedJob',
            'pelican_subuser_id' => $pelicanSubuserId,
            'event_kind' => $kind->value,
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
