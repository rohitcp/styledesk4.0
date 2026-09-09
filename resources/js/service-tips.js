/**
 * The Tips card on the service form.
 *
 * Three jobs, all of them about what is worth showing:
 *
 * - everything below "Accept tips for this service" only means anything while
 *   that switch is on, so it goes away when it is not;
 * - the quick picks belong to the tip type — percentages where the tip is a
 *   percentage, flat sums where it is a sum — and the wrong set beside a
 *   number is worse than no set at all;
 * - a pick fills the box rather than replacing it, so a service wanting 18
 *   where the business offers 15, 20 and 25 can still say so.
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

        if (!types.length || !presets.length || !value) {
            return;
        }

        /* "Follows the default" is a percentage until the business says
           otherwise, so its quick picks are the percentages — the same ones
           the till would offer if this service never disagreed. */
        const chosenType = () => {
            const checked = card.querySelector('[data-tip-type] input[type="radio"]:checked');

            return checked && checked.value === 'fixed' ? 'fixed' : 'percent';
        };

        const paint = () => {
            const type = chosenType();

            presets.forEach((preset) => {
                preset.hidden = preset.dataset.tipPresetFor !== type;
                preset.classList.toggle(
                    'is-active',
                    !preset.hidden && preset.dataset.tipPreset === value.value.trim(),
                );
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
