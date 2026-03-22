export interface User {
    id: number;
    name: string;
    email: string;
    locale: string;
    is_admin: boolean;
    pelican_user_id: number | null;
    created_at: string;
}
