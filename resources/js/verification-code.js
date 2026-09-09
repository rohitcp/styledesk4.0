/**
 * The six-box confirmation code, and the resend countdown beside it.
 *
 * The boxes are a presentation of one value: they collect into a hidden input
 * that the form actually posts, so the server sees a single six-digit field
 * whether the reader typed it, pasted it, or has JavaScript switched off and
 * filled nothing at all — in which case the field arrives empty and the page
 * says so, rather than the form posting six half-fields it cannot read.
 *
 * Markup contract:
 *   [data-code-form]   the form
 *   [data-code-input]  the six single-character boxes, in order
 *   [data-code-value]  the hidden input that is posted
 *   [data-code-error]  where an inline message goes
 */
export function initVerificationCode(root = document) {
    root.querySelectorAll('[data-code-form]').forEach((form) => {
        if (form.dataset.codeReady) {
            return;
        }

        form.dataset.codeReady = '1';

        const boxes = Array.from(form.querySelectorAll('[data-code-input]'));
        const value = form.querySelector('[data-code-value]');
        const error = form.querySelector('[data-code-error]');

        if (boxes.length === 0 || !value) {
            return;
        }

        const digitsOf = () => boxes.map((box) => box.value).join('');

        const sync = () => {
            value.value = digitsOf();
        };

        /* Typing clears the last refusal. Leaving a stale "that code is
           incorrect" above a code the reader has just retyped reads as the new
           one having been rejected too. */
        const clearError = () => {
            if (error && !error.hidden) {
                error.hidden = true;
                error.textContent = '';
            }
        };

        const fill = (digits) => {
            boxes.forEach((box, index) => {
                box.value = digits[index] ?? '';
            });

            sync();

            /* Focus the first empty box, or the last one when the code is
               complete — so the reader's next keystroke lands somewhere
               useful either way. */
            const next = boxes.find((box) => box.value === '') ?? boxes[boxes.length - 1];
            next.focus();
            next.select?.();
        };

        boxes.forEach((box, index) => {
            box.addEventListener('input', () => {
                /* A soft keyboard or an autofill can deliver several
                   characters into one box; the extras move along rather than
                   being dropped. */
                const digits = box.value.replace(/\D/g, '');

                if (digits.length > 1) {
                    fill((digitsOf().slice(0, index) + digits).replace(/\D/g, '').slice(0, boxes.length));
                    clearError();

                    return;
                }

                box.value = digits;
                sync();
                clearError();

                if (digits !== '' && index < boxes.length - 1) {
                    boxes[index + 1].focus();
                    boxes[index + 1].select?.();
                }
            });

            box.addEventListener('keydown', (event) => {
                /* Backspace in an empty box steps back and clears the one
                   before it: without this the caret sticks and the reader has
                   to click each box to correct a mistyped code. */
                if (event.key === 'Backspace' && box.value === '' && index > 0) {
                    event.preventDefault();
                    boxes[index - 1].value = '';
                    boxes[index - 1].focus();
                    sync();
                    clearError();

                    return;
                }

                if (event.key === 'ArrowLeft' && index > 0) {
                    event.preventDefault();
                    boxes[index - 1].focus();
                }

                if (event.key === 'ArrowRight' && index < boxes.length - 1) {
                    event.preventDefault();
                    boxes[index + 1].focus();
                }
            });

            /* Paste anywhere in the row fills the whole row. Readers copy the
               code out of the email as one string and drop it on whichever box
               happens to be under the cursor. */
            box.addEventListener('paste', (event) => {
                const pasted = (event.clipboardData ?? window.clipboardData)?.getData('text') ?? '';
                const digits = pasted.replace(/\D/g, '').slice(0, boxes.length);

                if (digits === '') {
                    return;
                }

                event.preventDefault();
                fill(digits);
                clearError();
            });

            box.addEventListener('focus', () => box.select?.());
        });

        /* Belt and braces: the hidden field is refreshed on the way out even
           if some path above missed an update. */
        form.addEventListener('submit', sync);
    });
}

/**
 * The resend button's cooldown.
 *
 * Counts down from the server's own remaining seconds — rendered into
 * data-resend-in — so the button re-enables at the moment the route would
 * start accepting again, and reloading the page cannot shorten the wait.
 *
 * Markup contract: [data-resend-button][data-resend-in="<seconds>"] wrapping
 * a [data-resend-label] whose text is replaced while the clock runs.
 */
export function initResendCooldown(root = document) {
    root.querySelectorAll('[data-resend-button]').forEach((button) => {
        if (button.dataset.resendReady) {
            return;
        }

        button.dataset.resendReady = '1';

        const label = button.querySelector('[data-resend-label]') ?? button;
        let remaining = Number.parseInt(button.dataset.resendIn ?? '0', 10);

        if (!Number.isFinite(remaining) || remaining <= 0) {
            return;
        }

        const render = () => {
            if (remaining <= 0) {
                button.disabled = false;
                /* Not the text it was rendered with — that text is the
                   countdown itself, because the server renders the disabled
                   state for a reader with no JavaScript. */
                label.textContent = 'Resend Email';

                return;
            }

            button.disabled = true;
            label.textContent = `Resend available in ${remaining} second${remaining === 1 ? '' : 's'}`;
        };

        render();

        const timer = window.setInterval(() => {
            remaining -= 1;
            render();

            if (remaining <= 0) {
                window.clearInterval(timer);
            }
        }, 1000);
    });
}
