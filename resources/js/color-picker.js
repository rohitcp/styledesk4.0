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
}
