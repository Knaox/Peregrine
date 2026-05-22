import { create } from 'zustand';

export type ActivityKind = 'upload' | 'compress' | 'decompress' | 'pull';

/**
 * Global file-activity indicator, driving the floating UploadProgressWidget.
 *
 * - `upload` is determinate (real % via XHR) and survives client-side
 *   navigation between server tabs; a hard reload still cancels the XHR, which
 *   is why the widget guards `beforeunload` for this kind only.
 * - `compress` / `decompress` / `pull` are indeterminate: Pelican exposes no
 *   progress for them, so we only signal "busy" while the operation runs.
 *   Reloading the page during these does NOT abort the server-side work, so
 *   they intentionally do not trigger the unload guard.
 */
interface FileActivityState {
    active: boolean;
    kind: ActivityKind;
    /** Only meaningful for the determinate `upload` kind. */
    percent: number;
    fileCount: number;
    directory: string;
    error: string | null;
    startUpload: (fileCount: number, directory: string) => void;
    setPercent: (percent: number) => void;
    startBusy: (kind: Exclude<ActivityKind, 'upload'>) => void;
    finish: () => void;
    fail: (error: string) => void;
    reset: () => void;
}

export const useUploadStore = create<FileActivityState>((set) => ({
    active: false,
    kind: 'upload',
    percent: 0,
    fileCount: 0,
    directory: '/',
    error: null,
    startUpload: (fileCount, directory) =>
        set({ active: true, kind: 'upload', percent: 0, fileCount, directory, error: null }),
    setPercent: (percent) => set({ percent: Math.min(100, Math.round(percent)) }),
    startBusy: (kind) => set({ active: true, kind, percent: 0, error: null }),
    finish: () => set({ active: false, percent: 100 }),
    fail: (error) => set({ active: false, error }),
    reset: () => set({ active: false, percent: 0, fileCount: 0, error: null }),
}));
