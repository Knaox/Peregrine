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
    mode: 'local' | 'oauth';
    oauth_client_id: string;
    oauth_client_secret: string;
    oauth_authorize_url: string;
    oauth_token_url: string;
    oauth_user_url: string;
}

export interface BridgeConfig {
    enabled: boolean;
    stripe_webhook_secret: string;
}

export interface SetupState {
    locale: string;
    database: DatabaseConfig;
    admin: AdminConfig;
    pelican: PelicanConfig;
    auth: AuthConfig;
    bridge: BridgeConfig;
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
