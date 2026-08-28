/**
 * The note composer on a client profile.
 *
 * A floating panel that writes a note without leaving the page: the profile
 * it describes stays visible behind it, the saved note is inserted at the top
 * of the list, and nothing reloads.
 *
 * Two things here are deliberately plain HTML rather than components. The
 * access list is real checkboxes in a panel, so the form posts what is ticked
 * and the keyboard gets a control it already understands; and the card the
 * server returns is server-rendered, so there is no second description of
 * what a note looks like living in this file.
 */
import { confirmAction } from './confirm';
import { createNoteEditor } from './note-editor';

export function initNoteComposer(root = document) {
    const composer = root.querySelector('#noteComposer');

    if (!composer) {
        return;
    }

    const form = composer.querySelector('[data-note-form]');
    const input = composer.querySelector('[data-note-input]');
    /* The checkbox, not the hidden "0" the toggle posts alongside it — that
       one comes first in the markup and has no checked state to read. */
    const privateToggle = composer.querySelector('input[type="checkbox"][name="is_private"]');
    const access = composer.querySelector('[data-note-access]');
    const picker = composer.querySelector('#notePeople');
    const search = composer.querySelector('[data-note-search]');
    const panel = composer.querySelector('[data-note-panel]');
    const options = Array.from(composer.querySelectorAll('[data-note-option]'));
    const noMatches = composer.querySelector('[data-note-no-matches]');
    const chips = composer.querySelector('[data-note-chips]');
    const summary = composer.querySelector('[data-note-summary]');
    const count = composer.querySelector('[data-note-count]');
    const assign = composer.querySelector('[data-note-picker-assign]');
    const pickerOpen = composer.querySelector('[data-note-picker-open]');
    const expand = composer.querySelector('[data-note-expand]');
    const error = composer.querySelector('[data-note-error]');
    const list = root.querySelector('[data-notes-list]');
    const empty = root.querySelector('[data-notes-empty]');
    const strings = JSON.parse(composer.dataset.strings || '{}');
    const editor = createNoteEditor(composer);

    let opener = null;

    const boxes = () => options.map((option) => option.querySelector('input[type="checkbox"]'));
    const chosen = () => options.filter((option) => option.querySelector('input').checked);

    // ------------------------------------------------------------- opening

    function open(trigger) {
        opener = trigger ?? null;
        composer.hidden = false;

        /* Set directly rather than through syncPrivate: that one asks before
           clearing a chosen list, and a composer that has just opened has no
           list to lose. */
        if (access && privateToggle) {
            access.hidden = !privateToggle.checked;
        }

        // Focus lands in the note itself, so the composer can be typed into
        // the moment it appears.
        editor ? editor.focus() : input?.focus();
        renderChips();
    }

    function close() {
        composer.hidden = true;
        form.reset();
        editor?.clear();
        collapse();
        closePicker();
        access.hidden = true;
        renderChips();

        if (error) {
            error.hidden = true;
            error.textContent = '';
        }

        if (search) {
            search.value = '';
            filter();
        }

        // Back to the button that opened it: the reader was in the Notes tab
        // and should still be.
        opener?.focus?.();
    }

    /** Cancel, X and Escape ask first — but only when there is something to lose. */
    async function requestClose() {
        const written = editor ? !editor.isEmpty() : input?.value.trim() !== '';

        if (written && ! await confirmAction({
            title: strings.discardTitle,
            message: strings.discard,
            confirm: strings.discardLabel,
            dismiss: strings.keepEditing,
        })) {
            return;
        }

        close();
    }

    // ----------------------------------------------------------- expanding

    function collapse() {
        composer.classList.remove('styledesk_composer--expanded');
        expand?.setAttribute('aria-pressed', 'false');
        toggleExpandIcon(false);
    }

    function toggleExpandIcon(expanded) {
        if (!expand) {
            return;
        }

        const label = expanded ? expand.dataset.restoreTip : expand.dataset.tip;

        expand.querySelector('[data-expand-icon]').hidden = expanded;
        expand.querySelector('[data-restore-icon]').hidden = !expanded;
        expand.setAttribute('aria-label', label);

        /* The tooltip is read off data-tip when it opens, so the attribute
           itself has to change — swapping only the label would leave the
           bubble saying Expand on a window that is already expanded. */
        expand.dataset.tip = label;
        expand.dataset.restoreTip = expanded ? strings.expand : strings.restore;
    }

    // ------------------------------------------------------- private notes

    /**
     * Access For belongs to a private note and nothing else.
     *
     * Turning privacy off throws away a list someone chose, so it asks first
     * — but only when there is a list to lose.
     */
    async function syncPrivate() {
        if (!access || !privateToggle) {
            return;
        }

        if (privateToggle.checked) {
            access.hidden = false;

            return;
        }

        if (chosen().length && ! await confirmAction({
            title: strings.removeAccessTitle,
            message: strings.removeAccess,
            confirm: strings.remove,
        })) {
            // Put the switch back: the reader said no to losing the list.
            privateToggle.checked = true;

            return;
        }

        access.hidden = true;
        boxes().forEach((box) => { box.checked = false; });
        renderChips();
    }

    // ------------------------------------------------ the Select People modal

    /**
     * The modal edits a draft, not the form.
     *
     * The checkboxes are what the form posts, so they are only written when
     * Assign is pressed — Cancel then genuinely changes nothing, rather than
     * having to undo what browsing the list already did.
     */
    let draft = new Set();

    function openPicker() {
        if (!picker) {
            return;
        }

        draft = new Set(chosen().map((option) => option.querySelector('input').value));

        options.forEach((option) => {
            option.querySelector('input').checked = draft.has(option.querySelector('input').value);
        });

        picker.hidden = false;

        if (search) {
            search.value = '';
            filter();
            search.focus();
        }

        refreshCount();
    }

    function closePicker() {
        if (!picker) {
            return;
        }

        picker.hidden = true;
        pickerOpen?.focus();
    }

    /** Cancel leaves the form exactly as the modal found it. */
    function cancelPicker() {
        options.forEach((option) => {
            const box = option.querySelector('input');
            box.checked = draft.has(box.value);
        });

        closePicker();
        renderChips();
    }

    function applyPicker() {
        closePicker();
        renderChips();
    }

    function refreshCount() {
        const selected = chosen().length;

        if (count) {
            count.textContent = (strings.selected || '').replace(':count', String(selected));
        }

        // Nothing chosen is a valid answer — owners and admins can still read
        // the note — but Assign would then be a button that does nothing.
        if (assign) {
            assign.disabled = selected === 0;
        }
    }

    function filter() {
        if (!search) {
            return;
        }

        const term = search.value.trim().toLowerCase();
        let shown = 0;

        options.forEach((option) => {
            const on = !term || option.dataset.label.includes(term);
            option.hidden = !on;

            if (on) {
                shown += 1;
            }
        });

        if (noMatches) {
            noMatches.hidden = shown !== 0;
        }
    }

    // --------------------------------------------------------- the choices

    /**
     * The chips and the field's summary, drawn from what is ticked.
     *
     * One direction only: the checkboxes are the state and these are a view
     * of it, so the two cannot disagree about who was chosen.
     */
    function renderChips() {
        if (!chips) {
            return;
        }

        chips.replaceChildren();

        const selected = chosen();

        /* A caption under the chips, not a placeholder in a field: when
           nobody is chosen it says so, rather than inviting a search that
           does not happen here. */
        if (summary) {
            summary.textContent = selected.length
                ? (strings.accessSummary || '').replace(':count', String(selected.length))
                : strings.accessNone;
        }

        selected.forEach((option) => {
            const chip = document.createElement('span');
            chip.className = 'styledesk_person';

            const avatar = document.createElement('span');
            avatar.className = 'sd-avatar styledesk_person__avatar';
            avatar.setAttribute('aria-hidden', 'true');

            if (option.dataset.avatar) {
                const image = document.createElement('img');
                image.src = option.dataset.avatar;
                image.alt = '';
                avatar.appendChild(image);
            } else {
                avatar.textContent = option.dataset.initials;
            }

            const name = document.createElement('span');
            name.className = 'styledesk_person__name';
            name.textContent = option.dataset.name;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'styledesk_person__remove';
            remove.setAttribute('aria-label', `${strings.remove} ${option.dataset.name}`);
            remove.innerHTML = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>';

            // Taking someone off an unsaved note is not destructive — nothing
            // has been written down yet — so it does not ask.
            remove.addEventListener('click', () => {
                option.querySelector('input').checked = false;
                renderChips();
            });

            chip.append(avatar, name, remove);
            chips.appendChild(chip);
        });
    }

    // ------------------------------------------------------------- wiring

    root.querySelectorAll('[data-note-compose]').forEach((button) => {
        button.addEventListener('click', () => open(button));
    });

    composer.querySelectorAll('[data-note-close]').forEach((button) => {
        button.addEventListener('click', requestClose);
    });

    expand?.addEventListener('click', () => {
        const expanded = composer.classList.toggle('styledesk_composer--expanded');
        expand.setAttribute('aria-pressed', expanded ? 'true' : 'false');
        toggleExpandIcon(expanded);
        editor?.focus();
    });

    privateToggle?.addEventListener('change', syncPrivate);

    pickerOpen?.addEventListener('click', openPicker);

    composer.querySelectorAll('[data-note-picker-cancel]').forEach((button) => {
        button.addEventListener('click', cancelPicker);
    });

    assign?.addEventListener('click', applyPicker);

    search?.addEventListener('input', filter);

    /* Enter in the search box picks the only match rather than submitting
       the note — a form's default action is the last thing wanted here. */
    search?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        const shown = options.filter((option) => !option.hidden);

        if (shown.length === 1) {
            const box = shown[0].querySelector('input');
            box.checked = !box.checked;
            refreshCount();
        }
    });

    options.forEach((option) => {
        option.querySelector('input').addEventListener('change', refreshCount);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        // The modal is on top, so it is what Escape closes first.
        if (picker && !picker.hidden) {
            event.stopPropagation();
            cancelPicker();

            return;
        }

        if (!composer.hidden) {
            requestClose();
        }
    });

    // -------------------------------------------------------------- saving

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const body = new FormData(form);

        fetch(form.getAttribute('action'), {
            method: 'POST',
            body,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then((response) => {
            if (response.status === 422) {
                return response.json().then((data) => {
                    const first = data.errors && Object.keys(data.errors)[0];

                    if (error && first) {
                        error.textContent = data.errors[first][0];
                        error.hidden = false;
                    }

                    throw new Error('invalid');
                });
            }

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            return response.json();
        }).then((data) => {
            list?.insertAdjacentHTML('afterbegin', data.html);

            if (empty) {
                empty.hidden = true;
            }

            close();
            window.styledesk?.toast?.(strings.added, 'success');
        }).catch((reason) => {
            /* A validation problem has already been shown in the composer.
               Anything else means the request could not be made at all, so
               hand the browser the form it would have posted. */
            if (reason?.message === 'invalid') {
                return;
            }

            form.submit();
        });
    });

    renderChips();
}

/**
 * Private notes open on request.
 *
 * Both halves are already on the page — this reader is allowed to see it —
 * so revealing is a class change rather than a round trip.
 */
export function initNoteReveal(root = document) {
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-note-view]');

        if (!button) {
            return;
        }

        const body = button.closest('[data-note-reveal]')?.querySelector('[data-note-body]');

        if (!body) {
            return;
        }

        const showing = !body.hidden;

        body.hidden = showing;
        button.textContent = showing ? button.dataset.show : button.dataset.hide;
        button.setAttribute('aria-expanded', showing ? 'false' : 'true');
    });
}
