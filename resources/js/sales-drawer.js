import { initBookingSheet } from './booking-sheet';

/**
 * Opening a booking from the Sales table.
 *
 * The grid announces a menu choice as an event rather than acting on it — what
 * "view booking" means is the page's business, not the grid's. Here it means
 * the drawer over the table, so a bookkeeper checking four transactions in a
 * row keeps the date range, the filters and their place in the list.
 */
export function initSalesDrawer() {
    const sheet = document.querySelector('[data-booking-sheet][data-labels]');
    const grid = document.querySelector('[data-grid]');

    /* The client profile has a sheet of its own and drives it itself; this is
       only for a page that has a grid announcing into it. */
    if (!sheet || !grid) {
        return;
    }

    const { open } = initBookingSheet(sheet, JSON.parse(sheet.dataset.labels ?? '{}'));

    const labels = JSON.parse(sheet.dataset.labels ?? '{}');

    /* One panel, three records. The footer button is worded for whichever it
       is showing — "View full booking" under a client is the wrong sentence,
       and a reader who follows it lands somewhere they did not ask for. */
    const kinds = {
        'sales:booking': {},
        'sales:client': { cta: labels.view_client },
        'sales:receipt': { cta: labels.view_receipt, download: labels.download },
    };

    document.addEventListener('styledesk:grid-action', (event) => {
        const kind = kinds[event.detail?.event];

        if (kind) {
            open(event.detail.payload?.url, kind);
        }
    });
}
