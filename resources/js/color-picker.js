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
 * The service's one deposit rule, and what it comes to on each price.
 *
 * The rule used to be configured on every price, which meant a business
 * pricing in three currencies answered the same question three times and
 * could answer it three different ways. It is asked once now, and this keeps
 * the price rows showing what that one answer works out to.
 */
export function initDepositToggles(root = document) {
    const card = root.querySelector('[data-deposit-card]');

    if (!card) {
        return;
    }

    const fields = card.querySelector('[data-deposit-fields]');
    const percentField = card.querySelector('[data-deposit-percent-field]');
    const amountFields = card.querySelector('[data-deposit-amount-fields]');
    const percentInput = card.querySelector('[data-deposit-percent]');
    const typeHolder = card.querySelector('[data-deposit-type]');

    const on = () => card.querySelector('[data-deposit-toggle] input[type="checkbox"]')?.checked ?? false;

    /* Re-queried rather than held: the combo is a Vue island and the hidden
       input it posts through is not the node that was there at load — and
       for the first moments of the page there is no such node at all, which
       is why the card states the saved type as a fallback. Reading 'percent'
       in that window would blank a saved fixed deposit's figures. */
    const type = () => typeHolder?.querySelector('input[type="hidden"]')?.value
        || card.dataset.depositSavedType
        || 'percent';

    /* Trailing zeroes off a percentage — "20%" rather than "20.00%" — and two
       places kept on money, where they are how the amount is read. */
    const trim = (number) => String(Number(number.toFixed(2)));
    const money = (symbol, minorish) => symbol + minorish.toFixed(2);

    const apply = () => {
        const enabled = on();
        const fixed = type() === 'fixed';

        if (fields) {
            fields.hidden = !enabled;
        }

        if (percentField) {
            percentField.hidden = fixed;
        }

        if (amountFields) {
            amountFields.hidden = !fixed;
        }


        root.querySelectorAll('[data-price-row]').forEach((row) => {
            const preview = row.querySelector('[data-deposit-preview]');

            if (!preview) {
                return;
            }

            const words = describe(row, enabled, fixed, preview);

            preview.hidden = words === null;
            preview.textContent = words === null
                ? ''
                : (preview.dataset.pattern || '__AMOUNT__').replace('__AMOUNT__', words);
        });
    };

    /**
     * What the rule comes to on one price, in words, or nothing.
     *
     * A percentage says both halves — "20% · $12.00" — because the rate is
     * the rule and the figure is what the client will actually be asked for.
     * Nothing at all where the sum cannot be worked out yet: a percentage of
     * a price nobody has typed is not a figure worth printing.
     */
    const describe = (row, enabled, fixed, preview) => {
        if (!enabled) {
            return null;
        }

        const symbol = preview.dataset.symbol || '';
        const price = Number(row.querySelector('input[name^="price["]')?.value);

        /* Nothing where there is no price: a currency this service is not
           priced in stores no deposit either. */
        if (!Number.isFinite(price) || price <= 0) {
            return null;
        }

        if (fixed) {
            const amount = Number(
                card.querySelector(`[data-deposit-amount][name="deposit_amount[${row.dataset.currency}]"]`)?.value,
            );

            return Number.isFinite(amount) && amount > 0 ? money(symbol, amount) : null;
        }

        const percent = Number(percentInput?.value);

        return Number.isFinite(percent) && percent > 0
            ? `${trim(percent)}% · ${money(symbol, (price * percent) / 100)}`
            : null;
    };

    /* input rather than change on the numbers, so the figure follows the
       price as it is typed rather than when the field is left. */
    root.addEventListener('input', (event) => {
        if (event.target.closest('[data-deposit-percent], [data-deposit-amount], [data-price-row] input')) {
            apply();
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.closest('[data-deposit-toggle]')) {
            apply();
        }
    });

    /* The type combo posts a hidden input rather than firing events on a
       field, so the DOM is watched instead of listened to. Same reason the
       email editor watches its own controls. */
    if (typeHolder) {
        /* childList as well as attributes: the island mounting is what puts
           the hidden input there in the first place, and that is a child
           being added rather than a value changing. Watching only attributes
           meant the first answer after mount was never noticed. */
        new MutationObserver(apply).observe(typeHolder, {
            attributes: true,
            childList: true,
            subtree: true,
            attributeFilter: ['value'],
        });
    }

    apply();
}
