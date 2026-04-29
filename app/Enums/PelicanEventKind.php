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
        ], true);
    }
}
