import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Reverb/Echo client (PLAN §2). Lazily created once per browser tab so multiple
 * components share the same socket. Returns null when Reverb is not configured,
 * letting the UI fall back to the server-provided notification feed.
 */
declare global {
    interface Window {
        Echo?: Echo<'reverb'>;
        Pusher?: typeof Pusher;
    }
}

let client: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> | null {
    if (typeof window === 'undefined') return null;
    if (client) return client;

    const key = document.querySelector('meta[name="reverb-key"]')?.getAttribute('content')
        || import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) return null;

    const host = document.querySelector('meta[name="reverb-host"]')?.getAttribute('content')
        || import.meta.env.VITE_REVERB_HOST
        || window.location.hostname;
    const port = Number(
        document.querySelector('meta[name="reverb-port"]')?.getAttribute('content')
        || import.meta.env.VITE_REVERB_PORT
        || 8080,
    );
    const scheme = document.querySelector('meta[name="reverb-scheme"]')?.getAttribute('content')
        || import.meta.env.VITE_REVERB_SCHEME
        || 'http';

    window.Pusher = Pusher;

    client = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = client;

    return client;
}