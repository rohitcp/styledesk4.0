/**
 * The Tips card on the service form.
 *
 * Three jobs, all of them about what is worth showing:
 *
 * - everything below "Accept tips for this service" only means anything while
 *   that switch is on, so it goes away when it is not;
 * - a service either follows the business default or names a flat sum, so the
 *   amount field and the sentence explaining the default take turns — an
 *   empty box beside "follows the default" only invites a number that would
 *   then outrank it;
 * - a quick pick fills the box rather than replacing it.
 *
 * The fields keep posting while hidden — they are still in the form — so
 * switching off and on again does not cost somebody the tip they had set.
 * Same reasoning as the resource list beside it.
 */
export function initServiceTips(root = document) {
    root.querySelectorAll('[data-tip-card]').forEach((card) => {
        const toggle = card.querySelector('[data-tip-toggle] input[type="checkbox"]');
        const fields = card.querySelector('[data-tip-fields]');

        if (!toggle || !fields) {
            return;
        }

        const sync = () => {
            fields.hidden = !toggle.checked;
        };

        toggle.addEventListener('change', sync);
        sync();

        const types = card.querySelectorAll('[data-tip-type] input[type="radio"]');
        const presets = card.querySelectorAll('[data-tip-preset]');
        const value = card.querySelector('[data-tip-value]');
        const amount = card.querySelector('[data-tip-amount]');
        const note = card.querySelector('[data-tip-default-note]');

        if (!types.length || !value) {
            return;
        }

        const isFixed = () => card.querySelector('[data-tip-type] input[type="radio"]:checked')?.value === 'fixed';

        const paint = () => {
            const fixed = isFixed();

            /* The amount and the sentence explaining the default are the two
               halves of the same answer, so exactly one of them shows. */
            if (amount) {
                amount.hidden = !fixed;
            }

            if (note) {
                note.hidden = fixed;
            }

            presets.forEach((preset) => {
                preset.classList.toggle('is-active', preset.dataset.tipPreset === value.value.trim());
            });
        };

        types.forEach((type) => type.addEventListener('change', paint));
        value.addEventListener('input', paint);

        presets.forEach((preset) => {
            preset.addEventListener('click', () => {
                value.value = preset.dataset.tipPreset;
                value.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        paint();
    });
}
