export function getCsrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

export class ApiError extends Error {
    constructor(
        public status: number,
        public data: Record<string, unknown>,
    ) {
        super(`API Error: ${status}`);
    }
}

export async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...options.headers,
        },
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({})) as Record<string, unknown>;
        maybeRedirectOn2faEnforcement(response.status, data);
        throw new ApiError(response.status, data);
    }

    return response.json() as Promise<T>;
}

export async function requestRaw(url: string, options: RequestInit = {}): Promise<Response> {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...options.headers,
        },
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({})) as Record<string, unknown>;
        maybeRedirectOn2faEnforcement(response.status, data);
        throw new ApiError(response.status, data);
    }

    return response;
}

/**
 * RequireTwoFactor middleware returns 403 with
 *   { error: 'auth.2fa.required_admin_setup', setup_url: '/2fa/setup?enforced=1' }
 * when an admin hits a gated route without 2FA configured. Hijack the response
 * — redirect the browser before the caller throws. Keeps admin-gated pages
 * from having to implement the 403 handling manually.
 *
 * Skips the redirect when we're already on the setup page (prevents a loop
 * if the setup page itself makes an API call).
 */
function maybeRedirectOn2faEnforcement(status: number, data: Record<string, unknown>): void {
    if (status !== 403) return;
    if (data['error'] !== 'auth.2fa.required_admin_setup') return;
    const target = data['setup_url'];
    if (typeof target !== 'string') return;
    if (window.location.pathname.startsWith('/2fa/setup')) return;
    window.location.href = target;
}
