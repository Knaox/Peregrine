export interface DatabaseConfig {
    host: string;
    port: number;
    database: string;
    username: string;
    password: string;
    /** When true, Peregrine drops every table before migrating. Use when the
     *  selected DB still has leftovers from a previous install. */
    fresh?: boolean;
}

export interface AdminConfig {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface PelicanConfig {
    url: string;
    api_key: string;
    client_api_key: string;
}

export interface AuthConfig {
    /**
     * When true, new users can register locally via /register with an
     * email + password. When false, account creation is closed — new users
     * must arrive via an OAuth provider configured post-install in
     * /admin/auth-settings.
     */
    allow_local_registration: boolean;
}

export interface SetupState {
    locale: string;
    database: DatabaseConfig;
    admin: AdminConfig;
    pelican: PelicanConfig;
    auth: AuthConfig;
}

export type ConnectionTestStatus = 'idle' | 'testing' | 'success' | 'error';

export interface ConnectionTestResult {
    status: ConnectionTestStatus;
    error?: string;
}

export interface StepProps {
    data: SetupState;
    onChange: (updates: Partial<SetupState>) => void;
    onNext: () => void;
    onPrevious: () => void;
}
