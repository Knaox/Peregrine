/**
 * Validate a post-login `?redirect=` target.
 *
 * Returns the path only when it is a safe in-app absolute path; otherwise null.
 * Guards against open-redirect: protocol-relative URLs (`//evil.com`),
 * backslash tricks (`/\evil.com`, which some browsers normalise to `//`), and
 * absolute URLs (`https://evil.com`) are all rejected. Callers fall back to
 * their default landing route when this returns null.
 *
 * Used by the login + 2FA flows so an invitation link
 * (`/login?redirect=/invite/{token}`) returns the user to the invite page to
 * accept, instead of dropping them on the dashboard.
 */
export function safeRedirectPath(value: string | null | undefined): string | null {
    if (typeof value !== 'string' || value === '') {
        return null;
    }

    if (!value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) {
        return null;
    }

    return value;
}
