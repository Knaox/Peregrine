export type AuthProviderId = 'shop' | 'google' | 'discord' | 'linkedin';

export interface AuthProvider {
    id: AuthProviderId;
    enabled: boolean;
    redirect_url: string;
    /** True for the Shop — the canonical identity provider. */
    canonical: boolean;
}

export interface AuthProvidersResponse {
    providers: AuthProvider[];
    local_enabled: boolean;
    local_registration_enabled: boolean;
}

export interface LinkedIdentity {
    provider: AuthProviderId;
    provider_email: string;
    last_login_at: string | null;
    created_at: string | null;
}

export interface LinkedIdentitiesResponse {
    data: LinkedIdentity[];
    /**
     * Plan §S7: false when the user's only remaining login method is a
     * single linked identity with no password. Frontend greys out unlink
     * buttons accordingly.
     */
    can_unlink_any: boolean;
}
