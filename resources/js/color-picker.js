/**
 * The custom colour card.
 *
 * The card is the interaction point: clicking it opens the browser's own
 * colour picker, and what comes back is written onto the radio the form
 * actually posts. The picker input carries no name of its own, so there is
 * never a moment where the two disagree about the chosen colour.
 *
 * Delegated from the document rather than bound per card, so a page with two
 * pickers — or one drawn after load — is covered without being registered.
 */
export function initColorPickers(root = document) {
    root.addEventListener('click', (event) => {
        const card = event.target.closest('[data-custom-color]');

        if (!card) {
            return;
        }

        const picker = card.querySelector('[data-custom-input]');

        /* The label would otherwise hand the click to the first control
           inside it — the radio — which selects an empty colour and opens
           nothing. */
        if (event.target === picker) {
            return;
        }

        event.preventDefault();
        picker.click();
    });

    /* input, not change: dragging around the picker paints the card as the
       reader moves, which is the whole point of choosing a colour. */
    root.addEventListener('input', (event) => {
        const picker = event.target.closest('[data-custom-input]');

        if (!picker) {
            return;
        }

        const card = picker.closest('[data-custom-color]');
        const radio = card.querySelector('[data-custom-radio]');
        const dot = card.querySelector('[data-custom-dot]');

        radio.value = picker.value;
        radio.checked = true;
        dot.style.setProperty('--service-color', picker.value);
        dot.classList.remove('is-empty');
    });
}

/**
 * A price's deposit fields, revealed by its own toggle.
 *
 * Delegated and scoped to the row the toggle is in, so switching the deposit
 * on for one price leaves every other price alone — which is the whole point
 * of the deposit belonging to the price rather than to the service.
 */
export function initDepositToggles(root = document) {
    root.addEventListener('change', (event) => {
        const toggle = event.target.closest('[data-deposit-toggle] input[type="checkbox"]');

        if (!toggle) {
            return;
        }

        const row = toggle.closest('[data-deposit-row]');
        const fields = row?.querySelector('[data-deposit-fields]');

        if (fields) {
            fields.hidden = !toggle.checked;
        }
    });

    syncDepositUnits(root);
}

/**
 * The deposit value field follows the deposit type.
 *
 * An amount is money and wears the currency symbol; a percentage is not, and
 * wearing one would be a field that lies about what it holds — a reader who
 * has just chosen "percentage" and sees a $ in the box will type dollars.
 *
 * The type combo posts a hidden input rather than firing events on a field, so
 * the DOM is watched instead of listened to. Same reason the email editor
 * watches its own controls.
 */
function syncDepositUnits(root) {
    const rows = root.querySelectorAll('[data-deposit-row]');

    if (!rows.length) {
        return;
    }

    const apply = (row) => {
        const type = row.querySelector('[data-deposit-type] input[type="hidden"]')?.value ?? 'percent';
        const field = row.querySelector('[data-deposit-value-field]');

        if (!field) {
            return;
        }

        const fixed = type === 'fixed';
        const input = field.querySelector('[data-deposit-value]');
        const label = field.querySelector('[data-deposit-label]');

        field.querySelector('[data-deposit-prefix]').hidden = !fixed;
        field.querySelector('[data-deposit-suffix]').hidden = fixed;
        input.classList.toggle('styledesk_input--prefixed', fixed);

        /* The label carries the unit too. Somebody filling this in from the
           keyboard never sees the symbol in the box. */
        const wording = fixed ? field.dataset.amountLabel : field.dataset.percentLabel;

        if (wording) {
            label.textContent = wording;
            input.setAttribute('aria-label', wording);
        }
    };

    rows.forEach((row) => {
        apply(row);

        /* Attributes, because Vue writes the hidden input's value as one. */
        new MutationObserver(() => apply(row)).observe(row, {
            attributes: true,
            subtree: true,
            attributeFilter: ['value'],
        });
    });
}
