/**
 * The clients grid.
 *
 * Tabulator draws it, the server decides what is in it. Search, filters and
 * paging stay on the server for the reason they always have: the query is
 * what knows which clients this business — and this member of staff — may
 * see, and a browser that filtered a full list would first have to be handed
 * one.
 *
 * Rows arrive a page at a time as the reader scrolls, so a salon with four
 * thousand clients sends twenty rows to draw twenty.
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
 * Loaded only where it is used.
 *
 * Tabulator is 450KB of the bundle; every other screen in StyleDesk draws its
 * tables by hand and has no reason to download a grid library to do it. The
 * dynamic import puts it in a chunk of its own that arrives with this page.
 */
export async function initClientGrid(root = document) {
    const el = root.querySelector('[data-client-grid]');

    if (!el || el.dataset.gridReady) {
        return null;
    }

    el.dataset.gridReady = '1';

    const { TabulatorFull: Tabulator } = await import('tabulator-tables');

    const labels = JSON.parse(el.dataset.labels);
    const can = JSON.parse(el.dataset.can);
    const menu = buildMenu(labels, can);

    const table = new Tabulator(el, {
        // The rows come from the page's own URL, so whatever the reader
        // searched or filtered for is already part of the request.
        ajaxURL: el.dataset.url,
        ajaxParams: JSON.parse(el.dataset.params),

        /* Paged rather than endlessly scrolled. A hundred rows is about as
           far as anyone reads before narrowing the search instead, and a
           pager gives them a place to stand: "page 3 of 5" is somewhere you
           can come back to, where "keep scrolling" is not.

           Remote, because the server is what filters and orders — asking the
           browser to page a list it does not hold would mean sending it the
           whole list first. */
        pagination: true,
        paginationMode: 'remote',
        paginationSize: 100,
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

        /**
         * No height of its own: the table draws every row it holds and the
         * page scrolls. One scrollbar for the screen rather than a second
         * one inside it — a grid that scrolls separately is a grid whose
         * last row can be reached two different ways and found by neither.
         */

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

        /* Columns leave in the order the specification set out, worst first:
           the ones a receptionist needs — who, how to reach them, what is
           next — are the last to go. */
        responsiveLayout: 'hide',

        columns: [
            {
                title: labels.columns.client,
                field: 'name',
                widthGrow: 3,
                minWidth: 200,
                responsive: 0,
                formatter: (cell) => {
                    const row = cell.getRow().getData();

                    /* Name and reference on one line: the reference is how a
                       client is quoted on the phone, so it belongs beside the
                       name rather than in a second line that doubles the
                       height of every row. */
                    return `<span class="styledesk_gridclient">
                        <span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">${escape(row.initials)}</span>
                        <span class="styledesk_gridclient__name">${escape(row.name)}</span>
                        <span class="styledesk_gridclient__ref">${escape(row.ref)}</span>
                    </span>`;
                },
            },
            {
                title: labels.columns.mobile,
                field: 'mobile',
                widthGrow: 1.4,
                minWidth: 130,
                responsive: 1,
                formatter: (cell) => orDash(cell.getValue()),
            },
            {
                title: labels.columns.email,
                field: 'email',
                widthGrow: 2,
                minWidth: 180,
                responsive: 1,
                formatter: (cell) => orDash(cell.getValue()),
            },
            {
                title: labels.columns.staff,
                field: 'staff',
                widthGrow: 1.5,
                minWidth: 140,
                responsive: 5,
                formatter: (cell) => orDash(cell.getValue()),
            },
            {
                title: labels.columns.location,
                field: 'location',
                widthGrow: 1.5,
                minWidth: 140,
                responsive: 5,
                formatter: (cell) => orDash(cell.getValue()),
            },
            {
                title: labels.columns.last_visit,
                field: 'last_visit',
                widthGrow: 1.3,
                minWidth: 120,
                responsive: 4,
                cssClass: 'is-muted',
            },
            {
                title: labels.columns.next_booking,
                field: 'next_booking',
                widthGrow: 1.3,
                minWidth: 120,
                responsive: 2,
                cssClass: 'is-muted',
            },
            {
                title: labels.columns.status,
                field: 'status',
                width: 110,
                responsive: 1,
                formatter: (cell) => {
                    const row = cell.getRow().getData();

                    return `<span class="styledesk_badge ${escape(row.status_class)}">${escape(row.status)}</span>`;
                },
            },
            {
                title: '',
                field: 'actions',
                width: 56,
                hozAlign: 'right',
                headerSort: false,
                responsive: 0,
                formatter: (cell) => {
                    const row = cell.getRow().getData();

                    return `<button type="button" class="styledesk_rowmenu__button" data-grid-menu
                        aria-haspopup="true" aria-expanded="false"
                        aria-label="${escape(labels.actions_for.replace(':name', row.name))}">
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
            },
        ],
    });

    /* A click anywhere on a row opens that client — except on the actions
       button, which has its own job, and except when the reader was selecting
       text or asking for a new tab. */
    table.on('rowClick', (event, row) => {
        if (event.metaKey || event.ctrlKey || event.shiftKey) {
            window.open(row.getData().url, '_blank');

            return;
        }

        if (event.target.closest('[data-grid-menu]')) {
            return;
        }

        if (window.getSelection && String(window.getSelection())) {
            return;
        }

        window.location = row.getData().url;
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
 */
function buildMenu(labels, can) {
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

    const open = (button, row) => {
        const archiveLabel = row.archived ? labels.restore : labels.archive;

        menu.innerHTML = `
            <a href="${escape(row.url)}" class="styledesk_rowmenu__item" role="menuitem">${escape(labels.view)}</a>
            ${can.edit ? `<a href="${escape(row.edit_url)}" class="styledesk_rowmenu__item" role="menuitem">${escape(labels.edit)}</a>` : ''}
            <span class="styledesk_rowmenu__item opacity-50 cursor-not-allowed" aria-disabled="true">${escape(labels.create_booking)}</span>
            ${can.archive ? `<span class="styledesk_rowmenu__rule" role="separator"></span>
            <button type="button" role="menuitem" data-grid-archive
                class="styledesk_rowmenu__item w-full ${row.archived ? '' : 'styledesk_rowmenu__item--danger'}"
                data-confirm="${escape((row.archived ? labels.restore_confirm : labels.archive_confirm).replace(':name', row.name))}"
                data-confirm-title="${escape(archiveLabel)}"
                data-confirm-label="${escape(archiveLabel)}"
                data-confirm-tone="${row.archived ? 'brand' : 'danger'}"
                data-action="${escape(row.archive_url)}">${escape(archiveLabel)}</button>` : ''}
        `;

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

    /* Archiving posts the form the page already carries, so the confirmation
       dialog and the CSRF token are the same ones every other archive uses. */
    menu.addEventListener('click', (event) => {
        const archive = event.target.closest('[data-grid-archive]');

        if (!archive) {
            return;
        }

        const form = document.getElementById('clientArchiveForm');

        if (!form) {
            return;
        }

        form.action = archive.dataset.action;
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
