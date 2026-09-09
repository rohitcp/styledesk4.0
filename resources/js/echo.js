import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Laravel Echo bound to the Reverb websocket server.
 *
 * Reverb speaks the Pusher protocol, so the `reverb` broadcaster is a thin
 * wrapper over pusher-js pointed at our own host instead of Pusher's cloud.
 *
 * Set up only when there is a key to set it up with, and never allowed to
 * throw. This module is the first import in app.js, and an exception here
 * aborts the whole bundle: every module after it — the one that publishes
 * window.SD, the combos, the tooltips, the grids, the password field's own
 * reveal toggle — is simply never evaluated. An environment that had not
 * filled in VITE_REVERB_APP_KEY got "You must pass your app key when you
 * instantiate Pusher", and with it a site where no JavaScript ran at all.
 *
 * Live updates are a feature; the rest of the application is not. A build
 * without broadcasting configured has to degrade to a page that works.
 */
const key = import.meta.env.VITE_REVERB_APP_KEY;

if (key) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.warn('[styledesk] Live updates are unavailable.', error);
    }
} else {
    /* Said once, quietly. A developer wondering why nothing updates live
       gets an answer; a visitor gets a page that works. */
    console.warn('[styledesk] VITE_REVERB_APP_KEY is not set — live updates are off.');
}

export default window.Echo ?? null;
