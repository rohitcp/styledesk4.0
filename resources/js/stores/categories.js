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
    const response = await fetch('/service-categories', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        },
        body: JSON.stringify({ name }),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        // 422 carries the duplicate-name message; anything else is unexpected.
        throw new Error(body?.errors?.name?.[0] ?? body?.message ?? 'Could not create the category.');
    }

    const created = body.data;

    // Insert in display order so the new row does not jump to the bottom of a
    // list the tenant has arranged.
    categories.items = [...categories.items, created].sort((a, b) =>
        (a.display_order ?? 0) - (b.display_order ?? 0) || a.name.localeCompare(b.name)
    );

    return created;
}
