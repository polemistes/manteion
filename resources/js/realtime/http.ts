import { echo, echoIsConfigured } from '@laravel/echo-vue';

/** A Wayfinder route definition (or anything shaped like one). */
export type Endpoint = {
    url: string;
    method: 'get' | 'head' | 'post' | 'put' | 'patch' | 'delete' | 'options';
};

/** A JSON request that did not succeed; `status` is 0 when the network failed. */
export class RequestError extends Error {
    constructor(
        public readonly status: number,
        message: string,
    ) {
        super(message);
        this.name = 'RequestError';
    }
}

/** The session's CSRF token as Laravel hands it out (the XSRF-TOKEN cookie). */
export function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

/** This tab's Reverb socket id, once connected. */
export function socketId(): string | null {
    if (!echoIsConfigured()) {
        return null;
    }

    try {
        return echo().socketId() || null;
    } catch {
        return null;
    }
}

/**
 * Calls one of the app's JSON endpoints as the signed-in page: the CSRF
 * token from the XSRF-TOKEN cookie, `Accept: application/json` (so Laravel
 * answers with JSON and status codes instead of redirects), and the Echo
 * socket id so a `->toOthers()` broadcast skips this tab. `keepalive` lets
 * the request outlive the page (presence when the tab closes).
 *
 * Resolves with the decoded JSON body, or null when there is none (204, or
 * a non-JSON answer); rejects with a RequestError otherwise.
 */
export async function requestJson<T>(
    endpoint: Endpoint,
    body?: unknown,
    options: { keepalive?: boolean; signal?: AbortSignal } = {},
): Promise<T | null> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const xsrf = xsrfToken();
    const socket = socketId();

    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }

    if (socket) {
        headers['X-Socket-ID'] = socket;
    }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const method = endpoint.method.toUpperCase();
    let response: Response;

    try {
        response = await fetch(endpoint.url, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            credentials: 'same-origin',
            keepalive: options.keepalive,
            signal: options.signal,
        });
    } catch (error) {
        throw new RequestError(
            0,
            `${method} ${endpoint.url} failed: ${String(error)}`,
        );
    }

    if (!response.ok) {
        throw new RequestError(
            response.status,
            `${method} ${endpoint.url} answered ${response.status}`,
        );
    }

    if (
        response.status === 204 ||
        !response.headers.get('Content-Type')?.includes('json')
    ) {
        return null;
    }

    try {
        return (await response.json()) as T;
    } catch {
        throw new RequestError(
            response.status,
            `${method} ${endpoint.url} answered invalid JSON`,
        );
    }
}
