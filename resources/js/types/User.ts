export type ThemeModePreference = 'auto' | 'light' | 'dark';

export interface User {
    id: number;
    name: string;
    email: string;
    locale: string;
    theme_mode: ThemeModePreference;
    is_admin: boolean;
    pelican_user_id: number | null;
    created_at: string;
}
