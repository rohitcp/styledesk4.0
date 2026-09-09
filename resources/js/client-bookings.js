/**
 * The Booking tab on a client's profile.
 *
 * Two segments over one fetch: what they have booked, and what they started
 * and never finished. Both are read while somebody is mid-conversation, so
 * nothing here navigates — switching segments, filtering and opening a
 * booking all happen on the page the reader is already on, and the tab they
 * were on survives every one of them.
 */
import { initBookingSheet } from './booking-sheet';

const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[character]));

export function initClientBookings() {
    const host = document.querySelector('[data-client-bookings]');

    if (!host) {
        return;
    }

    const labels = JSON.parse(host.dataset.labels ?? '{}');
    const bookingList = host.querySelector('[data-booking-list]');
    /* Outside the bookings panel: leads are a tab of their own, filled from
       the same answer so the two halves cannot arrive a beat apart. */
    const leadList = document.querySelector('[data-lead-list]');
    const filters = host.querySelector('[data-booking-filters]');
    const whenControl = host.querySelector('[data-booking-when]');
    const sheet = document.querySelector('[data-booking-sheet]');

    /** What the three combos are set to, read from what they post. */
    const chosen = (name) => filters.querySelector(`[name="${name}"]`)?.value ?? '';

    /** Upcoming, completed, or the whole history. */
    let when = '';

    // ---------------------------------------------------------- the cards

    const bookingCard = (booking) => `
        <button type="button" class="styledesk_bookingcard" data-drawer="${escape(booking.drawer_url)}">
            <span class="flex items-start justify-between gap-3">
                <span class="min-w-0 text-left">
                    <span class="block text-[12px] font-semibold text-sub font-mono">${escape(booking.reference)}</span>
                    <span class="block text-[13px] font-semibold text-head mt-0.5">${escape(booking.when)}</span>
                    <span class="block text-[12.5px] text-ink mt-1">${escape(booking.services)}</span>
                    <span class="block text-[12px] text-sub">${escape(booking.staff)}</span>
                    <span class="block text-[12px] text-sub mt-1">${escape(booking.meta)}</span>
                </span>

                <span class="shrink-0 flex flex-col items-end gap-1">
                    <span class="styledesk_badge ${escape(booking.status_class)}">${escape(booking.status)}</span>
                    <span class="styledesk_badge ${escape(booking.payment_class)}">${escape(booking.payment)}</span>
                </span>
            </span>
        </button>`;

    const leadCard = (lead) => `
        <button type="button" class="styledesk_bookingcard" data-drawer="${escape(lead.drawer_url)}">
            <span class="flex items-start justify-between gap-3">
                <span class="min-w-0 text-left">
                    <span class="block text-[12px] font-semibold text-sub font-mono">${escape(lead.reference)}</span>
                    <span class="block text-[12.5px] text-ink mt-1">${escape(lead.services)}</span>
                    <span class="block text-[12px] text-sub mt-0.5">${escape(labels.stopped_at)}: <span class="text-ink font-medium">${escape(lead.step)}</span></span>
                    <span class="block text-[12px] text-sub mt-1">${escape(lead.value)} · ${escape(labels.last_activity)} ${escape(lead.activity)}</span>
                </span>

                <span class="styledesk_badge ${escape(lead.status_class)} shrink-0">${escape(lead.status)}</span>
            </span>
        </button>`;

    const render = (data) => {
        /* Grouped by the month they happened in, forward in time: a history
           is read from its beginning, and forty in a flat list is a scroll. */
        bookingList.innerHTML = data.groups.length
            ? data.groups.map((group) => `
                <section class="mb-5">
                    <h3 class="styledesk_heading">${escape(group.label)}</h3>
                    <div class="mt-2 space-y-2">${group.bookings.map(bookingCard).join('')}</div>
                </section>`).join('')
            : `<p class="text-[13px] text-sub">${escape(labels.none)}</p>`;

        if (leadList) {
            leadList.innerHTML = data.leads.length
                ? `<div class="space-y-2">${data.leads.map(leadCard).join('')}</div>`
                : `<p class="text-[13px] text-sub">${escape(labels.no_leads)}</p>`;
        }
    };

    /* The combo writes its answer into a hidden input and then announces
       it. Listening for the announcement rather than for `change`: the
       island's own value lands a tick after the click, and reading it too
       early gets the answer before last. */
    filters.addEventListener('sd:combo-change', () => load());

    /* The segment is pressed rather than chosen from a list, so it says which
       one it is with aria-current — the same thing the styling keys off, and
       the thing a screen reader announces. */
    whenControl?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-when]');

        if (!button) {
            return;
        }

        when = button.dataset.when;

        whenControl.querySelectorAll('[data-when]').forEach((item) => {
            if (item === button) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        load();
    });

    const load = async () => {
        const url = new URL(host.dataset.url, window.location.origin);
        const year = chosen('booking_year');
        const month = chosen('booking_month');
        const service = chosen('booking_service');

        /* A month without a year is every August there has ever been, so the
           two are only sent together. */
        if (year && month) {
            url.searchParams.set('month', `${year}-${month}`);
        } else if (year) {
            url.searchParams.set('year', year);
        }

        if (service) {
            url.searchParams.set('service', service);
        }

        if (when) {
            url.searchParams.set('when', when);
        }

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            render(await response.json());
        } catch (error) {
            /* Leave what is on the screen: a half-loaded panel is worse than
               the one the reader was already looking at. */
        }
    };

    /* Fetched once the panel exists, whether or not the tab is the one on
       screen: the answer is small, and a tab that spins when it is opened is
       a tab that feels slower than the page it is on. */
    load();

    // ---------------------------------------------------------- the sheet

    if (sheet) {
        /* The same renderer the Sales table and the leads listing use. One
           booking, one drawer, wherever it was opened from. */
        const { openFrom } = initBookingSheet(sheet, labels);

        host.addEventListener('click', openFrom);
        leadList?.addEventListener('click', openFrom);
        /* The next-appointment card in the third column opens the same
           panel: one booking, one drawer, wherever it was clicked from. */
        document.querySelector('[data-drawer].styledesk_nextbooking')?.addEventListener('click', openFrom);
    }
}
