/**
 * The website field: a host typed beside a scheme dropdown, checked as it is
 * typed.
 *
 * The rules mirror the server's. They have to: a field that accepts what the
 * server refuses is a form that looks fine and then fails on submit, and the
 * reader has no idea which of eight fields it was.
 *
 * Markup contract — a container carrying data-website, holding:
 *   [data-website-scheme]  the scheme select
 *   [data-website-field]   the host input
 *   [data-error-for="<id>"] where the message goes, via SD.setError
 */

/** A host, optionally followed by a path. Deliberately the server's pattern. */
const HOST = /^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}(\/\S*)?$/i;

export function initWebsiteFields(root = document) {
    root.querySelectorAll('[data-website]').forEach((group) => mount(group));
}

function mount(group) {
    if (group.dataset.websiteReady) {
        return;
    }

    group.dataset.websiteReady = '1';

    const field = group.querySelector('[data-website-field]');
    const scheme = group.querySelector('[data-website-scheme]');

    if (!field) {
        return;
    }

    const message = group.dataset.invalidMessage || 'Enter a valid website address.';

    const say = (text) => {
        /* SD owns the error styling everywhere else on this form, so the
           website field says things the same way the rest of it does. */
        if (window.SD && typeof window.SD.setError === 'function') {
            window.SD.setError(field, text);
        }

        field.setCustomValidity(text);
    };

    /**
     * A pasted address, reduced to the part this field holds.
     *
     * People paste the whole thing — "https://www.example.com" — because that
     * is what their browser gave them. Silently moving the scheme into the
     * dropdown beside it is kinder than refusing the paste.
     */
    function absorbScheme(value) {
        const match = value.match(/^\s*(https?:\/\/)(www\.)?/i);

        if (!match) {
            return value.trim();
        }

        if (scheme) {
            const wanted = (match[1] + (match[2] ?? '')).toLowerCase();
            const option = Array.from(scheme.options).find((o) => o.value.toLowerCase() === wanted);

            if (option) {
                scheme.value = option.value;
            }
        }

        return value.slice(match[0].length).trim();
    }

    function check() {
        const value = field.value.trim();

        /* Optional: an empty field is not a wrong one. */
        say(value === '' || HOST.test(value) ? '' : message);
    }

    field.addEventListener('input', () => {
        const cleaned = absorbScheme(field.value).replace(/\s+/g, '');

        if (cleaned !== field.value) {
            field.value = cleaned;
        }

        /* Only once it is already wrong, so the message does not appear on
           the first character of a correct address. */
        if (field.getAttribute('aria-invalid')) {
            check();
        }
    });

    field.addEventListener('blur', check);

    if (field.form) {
        field.form.addEventListener('submit', check);
    }

    /* A value the server sent back, or the browser restored, is graded rather
       than left unsaid. */
    if (field.value.trim() !== '') {
        check();
    }
}
