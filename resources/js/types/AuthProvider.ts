export type AuthProviderId = 'shop' | 'google' | 'discord' | 'linkedin' | 'paymenter' | 'whmcs';

/** Canonical IdPs auto-create users, sync email to Pelican, and may surface a register URL. */
export type CanonicalProviderId = 'shop' | 'paymenter' | 'whmcs';

export interface AuthProvider {
    id: AuthProviderId;
    enabled: boolean;
    redirect_url: string;
    /** True for canonical IdPs (Shop, Paymenter, WHMCS) when active. */
    canonical: boolean;
    /** Absolute URL of an admin-uploaded button logo. Null = use default SVG. */
    logo_url: string | null;
}

export interface AuthProvidersResponse {
    providers: AuthProvider[];
    local_enabled: boolean;
    local_registration_enabled: boolean;
    /** The currently active canonical IdP id, or null when none is enabled. */
    canonical_provider: CanonicalProviderId | null;
    /**
     * Admin-configured URL to the canonical IdP's sign-up page. When non-null,
     * the login page shows a "Create account on {provider}" CTA that
     * external-links here. Null means no canonical IdP is enabled or no
     * register URL was configured — fall back to the local /register flow.
     */
    canonical_register_url: string | null;
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
