import { initBookingSheet } from './booking-sheet';

/**
 * Opening a booking from the calendar.
 *
 * The timeline is a Vue island and the drawer is Blade, so the island
 * announces the choice rather than reaching into the panel: what "open this
 * booking" means belongs to the page, not to the grid drawing it.
 *
 * A drawer rather than a page, for the reason every other listing uses one —
 * the receptionist checks three appointments in a row while somebody is on
 * hold, and navigating away would throw out the day, the filters and their
 * place on the timeline every time.
 */
export function initCalendarDrawer() {
    const sheet = document.querySelector('[data-booking-sheet][data-labels]');
    const island = document.querySelector('[data-vue-component="CalendarBoard"]');

    if (!sheet || !island) {
        return;
    }

    const { open } = initBookingSheet(sheet, JSON.parse(sheet.dataset.labels ?? '{}'));

    document.addEventListener('styledesk:calendar-booking', (event) => {
        if (event.detail?.url) {
            open(event.detail.url);
        }
    });
}
