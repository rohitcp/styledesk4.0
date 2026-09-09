/**
 * The booking drawer, wherever it is opened from.
 *
 * One renderer for one payload. The client profile, the leads listing and the
 * Sales table all show the same appointment, and three renderers would be
 * three chances for them to describe it differently — a status badge styled
 * one way here and another there is how a reader stops trusting either.
 *
 * The panel is a drawer rather than a page on purpose: a receptionist checks
 * three or four of these in a row while somebody is on hold, and navigating
 * away would throw out the filters, the page and the scroll position every
 * time.
 */
const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[character]));

/**
 * Wire a `.styledesk_sheet` element up.
 *
 * Returns an `open(url)` so a caller can drive it from something other than a
 * click — the Sales grid announces its menu choices as events rather than as
 * DOM the page can listen to.
 *
 * @param {HTMLElement|null} sheet
 * @param {Record<string, string>} labels
 */
export function initBookingSheet(sheet, labels = {}) {
    if (!sheet) {
        return { open: () => {}, close: () => {} };
    }

    const body = sheet.querySelector('[data-sheet-body]');
    const name = sheet.querySelector('[data-sheet-name]');
    const reference = sheet.querySelector('[data-sheet-reference]');
    const status = sheet.querySelector('[data-sheet-status]');
    const step = sheet.querySelector('[data-sheet-step]');
    const primary = sheet.querySelector('[data-sheet-primary]');
    const download = sheet.querySelector('[data-sheet-download]');

    const facts = (section) => `
        <section class="styledesk_sheet__section">
            <p class="styledesk_eyebrow">${escape(section.title)}</p>
            <dl class="mt-2">${Object.entries(section.rows).map(([label, value]) => `
                <div class="styledesk_sheet__row">
                    <dt>${escape(label)}</dt>
                    <dd class="${value === labels.not_selected ? 'is-blank' : ''}">${escape(value)}</dd>
                </div>`).join('')}</dl>
        </section>`;

    const close = () => {
        sheet.classList.remove('is-open');
        window.setTimeout(() => { sheet.hidden = true; }, 180);
    };

    sheet.querySelectorAll('[data-sheet-close]').forEach((button) => button.addEventListener('click', close));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !sheet.hidden) {
            close();
        }
    });

    /**
     * @param {string} url
     * @param {{cta?: string, download?: string}} options
     *   `cta` overrides the footer button's wording — the same panel shows a
     *   booking, a client and a receipt, and "View full booking" under a
     *   client is the wrong sentence.
     */
    const open = async (url, options = {}) => {
        if (!url) {
            return;
        }

        sheet.hidden = false;
        /* Forced into the layout rather than waited a frame for:
           requestAnimationFrame is throttled in a tab that is not being
           painted, and a panel that opens only when the window is in front is
           a panel that looks broken everywhere else. */
        void sheet.offsetWidth;
        sheet.classList.add('is-open');

        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            close();

            return;
        }

        const record = await response.json();

        name.textContent = record.name;
        reference.textContent = record.reference;
        status.textContent = record.status;
        status.className = `styledesk_badge ${record.status_class}`;

        if (step) {
            step.textContent = record.step ?? '';
        }

        /* One renderer, two records. A lead answers in named blocks; a booking
           answers in the same shape, so neither can quietly grow a panel that
           looks like something else. */
        const sections = record.sections ?? [
            { title: labels.summary, rows: record.summary },
            { title: labels.client, rows: record.client },
            { title: labels.booking, rows: record.booking },
            { title: labels.payment, rows: record.payment },
        ].filter((section) => section.rows);

        const transactions = (record.transactions ?? []).map((line) => `
            <div class="styledesk_sheet__note">${escape(line.label)} · ${escape(line.amount)}
                <span>${escape(line.at)}${line.by ? ` · ${escape(line.by)}` : ''}</span>
            </div>`).join('');

        /* The same cards the client's own profile draws — same classes, same
           tones — so a reader who has seen one recognises the other. Only the
           client drawer sends them; a booking has no figures of this kind. */
        const metrics = (record.metrics ?? []).map((metric) => `
            <div class="styledesk_metric styledesk_metric--${escape(metric.tone ?? 'blue')}">
                <p class="styledesk_metric__label">${escape(metric.label)}</p>
                ${metric.value
                    ? `<p class="styledesk_metric__value${String(metric.value).length > 12 ? ' styledesk_metric__value--compact' : ''}">${escape(metric.value)}</p>
                       ${metric.detail ? `<p class="text-[12px] text-sub mt-0.5">${escape(metric.detail)}</p>` : ''}`
                    : `<p class="styledesk_metric__empty">${escape(metric.empty ?? '')}</p>`}
            </div>`).join('');

        body.innerHTML = (metrics
            ? `<section class="styledesk_sheet__section"><div class="styledesk_metrics">${metrics}</div></section>`
            : '')
            + sections.map(facts).join('')
            + (transactions
                ? `<section class="styledesk_sheet__section">
                        <p class="styledesk_eyebrow">${escape(labels.transactions)}</p>
                        <div class="mt-2 space-y-2">${transactions}</div>
                    </section>`
                : '');

        if (primary) {
            primary.href = record.urls.show ?? record.urls.complete;
            primary.textContent = options.cta ?? (record.urls.show ? labels.view_full : labels.complete);
            primary.hidden = !primary.href;
        }

        /* A second button only where there is something to keep. Printing the
           receipt page is what every browser offers as "Save as PDF", so the
           file it produces is the receipt itself rather than a second
           rendering of it that could disagree. */
        if (download) {
            download.hidden = !record.urls.download;
            download.href = record.urls.download ?? '#';
            download.textContent = options.download ?? labels.download ?? '';
        }
    };

    /** Anything carrying `data-drawer` opens it. */
    const openFrom = (event) => {
        const trigger = event.target.closest('[data-drawer]');

        if (trigger) {
            open(trigger.dataset.drawer);
        }
    };

    return { open, close, openFrom };
}
