import { create } from 'zustand';

export type ThemeMode = 'auto' | 'light' | 'dark';
export type EffectiveMode = 'light' | 'dark';

interface ThemeModeState {
    mode: ThemeMode;
    effective: EffectiveMode;
    setMode: (mode: ThemeMode) => void;
}

const SYSTEM_QUERY = '(prefers-color-scheme: light)';

function systemMode(): EffectiveMode {
    if (typeof window === 'undefined' || !window.matchMedia) return 'dark';
    return window.matchMedia(SYSTEM_QUERY).matches ? 'light' : 'dark';
}

function resolve(mode: ThemeMode): EffectiveMode {
    if (mode === 'light' || mode === 'dark') return mode;
    return systemMode();
}

// Boot value: prefer the server-rendered value (window.__THEME_MODE__)
// because it already matches the DB and was used to SSR the CSS vars.
// Falls back to localStorage for guests, then 'auto'.
function bootMode(): ThemeMode {
    if (typeof window === 'undefined') return 'auto';
    const fromServer = (window as unknown as { __THEME_MODE__?: string }).__THEME_MODE__;
    if (fromServer === 'light' || fromServer === 'dark' || fromServer === 'auto') return fromServer;
    const saved = window.localStorage.getItem('theme_mode');
    if (saved === 'light' || saved === 'dark' || saved === 'auto') return saved;
    return 'auto';
}

const initialMode = bootMode();

export const useThemeModeStore = create<ThemeModeState>((set) => ({
    mode: initialMode,
    effective: resolve(initialMode),
    setMode: (mode) => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem('theme_mode', mode);
        }
        set({ mode, effective: resolve(mode) });
    },
}));

// Keep 'auto' in sync with the OS-level preference without requiring a reload.
if (typeof window !== 'undefined' && window.matchMedia) {
    const mq = window.matchMedia(SYSTEM_QUERY);
    const onChange = (): void => {
        const { mode, setMode } = useThemeModeStore.getState();
        if (mode === 'auto') setMode('auto');
    };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else mq.addListener(onChange); // older Safari fallback
}
