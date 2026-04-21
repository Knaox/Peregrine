export type AuthProviderId = 'shop' | 'google' | 'discord' | 'linkedin';

export interface AuthProvider {
    id: AuthProviderId;
    enabled: boolean;
    redirect_url: string;
    /** True for the Shop — the canonical identity provider. */
    canonical: boolean;
    /** Absolute URL of an admin-uploaded button logo. Null = use default SVG. */
    logo_url: string | null;
}

export interface AuthProvidersResponse {
    providers: AuthProvider[];
    local_enabled: boolean;
    local_registration_enabled: boolean;
    /**
     * Admin-configured URL to the Shop's sign-up page. When non-null, the
     * login page shows a "Create account on Shop" CTA that external-links
     * here. Null means the Shop either isn't enabled or no register URL was
     * configured — fall back to the local /register flow.
     */
    shop_register_url: string | null;
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
