<?php

namespace App\Services\Bridge;

use App\Enums\PelicanEventKind;
use App\Jobs\Bridge\SyncEggFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncNodeFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncUserFromPelicanWebhookJob;

/**
 * Maps a classified Pelican webhook event to its sync job dispatch.
 *
 * Scope after the mirror feature was removed : the dispatcher only
 * handles the four CORE Pelican models (Server / User / Node / Egg +
 * EggVariable). Per-server resources (Backup / Allocation / Database /
 * DatabaseHost / ServerTransfer / Subuser) are NOT mirrored anymore —
 * the SPA reads them live from Pelican on demand. Any of those events
 * still ticked in `/admin/webhooks` Pelican falls into `ignored` and
 * is recorded for audit only.
 *
 * Each method returns an audit summary the controller persists to
 * `pelican_processed_events`.
 */
final class PelicanWebhookDispatcher
{
    public function __construct(
        private readonly BridgeModeService $bridgeMode,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function dispatchByKind(PelicanEventKind $kind, string $eventType, int $modelId, array $data): ?array
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
            default => $this->ignored($eventType),
        };
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function dispatchEggVariableSync(PelicanEventKind $kind, array $data, string $eventType): array
    {
        $pelicanEggId = (int) ($data['egg_id'] ?? 0);
        if ($pelicanEggId === 0) {
            return ['ignored' => 'egg_variable_missing_egg_id', 'type' => $eventType];
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

    /** @return array<string, mixed> */
    private function ignored(string $eventType): array
    {
        return ['ignored' => 'unsupported_event_type', 'type' => $eventType];
    }
}
