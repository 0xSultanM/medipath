export class ApiError extends Error {
    status: number;
    code: string;
    retryable: boolean;
    lastKnownAt: string;
    errors?: Record<string, string>;

    constructor(message: string, status = 0, code = 'request_failed', retryable = true, errors?: Record<string, string>) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.retryable = retryable;
        this.lastKnownAt = new Date().toISOString();
        this.errors = errors;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(url: string, init: RequestInit = {}, retries = 1): Promise<T> {
    let lastError: ApiError | null = null;

    for (let attempt = 0; attempt <= retries; attempt += 1) {
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...init,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                    ...(init.headers ?? {}),
                },
            });

            if (response.status === 204) return undefined as T;

            const contentType = response.headers.get('content-type') ?? '';
            const payload = contentType.includes('application/json') ? await response.json() : null;

            if (!response.ok) {
                const error = new ApiError(
                    payload?.message ?? `Request failed with status ${response.status}.`,
                    response.status,
                    payload?.code ?? 'request_failed',
                    payload?.retryable ?? response.status >= 500,
                    payload?.errors,
                );
                if (attempt < retries && error.retryable) {
                    lastError = error;
                    continue;
                }
                throw error;
            }

            return payload as T;
        } catch (error) {
            if (error instanceof ApiError) throw error;
            lastError = new ApiError(
                error instanceof Error ? error.message : 'Network unavailable.',
                0,
                'network_error',
                true,
            );
            if (attempt >= retries) throw lastError;
        }
    }

    throw lastError ?? new ApiError('Request failed.');
}

export function getJson<T>(url: string, retries = 1): Promise<T> {
    return request<T>(url, { method: 'GET' }, retries);
}

export function postJson<T>(url: string, body: unknown, retries = 0): Promise<T> {
    return request<T>(
        url,
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        },
        retries,
    );
}