/**
 * The business address field: a subdomain generated from a name, editable,
 * and checked while it is being typed.
 *
 * Reusable rather than page-local. Onboarding asks for an address today and
 * the business settings screen will ask for the same thing tomorrow; the
 * rules for what a subdomain may contain must not be written twice.
 *
 * The cleaning rules mirror App\Support\Subdomain exactly. They have to: a
 * field that accepts what the server refuses is a form that says "Available"
 * and then fails on submit.
 *
 * Markup contract — a container carrying data-subdomain, holding:
 *   [data-subdomain-source]     the name it is generated from
 *   [data-subdomain-field]      the address input
 *   [data-subdomain-status]     where Available / taken / invalid is said
 *   [data-subdomain-regenerate] optional, resets it from the name
 * and, on the container, data-check-url and data-labels (JSON).
 */

const MAX_LENGTH = 60;

/** A name, as an address: letters and digits only, so no spaces survive. */
export function fromName(value) {
    return String(value ?? '')
        .normalize('NFD')
        // Strip the accents NFD just separated, so "Café" keeps its e.
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '')
        .slice(0, MAX_LENGTH);
}

/**
 * What the user typed, as an address.
 *
 * Kinder than fromName: a hyphen they typed on purpose is kept, because it is
 * legal and they meant it.
 */
export function normalise(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '')
        .slice(0, MAX_LENGTH);
}

export function isValid(value) {
    return /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/.test(String(value ?? ''));
}

export function initSubdomainFields(root = document) {
    root.querySelectorAll('[data-subdomain]').forEach((group) => mount(group));
}

function mount(group) {
    if (group.dataset.subdomainReady) {
        return;
    }

    group.dataset.subdomainReady = '1';

    const source = group.querySelector('[data-subdomain-source]')
        ?? document.querySelector(group.dataset.subdomainSource ?? '');
    const field = group.querySelector('[data-subdomain-field]');
    const status = group.querySelector('[data-subdomain-status]');
    const regenerate = group.querySelector('[data-subdomain-regenerate]');

    if (!field) {
        return;
    }

    const labels = JSON.parse(group.dataset.labels ?? '{}');
    const checkUrl = group.dataset.checkUrl;

    /* Whether the user has taken the field over. Once they have, typing in
       the name must not overwrite what they chose — which is what the
       Regenerate control is for. */
    let touched = field.value.trim() !== '';
    let sequence = 0;
    let timer = null;

    const say = (state, text) => {
        if (!status) {
            return;
        }

        status.textContent = text ?? labels[state] ?? '';
        status.dataset.state = state;
        status.className = 'mt-1.5 text-[12px] '
            + (state === 'available' ? 'text-success'
                : state === 'checking' || state === 'empty' ? 'text-faint' : 'text-danger');
    };

    /**
     * Whether the form may be submitted.
     *
     * setCustomValidity rather than a disabled button: the browser then
     * refuses the submit itself and says why, on the field the problem is on,
     * with no second copy of "is this form valid" to keep in step.
     */
    const gate = (ok, message) => field.setCustomValidity(ok ? '' : message ?? '');

    async function check() {
        const value = field.value.trim();

        if (value === '') {
            say('empty', labels.empty ?? '');
            gate(true);

            return;
        }

        if (!isValid(value)) {
            say('invalid');
            gate(false, labels.invalid ?? 'This address cannot be used.');

            return;
        }

        if (!checkUrl) {
            gate(true);

            return;
        }

        say('checking');

        /* Only the newest answer is allowed to speak. Two keystrokes in
           flight can come back out of order, and the slower one would
           otherwise describe an address the field no longer holds. */
        const mine = ++sequence;

        try {
            const response = await fetch(`${checkUrl}?slug=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok || mine !== sequence) {
                return;
            }

            const result = await response.json();

            if (mine !== sequence) {
                return;
            }

            say(result.status);
            gate(result.status === 'available', labels[result.status] ?? '');
        } catch (error) {
            /* The network is not the user's problem: let the submit through
               and let the server have the final word, which it has anyway. */
            say('empty', '');
            gate(true);
        }
    }

    const checkSoon = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(check, 350);
    };

    field.addEventListener('input', () => {
        touched = true;

        /* Cleaned as it is typed, with the caret put back where it was: the
           field would otherwise jump to the end on every corrected
           character. */
        const before = field.value;
        const caret = field.selectionStart ?? before.length;
        const cleaned = normalise(before);

        if (cleaned !== before) {
            field.value = cleaned;
            const shift = before.length - cleaned.length;
            field.setSelectionRange(Math.max(0, caret - shift), Math.max(0, caret - shift));
        }

        checkSoon();
    });

    field.addEventListener('blur', check);

    if (source) {
        source.addEventListener('input', () => {
            if (touched) {
                return;
            }

            field.value = fromName(source.value);
            checkSoon();
        });
    }

    if (regenerate && source) {
        regenerate.addEventListener('click', () => {
            field.value = fromName(source.value);
            touched = false;
            check();
            field.focus();
        });
    }

    /* A value restored by the browser, or one the server sent back after a
       failed submit, is graded rather than left unsaid. */
    if (field.value.trim() !== '') {
        check();
    }
}
