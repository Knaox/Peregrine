import { create } from 'zustand';
import i18n from '@/i18n/config';
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

export const useAuthStore = create<AuthState>((set) => ({
    user: null,
    isLoading: true,
    isAuthenticated: false,

    loadUser: async () => {
        try {
            const { user } = await fetchCurrentUser();
            applyLocale(user);
            set({ user, isAuthenticated: !!user, isLoading: false });
        } catch {
            set({ user: null, isAuthenticated: false, isLoading: false });
        }
    },

    login: async (email, password, remember = false) => {
        const { user } = await apiLogin(email, password, remember);
        applyLocale(user);
        set({ user, isAuthenticated: true });
    },

    register: async (data) => {
        const { user } = await apiRegister(data);
        applyLocale(user);
        set({ user, isAuthenticated: true });
    },

    logout: async () => {
        await apiLogout();
        set({ user: null, isAuthenticated: false });
    },
}));
