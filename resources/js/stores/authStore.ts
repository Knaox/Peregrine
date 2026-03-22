import { create } from 'zustand';
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

export const useAuthStore = create<AuthState>((set) => ({
    user: null,
    isLoading: true,
    isAuthenticated: false,

    loadUser: async () => {
        try {
            const { user } = await fetchCurrentUser();
            set({ user, isAuthenticated: !!user, isLoading: false });
        } catch {
            set({ user: null, isAuthenticated: false, isLoading: false });
        }
    },

    login: async (email, password, remember = false) => {
        const { user } = await apiLogin(email, password, remember);
        set({ user, isAuthenticated: true });
    },

    register: async (data) => {
        const { user } = await apiRegister(data);
        set({ user, isAuthenticated: true });
    },

    logout: async () => {
        await apiLogout();
        set({ user: null, isAuthenticated: false });
    },
}));
