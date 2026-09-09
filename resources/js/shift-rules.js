/**
 * The shift-rule form's conditional fields.
 *
 * Several settings each govern fields that only mean anything while they are
 * on: which branches, how long the break is, the overtime figures, the
 * periods the day is divided into. Hidden rather than disabled, because a
 * disabled field still occupies the form and still reads as something the
 * reader has failed to fill in — and because the values keep posting while
 * hidden, so switching a setting off and on again does not cost the reader
 * what they had already typed.
 *
 * The server decides the same thing again — a scope of "specific" with no
 * locations is refused there — so this is about what the reader is asked, not
 * about what is allowed.
 */

/** Show or hide a panel, and clear its error when it goes away. */
function reveal(panel, visible) {
    if (!panel) {
        return;
    }

    panel.hidden = !visible;

    if (!visible) {
        panel.querySelectorAll('[data-error-for]').forEach((box) => {
            box.textContent = '';
            box.hidden = true;
        });
    }
}

/**
 * The feature switch at the top of the page.
 *
 * Submits itself when it moves, because a switch that needs a Save button
 * beside it is two controls for one decision — and the reader has already
 * said what they want by moving it. The button inside <noscript> is the
 * fallback, not a second way to do it.
 */
export function initShiftRuleFeatureToggle(root = document) {
    const toggle = root.querySelector('[data-feature-toggle]');

    if (!toggle || toggle.dataset.featureReady) {
        return;
    }

    toggle.dataset.featureReady = '1';
    toggle.addEventListener('change', () => toggle.closest('[data-feature-form]')?.submit());
}

export function initShiftRuleForm(root = document) {
    const form = root.querySelector('#shiftRuleForm');

    if (!form || form.dataset.shiftRuleReady) {
        return;
    }

    form.dataset.shiftRuleReady = '1';

    /**
     * The combo boxes are Vue islands whose answer lives in a hidden input, so
     * there is no change event to listen for on a <select>. They announce a
     * selection on the DOM instead — see MultiSelect.vue — which is what this
     * follows.
     */
    form.addEventListener('styledesk:selection', (event) => {
        const { name, values } = event.detail ?? {};

        if (name === 'location_scope') {
            reveal(form.querySelector('[data-scope-locations]'), values[0] === 'specific');
        }

        if (name === 'break_type') {
            const type = values[0];

            reveal(form.querySelector('[data-break-duration]'), type === 'duration');

            /* The fixed pair is `display: contents` so the two pickers sit in
               the grid beside the type rather than in a box of their own —
               and `hidden` is what a contents box still honours. */
            reveal(form.querySelector('[data-break-fixed]'), type === 'fixed');
        }
    });

    /**
     * The switches that govern a panel below them.
     *
     * Each pair is a setting and the questions that only mean anything while
     * it is on: overtime and its figures, split shifts and the periods the day
     * is divided into, one person on several periods and the ceiling on how
     * many.
     */
    [
        ['[data-overtime-toggle]', '[data-overtime-fields]'],
        ['[data-split-toggle]', '[data-split-fields]'],
        ['[data-same-employee-toggle]', '[data-same-employee-fields]'],
    ].forEach(([toggleSelector, panelSelector]) => {
        const toggle = form.querySelector(`${toggleSelector} input[type="checkbox"]`);
        const panel = form.querySelector(panelSelector);

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('change', () => reveal(panel, toggle.checked));
    });
}
