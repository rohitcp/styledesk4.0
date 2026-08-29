/**
 * "Requires a Resource" and the list it governs.
 *
 * Two jobs, both about the same pair of controls:
 *
 *   - the list of resources is shown only while the switch is on;
 *   - the form will not post with the switch on and the list empty.
 *
 * The second is the browser's copy of a rule the server also enforces, and
 * the server is the one that decides. This exists so the reader is told where
 * the problem is before a round trip reloads a five-section form.
 */

/** The message the server would send back, handed over by the markup. */
function messageFor(container) {
    return container.dataset.resourceMessage || '';
}

function fieldsOf(container) {
    return container.querySelector('[data-resource-fields]');
}

function toggleOf(container) {
    return container.querySelector('[data-resource-toggle] input[type="checkbox"]');
}

/** How many resources are chosen, read from what the control posts. */
function chosenCount(container) {
    return container.querySelectorAll('[data-resource-fields] input[name="resources[]"]').length;
}

function paint(container, message) {
    const box = container.querySelector('[data-error-for="resources"]');

    if (!box) {
        return;
    }

    box.textContent = message;
    box.hidden = !message;
}

export function initResourceRequirement(root = document) {
    const containers = Array.from(root.querySelectorAll('[data-resource-requirement]'));

    if (containers.length === 0) {
        return;
    }

    containers.forEach((container) => {
        if (container.dataset.resourceReady) {
            return;
        }

        container.dataset.resourceReady = '1';

        const toggle = toggleOf(container);
        const fields = fieldsOf(container);

        if (!toggle || !fields) {
            return;
        }

        toggle.addEventListener('change', () => {
            fields.hidden = !toggle.checked;

            /* Turning the switch off cannot leave a complaint about a list
               that is no longer being asked for. */
            if (!toggle.checked) {
                paint(container, '');
            }
        });

        /* The control announces its own selections on the DOM — see
           MultiSelect.vue — which is the only thing that says a choice has
           been made: the hidden inputs it posts are written by Vue and fire
           no events of their own. */
        container.addEventListener('styledesk:selection', (event) => {
            if (event.detail?.name === 'resources' && event.detail.values.length > 0) {
                paint(container, '');
            }
        });
    });

    /**
     * Checked on the way out, at the document and in the capture phase.
     *
     * Both forms this appears on carry their own submit handler that disables
     * the save button, and those were bound while the page was still parsing
     * — before anything here could run. A listener added later on the form
     * itself would run after them, leaving a form that is not being submitted
     * behind a button that can no longer submit it. Capturing at the document
     * runs first, and stopping propagation there means those handlers never
     * see a submission that is not going to happen.
     */
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const container = form.querySelector?.('[data-resource-requirement]');

        if (!container) {
            return;
        }

        const toggle = toggleOf(container);

        if (!toggle?.checked || chosenCount(container) > 0) {
            paint(container, '');

            return;
        }

        event.preventDefault();
        event.stopPropagation();

        paint(container, messageFor(container));

        fieldsOf(container).hidden = false;
        container.querySelector('[data-resource-fields] button')?.focus();
        container.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, true);
}
