import { initBookingSheet } from './booking-sheet';

/**
 * Opening a membership from the Members table.
 *
 * The grid announces a menu choice as an event rather than acting on it —
 * what "Plan details" means is the page's business, not the grid's. Here it
 * means the panel over the table, so a desk checking three memberships in a
 * row keeps its filters and its place in the list.
 *
 * The same renderer the bookings, the client profile and the Sales table use:
 * one membership, one panel, wherever it was opened from.
 */
export function initMembershipDrawer() {
    const sheet = document.querySelector('[data-membership-sheet]');

    if (!sheet) {
        return;
    }

    const { open } = initBookingSheet(sheet, {});

    document.addEventListener('styledesk:grid-action', (event) => {
        if (event.detail?.event === 'membership:details') {
            open(event.detail.payload?.url);
        }
    });
}
