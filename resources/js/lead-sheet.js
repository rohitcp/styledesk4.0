/**
 * The booking lead detail sheet.
 *
 * A panel over the listing rather than a page instead of it: a front desk
 * reads three or four leads in a row while somebody is on hold, and every
 * one of those trips would otherwise cost the filters, the page and the
 * scroll position. Nothing here navigates.
 *
 * The grid rows carry no `url`, so the shared grid's own click handler stops
 * at its first guard and this one takes over.
 */
const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[character]));

/** A block of label → value rows, with the blanks marked as blanks. */
function facts(title, rows, blank, footer = '') {
    const body = Object.entries(rows ?? {}).map(([label, value]) => `
        <div class="styledesk_sheet__row">
            <dt>${escape(label)}</dt>
            <dd class="${value === blank ? 'is-blank' : ''}">${escape(value)}</dd>
        </div>`).join('');

    return `<section class="styledesk_sheet__section">
        <p class="styledesk_eyebrow">${escape(title)}</p>
        <dl class="mt-2">${body}</dl>
        ${footer}
    </section>`;
}

export function initLeadSheet(labels) {
    const sheet = document.querySelector('[data-lead-sheet]');
    const grid = document.querySelector('[data-grid]');

    if (!sheet || !grid) {
        return;
    }

    const body = sheet.querySelector('[data-sheet-body]');
    const name = sheet.querySelector('[data-sheet-name]');
    const reference = sheet.querySelector('[data-sheet-reference]');
    const status = sheet.querySelector('[data-sheet-status]');
    const step = sheet.querySelector('[data-sheet-step]');
    const complete = sheet.querySelector('[data-sheet-complete]');
    const cancelButton = sheet.querySelector('[data-sheet-cancel]');
    const modal = document.querySelector('[data-cancel-modal]');

    let current = null;

    const render = (lead) => {
        current = lead;
        name.textContent = lead.name;
        reference.textContent = lead.reference;
        status.textContent = lead.status;
        status.className = `styledesk_badge ${lead.status_class}`;
        step.textContent = `${labels.stopped_at}: ${lead.step}`;
        complete.href = lead.urls.booking ?? lead.urls.complete;
        complete.textContent = lead.urls.booking ? labels.view_booking : labels.complete;

        /* Cancelling is only offered while there is something to cancel. */
        cancelButton.hidden = !lead.is_open;

        const journey = lead.journey.map((step) => `
            <div class="styledesk_journey__step ${step.done ? 'is-done' : ''} ${step.current ? 'is-current' : ''}">
                <span class="styledesk_journey__mark">${step.done ? '✓' : ''}</span>
                <span>${escape(step.label)}</span>
            </div>`).join('');

        const activity = lead.events.length
            ? lead.events.map((event) => `
                <div class="styledesk_activity__item">
                    <strong>${escape(event.label)}</strong>
                    <span>${escape(event.at)}${event.by ? ` · ${escape(event.by)}` : ''}</span>
                </div>`).join('')
            : `<p class="text-[12.5px] text-sub">${escape(labels.no_activity)}</p>`;

        notesList.innerHTML = lead.notes.length
            ? lead.notes.map((note) => `
                <div class="styledesk_sheet__note">${escape(note.body)}
                    <span>${escape(note.at)}${note.by ? ` · ${escape(note.by)}` : ''}</span>
                </div>`).join('')
            : `<p class="text-[12.5px] text-sub">${escape(labels.notes_empty)}</p>`;

        body.innerHTML = `
            ${facts(labels.summary, lead.summary, labels.not_selected)}
            ${facts(labels.client, lead.client, labels.not_selected, lead.client_url
                ? `<a href="${escape(lead.client_url)}" class="mt-2 inline-block text-[12.5px] font-semibold text-link">${escape(labels.view_client)}</a>`
                : '')}
            ${facts(labels.booking, lead.booking, labels.not_selected)}
            ${facts(labels.payment, lead.payment, labels.not_selected)}

            <section class="styledesk_sheet__section">
                <p class="styledesk_eyebrow">${escape(labels.journey)}</p>
                <div class="styledesk_journey mt-2">${journey}</div>
            </section>

            <section class="styledesk_sheet__section">
                <p class="styledesk_eyebrow">${escape(labels.activity)}</p>
                <div class="styledesk_activity mt-2">${activity}</div>
            </section>`;
    };

    const notesPanel = sheet.querySelector('[data-notes-panel]');
    const notesList = sheet.querySelector('[data-notes-list]');
    const notesInput = sheet.querySelector('[data-notes-input]');
    const notesError = sheet.querySelector('[data-notes-error]');

    let closing = null;

    const open = async (url) => {
        window.clearTimeout(closing);
        sheet.hidden = false;

        /* Read a layout property to force the panel into the layout before
           the class that fades it in, rather than waiting a frame for it.
           requestAnimationFrame is throttled in a tab that is not being
           painted, and a panel that opens only when the window is in front
           is a panel that looks broken everywhere else. */
        void sheet.offsetWidth;
        sheet.classList.add('is-open');

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (response.ok) {
                render(await response.json());
            }
        } catch (error) {
            close();
        }
    };

    /** Posts JSON and answers with the lead, or null when it would not. */
    const post = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(payload),
        });

        return response.ok ? response.json() : null;
    };

    const close = () => {
        notesPanel.hidden = true;
        sheet.classList.remove('is-open');
        closing = window.setTimeout(() => { sheet.hidden = true; }, 180);
    };

    sheet.querySelectorAll('[data-sheet-close]').forEach((button) => button.addEventListener('click', close));

    // -------------------------------------------------------------- notes

    const notesOpen = sheet.querySelector('[data-notes-open]');

    notesOpen.addEventListener('click', () => {
        notesError.hidden = true;
        notesInput.value = '';
        notesPanel.hidden = false;
        notesInput.focus();
    });

    sheet.querySelector('[data-notes-close]').addEventListener('click', () => {
        notesPanel.hidden = true;
    });

    sheet.querySelector('[data-notes-save]').addEventListener('click', async () => {
        const body = notesInput.value.trim();

        if (!body || !current) {
            return;
        }

        /* A walk-in has no record to keep a note on, and saying so is better
           than saving one nobody will ever find. */
        if (!current.can_note) {
            notesError.textContent = labels.notes_needs_client;
            notesError.hidden = false;

            return;
        }

        const response = await post(current.urls.notes, { body });

        if (!response) {
            notesError.textContent = labels.cancel_failed;
            notesError.hidden = false;

            return;
        }

        render(response);
        notesInput.value = '';
        window.styledesk?.toast(labels.notes_saved);

        /* Writing a note is contact, so the row's status has moved. */
        grid.styledeskGrid?.replaceData();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (modal && !modal.hidden) {
            modal.hidden = true;

            return;
        }

        if (!notesPanel.hidden) {
            notesPanel.hidden = true;

            return;
        }

        if (!sheet.hidden) {
            close();
        }
    });

    /* The grid is built on load; wait for it rather than racing it. */
    const waitForGrid = window.setInterval(() => {
        if (!grid.styledeskGrid) {
            return;
        }

        window.clearInterval(waitForGrid);

        grid.styledeskGrid.on('rowClick', (event, row) => {
            const data = row.getData();

            if (!data.drawer_url || event.target.closest('[data-grid-menu]')) {
                return;
            }

            /* A reader dragging across a phone number is selecting it, not
               asking for the sheet. A caret left behind by an earlier click
               is not a selection at all, though — hence isCollapsed, without
               which the second row anybody clicks does nothing. */
            const selection = window.getSelection?.();

            if (selection && ! selection.isCollapsed && String(selection).trim()) {
                return;
            }

            open(data.drawer_url);
        });
    }, 60);

    // ------------------------------------------------------------- cancel

    if (!modal) {
        return;
    }

    const form = modal.querySelector('[data-cancel-form]');
    const error = modal.querySelector('[data-cancel-error]');

    cancelButton.addEventListener('click', () => {
        form.reset();
        error.hidden = true;
        modal.hidden = false;
    });

    modal.querySelectorAll('[data-cancel-close]').forEach((button) => button.addEventListener('click', () => {
        modal.hidden = true;
    }));

    modal.querySelector('[data-cancel-confirm]').addEventListener('click', async () => {
        const data = new FormData(form);

        if (!data.get('reason')) {
            error.textContent = labels.choose_reason;
            error.hidden = false;

            return;
        }

        const lead = await post(current.urls.cancel, {
            reason: data.get('reason'),
            note: data.get('note') || null,
        });

        if (!lead) {
            error.textContent = labels.cancel_failed;
            error.hidden = false;

            return;
        }

        modal.hidden = true;
        render(lead);
        window.styledesk?.toast(labels.cancelled);

        /* The row's badge is now wrong. Reloaded in place, so the filters,
           the page and the reader's place in the list all survive. */
        grid.styledeskGrid?.replaceData();
    });
}
