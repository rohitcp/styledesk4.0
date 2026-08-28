/**
 * The active-filter row on a listing screen.
 *
 * The multi-select controls own what is chosen; this reads them, draws one
 * chip per value, and reloads the grid. Nothing here keeps its own copy of
 * the selection — a second copy is a second thing that can be wrong, and the
 * first symptom would be a chip for a filter the grid is no longer applying.
 *
 * Removing a chip asks the control to drop the value rather than editing the
 * hidden input behind its back, so the control's own count stays right.
 */
/** The controls that hold a list of values. */
const FILTERS = ['location', 'staff', 'tag'];

/** The single-value filters that also earn a chip: status, and the cards. */
const SINGLES = ['status', 'created', 'upcoming'];

export function initClientFilters(root = document) {
    const row = root.querySelector('[data-active-filters]');
    const form = row?.closest('form');

    if (!row || !form) {
        return;
    }

    const chips = row.querySelector('[data-active-chips]');
    const grid = document.querySelector('[data-client-grid]');

    /** What each control holds, read from the inputs it posts. */
    function current() {
        const data = new FormData(form);

        const many = FILTERS.flatMap((name) =>
            data.getAll(`${name}[]`).map((value) => ({ name, value })));

        const one = SINGLES
            .map((name) => ({ name, value: (data.get(name) ?? '').toString() }))
            .filter(({ value }) => value !== '');

        return [...many, ...one];
    }

    /** The label a value is shown under, taken from the control's own list. */
    function labelFor(name, value) {
        const option = form.querySelector(`[data-filter-labels="${name}"]`);
        const labels = option ? JSON.parse(option.dataset.labels) : {};

        return labels[value] ?? value;
    }

    /** Drop one value, whichever kind of control is holding it. */
    function removeValue(name, value) {
        if (FILTERS.includes(name)) {
            // The island owns its selection; asking it to drop the value
            // keeps its own count right.
            document.dispatchEvent(new CustomEvent('styledesk:filter-remove', {
                detail: { name, value },
            }));

            return;
        }

        const field = form.querySelector(`[name="${name}"]`);

        if (field) {
            field.value = '';
        }

        if (name === 'status') {
            document.dispatchEvent(new CustomEvent('styledesk:filter-remove', {
                detail: { name, value },
            }));
        }

        window.setTimeout(() => {
            paint();
            reload();
        }, 0);
    }

    function paint() {
        const active = current();

        paintCards();
        row.hidden = active.length === 0;
        chips.innerHTML = '';

        active.forEach(({ name, value }) => {
            const chip = document.createElement('span');
            chip.className = 'styledesk_chip';
            chip.innerHTML = `<span class="truncate"></span>`;
            chip.firstChild.textContent = labelFor(name, value);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'styledesk_chip__remove';
            remove.setAttribute('aria-label', `${row.dataset.removeLabel} ${labelFor(name, value)}`);
            remove.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>';

            remove.addEventListener('click', () => removeValue(name, value));

            chip.appendChild(remove);
            chips.appendChild(chip);
        });
    }

    /**
     * The grid asks the server again with the filters as they now stand, and
     * the address bar is rewritten to match — so a reload, a bookmark or the
     * Back button all show the list the reader is looking at.
     */
    function reload() {
        const params = new URLSearchParams();
        const search = form.querySelector('[name="search"]')?.value.trim();

        if (search) params.set('search', search);

        // A list filter appends, a single one sets. Appending both would send
        // status twice — once as itself and once as an array — and the second
        // one is not a filter the server knows.
        current().forEach(({ name, value }) => {
            if (FILTERS.includes(name)) {
                params.append(`${name}[]`, value);
            } else {
                params.set(name, value);
            }
        });

        const query = params.toString();

        window.history.replaceState({}, '', query ? `${form.action}?${query}` : form.action);

        if (grid?.styledeskGrid) {
            grid.styledeskGrid.setData(query ? `${grid.dataset.url}?${query}` : grid.dataset.url);
        }
    }

    /* One handler for every way a selection can change: a tick in a dropdown,
       a chip's cross, or Clear all. */
    document.addEventListener('styledesk:selection', (event) => {
        /**
         * After the control has redrawn, not during.
         *
         * The event comes from the island's own watcher, which runs before
         * Vue has written the hidden inputs the row is read from — painting
         * immediately draws the selection as it was a moment ago. A timeout
         * runs after the render queue, whatever is in it.
         */
        window.setTimeout(() => {
            paint();

            /* A control announcing what it mounted with is not a change: the
               grid was already asked for exactly those filters when the page
               loaded, and reloading here would repeat every request on every
               page view. */
            if (!event.detail?.initial) {
                reload();
            }
        }, 0);
    });

    function clearAll() {
        document.dispatchEvent(new CustomEvent('styledesk:filter-clear'));

        const search = form.querySelector('[name="search"]');
        if (search) search.value = '';

        // The single-value status control has no island event to wait for.
        const status = form.querySelector('[name="status"]');
        if (status) status.value = '';

        window.setTimeout(() => {
            paint();
            reload();
        }, 0);
    }

    /* Two ways out of a filtered list: the button beside the chips, and the
       one the grid offers when the filters have left it empty. Delegated,
       because the second is drawn by the grid and replaced on every load. */
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-clear-filters], [data-grid-clear]')) {
            clearAll();
        }
    });

    /* The stat cards. Each is a filter: pressing one narrows the grid the
       same way a dropdown does and leaves a chip behind saying so. Pressing
       the one already applied turns it off, because a card that only ever
       adds a filter is a card you cannot undo from where you pressed it. */
    function paintCards() {
        const active = current();

        document.querySelectorAll('[data-stat-filter]').forEach((card) => {
            const spec = card.dataset.statFilter;

            card.classList.toggle('is-active', spec !== '' && active.some(({ name, value }) => {
                const [key, wanted] = spec.split(':');

                return name === key && (wanted === undefined || value === wanted);
            }));
        });
    }

    document.querySelectorAll('[data-stat-filter]').forEach((card) => {
        card.addEventListener('click', () => {
            const spec = card.dataset.statFilter;

            // The Total card is not a filter — it is the absence of them.
            if (spec === '') {
                clearAll();

                return;
            }

            const [name, wanted] = spec.split(':');
            const value = wanted ?? (name === 'created' ? 'this_month' : '1');
            const on = card.classList.contains('is-active');

            if (on) {
                removeValue(name, value);

                return;
            }

            if (name === 'status') {
                document.dispatchEvent(new CustomEvent('styledesk:filter-set', {
                    detail: { name, values: [value] },
                }));
            }

            const field = form.querySelector(`[name="${name}"]`);

            if (field) {
                field.value = value;
            }

            window.setTimeout(() => {
                paint();
                reload();
            }, 0);
        });
    });

    paint();
}
