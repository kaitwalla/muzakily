import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        __PUSHER_CONFIG__?: { key: string; cluster: string };
    }
}

window.Pusher = Pusher;

export function initEcho(token: string): void {
    const config = window.__PUSHER_CONFIG__;
    if (!config?.key || !config?.cluster) {
        console.warn('Muzakily: Pusher config missing, real-time features disabled');
        return;
    }

    disconnectEcho();

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: config.key,
        cluster: config.cluster,
        forceTLS: true,
        authEndpoint: '/api/v1/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        },
    }) as unknown as typeof window.Echo;
}

export function disconnectEcho(): void {
    if (window.Echo) {
        (window.Echo as unknown as { disconnect: () => void }).disconnect();
        window.Echo = undefined;
    }
}
