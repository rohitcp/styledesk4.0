/**
 * Keeping the chosen shift rule honest about the chosen location.
 *
 * A rule restricted to particular branches cannot be given to somebody at a
 * different one. The server refuses that outright — the form only decides what
 * is drawn — but a reader who picks a rule, then changes the location, would
 * otherwise not find out until they pressed Save.
 *
 * So the selection is dropped the moment it stops being available, and the
 * reason is said where the field is. Dropped rather than silently corrected:
 * choosing the replacement is the reader's decision, not this file's.
 */

/** Which branches each rule is restricted to; an empty list means all. */
function restrictionsFor(card) {
    try {
        return JSON.parse(card.dataset.ruleLocations || '{}');
    } catch (error) {
        return {};
    }
}

function paint(card, message) {
    const box = card.querySelector('[data-error-for="shift_rule_id"]');

    if (!box) {
        return;
    }

    box.textContent = message;
    box.hidden = !message;
}

export function initStaffShiftRule(root = document) {
    const card = root.querySelector('[data-shift-rule-card]');

    if (!card || card.dataset.shiftRuleReady) {
        return;
    }

    card.dataset.shiftRuleReady = '1';

    const form = card.closest('form');
    const restrictions = restrictionsFor(card);

    if (!form) {
        return;
    }

    /**
     * Both controls announce their selections on the document — see
     * MultiSelect.vue — which is the only thing that says a combo's answer
     * changed: the hidden input it posts is written by Vue and fires no
     * events of its own.
     */
    document.addEventListener('styledesk:selection', (event) => {
        const { name, values } = event.detail ?? {};

        if (name !== 'location_id' && name !== 'shift_rule_id') {
            return;
        }

        /* Read from the inputs rather than kept here: a copy of the answer is
           a second thing that can be wrong, and the first symptom would be a
           message about a location nobody is on. */
        const location = form.querySelector('input[name="location_id"]')?.value || '';
        const rule = name === 'shift_rule_id'
            ? (values[0] ?? '')
            : (form.querySelector('input[name="shift_rule_id"]')?.value || '');

        if (!rule) {
            paint(card, '');

            return;
        }

        const branches = restrictions[rule];

        /* Unknown to the map means it was not among the active rules — the
           one already on this person, kept selectable so an edit does not
           silently drop it. Left alone: it is theirs already. */
        if (branches === undefined || branches.length === 0) {
            paint(card, '');

            return;
        }

        if (branches.map(String).includes(String(location))) {
            paint(card, '');

            return;
        }

        document.dispatchEvent(new CustomEvent('styledesk:filter-set', {
            detail: { name: 'shift_rule_id', values: [] },
        }));

        paint(card, card.dataset.mismatchMessage || '');
    });
}
