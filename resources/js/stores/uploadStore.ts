import { create } from 'zustand';

/**
 * Global upload state. Lives outside the Files page component so an in-flight
 * upload — and its progress bar — survives client-side navigation between
 * server tabs (Console, Backups…). A hard page reload (F5) still cancels the
 * transfer: the browser tears down the document and the XHR with it, which is
 * why AppLayout also wires a `beforeunload` guard while `isUploading` is true.
 */
interface UploadState {
    isUploading: boolean;
    /** Aggregate progress across all files in the batch, 0–100. */
    percent: number;
    fileCount: number;
    /** Directory the batch is being uploaded into (shown in the widget). */
    directory: string;
    error: string | null;
    start: (fileCount: number, directory: string) => void;
    setPercent: (percent: number) => void;
    finish: () => void;
    fail: (error: string) => void;
    reset: () => void;
}

export const useUploadStore = create<UploadState>((set) => ({
    isUploading: false,
    percent: 0,
    fileCount: 0,
    directory: '/',
    error: null,
    start: (fileCount, directory) =>
        set({ isUploading: true, percent: 0, fileCount, directory, error: null }),
    setPercent: (percent) => set({ percent: Math.min(100, Math.round(percent)) }),
    finish: () => set({ isUploading: false, percent: 100 }),
    fail: (error) => set({ isUploading: false, error }),
    reset: () => set({ isUploading: false, percent: 0, fileCount: 0, error: null }),
}));
