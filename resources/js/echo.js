import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Resolve Reverb host from the browser's current location so the same build
// works whether the page is reached via localhost, 127.0.0.1, or a LAN IP.
// VITE_REVERB_HOST is honoured when explicitly set (e.g. for an external
// WebSocket endpoint), but defaults to whatever hostname served this page.
// Values are sanitised so a common .env slip (a scheme or path in the host,
// an empty/unresolved port) can't silently break the live feed.
const usable = (v) => typeof v === 'string' && v.trim() !== '' && !v.includes('${');
const pageIsHttps = window.location.protocol === 'https:';

const envHost = import.meta.env.VITE_REVERB_HOST;
const reverbHost = usable(envHost)
    ? envHost.trim().replace(/^[a-z]+:\/\//i, '').replace(/[/:].*$/, '')
    : window.location.hostname;

// On an https page the socket rides the same TLS origin (reverse proxy on 443);
// the 8090 fallback is only for plain-http local dev.
const envPort = Number(import.meta.env.VITE_REVERB_PORT);
const reverbPort = envPort > 0 ? envPort : (pageIsHttps ? 443 : 8090);

const envScheme = import.meta.env.VITE_REVERB_SCHEME;
const reverbScheme = usable(envScheme) ? envScheme.trim() : (pageIsHttps ? 'https' : 'http');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    // An https page can never open ws:// (mixed content), whatever the env says.
    forceTLS: pageIsHttps || reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
