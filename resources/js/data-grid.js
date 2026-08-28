/**
 * The listing grid, shared by every module that has a list.
 *
 * Tabulator draws it, the server decides what is in it. Search, filters and
 * paging stay on the server for the reason they always have: the query is
 * what knows which rows this business — and this member of staff — may see,
 * and a browser that filtered a full list would first have to be handed one.
 *
 * Nothing here knows what a client or a service is. A page supplies the
 * columns and the labels through data-config, and each row supplies its own
 * actions menu, so a new listing is a controller and a blade rather than a
 * second copy of this file.
 */

/** Text that reaches the DOM, escaped once, here. */
function escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

/** An em dash, for a field the business simply has not filled in. */
function orDash(value) {
    return value ? escape(value) : '<span class="text-faint">—</span>';
}

/**
 * The formatters a column may ask for by name.
 *
 * A name rather than a function, because the column list arrives as JSON from
 * a blade: a page cannot hand over a closure, and it should not have to.
 */
const FORMATTERS = {
    /* The first column: what the row is, with the thing that identifies it
       beside rather than beneath — a second line doubles the height of every
       row in the table to carry one short string. */
    primary: (cell) => {
        const row = cell.getRow().getData();
        const badge = row.primary_badge
            ? `<span class="styledesk_gridclient__ref">${escape(row.primary_badge)}</span>`
            : '';
        const avatar = row.initials === undefined || row.initials === null
            ? ''
            : `<span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">${escape(row.initials)}</span>`;
        const swatch = row.color
            ? `<span class="styledesk_servicedot shrink-0" style="--service-color: ${escape(row.color)}" aria-hidden="true"></span>`
            : '';

        return `<span class="styledesk_gridclient">
            ${avatar}${swatch}
            <span class="styledesk_gridclient__name">${escape(cell.getValue())}</span>
            ${badge}
        </span>`;
    },

    text: (cell) => orDash(cell.getValue()),

    /* A status or a state, in the colour it is given everywhere else. The
       class travels with the row so the palette is decided once, on the
       server, rather than mapped again per listing. */
    badge: (cell) => {
        const row = cell.getRow().getData();
        const tone = row[`${cell.getColumn().getField()}_class`] ?? '';
        const value = cell.getValue();

        return value ? `<span class="styledesk_badge ${escape(tone)}">${escape(value)}</span>` : orDash(value);
    },
};

/**
 * Loaded only where it is used.
 *
 * Tabulator is 450KB of the bundle; every other screen in StyleDesk draws its
 * tables by hand and has no reason to download a grid library to do it. The
 * dynamic import puts it in a chunk of its own that arrives with this page.
 */
export async function initDataGrid(root = document) {
    const elements = Array.from(root.querySelectorAll('[data-grid]'));

    if (!elements.length) {
        return null;
    }

    const { TabulatorFull: Tabulator } = await import('tabulator-tables');

    return elements.map((el) => mount(Tabulator, el)).filter(Boolean)[0] ?? null;
}

function mount(Tabulator, el) {
    if (el.dataset.gridReady) {
        return null;
    }

    el.dataset.gridReady = '1';

    const config = JSON.parse(el.dataset.config);
    const labels = config.labels;
    const menu = buildMenu();

    const columns = config.columns.map((column) => {
        const definition = {
            title: column.title ?? '',
            field: column.field,
            responsive: column.responsive ?? 1,
            headerSort: column.sortable ?? false,
        };

        if (column.width) {
            definition.width = column.width;
        } else {
            definition.widthGrow = column.grow ?? 1;
            definition.minWidth = column.min ?? 120;
        }

        if (column.muted) {
            definition.cssClass = 'is-muted';
        }

        if (column.type === 'actions') {
            return {
                ...definition,
                title: '',
                width: column.width ?? 56,
                hozAlign: 'right',
                headerSort: false,
                responsive: 0,
                formatter: (cell) => {
                    const row = cell.getRow().getData();

                    return `<button type="button" class="styledesk_rowmenu__button" data-grid-menu
                        aria-haspopup="true" aria-expanded="false"
                        aria-label="${escape((labels.actions_for ?? '').replace(':name', row[config.name_field ?? 'name']))}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                        </svg>
                    </button>`;
                },
                cellClick: (event, cell) => {
                    const button = event.target.closest('[data-grid-menu]');

                    if (!button) {
                        return;
                    }

                    // Stopped here so the row's own click does not navigate
                    // out from under a menu that is opening.
                    event.stopPropagation();
                    menu.toggle(button, cell.getRow().getData());
                },
            };
        }

        definition.formatter = FORMATTERS[column.type] ?? FORMATTERS.text;

        return definition;
    });

    const table = new Tabulator(el, {
        // The rows come from the page's own URL, so whatever the reader
        // searched or filtered for is already part of the request.
        ajaxURL: config.url ?? el.dataset.url,
        ajaxParams: config.params ?? {},

        /* Paged rather than endlessly scrolled. A hundred rows is about as
           far as anyone reads before narrowing the search instead, and a
           pager gives them a place to stand: "page 3 of 5" is somewhere you
           can come back to, where "keep scrolling" is not.

           Remote, because the server is what filters and orders — asking the
           browser to page a list it does not hold would mean sending it the
           whole list first. */
        pagination: true,
        paginationMode: 'remote',
        paginationSize: config.page_size ?? 100,
        paginationCounter: (pageSize, currentRow, currentPage, totalRows) => {
            if (!totalRows) {
                return '';
            }

            return labels.showing
                .replace(':from', currentRow)
                .replace(':to', Math.min(currentRow + pageSize - 1, totalRows))
                .replace(':total', totalRows);
        },

        layout: 'fitColumns',

        // Every cell centred against its row, so a badge, an avatar and a
        // line of text all sit on the same line rather than each finding its
        // own top edge.
        columnDefaults: { vertAlign: 'middle' },

        /**
         * The count above the grid, taken from the response that drew the
         * rows. Reading it from the same payload is what keeps the two in
         * step: a total worked out anywhere else eventually describes a
         * different list.
         */
        ajaxResponse: (url, params, response) => {
            const total = response.total ?? 0;
            const counter = document.querySelector('[data-result-count]');

            if (counter) {
                counter.textContent = total === 0
                    ? labels.results.zero
                    : total === 1
                        ? labels.results.one
                        : labels.results.many.replace(':count', total.toLocaleString());
            }

            return response;
        },
        placeholder: `<span class="block text-[13px] text-sub">${escape(labels.empty)}</span>
            <button type="button" class="styledesk_action styledesk_action--sm mt-3" data-grid-clear>${escape(labels.clear_filters)}</button>`,
        index: 'id',

        /* Columns leave in the order the page set out, worst first: the ones
           a reader needs are the last to go. */
        responsiveLayout: 'hide',

        columns,
    });

    /* A click anywhere on a row opens it — except on the actions button,
       which has its own job, and except when the reader was selecting text or
       asking for a new tab. */
    table.on('rowClick', (event, row) => {
        const url = row.getData().url;

        if (!url) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey) {
            window.open(url, '_blank');

            return;
        }

        if (event.target.closest('[data-grid-menu]')) {
            return;
        }

        if (window.getSelection && String(window.getSelection())) {
            return;
        }

        window.location = url;
    });

    /* Reachable from the page's filter row, which reloads it when a chip is
       removed. Hung on the element rather than exported as a singleton
       because the element is what the page already has a handle on. */
    el.styledeskGrid = table;

    return table;
}

/**
 * The row actions menu.
 *
 * One panel reused by every row rather than one per row: Tabulator draws and
 * discards rows as the reader scrolls, and a menu that belonged to a row
 * would be thrown away with it — sometimes while open.
 *
 * The items come from the row itself. Which actions a row offers, what they
 * are called and what they warn about are decisions the server has already
 * made in the reader's own language, and re-deciding them here would be a
 * second place for a permission rule to live.
 */
function buildMenu() {
    const menu = document.createElement('div');
    menu.className = 'styledesk_rowmenu__pop';
    menu.setAttribute('role', 'menu');
    menu.hidden = true;
    document.body.appendChild(menu);

    let current = null;

    const close = () => {
        menu.hidden = true;
        if (current) {
            current.button.setAttribute('aria-expanded', 'false');
        }
        current = null;
    };

    const render = (item) => {
        if (item.separator) {
            return '<span class="styledesk_rowmenu__rule" role="separator"></span>';
        }

        if (item.disabled) {
            return `<span class="styledesk_rowmenu__item opacity-50 cursor-not-allowed" aria-disabled="true">${escape(item.label)}</span>`;
        }

        /* An entry the page handles itself — one that opens a dialog rather
           than going anywhere. Announced rather than acted on: what a
           resource block is, is the resources page's business, not this
           file's. */
        if (item.event) {
            return `<button type="button" role="menuitem" data-grid-event
                class="styledesk_rowmenu__item w-full"
                data-event="${escape(item.event)}"
                data-payload="${escape(JSON.stringify(item.payload ?? {}))}"
                >${escape(item.label)}</button>`;
        }

        if (!item.method || item.method === 'GET') {
            return `<a href="${escape(item.url)}" class="styledesk_rowmenu__item" role="menuitem">${escape(item.label)}</a>`;
        }

        /* Anything that changes something is a button that posts, never a
           link: a link that mutates is a link a browser may follow while
           prefetching. */
        return `<button type="button" role="menuitem" data-grid-action
            class="styledesk_rowmenu__item w-full ${item.danger ? 'styledesk_rowmenu__item--danger' : ''}"
            data-action="${escape(item.url)}"
            data-method="${escape(item.method)}"
            ${item.confirm ? `data-confirm="${escape(item.confirm)}"
            data-confirm-title="${escape(item.confirm_title ?? item.label)}"
            data-confirm-label="${escape(item.confirm_label ?? item.label)}"
            data-confirm-tone="${escape(item.tone ?? 'brand')}"` : ''}
            >${escape(item.label)}</button>`;
    };

    const open = (button, row) => {
        menu.innerHTML = (row.menu ?? []).map(render).join('');
        menu.hidden = false;

        // Positioned against the button because the panel is fixed; flipped
        // up when there is no room below it.
        const rect = button.getBoundingClientRect();
        const below = window.innerHeight - rect.bottom;
        const top = below < menu.offsetHeight + 12 ? rect.top - menu.offsetHeight - 4 : rect.bottom + 4;

        menu.style.top = `${Math.max(8, top)}px`;
        menu.style.left = `${Math.max(8, rect.right - menu.offsetWidth)}px`;

        button.setAttribute('aria-expanded', 'true');
        current = { button, row };
    };

    /* Built and submitted here rather than posted with fetch, so the
       confirmation dialog, the CSRF token and the redirect that follows are
       the same ones every other form on the page uses. */
    menu.addEventListener('click', (event) => {
        const announced = event.target.closest('[data-grid-event]');

        if (announced) {
            document.dispatchEvent(new CustomEvent('styledesk:grid-action', {
                detail: {
                    event: announced.dataset.event,
                    payload: JSON.parse(announced.dataset.payload || '{}'),
                },
            }));

            close();

            return;
        }

        const action = event.target.closest('[data-grid-action]');

        if (!action) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action.dataset.action;
        form.className = 'hidden';

        const token = document.querySelector('meta[name="csrf-token"]');
        form.innerHTML = `<input type="hidden" name="_token" value="${escape(token ? token.content : '')}">`;

        if (action.dataset.method !== 'POST') {
            form.innerHTML += `<input type="hidden" name="_method" value="${escape(action.dataset.method)}">`;
        }

        document.body.appendChild(form);
        form.submit();
    });

    document.addEventListener('click', close);
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    window.addEventListener('resize', close);
    window.addEventListener('scroll', close, true);

    return {
        toggle(button, row) {
            if (current && current.button === button) {
                close();

                return;
            }

            close();
            open(button, row);
        },
    };
}
