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
        throw new ApiError(response.status, data);
    }

    return response;
}
