/**
 * The Tips card on the service form.
 *
 * One job: everything below "Accept tips for this service" only means
 * anything while that switch is on, so it goes away when it is not.
 *
 * The fields keep posting while hidden — they are still in the form — so
 * switching off and on again does not cost somebody the percentage they had
 * set. Same reasoning as the resource list beside it.
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
    });
}
