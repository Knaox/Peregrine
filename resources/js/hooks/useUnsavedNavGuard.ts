import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useSaveCoordinatorStore, selectTotalDirty } from '@/stores/saveCoordinatorStore';

/**
 * Returns a click guard for in-app server-tab navigation. The app uses
 * `<BrowserRouter>` (not the data-router stack), so `useBlocker` is unavailable;
 * the closest the codebase guards in-app navigation is via explicit click
 * interception (cf. ThemeStudioPage's back button). When a save source holds
 * unsaved changes, the guard confirms before letting the navigation through.
 *
 * Reads the coordinator via `getState()` (no subscription) so the sidebar
 * doesn't re-render on every keystroke. Returns `true` when navigation may
 * proceed, `false` (and calls `preventDefault`) when the user cancels.
 */
export function useUnsavedNavGuard(): (event?: { preventDefault: () => void }) => boolean {
    const { t } = useTranslation('server-shell');

    return useCallback(
        (event) => {
            const dirty = selectTotalDirty(useSaveCoordinatorStore.getState());
            if (dirty > 0 && !window.confirm(t('save_bar.leave_confirm'))) {
                event?.preventDefault();
                return false;
            }
            return true;
        },
        [t],
    );
}
