import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Resolve Reverb host from the browser's current location so the same build
// works whether the page is reached via localhost, 127.0.0.1, or a LAN IP.
// VITE_REVERB_HOST is honoured when explicitly set (e.g. for an external
// WebSocket endpoint), but defaults to whatever hostname served this page.
const envHost = import.meta.env.VITE_REVERB_HOST;
const reverbHost = (typeof envHost === 'string' && envHost && !envHost.includes('${'))
    ? envHost
    : window.location.hostname;
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT) || 8090;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME
    ?? (window.location.protocol === 'https:' ? 'https' : 'http');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
