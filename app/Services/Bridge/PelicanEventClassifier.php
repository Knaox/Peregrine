<?php

namespace App\Services\Bridge;

use App\Enums\PelicanEventKind;

/**
 * Classifies Pelican webhook event strings into a typed PelicanEventKind.
 *
 * Pelican emits two interchangeable label shapes for the same event:
 *   short (UI)  : "created: Server", "updated: User"
 *   long (raw)  : "eloquent.created: App\Models\Server", "eloquent.updated: App\Models\User"
 *
 * Custom events use a different shape:
 *   short (UI)  : "event: Server\Installed"
 *   long (raw)  : "App\Events\Server\Installed"
 *
 * The classifier normalises whitespace and double-escaped backslashes
 * (Pelican's payload sometimes ships "App\\Models\\Server"), then matches
 * against a curated list. Unknown inputs return PelicanEventKind::Ignored —
 * the receiver still logs the event in pelican_processed_events for audit.
 */
class PelicanEventClassifier
{
    public function classify(string $eventType): PelicanEventKind
    {
        $normalized = $this->normalize($eventType);

        return $this->matchCustomEvent($normalized)
            ?? $this->matchEloquentModelEvent($normalized)
            ?? PelicanEventKind::Ignored;
    }

    private function normalize(string $eventType): string
    {
        return str_replace([' ', '\\\\'], ['', '\\'], $eventType);
    }

    private function matchCustomEvent(string $normalized): ?PelicanEventKind
    {
        // Short form ("event:Server\Installed") and long form
        // ("App\Events\Server\Installed") both end with the event class path.
        if (
            str_ends_with($normalized, 'Server\\Installed')
            || $normalized === 'event:Server\\Installed'
        ) {
            return PelicanEventKind::ServerInstalled;
        }

        if (
            str_ends_with($normalized, 'Server\\SubUserAdded')
            || $normalized === 'event:Server\\SubUserAdded'
        ) {
            return PelicanEventKind::SubuserAdded;
        }

        if (
            str_ends_with($normalized, 'Server\\SubUserRemoved')
            || $normalized === 'event:Server\\SubUserRemoved'
        ) {
            return PelicanEventKind::SubuserRemoved;
        }

        return null;
    }

    private function matchEloquentModelEvent(string $normalized): ?PelicanEventKind
    {
        $action = $this->extractAction($normalized);
        if ($action === null) {
            return null;
        }

        $modelKey = $this->extractModelKey($normalized);
        if ($modelKey === null) {
            return null;
        }

        return match ($modelKey) {
            'Server' => match ($action) {
                'created' => PelicanEventKind::ServerCreated,
                'updated' => PelicanEventKind::ServerUpdated,
                'deleted' => PelicanEventKind::ServerDeleted,
                default => null,
            },
            'User' => match ($action) {
                'created' => PelicanEventKind::UserCreated,
                'updated' => PelicanEventKind::UserUpdated,
                'deleted' => PelicanEventKind::UserDeleted,
                default => null,
            },
            'Node' => match ($action) {
                'created' => PelicanEventKind::NodeCreated,
                'updated' => PelicanEventKind::NodeUpdated,
                'deleted' => PelicanEventKind::NodeDeleted,
                default => null,
            },
            'Egg' => match ($action) {
                'created' => PelicanEventKind::EggCreated,
                'updated' => PelicanEventKind::EggUpdated,
                'deleted' => PelicanEventKind::EggDeleted,
                default => null,
            },
            'EggVariable' => match ($action) {
                'created' => PelicanEventKind::EggVariableCreated,
                'updated' => PelicanEventKind::EggVariableUpdated,
                'deleted' => PelicanEventKind::EggVariableDeleted,
                default => null,
            },
            'Backup' => match ($action) {
                'created' => PelicanEventKind::BackupCreated,
                'updated' => PelicanEventKind::BackupUpdated,
                'deleted' => PelicanEventKind::BackupDeleted,
                default => null,
            },
            'Allocation' => match ($action) {
                'created' => PelicanEventKind::AllocationCreated,
                'updated' => PelicanEventKind::AllocationUpdated,
                'deleted' => PelicanEventKind::AllocationDeleted,
                default => null,
            },
            'Database' => match ($action) {
                'created' => PelicanEventKind::DatabaseCreated,
                'updated' => PelicanEventKind::DatabaseUpdated,
                'deleted' => PelicanEventKind::DatabaseDeleted,
                default => null,
            },
            'DatabaseHost' => match ($action) {
                'created' => PelicanEventKind::DatabaseHostCreated,
                'updated' => PelicanEventKind::DatabaseHostUpdated,
                'deleted' => PelicanEventKind::DatabaseHostDeleted,
                default => null,
            },
            'ServerTransfer' => match ($action) {
                'created' => PelicanEventKind::ServerTransferCreated,
                'updated' => PelicanEventKind::ServerTransferUpdated,
                'deleted' => PelicanEventKind::ServerTransferDeleted,
                default => null,
            },
            'Subuser' => match ($action) {
                // Eloquent CRUD on Subuser. We map both UI custom events
                // (Server\SubUserAdded/Removed) and these eloquent events
                // to the same kind to avoid duplicate processing.
                'created' => PelicanEventKind::SubuserAdded,
                'deleted' => PelicanEventKind::SubuserRemoved,
                default => null,
            },
            default => null,
        };
    }

    /**
     * Pulls "created" / "updated" / "deleted" out of either:
     *   "eloquent.created:App\Models\Server"
     *   "created:Server"
     */
    private function extractAction(string $normalized): ?string
    {
        if (preg_match('/^eloquent\.(created|updated|deleted):/', $normalized, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^(created|updated|deleted):/', $normalized, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Resolves the model short name from either:
     *   "eloquent.created:App\Models\Server"  -> "Server"
     *   "created:Server"                       -> "Server"
     */
    private function extractModelKey(string $normalized): ?string
    {
        $colon = strpos($normalized, ':');
        if ($colon === false) {
            return null;
        }

        $right = substr($normalized, $colon + 1);

        // Long form: strip "App\Models\" prefix.
        if (str_starts_with($right, 'App\\Models\\')) {
            $right = substr($right, strlen('App\\Models\\'));
        }

        // Reject anything that still contains a backslash — only known
        // custom events (handled separately) use namespaces post-prefix.
        if (str_contains($right, '\\')) {
            return null;
        }

        return $right === '' ? null : $right;
    }
}
