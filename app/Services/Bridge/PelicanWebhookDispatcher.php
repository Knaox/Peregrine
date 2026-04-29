<?php

namespace App\Services\Bridge;

use App\Enums\PelicanEventKind;
use App\Jobs\Bridge\DispatchSubuserSyncedJob;
use App\Jobs\Bridge\SyncAllocationFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncBackupFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncDatabaseFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncDatabaseHostFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncEggFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncNodeFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncServerTransferFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncUserFromPelicanWebhookJob;

/**
 * Maps a classified Pelican webhook event to its sync job dispatch.
 *
 * Extracted from PelicanWebhookController so the controller stays focused
 * on idempotence + payload extraction + audit recording. Each method
 * returns an audit summary the controller persists to
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function dispatchSubuserSync(PelicanEventKind $kind, int $pelicanSubuserId, array $data): array
    {
        DispatchSubuserSyncedJob::dispatch(
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

    /** @return array<string, mixed> */
    private function ignored(string $eventType): array
    {
        return ['ignored' => 'unsupported_event_type', 'type' => $eventType];
    }
}
