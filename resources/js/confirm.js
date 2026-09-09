/**
 * The app's confirmation dialog.
 *
 * `window.confirm` is what this replaces. It cannot be styled, it names the
 * action in the browser's words rather than the product's, and it blocks the
 * page — including anything an automated session tries to do next.
 *
 * Returns a promise so a caller reads as a question:
 *
 *     if (!(await confirmAction({ title, message, confirm }))) return;
 */
const TONES = {
    danger: 'bg-danger hover:opacity-90',
    brand: 'bg-brand hover:bg-brand-dark',
};

export function confirmAction({ title, message, confirm, dismiss, tone = 'danger' }) {
    const modal = document.getElementById('sdConfirm');

    /**
     * No dialog on the page means no way to ask.
     *
     * Resolving false rather than true: a missing dialog must not turn a
     * confirmed deletion into an unconfirmed one.
     */
    if (!modal) {
        return Promise.resolve(false);
    }

    const accept = modal.querySelector('[data-confirm-accept]');
    const decline = modal.querySelector('[data-confirm-dismiss-label]');
    const opener = document.activeElement;

    /* Falls back to the dialog's own wording, so a caller that has nothing
       better to say than "Cancel" does not have to say anything. */
    if (decline) {
        // Remembered before the first override, or the fallback would become
        // whatever the last caller happened to name it.
        decline.dataset.default = decline.dataset.default || decline.textContent.trim();
        decline.textContent = dismiss || decline.dataset.default;
    }

    modal.querySelector('#sdConfirmTitle').textContent = title;
    modal.querySelector('#sdConfirmBody').textContent = message;
    accept.textContent = confirm;
    accept.className = `h-9 px-4 inline-flex items-center rounded-lg text-white text-[13px] font-semibold transition-colors ${TONES[tone] ?? TONES.danger}`;

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    accept.focus();

    return new Promise((resolve) => {
        const finish = (answer) => {
            modal.hidden = true;
            document.body.style.overflow = '';

            modal.removeEventListener('click', onClick);
            document.removeEventListener('keydown', onKey);

            // Back where they were, so the keyboard does not restart at the
            // top of the page after every cancelled action.
            if (opener && typeof opener.focus === 'function') {
                opener.focus();
            }

            resolve(answer);
        };

        const onClick = (event) => {
            if (event.target.closest('[data-confirm-accept]')) {
                finish(true);
            } else if (event.target.closest('[data-confirm-dismiss]')) {
                finish(false);
            }
        };

        /* Escape cancels, and Tab is held inside the dialog: focus that
           wandered onto the page behind a modal can act on it. */
        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                finish(false);

                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusable = modal.querySelectorAll('button');
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        modal.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
    });
}

/**
 * The markup form: any control carrying `data-confirm` asks first.
 *
 * The click is swallowed, the question asked, and the original action replayed
 * only on a yes — submitting the control's form when it has one, and
 * following it when it is a link.
 */
document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-confirm]');

    if (!trigger || trigger.dataset.confirmed === '1') {
        return;
    }

    /**
     * The click is stopped outright, not merely default-prevented.
     *
     * preventDefault cancels the browser's own action and nothing else: every
     * other listener on the control still runs, so a page that submits its
     * form from a click handler would submit it whatever the reader answered.
     * Cancel has to mean cancel.
     */
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    const answered = await confirmAction({
        title: trigger.dataset.confirmTitle || '',
        message: trigger.dataset.confirm,
        confirm: trigger.dataset.confirmLabel || '',
        dismiss: trigger.dataset.confirmDismiss || '',
        tone: trigger.dataset.confirmTone || 'danger',
    });

    if (!answered) {
        return;
    }

    // Marked before replaying, so the handler above lets the second click
    // through instead of asking the same question forever.
    trigger.dataset.confirmed = '1';
    trigger.click();
    delete trigger.dataset.confirmed;
}, true);
