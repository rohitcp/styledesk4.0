/**
 * Client-side mirror of App\Support\InputCase.
 *
 * Opt-in via data-capitalize, never automatic: applying this to every text
 * input would corrupt the fields where case carries meaning — emails,
 * passwords, URLs, subdomains, usernames, codes and API keys.
 *
 * The server applies the same rule regardless, so this is feedback rather than
 * enforcement. A field that misses the attribute is still stored correctly; it
 * just does not show the change until the page reloads.
 */

export function capitalizeFirst(value) {
    const text = value ?? '';

    if (text === '') {
        return text;
    }

    // Only the first character changes. Everything after it is left exactly as
    // typed, which is what preserves FULL UPPERCASE and mixed case like
    // "styleDesk NYC" without needing to detect either.
    return text.charAt(0).toUpperCase() + text.slice(1);
}

export function initCapitalization(root = document) {
    // Delegated, so inputs rendered later — a Vue island, a repeated row —
    // are covered without re-binding.
    root.addEventListener('input', (event) => {
        const field = event.target;

        if (!field.matches?.('[data-capitalize]')) {
            return;
        }

        const next = capitalizeFirst(field.value);

        if (next === field.value) {
            return;
        }

        // Rewriting .value moves the caret to the end, which is maddening when
        // editing the middle of a word. Only the first character can change,
        // so the caret position itself is always still valid.
        const start = field.selectionStart;
        const end = field.selectionEnd;

        field.value = next;

        if (field.setSelectionRange && start !== null) {
            field.setSelectionRange(start, end);
        }
    });
}
