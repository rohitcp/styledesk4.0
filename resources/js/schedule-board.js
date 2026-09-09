/**
 * The current-month marker on the staff schedule board.
 *
 * The badge sits above the table rather than inside a header cell, which is
 * the one thing the grid cannot draw for itself: the library owns the header
 * DOM and every cell in it is a fixed-height box, so anything above the month
 * name has to live outside the table and be pointed at the right column.
 *
 * Found by the column's own field attribute rather than by counting columns,
 * and placed from its measured position, so it stays over its month through a
 * sideways scroll, a resize and a redraw without knowing what any of those
 * did.
 */
export function initScheduleBoard(root = document) {
    const frame = root.querySelector('[data-board-marker]');

    if (!frame || frame.dataset.markerReady) {
        return;
    }

    const badge = frame.querySelector('[data-board-now]');
    const grid = frame.querySelector('[data-grid]');
    const field = grid?.dataset.monthField;

    if (!grid) {
        return;
    }

    frame.dataset.markerReady = '1';

    const place = () => {
        if (!badge || !field) {
            return;
        }

        const column = grid.querySelector(`.tabulator-col[tabulator-field="${CSS.escape(field)}"]`);
        const header = grid.querySelector('.tabulator-header');

        if (!column || !header) {
            badge.hidden = true;

            return;
        }

        const columnBox = column.getBoundingClientRect();
        const headerBox = header.getBoundingClientRect();
        const frameBox = frame.getBoundingClientRect();

        /* The pinned staff column is drawn over the scrolling months, so the
           marker has to disappear behind it rather than float above a month
           the reader can no longer see. */
        const frozen = grid.querySelector('.tabulator-header .tabulator-col.tabulator-frozen');
        const leftEdge = frozen ? frozen.getBoundingClientRect().right : headerBox.left;
        const centre = columnBox.left + columnBox.width / 2;

        if (centre < leftEdge + 8 || centre > headerBox.right - 8) {
            badge.hidden = true;

            return;
        }

        badge.hidden = false;
        badge.style.left = `${centre - frameBox.left}px`;
    };

    const rail = mirrorScrollbar(frame, grid);

    /* Measured on the spot rather than on the next animation frame. A frame
       is not promised: a browser throttles them in a tab it is not painting,
       and the marker and the scrollbar would then be laid out only once
       somebody looked at the page — which is exactly when they are already
       wrong.

       Safe against its own observer: nothing here changes the children it
       watches except moving the rail, which it only does when the rail is
       not already where it belongs. */
    const measure = () => {
        place();
        rail();
    };

    /* Scrolling is the one caller that fires faster than a frame, so that
       one is batched. */
    let queued = false;
    const schedule = () => {
        if (queued) {
            return;
        }

        queued = true;
        window.requestAnimationFrame(() => {
            queued = false;
            measure();
        });
    };

    /* The grid arrives in a chunk of its own, so the header does not exist
       yet when this runs. Watching the container is what catches it — and the
       same observer catches every redraw afterwards. */
    new MutationObserver(measure).observe(grid, { childList: true, subtree: true });

    /* Capture, because the element that scrolls is inside the grid and is
       replaced whenever the library rebuilds the table. */
    grid.addEventListener('scroll', schedule, true);
    window.addEventListener('resize', measure);

    if (window.ResizeObserver) {
        new ResizeObserver(measure).observe(grid);
    }

    measure();
}

/**
 * The months' scrollbar, indented past the pinned column.
 *
 * The table's own bar runs the full width of the board, which puts a
 * scrollbar under the staff name — a column that does not scroll. This one is
 * pushed right by the width of that column, so it sits under the months and
 * says what it moves.
 *
 * A real scroller rather than a drawn one: it is one element as wide as the
 * months inside a strip as wide as the space they are shown in, and the
 * browser draws the same scrollbar it would have drawn anyway. Because both
 * strips lose the same fixed column, the two scroll the same distance and are
 * kept in step by copying scrollLeft between them.
 *
 * @returns {Function} Re-measures the rail against the table.
 */
function mirrorScrollbar(frame, grid) {
    const rail = frame.querySelector('[data-board-scroll]');
    const inner = rail?.firstElementChild;

    if (!rail || !inner) {
        return () => {};
    }

    /* Set while one side is being moved from the other, so the scroll event
       that follows does not bounce straight back. */
    let syncing = false;

    const holder = () => grid.querySelector('.tabulator-tableholder');

    const follow = (from, to) => {
        if (syncing) {
            return;
        }

        syncing = true;
        to.scrollLeft = from.scrollLeft;
        window.requestAnimationFrame(() => {
            syncing = false;
        });
    };

    rail.addEventListener('scroll', () => {
        const body = holder();

        if (body) {
            follow(rail, body);
        }
    });

    grid.addEventListener('scroll', (event) => {
        if (event.target === holder()) {
            follow(event.target, rail);
        }
    }, true);

    return () => {
        const body = holder();

        if (!body) {
            rail.hidden = true;

            return;
        }

        /* Moved to sit directly under the rows, between the table and the
           pager, rather than below the whole grid: a scrollbar a pager's
           height away from what it scrolls is a scrollbar for the page. The
           check makes this idempotent — the measure runs on every redraw. */
        if (rail.previousElementSibling !== body) {
            body.after(rail);
        }

        /* Measured rather than assumed: the pinned column's width is the
           grid's to decide, and it changes with the window. */
        const frozen = grid.querySelector('.tabulator-header .tabulator-col.tabulator-frozen');
        const pinned = frozen ? frozen.getBoundingClientRect().width : 0;

        rail.style.marginLeft = `${pinned}px`;
        inner.style.width = `${Math.max(0, body.scrollWidth - pinned)}px`;

        /* Nothing to scroll is nothing to show: an empty scrollbar is a
           control that teaches the reader it does not work. */
        rail.hidden = body.scrollWidth <= body.clientWidth + 1;
    };
}

/**
 * The board's filters, applied as they are chosen.
 *
 * Four controls and a fifth button that only means "yes, really" is a row
 * with one control too many. The combos announce every change on the DOM —
 * see MultiSelect — so the form can send itself the moment one of them is
 * answered.
 *
 * The submit button stays in the markup and is hidden here rather than in the
 * template: it is the only thing that works in a browser running no script,
 * and a page that removes it server-side removes it from that browser too.
 */
export function initBoardFilters(root = document) {
    const form = root.querySelector('[data-board-filters]');

    if (!form || form.dataset.filtersReady) {
        return;
    }

    form.dataset.filtersReady = '1';
    form.querySelector('[data-board-apply]')?.setAttribute('hidden', 'hidden');

    /* sd:combo-change rather than styledesk:selection, which the same
       control also fires: the selection event is announced inside the watcher
       that caused it, before the hidden input holding the answer has been
       rewritten, so a form submitted on it posts the value being replaced.
       This one is announced from the input itself once that write has
       happened — and only on a real change, never on mount. */
    form.addEventListener('sd:combo-change', () => form.requestSubmit());
}

/**
 * The published-month modal.
 *
 * A month that has been sent is opened to be checked rather than changed, and
 * the schedule page is a whole screen away from the board a manager is
 * working down. The cell opens it in place instead — fetched when asked for,
 * because a month is thirty-one rows and a team is thirty months.
 *
 * The cell stays a link. Without script it goes where it always went, which
 * is the same month on its own page.
 */
export function initMonthModal(root = document) {
    const modal = root.querySelector('[data-month-modal]');

    if (!modal || modal.dataset.modalReady) {
        return;
    }

    modal.dataset.modalReady = '1';

    const title = modal.querySelector('[data-month-title]');
    const state = modal.querySelector('[data-month-state]');
    const count = modal.querySelector('[data-month-count]');
    const rows = modal.querySelector('[data-month-rows]');
    const body = modal.querySelector('[data-month-body]');
    const edit = modal.querySelector('[data-month-edit]');
    const labels = JSON.parse(modal.dataset.monthLabels ?? '{}');

    const close = () => {
        modal.hidden = true;
    };

    modal.querySelectorAll('[data-month-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });

    const cell = (text, className = '') => {
        const td = document.createElement('td');
        td.textContent = text ?? '—';

        if (className) {
            td.className = className;
        }

        return td;
    };

    const draw = (month) => {
        title.textContent = `${month.staff} — ${month.month}`;
        state.textContent = month.state_label;
        count.textContent = month.shifts;
        edit.href = month.assign_url;
        rows.replaceChildren();

        month.days.forEach((day) => {
            /* A split day is two rows under one date: the date is written
               once and the second block is left to line up under it, which
               is how a printed rota reads. */
            const blocks = day.shifts.length ? day.shifts : [null];

            blocks.forEach((shift, index) => {
                const tr = document.createElement('tr');

                tr.append(
                    cell(index === 0 ? day.date : ''),
                    cell(index === 0 ? day.day : ''),
                    cell(shift?.name ?? (day.working ? '1' : '—')),
                    cell(shift?.start),
                    cell(shift?.end),
                    cell(shift?.break),
                    cell(shift?.hours ?? day.hours),
                );

                const status = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = `styledesk_badge ${day.working ? 'styledesk_badge--active' : 'styledesk_badge--soon'}`;
                badge.textContent = day.label;
                status.append(badge);
                tr.append(status);

                rows.append(tr);
            });
        });
    };

    const open = async (url) => {
        title.textContent = labels.loading ?? '';
        state.textContent = '';
        count.textContent = '';
        rows.replaceChildren();
        modal.hidden = false;
        body.scrollTop = 0;

        try {
            const month = await fetch(url, { headers: { Accept: 'application/json' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.json();
                });

            draw(month);
        } catch (error) {
            /* Said in the dialog rather than left blank: a window that opens
               empty reads as a month with nothing in it, which is the one
               thing this month is not. */
            title.textContent = labels.failed ?? '';
        }
    };

    /* Delegated, because the grid draws and discards its cells as the reader
       pages and scrolls — and in the capture phase, because the grid stops a
       click on a month from bubbling so that the row underneath does not
       navigate on top of it. A listener waiting on the way up would never
       hear the click at all. */
    root.addEventListener('click', (event) => {
        const link = event.target.closest('[data-schedule-modal]');

        if (!link || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        open(link.dataset.scheduleModal);
    }, true);
}
