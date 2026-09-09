/**
 * A submit button that fires once.
 *
 * A second click on Continue posts the form twice. On the onboarding step
 * that is two tenants; on a client form it is two clients with the same
 * details. The browser gives no feedback for a slow POST, so a reader who
 * sees nothing happen quite reasonably clicks again.
 *
 * Bound to the form's submit rather than the button's click, which is what
 * makes it safe: `submit` only fires after the browser's own constraint
 * validation has passed, so a form that fails validation never disables the
 * button the reader still needs.
 *
 * Markup contract: a submit button carrying data-submit-once, optionally with
 * data-busy-label for what it should say while the request is in flight. The
 * button may live outside its form and reference it with `form="..."` — that
 * is what `button.form` resolves.
 */
export function initSubmitOnce(root = document) {
    root.querySelectorAll('[data-submit-once]').forEach((button) => {
        const form = button.form;

        if (!form || button.dataset.submitOnceReady) {
            return;
        }

        button.dataset.submitOnceReady = '1';

        let sent = false;

        form.addEventListener('submit', (event) => {
            if (sent) {
                event.preventDefault();

                return;
            }

            sent = true;

            /* On the next tick, not this one: a disabled control is left out
               of the submitted data, and disabling during the submit handler
               races the browser's serialisation of the form. */
            window.setTimeout(() => {
                button.disabled = true;
                button.classList.add('opacity-60', 'pointer-events-none');

                if (button.dataset.busyLabel) {
                    button.textContent = button.dataset.busyLabel;
                }
            }, 0);
        });

        /* A page restored from the back/forward cache keeps the DOM it was
           unloaded with — including a button left disabled on the way out.
           Without this the reader returns to a form they cannot submit. */
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) {
                return;
            }

            sent = false;
            button.disabled = false;
            button.classList.remove('opacity-60', 'pointer-events-none');
        });
    });
}
