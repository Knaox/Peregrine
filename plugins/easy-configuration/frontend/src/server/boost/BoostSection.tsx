import { useState } from 'react';
import { BoostDialog, type BoostableParam } from './BoostDialog';
import { BoostPanel } from './BoostPanel';
import type { Boost } from './useBoosts';

interface Props {
    serverId: number;
    boosts: Boost[];
    /** Parameters ticked in the editor — fed to a NEW boost. */
    selectedParams: BoostableParam[];
    selectedCount: number;
}

/**
 * Boost management surface (list + create + edit), kept OUTSIDE the config lock
 * so it stays usable while the server is running — scheduling a boost is a DB
 * operation, not a live file edit. Creating a boost still needs parameters
 * ticked in the (offline-only) editor; editing a pending boost works any time.
 * Dialogs are mounted on demand so they always open with fresh state.
 */
export function BoostSection({ serverId, boosts, selectedParams, selectedCount }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Boost | null>(null);

    return (
        <>
            <BoostPanel
                serverId={serverId}
                boosts={boosts}
                selectedCount={selectedCount}
                onNew={() => setCreateOpen(true)}
                onEdit={setEditing}
            />

            {createOpen && (
                <BoostDialog
                    open
                    serverId={serverId}
                    selected={selectedParams}
                    editing={null}
                    onClose={() => setCreateOpen(false)}
                    onSaved={() => setCreateOpen(false)}
                />
            )}

            {editing && (
                <BoostDialog
                    open
                    serverId={serverId}
                    selected={[]}
                    editing={editing}
                    onClose={() => setEditing(null)}
                    onSaved={() => setEditing(null)}
                />
            )}
        </>
    );
}
