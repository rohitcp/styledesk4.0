import { reactive } from 'vue';

/**
 * Service categories, shared by every picker on the page.
 *
 * One store rather than a fetch per row: a category added on one service must
 * appear on the others straight away, and without a shared list each picker
 * would hold its own stale copy.
 */
export const categories = reactive({
    items: [],
});

export function setCategories(items) {
    categories.items = items;
}

export async function createCategory(name) {
    let response;

    try {
        response = await fetch('/service-categories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            },
            // Same-origin cookies are what carry the session; without them the
            // request arrives unauthenticated and is bounced to the login page.
            credentials: 'same-origin',
            body: JSON.stringify({ name }),
        });
    } catch (e) {
        throw new Error('Could not reach the server. Check your connection and try again.');
    }

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        /**
         * Say which failure it was.
         *
         * A single "could not create" message is useless to diagnose: an
         * expired CSRF token on a page left open, a permission refusal and a
         * duplicate name all look identical, and only one of them is fixed by
         * trying again.
         */
        if (response.status === 419) {
            throw new Error('This page has been open a while and its security token expired. Reload the page and try again.');
        }

        if (response.status === 401 || response.status === 302) {
            throw new Error('Your session has ended. Reload the page and sign in again.');
        }

        if (response.status === 403) {
            throw new Error('Your role does not allow adding categories.');
        }

        console.error('[styledesk] Creating a category failed', response.status, body);

        throw new Error(body?.errors?.name?.[0] ?? body?.message ?? `Could not create the category (HTTP ${response.status}).`);
    }

    const created = body.data;

    // Insert in display order so the new row does not jump to the bottom of a
    // list the tenant has arranged.
    categories.items = [...categories.items, created].sort((a, b) =>
        (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name)
    );

    return created;
}
