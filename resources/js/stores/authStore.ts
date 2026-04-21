import { create } from 'zustand';
import i18n from '@/i18n/config';
import { useThemeModeStore } from '@/stores/themeModeStore';
import type { User } from '@/types/User';
import { fetchCurrentUser, login as apiLogin, logout as apiLogout, register as apiRegister } from '@/services/api';
import { twoFactorChallenge } from '@/services/authApi';

interface AuthState {
    user: User | null;
    isLoading: boolean;
    isAuthenticated: boolean;
    pendingChallengeId: string | null;
    loadUser: () => Promise<void>;
    /**
     * Resolves to { requires2fa: true, challengeId } when the server defers
     * to the 2FA challenge page, otherwise sets the user as authenticated.
     */
    login: (
        email: string,
        password: string,
        remember?: boolean,
    ) => Promise<{ requires2fa: true; challengeId: string } | { requires2fa: false }>;
    register: (data: { name: string; email: string; password: string; password_confirmation: string }) => Promise<void>;
    submitChallenge: (code: string) => Promise<string>;
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

export const useAuthStore = create<AuthState>((set, get) => ({
    user: null,
    isLoading: true,
    isAuthenticated: false,
    pendingChallengeId: null,

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
        const response = await apiLogin(email, password, remember);

        if (response.requires_2fa === true) {
            set({ pendingChallengeId: response.challenge_id, user: null, isAuthenticated: false });
            return { requires2fa: true, challengeId: response.challenge_id };
        }

        applyLocale(response.user);
        applyThemeMode(response.user);
        set({ user: response.user, isAuthenticated: true, pendingChallengeId: null });
        return { requires2fa: false };
    },

    register: async (data) => {
        const { user } = await apiRegister(data);
        applyLocale(user);
        applyThemeMode(user);
        set({ user, isAuthenticated: true });
    },

    submitChallenge: async (code) => {
        const challengeId = get().pendingChallengeId;
        if (!challengeId) {
            throw new Error('No pending 2FA challenge.');
        }
        const { user, redirect_url } = await twoFactorChallenge(challengeId, code);
        applyLocale(user);
        applyThemeMode(user);
        set({ user, isAuthenticated: true, pendingChallengeId: null });
        return redirect_url;
    },

    logout: async () => {
        await apiLogout();
        set({ user: null, isAuthenticated: false, pendingChallengeId: null });
    },
}));
