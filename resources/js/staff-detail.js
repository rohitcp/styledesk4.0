/**
 * The disclosure panels on the staff member's tabs.
 *
 * Two panels that are hidden until asked for — "Add Services" and the Shift
 * Rule panel on the schedule tab. A plain disclosure: both hold real forms
 * that work with no JavaScript at all, so this only decides whether they are
 * on screen.
 *
 * Hidden rather than absent, so a submission the server refuses comes back
 * with the panel open and the message where the reader left it — the panel is
 * revealed on load when its form is carrying an error.
 */
function disclose(root, toggleSelector, panelId, cancelSelector) {
    const toggle = root.querySelector(toggleSelector);
    const panel = root.querySelector(`#${panelId}`);

    if (!toggle || !panel || toggle.dataset.discloseReady) {
        return;
    }

    toggle.dataset.discloseReady = '1';

    const set = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', () => set(panel.hidden));

    /* Every way out, not just the first: a panel drawn as a dialog closes
       from its scrim, its × and its Cancel, and querySelector would have
       wired up whichever of the three happened to come first in the DOM. */
    root.querySelectorAll(cancelSelector).forEach((cancel) => {
        cancel.addEventListener('click', () => set(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            set(false);
        }
    });

    /* Open on load when the server refused it: a message inside a collapsed
       panel is a message nobody reads. */
    if (panel.querySelector('.text-danger:not([hidden])')) {
        set(true);
    }
}

export function initStaffDetail(root = document) {
    disclose(root, '[data-add-services-toggle]', 'addServices', '[data-add-services-cancel]');
    disclose(root, '[data-rule-toggle]', 'shiftRulePanel', '[data-rule-cancel]');
    disclose(root, '[data-period-toggle]', 'schedulePeriodPanel', '[data-period-cancel]');
    disclose(root, '[data-assign-toggle]', 'assignSchedulePanel', '[data-assign-cancel]');
}
