import { create } from 'zustand';
import i18n from '@/i18n/config';
import { useThemeModeStore } from '@/stores/themeModeStore';
import type { User } from '@/types/User';
import { fetchCurrentUser, login as apiLogin, logout as apiLogout, register as apiRegister } from '@/services/api';

interface AuthState {
    user: User | null;
    isLoading: boolean;
    isAuthenticated: boolean;
    loadUser: () => Promise<void>;
    login: (email: string, password: string, remember?: boolean) => Promise<void>;
    register: (data: { name: string; email: string; password: string; password_confirmation: string }) => Promise<void>;
    logout: () => Promise<void>;
}

/**
 * Push the user's saved locale into i18next so the whole UI follows the
 * profile setting — on login, on register, and on every /me refresh after
 * boot. Without this, the app would keep the browser/localStorage detection
 * even when the DB has a different preference.
 */
function applyLocale(user: User | null): void {
    if (!user?.locale) return;
    if (i18n.language === user.locale) return;
    void i18n.changeLanguage(user.locale);
}

/**
 * Sync the user's saved theme_mode into the theme store so the UI follows
 * the DB preference (across devices). Guest/no-preference falls back to
 * whatever the visitor picked on this device (localStorage) or 'auto'.
 */
function applyThemeMode(user: User | null): void {
    if (!user?.theme_mode) return;
    const store = useThemeModeStore.getState();
    if (store.mode === user.theme_mode) return;
    store.setMode(user.theme_mode);
}

export const useAuthStore = create<AuthState>((set) => ({
    user: null,
    isLoading: true,
    isAuthenticated: false,

    loadUser: async () => {
        try {
            const { user } = await fetchCurrentUser();
            applyLocale(user);
            applyThemeMode(user);
            set({ user, isAuthenticated: !!user, isLoading: false });
        } catch {
            set({ user: null, isAuthenticated: false, isLoading: false });
        }
    },

    login: async (email, password, remember = false) => {
        const { user } = await apiLogin(email, password, remember);
        applyLocale(user);
        applyThemeMode(user);
        set({ user, isAuthenticated: true });
    },

    register: async (data) => {
        const { user } = await apiRegister(data);
        applyLocale(user);
        applyThemeMode(user);
        set({ user, isAuthenticated: true });
    },

    logout: async () => {
        await apiLogout();
        set({ user: null, isAuthenticated: false });
    },
}));
