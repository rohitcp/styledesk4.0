/**
 * Repeatable contact rows on the client form.
 *
 * Three rules the markup alone cannot keep:
 *
 *  - a new row's fields must post under a key nothing else is using, or two
 *    numbers would arrive as one;
 *  - exactly one row is Primary, so choosing it on one row demotes whichever
 *    row held it, and removing the primary row promotes another rather than
 *    leaving the record with none;
 *  - the last row keeps its number instead of being removable to nothing —
 *    a card with no fields is a puzzle, not a form.
 *
 * Delegated from the document, so a row added after load behaves like the
 * ones rendered with the page.
 */
import { initPhoneFields } from './phone';
import { initCombos } from './combos';
import { confirmAction } from './confirm';

/** A key nothing else on the page is using. */
function nextKey(group) {
    const used = new Set(
        Array.from(group.querySelectorAll('[data-contact-row]')).map((row) => row.dataset.key)
    );

    let n = used.size;

    while (used.has(`r${n}`)) {
        n += 1;
    }

    return `r${n}`;
}

function rows(group) {
    return Array.from(group.querySelectorAll('[data-contact-row]'));
}

/**
 * One row primary, and the delete button hidden when only one row is left.
 *
 * Both are consequences of the row set rather than of any single click, so
 * they are recomputed after every change instead of patched at each site.
 */
function priorities(group) {
    return rows(group).map((row) => row.querySelector('[data-contact-priority]')).filter(Boolean);
}

/**
 * Exactly one Primary.
 *
 * `chosen` is the row the reader just acted on, and it wins: every other row
 * becomes Secondary. When nobody is Primary — the only Primary was just
 * demoted, or its row was deleted — the first row takes it, because a client
 * with three numbers and no primary leaves every screen downstream picking
 * one for itself.
 */
function settlePriority(group, chosen) {
    const selects = priorities(group);

    if (!selects.length) {
        return;
    }

    if (chosen && chosen.value === 'primary') {
        selects.forEach((select) => {
            if (select !== chosen) {
                select.value = 'secondary';
            }
        });

        return;
    }

    if (!selects.some((select) => select.value === 'primary')) {
        selects[0].value = 'primary';
    }
}

function refresh(group) {
    const all = rows(group);

    settlePriority(group, null);

    all.forEach((row) => {
        const remove = row.querySelector('[data-remove-contact]');

        if (remove) {
            remove.hidden = all.length < 2;
        }
    });

    const add = group.querySelector('[data-add-contact]');
    const max = Number(group.dataset.max || 0);

    if (add && max) {
        add.hidden = all.length >= max;
    }
}

function addRow(group) {
    const template = group.querySelector('[data-contact-template]');
    const list = group.querySelector('[data-contact-rows]');

    if (!template || !list) {
        return;
    }

    const key = nextKey(group);
    const markup = template.innerHTML.replace(/__KEY__/g, key);

    const holder = document.createElement('div');
    holder.innerHTML = markup;

    const row = holder.firstElementChild;

    if (!row) {
        return;
    }

    list.appendChild(row);

    // A cloned row is inert markup until these run: the phone widget builds
    // the country list and dialling code, and the dropdowns are still plain
    // selects until they are upgraded.
    initPhoneFields(row);
    initCombos(row);
    refresh(group);

    const field = row.querySelector('input[type="tel"], input[type="email"]');

    if (field) {
        field.focus();
    }
}

export function initClientContacts(root = document) {
    root.querySelectorAll('[data-contacts]').forEach((group) => refresh(group));
}

document.addEventListener('click', async (event) => {
    const add = event.target.closest('[data-add-contact]');

    if (add) {
        addRow(add.closest('[data-contacts]'));

        return;
    }

    const remove = event.target.closest('[data-remove-contact]');

    if (!remove) {
        return;
    }

    const group = remove.closest('[data-contacts]');
    const row = remove.closest('[data-contact-row]');

    if (!group || !row || rows(group).length < 2) {
        return;
    }

    /**
     * Asked before removing, and the question names the number rather than
     * the row: "Remove this?" beside four identical-looking rows is not a
     * question anyone can answer with confidence.
     */
    const field = row.querySelector('input[type="tel"], input[type="email"]');
    const value = field && field.value.trim();
    const labels = JSON.parse(group.dataset.confirmLabels || '{}');

    if (value) {
        const answered = await confirmAction({
            title: labels.title,
            message: labels.message.replace(':contact', value),
            confirm: labels.confirm,
        });

        if (!answered) {
            return;
        }
    }

    row.remove();
    refresh(group);
});

document.addEventListener('change', (event) => {
    const select = event.target.closest('[data-contact-priority]');

    if (select) {
        settlePriority(select.closest('[data-contacts]'), select);
    }
});
