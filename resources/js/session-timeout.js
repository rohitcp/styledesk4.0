/**
 * The idle-session warning, and the wall it puts up once the session is gone.
 *
 * Global rather than per-page: every authenticated screen shares the shell,
 * so this is wired once and covers the dashboard, the calendar, every module
 * and everything added later.
 *
 * A deadline, not a countdown. The previous version scheduled a setTimeout
 * for the whole idle window; browsers throttle timers in background tabs and
 * suspend them entirely while the machine sleeps, so a tab left open
 * overnight never fired and still looked signed in. A timestamp compared
 * against the clock survives both — the tab notices the moment it wakes.
 *
 * The server remains the authority. This decides what an untouched tab shows;
 * a 401 from any request is what proves the session has actually gone.
 */

/** How often to compare the clock against the deadline. */
const TICK_MS = 5000;

export function initSessionTimeout(root = document) {
    const config = window.styledeskSession;
    const warning = root.getElementById?.('sd-timeout') ?? document.getElementById('sd-timeout');
    const expired = document.getElementById('sd-expired');

    if (!config || !config.timeoutMs || !warning || !expired) {
        return;
    }

    let deadline = Date.now() + config.timeoutMs;
    let over = false;

    const show = (dialog) => {
        dialog.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    const hide = (dialog) => {
        dialog.hidden = true;
        document.body.style.overflow = '';
    };

    /** mm:ss, because "119 seconds" is a number the reader has to convert. */
    const clock = (ms) => {
        const total = Math.max(0, Math.round(ms / 1000));
        const minutes = String(Math.floor(total / 60)).padStart(2, '0');
        const seconds = String(total % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    };

    /**
     * The session is gone. Said plainly, and not dismissible: behind this
     * dialog is a page whose every action would now fail.
     */
    function expire() {
        if (over) {
            return;
        }

        over = true;
        hide(warning);
        show(expired);
    }

    function restart() {
        if (over) {
            return;
        }

        deadline = Date.now() + config.timeoutMs;
        hide(warning);
    }

    function tick() {
        if (over) {
            return;
        }

        const remaining = deadline - Date.now();

        if (remaining <= 0) {
            expire();

            return;
        }

        if (remaining <= config.warnMs) {
            show(warning);
            const count = document.getElementById('sd-timeout-count');

            if (count) {
                count.textContent = clock(remaining);
            }
        }
    }

    /* One second while the warning is up so the clock reads truthfully, five
       otherwise — there is nothing to see until then. */
    window.setInterval(tick, 1000);
    window.setInterval(tick, TICK_MS);

    /* Checked again the moment the tab is looked at, which is when a
       suspended machine catches up. */
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            tick();
        }
    });

    /* Real interaction only. Deliberately not scroll or mousemove: a trackpad
       nudge or a phone in a pocket would keep a session alive forever, which
       is the opposite of a timeout. */
    ['click', 'keydown', 'submit'].forEach((event) => {
        document.addEventListener(event, () => {
            if (warning.hidden && !over) {
                restart();
            }
        }, true);
    });

    warning.addEventListener('click', (event) => {
        if (event.target.closest('[data-timeout-signout]')) {
            signOut();

            return;
        }

        if (!event.target.closest('[data-timeout-stay]')) {
            return;
        }

        /* The server is touched as well. Dismissing the dialog alone would
           restart this countdown while the server's kept running, and the
           next click would land on a login screen anyway. */
        const token = document.querySelector('meta[name=csrf-token]');

        fetch(config.keepAliveUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? restart() : expire()))
            .catch(() => restart());
    });

    expired.addEventListener('click', (event) => {
        if (event.target.closest('[data-timeout-signin]')) {
            window.location.href = config.loginUrl;
        }
    });

    function signOut() {
        const form = document.getElementById('sd-logout-form');

        if (form) {
            form.submit();

            return;
        }

        window.location.href = config.loginUrl;
    }

    /**
     * Any request that comes back 401 has been told by the server that the
     * session is over — which is the only authority that counts. A tab whose
     * own clock is wrong, or which was restored from cache with a stale
     * deadline, finds out here.
     */
    const originalFetch = window.fetch;

    window.fetch = function styledeskFetch(...args) {
        return originalFetch.apply(this, args).then((response) => {
            if (response.status === 401) {
                expire();
            }

            return response;
        });
    };
}
