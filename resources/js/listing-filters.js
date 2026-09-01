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
/**
 * Which filters this page has, read from the page.
 *
 * Named lists used to live here, which meant this file knew the clients
 * screen by heart: a second listing got chips for the three filters clients
 * happen to share with it and none for its own. The controls already say what
 * they are — a combo carries its name and whether it takes one value in its
 * props — so they are asked instead of described.
 */
function readFilters(form) {
    const many = [];
    const one = [];
    const combos = [];

    form.querySelectorAll('[data-vue-component="MultiSelect"][data-props]').forEach((el) => {
        let props;

        try {
            props = JSON.parse(el.dataset.props);
        } catch (error) {
            return;
        }

        if (!props.name) {
            return;
        }

        combos.push(props.name);
        (props.single ? one : many).push(props.name);
    });

    /* Filters that are not dropdowns at all — the stat cards post theirs
       through hidden inputs, and they earn a chip like any other. */
    form.querySelectorAll('[data-filter-labels]').forEach((el) => {
        const name = el.dataset.filterLabels;

        if (!many.includes(name) && !one.includes(name)) {
            one.push(name);
        }
    });

    return { many, one, combos };
}

export function initListingFilters(root = document) {
    const row = root.querySelector('[data-active-filters]');
    const form = row?.closest('form');

    if (!row || !form) {
        return;
    }

    const chips = row.querySelector('[data-active-chips]');
    const grid = document.querySelector('[data-grid]');
    const { many: FILTERS, one: SINGLES, combos: COMBOS } = readFilters(form);

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

        /* A single-value dropdown is still an island: told to drop the value
           rather than written to, or its button carries on showing what the
           chip just removed. */
        if (COMBOS.includes(name)) {
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
        /* Under the minimum is no term at all — the same rule the box
           applies as it is typed, so a reload from anywhere else agrees with
           what the reader last saw. */
        const typed = form.querySelector('[name="search"]')?.value.trim() ?? '';
        const search = typed.length >= 2 ? typed : '';

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
            /* The path only. The server bakes the filters it rendered with
               into `data-url`, so appending to it produced a second question
               mark — "?search=mas?status=active" — and everything after the
               first one arrived as part of the search term. Harmless while
               the page was only ever loaded bare; reachable the moment a
               search rewrote the address and somebody reloaded it.

               Nothing is lost by dropping it: every filter is read back off
               the page a few lines above. */
            const base = new URL(grid.dataset.url, window.location.origin);

            grid.styledeskGrid.setData(query ? `${base.pathname}?${query}` : base.pathname);
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

        /* The hidden inputs behind the single-value filters: the islands are
           cleared by the event above, but a filter posted through a plain
           input has nothing listening for it. */
        SINGLES.forEach((name) => {
            const field = form.querySelector(`[name="${name}"]`);

            if (field) {
                field.value = '';
            }
        });

        window.setTimeout(() => {
            paint();
            reload();
        }, 0);
    }

    /* ------------------------------------------------- the search box ---

       Typed, not submitted.

       It used to need Enter or a button press, which is a step nobody takes
       until they have already given up scrolling. Now the list narrows as the
       reader types — on a debounce, so a six-letter word is one request
       rather than six.

       Two characters before it starts: one letter matches most of the list,
       and a request that returns almost everything is a request that answered
       nothing. Clearing goes straight through, because "show me all of them
       again" should not wait for a timer. */
    const SEARCH_DEBOUNCE = 300;
    const SEARCH_MINIMUM = 2;

    const searchField = form.querySelector('[name="search"]');

    if (searchField) {
        /* What the grid was last asked for. Compared against, so a keystroke
           that leaves the effective term unchanged — a trailing space, or a
           second letter while still under the minimum — asks for nothing. */
        let asked = searchField.value.trim();
        let timer = null;

        const busy = document.querySelector('[data-search-busy]');
        const clear = form.querySelector('[data-search-clear]');

        /* Nothing, or something worth asking about. A term under the minimum
           is treated as no term at all rather than as a narrower one: the
           reader is mid-word, and the list they want back is the whole one. */
        const effective = () => {
            const typed = searchField.value.trim();

            return typed.length >= SEARCH_MINIMUM ? typed : '';
        };

        function paintSearch(loading = false) {
            if (clear) {
                clear.hidden = searchField.value === '';
            }

            if (busy) {
                busy.hidden = ! loading;
            }
        }

        function runSearch() {
            const term = effective();

            if (term === asked) {
                paintSearch(false);

                return;
            }

            asked = term;
            paintSearch(true);

            /* setData starts the list again at page one, which is what a new
               search means: page 3 of the old results is not page 3 of
               these. */
            reload();
        }

        searchField.addEventListener('input', () => {
            paintSearch(false);
            window.clearTimeout(timer);

            /* Cleared entirely: the whole list comes straight back rather
               than after a wait nobody expects for undoing something. */
            if (searchField.value.trim() === '') {
                runSearch();

                return;
            }

            timer = window.setTimeout(runSearch, SEARCH_DEBOUNCE);
        });

        /* Enter still works — it just no longer has to. Prevented so the form
           does not navigate away from a list already showing the answer. */
        searchField.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                window.clearTimeout(timer);
                runSearch();
            }

            if (event.key === 'Escape' && searchField.value !== '') {
                searchField.value = '';
                window.clearTimeout(timer);
                runSearch();
            }
        });

        /* Delegated: there are two of these — the cross inside the field, and
           the one the grid draws when a search has emptied the list — and the
           second is replaced on every load, so a listener bound to it once
           would stop working the first time the list redrew. */
        document.addEventListener('click', (event) => {
            if (! event.target.closest('[data-search-clear]')) {
                return;
            }

            searchField.value = '';
            window.clearTimeout(timer);
            runSearch();
            searchField.focus();
        });

        /* The grid says when it has finished, whatever finished it. */
        document.addEventListener('styledesk:grid-loaded', () => paintSearch(false));

        paintSearch(false);
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
