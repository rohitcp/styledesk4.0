/**
 * The one live-validation implementation, driven from markup.
 *
 * Extracted from the sign-up page, which is where this behaviour was first
 * written and where it lived as a hand-built list of checks. A second form
 * copying that list would have been a second set of messages to keep in step
 * with the server's, and a second set of decisions about when an error is
 * allowed to appear. So the rules are declared on the fields instead, and
 * every form that opts in behaves identically.
 *
 * Markup contract:
 *
 *   <form data-validate-form data-validation-messages='{"required":"…"}'>
 *     <input data-rules="required|email|max:255" …>
 *     <p data-error-for="<the input's id>" role="alert" hidden></p>
 *
 * Error painting and focus are SD.setError / SD.focusFirstError — the same
 * helpers the server-rendered errors use — so a message the browser wrote and
 * one the server wrote look identical and are announced identically.
 *
 * When a field's rules are not satisfied, nothing is cleared, moved or
 * reformatted: the reader's own value stays exactly as typed.
 */

/** Messages are per form, so a page can carry two forms in two languages. */
function messagesFor(form) {
    try {
        return JSON.parse(form.dataset.validationMessages || '{}');
    } catch (error) {
        return {};
    }
}

/**
 * What to call this field when a message names it.
 *
 * Read from the label the field already has rather than from an attribute
 * repeating it: two copies of a name drift, and the one in the message is the
 * copy nobody looks at.
 */
function labelFor(field) {
    const label = field.id ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;

    if (!label) {
        return field.getAttribute('aria-label') || '';
    }

    /* The required star and the "(optional)" note are decoration on the
       label, not part of what the field is called. */
    return label.textContent.replace(/[*]/g, '').replace(/\(.*?\)/g, '').trim();
}

function fill(template, replacements) {
    return Object.keys(replacements).reduce(
        (text, key) => text.split(':' + key).join(replacements[key]),
        template || '',
    );
}

const TESTS = {
    /* Checkboxes answer with checked; a select answers with its value, so an
       unchosen placeholder option must carry an empty value to be refused. */
    required: (value, field) => (field.type === 'checkbox' || field.type === 'radio'
        ? field.checked
        : String(value ?? '').trim() !== ''),

    /* Deliberately loose, and the same expression the sign-up page used: the
       server owns the real answer, and a browser that refuses an address the
       server would accept is worse than one that waits. */
    email: (value) => value.trim() === '' || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value.trim()),

    /* Digits, and enough of them to be a number somewhere. Punctuation and
       country codes are the reader's business — the phone field formats what
       they type, and refusing an unfamiliar shape would refuse real numbers.
       Which is exactly what an earlier version did: it required the first
       character to be a digit or a +, and the US and Canadian masks in
       styledesk.js open with a bracket — '(305) 456-5656' — so every number
       the field had just formatted was then refused as invalid. The dialling
       code is not in this input at all; it lives in the button beside it. */
    phone: (value) => value.trim() === '' || (value.replace(/\D/g, '').length >= 6
        && /^\+?[\d\s().-]+$/.test(value.trim())),

    date: (value) => value.trim() === '' || !Number.isNaN(Date.parse(value)),

    numeric: (value) => value.trim() === '' || !Number.isNaN(Number(value)),

    integer: (value) => value.trim() === '' || /^-?\d+$/.test(value.trim()),
};

/**
 * Rules that take an argument. Length rules read the text; value rules read
 * the number — "max:255" on a name and "max:730" on a day count are different
 * questions, and the field's own type is what tells them apart.
 */
const BOUNDED = {
    min: (value, field, bound) => (value.trim() === '' ? true : (isNumeric(field)
        ? Number(value) >= Number(bound)
        : value.trim().length >= Number(bound))),

    max: (value, field, bound) => (value.trim() === '' ? true : (isNumeric(field)
        ? Number(value) <= Number(bound)
        : value.trim().length <= Number(bound))),
};

function isNumeric(field) {
    return field.type === 'number' || (field.dataset.rules || '').includes('numeric')
        || (field.dataset.rules || '').includes('integer');
}

/**
 * The first rule this field fails, as a finished message.
 *
 * First, not all of them: a field with three things wrong is still one field,
 * and three stacked messages is a wall to read before the first correction.
 */
function failureFor(field, messages) {
    const rules = (field.dataset.rules || '').split('|').map((rule) => rule.trim()).filter(Boolean);
    const value = field.value ?? '';
    const label = labelFor(field);

    for (const rule of rules) {
        const [name, bound] = rule.split(':');

        /* A field may name its own wording — data-message-required — for the
           cases the bundle cannot phrase from a label: a consent checkbox
           whose label is a whole sentence, or a field the server refuses in
           words of its own. */
        const custom = field.dataset[`message${name.charAt(0).toUpperCase()}${name.slice(1)}`];

        if (TESTS[name] && !TESTS[name](value, field)) {
            return custom || fill(messages[name], { field: label, attribute: label });
        }

        if (BOUNDED[name] && !BOUNDED[name](value, field, bound)) {
            const key = isNumeric(field) ? `${name}_value` : name;

            return custom || fill(messages[key] ?? messages[name], { field: label, attribute: label, [name]: bound });
        }
    }

    return '';
}

function paint(field, message) {
    if (window.SD && typeof window.SD.setError === 'function') {
        window.SD.setError(field, message);

        return;
    }

    /* Only reached if the shared helpers are not on the page. The behaviour
       has to degrade to something, not to nothing. */
    const box = field.id ? document.querySelector(`[data-error-for="${CSS.escape(field.id)}"]`) : null;

    if (box) {
        box.textContent = message;
        box.hidden = !message;
    }

    field.classList.toggle('is-error', Boolean(message));

    if (message) {
        field.setAttribute('aria-invalid', 'true');
    } else {
        field.removeAttribute('aria-invalid');
    }
}

/**
 * Ask the server a question the browser cannot answer — is this address
 * already on a client?
 *
 * Debounced, and only once the value passes its own rules: an address that is
 * not yet an address is not worth a request, and one request per keystroke
 * would be a request per keystroke.
 *
 * A failed lookup says nothing. The server checks again on submit, so a
 * network that is down must not invent an error the reader cannot clear.
 */
function remoteCheck(field, messages) {
    const url = field.dataset.remoteCheck;

    if (!url) {
        return;
    }

    window.clearTimeout(field.dataset.remoteTimer ? Number(field.dataset.remoteTimer) : 0);

    const value = field.value.trim();

    if (value === '' || failureFor(field, messages)) {
        return;
    }

    const timer = window.setTimeout(async () => {
        try {
            const response = await fetch(`${url}${url.includes('?') ? '&' : '?'}value=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            /* Only paints a problem, never clears one: the field may have
               moved on while the request was in flight, and the answer to
               the address typed a second ago is not an answer about this one. */
            if (payload.ok === false && field.value.trim() === value) {
                paint(field, payload.message || messages.taken || '');
            }
        } catch (error) {
            /* Silent by design — see above. */
        }
    }, 400);

    field.dataset.remoteTimer = String(timer);
}

/**
 * Put the reader on the first thing that is wrong.
 *
 * A combo box holds its answer in a hidden input, which cannot take focus and
 * would swallow the keystroke that followed. So focus goes to the control the
 * reader actually operates — the button beside it — and only falls back to
 * the field itself when it is one that can be typed into.
 */
function focusFirst(field, form) {
    const visible = field.offsetParent !== null || field.type !== 'hidden';

    if (visible) {
        if (window.SD && typeof window.SD.focusFirstError === 'function') {
            window.SD.focusFirstError(form);
        }

        field.focus();
        field.scrollIntoView({ block: 'center', behavior: 'smooth' });

        return;
    }

    const standIn = field.parentElement?.querySelector('button, [tabindex]:not([tabindex="-1"])');

    (standIn ?? field).focus?.();
    (standIn ?? field).scrollIntoView?.({ block: 'center', behavior: 'smooth' });
}

/**
 * The fields a form is currently asking for.
 *
 * A field inside a hidden panel is not one of them. Several forms reveal a
 * section only when the setting above it is on — the overtime figures, the
 * shift periods a day is divided into — and those fields keep posting while
 * hidden so that switching the setting off and on again does not cost what was
 * typed. Validated anyway, a blank row in a section nobody can see would
 * refuse the form for a reason the reader cannot act on or even find.
 *
 * Matched on the `hidden` attribute rather than on visibility: a control whose
 * answer lives in a hidden input — a combo box, a time picker — is never
 * visible itself and must still be checked.
 */
function fieldsOf(form) {
    return Array.from(form.querySelectorAll('[data-rules]'))
        .filter((field) => field.closest('[hidden]') === null);
}

/**
 * Whether this form's fields are all satisfied, painting anything that is not.
 *
 * For a form that submits itself — see form.sdValidate above. Answers true for
 * a form that never opted into validation, because "no rules" is not the same
 * as "failed".
 */
export function validateForm(form) {
    return typeof form?.sdValidate === 'function' ? form.sdValidate() : true;
}

export function initLiveValidation(root = document) {
    root.querySelectorAll('[data-validate-form]').forEach((form) => {
        if (form.dataset.liveValidation) {
            return;
        }

        form.dataset.liveValidation = '1';

        /* The browser's own bubble — "Please fill out this field" — says the
           same thing this file is about to say, in wording nobody here chose
           and in a place nothing else on the page appears. One message per
           problem: the one written beneath the field. */
        form.noValidate = true;

        const messages = messagesFor(form);

        const validate = (field) => {
            const message = failureFor(field, messages);
            paint(field, message);

            return !message;
        };

        fieldsOf(form).forEach((field) => {
            /* On leaving the field, never before: a form that turns red as it
               is opened is telling somebody off for nothing. */
            field.addEventListener('blur', () => {
                validate(field);
                remoteCheck(field, messages);
            });

            /* And live once it is already wrong, so a correction clears the
               message as it becomes true rather than making the reader leave
               the field to find out they have fixed it. */
            const event = field.type === 'checkbox' || field.type === 'radio' || field.tagName === 'SELECT'
                ? 'change'
                : 'input';

            field.addEventListener(event, () => {
                if (field.getAttribute('aria-invalid')) {
                    validate(field);
                }
            });
        });

        /**
         * A combo box writes into a hidden input, so the blur that matters
         * happens on a control the reader never focuses. Its own event is
         * what says the answer changed.
         */
        form.addEventListener('sd:combo-change', (event) => {
            const field = event.target.closest('[data-rules]');

            if (field) {
                validate(field);
            }
        });

        /**
         * The same check, callable by a form that submits itself.
         *
         * Several screens post over fetch so that a failure keeps everything
         * typed into the page. Their handler is registered while the document
         * is parsed and this one is registered on DOMContentLoaded, so this
         * one runs second — and stopImmediatePropagation cannot stop a
         * handler that has already run. A form in that position asks first.
         *
         * Published on the element rather than in a registry so it cannot
         * outlive the form it belongs to.
         */
        form.sdValidate = () => {
            const failed = fieldsOf(form).filter((field) => !validate(field));

            if (failed.length > 0) {
                focusFirst(failed[0], form);
            }

            return failed.length === 0;
        };

        form.addEventListener('submit', (event) => {
            if (form.sdValidate()) {
                return;
            }

            event.preventDefault();
            /* Stopped here, so no other submit handler goes on to disable the
               buttons of a form that is not being submitted. */
            event.stopImmediatePropagation();
        });
    });
}
