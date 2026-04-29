<?php

namespace App\Enums;

/**
 * Discriminator for incoming Pelican webhook events.
 *
 * Pelican fires webhooks under two label shapes interchangeably:
 *   short (UI label)  : "created: Server", "updated: User", "event: Server\Installed"
 *   long (legacy raw) : "eloquent.created: App\Models\Server", "App\Events\Server\Installed"
 *
 * PelicanEventClassifier normalises whitespace + double-escaped backslashes,
 * then matches against this enum so the receiver dispatches via a flat
 * `match` instead of stacked `str_contains` checks.
 *
 * Kinds outside this list resolve to Ignored — the receiver still records
 * the event in pelican_processed_events for audit, just no job dispatch.
 */
enum PelicanEventKind: string
{
    case Ignored = 'ignored';

    case ServerCreated = 'server_created';
    case ServerUpdated = 'server_updated';
    case ServerDeleted = 'server_deleted';
    case ServerInstalled = 'server_installed';

    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case UserDeleted = 'user_deleted';

    case NodeCreated = 'node_created';
    case NodeUpdated = 'node_updated';
    case NodeDeleted = 'node_deleted';

    case EggCreated = 'egg_created';
    case EggUpdated = 'egg_updated';
    case EggDeleted = 'egg_deleted';

    case EggVariableCreated = 'egg_variable_created';
    case EggVariableUpdated = 'egg_variable_updated';
    case EggVariableDeleted = 'egg_variable_deleted';

    case BackupCreated = 'backup_created';
    case BackupUpdated = 'backup_updated';
    case BackupDeleted = 'backup_deleted';

    case AllocationCreated = 'allocation_created';
    case AllocationUpdated = 'allocation_updated';
    case AllocationDeleted = 'allocation_deleted';

    case DatabaseCreated = 'database_created';
    case DatabaseUpdated = 'database_updated';
    case DatabaseDeleted = 'database_deleted';

    case DatabaseHostCreated = 'database_host_created';
    case DatabaseHostUpdated = 'database_host_updated';
    case DatabaseHostDeleted = 'database_host_deleted';

    case ServerTransferCreated = 'server_transfer_created';
    case ServerTransferUpdated = 'server_transfer_updated';
    case ServerTransferDeleted = 'server_transfer_deleted';

    case SubuserAdded = 'subuser_added';
    case SubuserRemoved = 'subuser_removed';

    public function isServer(): bool
    {
        return in_array($this, [
            self::ServerCreated,
            self::ServerUpdated,
            self::ServerDeleted,
            self::ServerInstalled,
        ], true);
    }

    public function isDeletion(): bool
    {
        return in_array($this, [
            self::ServerDeleted,
            self::UserDeleted,
            self::NodeDeleted,
            self::EggDeleted,
            self::EggVariableDeleted,
            self::BackupDeleted,
            self::AllocationDeleted,
            self::DatabaseDeleted,
            self::DatabaseHostDeleted,
            self::ServerTransferDeleted,
            self::SubuserRemoved,
        ], true);
    }
}
