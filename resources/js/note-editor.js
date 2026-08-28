/**
 * The rich text editor inside the note composer.
 *
 * Tiptap, with only the marks a client note actually needs. The toolbar is
 * deliberately short: a note is a message to the next person holding the
 * scissors, not a document, and every control offered is one more thing to
 * decide about while a client is waiting.
 *
 * The editor writes into a hidden textarea, so the form still posts a body
 * the ordinary way and the server sees the same field whether the editor
 * loaded or not.
 */
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';

/** Toolbar buttons that toggle a mark or a block, and how to ask about each. */
const TOGGLES = {
    bold: { run: (chain) => chain.toggleBold(), active: 'bold' },
    italic: { run: (chain) => chain.toggleItalic(), active: 'italic' },
    underline: { run: (chain) => chain.toggleUnderline(), active: 'underline' },
    bulletList: { run: (chain) => chain.toggleBulletList(), active: 'bulletList' },
    orderedList: { run: (chain) => chain.toggleOrderedList(), active: 'orderedList' },
    heading: { run: (chain) => chain.toggleHeading({ level: 3 }), active: ['heading', { level: 3 }] },
};

export function createNoteEditor(composer) {
    const mount = composer.querySelector('[data-editor]');
    const field = composer.querySelector('[data-note-input]');

    if (!mount || !field) {
        return null;
    }

    const toolbar = composer.querySelector('[data-editor-toolbar]');
    const strings = JSON.parse(composer.dataset.strings || '{}');
    const fileInput = composer.querySelector('[data-editor-file]');
    const status = composer.querySelector('[data-editor-status]');

    let lastUpload = null;

    const editor = new Editor({
        element: mount,
        extensions: [
            StarterKit.configure({
                // Nothing a client note needs, and each one is a control the
                // toolbar would have to explain.
                heading: { levels: [3] },
                blockquote: false,
                codeBlock: false,
                horizontalRule: false,
                code: false,
                link: false,
                underline: false,
            }),
            Underline,
            Link.configure({ openOnClick: false, autolink: true }),
            Image.configure({ inline: false, allowBase64: false }),
            /* Drawn by the extension rather than typed into the document, so
               an untouched note is genuinely empty and saves as nothing. */
            Placeholder.configure({ placeholder: () => composer.dataset.placeholder ?? '' }),
        ],
        content: '',
        editorProps: {
            attributes: {
                class: 'styledesk_richtext styledesk_editor__content',
                'aria-label': strings.editorLabel ?? '',
            },
        },
        onUpdate: sync,
        onSelectionUpdate: refresh,
        onTransaction: refresh,
    });

    /* The hidden field is what the form posts, so it is kept in step with
       every keystroke rather than read out of the editor at submit time —
       a submit path that depends on the editor still being alive is a submit
       path that can fail silently. */
    function sync() {
        field.value = editor.isEmpty ? '' : editor.getHTML();
    }

    /** Buttons show what is on at the cursor, in more than colour. */
    function refresh() {
        if (!toolbar) {
            return;
        }

        toolbar.querySelectorAll('[data-command]').forEach((button) => {
            const command = button.dataset.command;
            const toggle = TOGGLES[command];

            if (toggle) {
                const on = Array.isArray(toggle.active)
                    ? editor.isActive(...toggle.active)
                    : editor.isActive(toggle.active);

                button.classList.toggle('is-active', on);
                button.setAttribute('aria-pressed', on ? 'true' : 'false');
            }

            if (command === 'undo') {
                button.disabled = !editor.can().undo();
            }

            if (command === 'redo') {
                button.disabled = !editor.can().redo();
            }
        });
    }

    function setStatus(message, tone) {
        if (!status) {
            return;
        }

        status.textContent = message ?? '';
        status.hidden = !message;
        status.classList.toggle('is-error', tone === 'error');
    }

    /**
     * Upload, then insert where the cursor was.
     *
     * The image goes in only once the server has it: an optimistic preview
     * would leave a picture in the note that does not exist anywhere, and a
     * note saved in that second would reference nothing.
     */
    function upload(file) {
        lastUpload = file;

        setStatus(strings.imageUploading, 'info');

        const body = new FormData();
        body.append('image', file);
        body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        fetch(composer.dataset.imageEndpoint, {
            method: 'POST',
            body,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then((response) => {
            if (response.status === 422) {
                return response.json().then((data) => {
                    throw new Error(data.errors?.image?.[0] ?? strings.imageFailed);
                });
            }

            if (!response.ok) {
                throw new Error(strings.imageFailed);
            }

            return response.json();
        }).then((data) => {
            editor.chain().focus().setImage({ src: data.url, alt: file.name }).run();
            sync();
            setStatus(null);
            lastUpload = null;
        }).catch((reason) => {
            // The failure keeps the file, so Retry is a button rather than a
            // second trip through the file picker.
            setStatus(reason.message || strings.imageFailed, 'error');
        });
    }

    toolbar?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-command]');

        if (!button) {
            return;
        }

        event.preventDefault();

        const command = button.dataset.command;
        const chain = editor.chain().focus();

        if (TOGGLES[command]) {
            TOGGLES[command].run(chain).run();
        } else if (command === 'undo') {
            chain.undo().run();
        } else if (command === 'redo') {
            chain.redo().run();
        } else if (command === 'clear') {
            chain.unsetAllMarks().clearNodes().run();
        } else if (command === 'image') {
            fileInput?.click();
        } else if (command === 'link') {
            promptLink();
        }

        sync();
        refresh();
    });

    /**
     * A link, asked for in the app's own dialog rather than window.prompt.
     *
     * Selecting nothing and pressing Link would otherwise create a link
     * around no text, which is invisible and impossible to remove.
     */
    function promptLink() {
        const panel = composer.querySelector('[data-link-panel]');
        const input = composer.querySelector('[data-link-input]');

        if (!panel || !input) {
            return;
        }

        panel.hidden = false;
        input.value = editor.getAttributes('link').href ?? '';
        input.focus();
    }

    composer.querySelector('[data-link-apply]')?.addEventListener('click', () => {
        const panel = composer.querySelector('[data-link-panel]');
        const input = composer.querySelector('[data-link-input]');
        const href = input.value.trim();

        if (href === '') {
            editor.chain().focus().unsetLink().run();
        } else {
            editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
        }

        panel.hidden = true;
        sync();
        refresh();
    });

    composer.querySelector('[data-link-cancel]')?.addEventListener('click', () => {
        composer.querySelector('[data-link-panel]').hidden = true;
        editor.commands.focus();
    });

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];

        if (file) {
            upload(file);
        }

        // Cleared so choosing the same file twice still fires a change.
        fileInput.value = '';
    });

    composer.querySelector('[data-editor-retry]')?.addEventListener('click', () => {
        if (lastUpload) {
            upload(lastUpload);
        }
    });

    refresh();

    return {
        editor,
        focus: () => editor.commands.focus(),
        isEmpty: () => editor.isEmpty,
        clear: () => {
            editor.commands.clearContent(true);
            setStatus(null);
            lastUpload = null;
            sync();
            refresh();
        },
    };
}
