<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ConfirmDialog from './ConfirmDialog.vue';
import SingleSelect from './SingleSelect.vue';
import UploadProgress from './UploadProgress.vue';

/**
 * The Files tab on a client's profile.
 *
 * Two readings of one set of rows, never two sets. All Files is the filing
 * cabinet — every document, sortable and searchable; Before & After is the
 * same rows read as treatments, because a pair of photographs is only worth
 * anything beside the other half of the pair. Both come from one payload, so
 * the gallery cannot quietly show a treatment the table does not.
 *
 * Nothing here holds a path. Every file is fetched from its own route, which
 * checks the permission again on each request and records the read against
 * the client — see App\Http\Controllers\ClientFileController. That is also
 * why a thumbnail is an <img> pointed at that route rather than at a disk:
 * there is no URL to the disk to point at.
 *
 * The whole payload is re-read after every write rather than patched here.
 * Adding an "after" photograph changes a record, a file row and a count, and
 * a screen doing its own bookkeeping across three lists is a screen where
 * they disagree.
 */
const props = defineProps({
    /** Every endpoint the tab uses; `:file` and `:record` are replaced. */
    urls: { type: Object, required: true },
    /** The tab's first payload, rendered with the page so it opens filled. */
    initial: { type: Object, default: () => ({}) },
    csrf: { type: String, required: true },
    /** Today, from the server: the browser's clock is not the salon's. */
    today: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const files = ref(props.initial.files ?? []);
const records = ref(props.initial.records ?? []);
const options = ref(props.initial.options ?? { services: [], bookings: [], staff: [], categories: [] });
const can = ref(props.initial.can ?? { upload: false, manage: false });

const view = ref('all');
const search = ref('');
const typeFilter = ref('');
const busy = ref(false);
const error = ref('');

/* ------------------------------------------------------------- the table --

   The treatment photographs are in this list too, flattened out of their
   records: the All Files view is meant to answer "what is on this client",
   and a document that only appears in the gallery is a document somebody
   searching for it will not find. It carries its record, so opening it opens
   the comparison rather than one loose image. */
const allRows = computed(() => {
    const loose = files.value.map((file) => ({ ...file, record: null }));

    const treatment = records.value.flatMap((record) => [...record.before, ...record.after]
        .map((file) => ({ ...file, record })));

    return [...loose, ...treatment].sort((a, b) => (a.uploaded_at < b.uploaded_at ? 1 : -1));
});

const visibleRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return allRows.value.filter((row) => {
        if (typeFilter.value && row.kind !== typeFilter.value) {
            return false;
        }

        if (term.length < 2) {
            return true;
        }

        return [row.name, row.category_label, row.service, row.booking, row.uploaded_by, row.note]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term));
    });
});

const typeOptions = computed(() => ({
    image: t('types.image'),
    document: t('types.document'),
    'before-after': t('types.before-after'),
}));

/* ------------------------------------------------------- the card's menu --

   Everything that can be done to one file, behind one control. Five buttons
   on every card is a wall of chrome twenty times over, and on a card a third
   of the window wide they wrap into a second card.

   Placed against its button in viewport coordinates rather than left to the
   flow: the panel is `position: fixed` in the stylesheet — which is what lets
   it escape a card that clips its own overflow — and a fixed panel cannot
   follow a scroll, so it is closed by one instead.

   Not the shared row-menu script: that binds to the elements it finds when
   the page loads, and these cards are rendered and re-rendered here. The
   classes are the shared ones, so it looks like every other row menu. */
const menuFor = ref(null);
const menuStyle = ref({});

function toggleMenu(row, event) {
    if (menuFor.value === row.id) {
        return closeMenu();
    }

    const rect = event.currentTarget.getBoundingClientRect();
    const width = 176;
    const height = 220;

    /* Below by preference, above when the room below has run out — a menu on
       the last card would otherwise open into the fold. */
    const above = window.innerHeight - rect.bottom < height && rect.top > height;

    menuStyle.value = {
        top: above ? 'auto' : `${rect.bottom + 4}px`,
        bottom: above ? `${window.innerHeight - rect.top + 4}px` : 'auto',
        left: `${Math.min(rect.right - width, window.innerWidth - width - 8)}px`,
    };

    menuFor.value = row.id;
}

function closeMenu() {
    menuFor.value = null;
}

/* A click anywhere else, Escape, or any scroll the panel cannot follow. */
function onDocumentClick() {
    closeMenu();
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        closeMenu();

        if (viewer.value) {
            closeViewer();
        }

        return;
    }

    /* The arrows walk the viewer. Bound on the document rather than on the
       dialog: the dialog is not focusable, so a reader who has not clicked
       inside it would otherwise press Right and move the page. */
    if (viewer.value && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
        event.preventDefault();
        step(event.key === 'ArrowLeft' ? -1 : 1);
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('scroll', closeMenu, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', closeMenu, true);

    /* Never leave the page unable to scroll because this component went
       away while a dialog was open. */
    document.body.style.overflow = '';
});

// ---------------------------------------------------------------- writing

/**
 * How far the upload in flight has got, 0–100, or null when none is.
 *
 * XMLHttpRequest rather than fetch for exactly this: fetch cannot report
 * upload progress at all, so a reader adding six photographs from a phone
 * watched a Save button that said "Uploading…" and nothing else for twenty
 * seconds. A form that gives no sign it heard you is a form people press
 * twice.
 */
const progress = ref(null);

/**
 * One request, one refreshed payload.
 *
 * Everything posts as FormData: several of these carry files, and a screen
 * with two ways of talking to the same controller is a screen where only one
 * of them gets the CSRF header right.
 */
function send(url, { method = 'POST', body = null, track = false } = {}) {
    if (busy.value) {
        return Promise.resolve(null);
    }

    busy.value = true;
    error.value = '';
    progress.value = track ? 0 : null;

    const payload = body ?? new FormData();

    /* PATCH and DELETE are spoofed: a multipart body does not survive either
       verb, and everything here posts as FormData so there is one way of
       talking to the controller rather than two. */
    if (method !== 'POST') {
        payload.append('_method', method);
    }

    return new Promise((resolve) => {
        const request = new XMLHttpRequest();

        const finish = () => {
            busy.value = false;
            progress.value = null;
        };

        request.open('POST', url, true);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-CSRF-TOKEN', props.csrf);

        if (track) {
            request.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    progress.value = Math.round((event.loaded / event.total) * 100);
                }
            });

            /* The bytes are up but the answer has not come back: the server
               is still writing the files. Held at 100 so the bar switches to
               its working state rather than sitting still at 99%. */
            request.upload.addEventListener('load', () => {
                progress.value = 100;
            });
        }

        request.addEventListener('load', () => {
            let json = {};

            try {
                json = JSON.parse(request.responseText);
            } catch (exception) {
                /* A response that is not JSON is a failure whatever it says
                   — an HTML error page, or a request PHP refused before
                   Laravel ever saw it. */
            }

            if (request.status < 200 || request.status >= 300) {
                /* The first thing the server actually objected to. A list of
                   every field is a wall the reader has to translate back into
                   "the name is missing". */
                error.value = Object.values(json.errors ?? {})[0]?.[0] ?? json.message ?? t('failed');

                finish();

                return resolve(null);
            }

            if (json.files) {
                apply(json.files);
            }

            finish();
            resolve(json);
        });

        request.addEventListener('error', () => {
            error.value = t('failed');
            finish();
            resolve(null);
        });

        request.addEventListener('abort', () => {
            finish();
            resolve(null);
        });

        request.send(payload);
    });
}

function apply(payload) {
    files.value = payload.files ?? files.value;
    records.value = payload.records ?? records.value;
    options.value = payload.options ?? options.value;
    can.value = payload.can ?? can.value;
}

// ----------------------------------------------------------- adding files

/* Adding is a page of its own now, not a dialog. Several files, two sets of
   thumbnails and six fields of treatment detail do not fit a 34rem panel, and
   the reader gets Back, Close and a real unsaved-changes guard there —
   see resources/js/components/ClientFileWorkflow.vue.

   A draft is reopened through the same page: `resume` carries the batch id of
   an upload or the id of a treatment, and the workflow works out which. */
const resumeUrl = (row) => `${props.urls.create}?draft=${row.record ? row.record.id : row.batch}`;

// ---------------------------------------------------------------- editing

const editing = ref(null);
const editingRecord = ref(null);

function editFile(row) {
    error.value = '';
    editing.value = {
        id: row.id,
        name: row.name,
        note: row.note ?? '',
        category: row.category ?? '',
        service_id: row.service_id ?? '',
        booking_id: row.booking_id ?? '',
    };
}

async function saveEdit() {
    const body = new FormData();

    body.append('name', editing.value.name);
    body.append('note', editing.value.note ?? '');
    body.append('category', editing.value.category ?? '');
    body.append('service_id', editing.value.service_id ?? '');
    body.append('booking_id', editing.value.booking_id ?? '');

    const result = await send(url('update', editing.value.id), { method: 'PATCH', body });

    if (result) {
        editing.value = null;
    }
}

function editRecord(record) {
    error.value = '';
    editingRecord.value = {
        id: record.id,
        title: record.title,
        note: record.note ?? '',
        service_id: record.service_id ?? '',
        booking_id: record.booking_id ?? '',
        staff_id: record.staff_id ?? '',
        treatment_date: record.date ?? props.today,
    };
}

async function saveRecordEdit() {
    const body = new FormData();

    body.append('title', editingRecord.value.title);
    body.append('treatment_date', editingRecord.value.treatment_date ?? '');
    body.append('note', editingRecord.value.note ?? '');
    body.append('service_id', editingRecord.value.service_id ?? '');
    body.append('booking_id', editingRecord.value.booking_id ?? '');
    body.append('staff_id', editingRecord.value.staff_id ?? '');

    const result = await send(props.urls.updateRecord.replace(':record', editingRecord.value.id), {
        method: 'PATCH',
        body,
    });

    if (result) {
        editingRecord.value = null;
    }
}

/* Replacing swaps the bytes and keeps the row, so the file stays where it was
   filed and the timeline says it was replaced rather than that one file
   vanished and another appeared. */
const replacing = ref(null);
const replaceInput = ref(null);

function askReplace(row) {
    replacing.value = row;
    nextTick(() => replaceInput.value?.click());
}

async function onReplace(event) {
    const file = event.target.files?.[0];

    event.target.value = '';

    if (! file || ! replacing.value) {
        return;
    }

    const body = new FormData();
    body.append('file', file);

    await send(url('replace', replacing.value.id), { body, track: true });

    replacing.value = null;
}

/** More photographs on a record that already exists. */
const moreInput = ref(null);
const addingTo = ref(null);

function askMore(record, side) {
    addingTo.value = { record, side };
    nextTick(() => moreInput.value?.click());
}

async function onMore(event) {
    const chosen = Array.from(event.target.files ?? []);

    event.target.value = '';

    if (! chosen.length || ! addingTo.value) {
        return;
    }

    const body = new FormData();
    body.append('side', addingTo.value.side);
    chosen.forEach((file) => body.append('images[]', file));

    await send(props.urls.addImages.replace(':record', addingTo.value.record.id), { body, track: true });

    addingTo.value = null;
}

// --------------------------------------------------------------- deleting

const confirming = ref(null);

const confirmTitle = computed(() => (confirming.value?.type === 'record'
    ? t('confirm_delete_record')
    : t('confirm_delete')));

const confirmBody = computed(() => (confirming.value?.type === 'record'
    ? t('confirm_delete_record_body')
    : t('confirm_delete_body')));

async function confirmDelete() {
    const target = confirming.value;

    confirming.value = null;

    if (! target) {
        return;
    }

    const endpoint = target.type === 'record'
        ? props.urls.destroyRecord.replace(':record', target.id)
        : url('destroy', target.id);

    const result = await send(endpoint, { method: 'DELETE' });

    if (result) {
        /* Deleting answers with the id rather than the whole tab — it is the
           one write that removes rather than changes — so the lists are
           re-read here. */
        await reload();

        if (viewer.value) {
            closeViewer();
        }
    }
}

async function reload() {
    const response = await fetch(props.urls.index, { headers: { Accept: 'application/json' } });

    if (response.ok) {
        apply(await response.json());
    }
}

const url = (key, id) => props.urls[key].replace(':file', id);

/* ------------------------------------------------ the card on the profile --

   One thing: the newest upload on this client, whatever kind it is. A card
   listing three is a second, worse copy of the tab a click away — what a
   reader opening a profile wants is to recognise the last thing that
   happened, not to browse.

   Rendered here rather than by the profile's own Blade so it shares this
   component's viewer. Two implementations of "open the newest photograph"
   is two behaviours to keep in step, and the one on the profile would be the
   one nobody remembers to update. */
const latest = computed(() => {
    const file = files.value[0] ?? null;
    const record = [...records.value]
        .sort((a, b) => (a.uploaded_at < b.uploaded_at ? 1 : -1))[0] ?? null;

    if (! file && ! record) {
        return null;
    }

    if (! record) {
        return { kind: 'file', file };
    }

    if (! file || record.uploaded_at > file.uploaded_at) {
        return { kind: 'record', record };
    }

    return { kind: 'file', file };
});

/* The photograph the card shows: the most recent one in the record,
   whichever side it is on. "The latest" is what somebody wants to see, and
   on a finished treatment that is an after. */
const latestImage = computed(() => {
    const record = latest.value?.record;

    if (! record) {
        return null;
    }

    return [...record.before, ...record.after]
        .sort((a, b) => (a.uploaded_at < b.uploaded_at ? 1 : -1))[0] ?? null;
});

// ---------------------------------------------------------------- viewing

/**
 * The viewer.
 *
 * A treatment opens as the comparison, because that is the thing being
 * looked at; one side at a time is for looking closely at a single
 * photograph. A loose image opens on its own with the rest of the list
 * behind the arrows, so somebody checking four consultation photographs does
 * not have to close and reopen four times.
 */
const viewer = ref(null);
const viewerMode = ref('side');
const zoom = ref(1);

function openViewer(row, list = null) {
    if (row.record) {
        viewer.value = { type: 'record', record: row.record, index: 0, side: row.side ?? 'before' };
        viewerMode.value = 'side';
        zoom.value = 1;

        return;
    }

    const images = (list ?? visibleRows.value).filter((item) => item.is_image && ! item.record);
    const index = Math.max(0, images.findIndex((item) => item.id === row.id));

    viewer.value = { type: 'file', file: row, images, index };
    zoom.value = 1;
}

/**
 * Open a treatment.
 *
 * `mode` decides what the reader is being shown, because the two places this
 * opens from want different things. From the gallery it is the comparison —
 * that is what the record exists for. From the profile card it is a walk
 * through every photograph on the record, because what was clicked was one
 * image and the next thing wanted is the one beside it.
 *
 * The same modal either way, with the same toggles: the difference is which
 * one it lands on.
 */
function openRecord(record, mode = 'side') {
    viewer.value = { type: 'record', record, index: 0, side: 'before' };
    viewerMode.value = mode;
    zoom.value = 1;
}

/** The card's thumbnail: the record, opened on the image that was clicked. */
function openLatest() {
    if (latest.value?.kind === 'record') {
        openRecord(latest.value.record, 'all');

        const at = viewerList.value.findIndex((image) => image.id === latestImage.value?.id);

        if (at > 0) {
            viewer.value = { ...viewer.value, index: at };
        }

        return;
    }

    openViewer(latest.value.file, [latest.value.file]);
}

function closeViewer() {
    viewer.value = null;
    zoom.value = 1;
}

/** The list the arrows walk, which depends on what is being looked at. */
const viewerList = computed(() => {
    if (! viewer.value) {
        return [];
    }

    if (viewer.value.type === 'file') {
        return viewer.value.images;
    }

    const record = currentRecord.value;

    if (viewerMode.value === 'all') {
        /* Both sides, in the order they sit on the record: a walk through
           the treatment rather than through one half of it. */
        return [...record.before, ...record.after];
    }

    if (viewerMode.value === 'before' || viewerMode.value === 'after') {
        return record[viewerMode.value];
    }

    /* Side by side: the unit the arrows move is the PAIR, not the image —
       one press advances both halves, because the whole point of the view is
       that the two are looked at together. So the list is as long as the
       longer side, and each entry stands for one step.
       
       The entry itself is the before where there is one, so Download and
       Delete in the footer act on something real; a side with fewer
       photographs simply runs out and shows its empty state. */
    return Array.from(
        { length: Math.max(record.before.length, record.after.length) },
        (_, index) => record.before[index] ?? record.after[index],
    );
});

/** The photograph on one side at the step the reader is on, if there is one. */
const sideAt = (side) => currentRecord.value?.[side]?.[viewer.value?.index ?? 0] ?? null;

const viewerCurrent = computed(() => viewerList.value[viewer.value?.index ?? 0] ?? null);

/* Zoom is offered only when an image is on screen. A document shows "this
   file cannot be shown here", and three buttons under it that magnify
   nothing are three controls the reader has to try before believing it. */
const canZoom = computed(() => {
    if (! viewer.value) {
        return false;
    }

    return viewer.value.type === 'file'
        ? Boolean(viewerCurrent.value?.is_image)
        : viewerMode.value !== 'side' && Boolean(viewerCurrent.value);
});

/* Read back out of the live list rather than held: adding a photograph while
   the viewer is open must show it, and a copy taken on open would not. */
const currentRecord = computed(() => {
    const held = viewer.value?.record;

    return records.value.find((record) => record.id === held?.id) ?? held;
});

/* The page behind a modal must not scroll. On a long profile the wheel
   otherwise moves the page under the dialog, and the reader closes it to
   find themselves somewhere else entirely.

   Watched rather than set in each opener: there are five things on this
   screen that open a dialog, and a lock applied at four of them is a lock
   that leaks. */
watch(
    () => viewer.value !== null || editing.value !== null || editingRecord.value !== null || confirming.value !== null,
    (locked) => {
        document.body.style.overflow = locked ? 'hidden' : '';
    },
);

function step(by) {
    if (! viewerList.value.length) {
        return;
    }

    const next = (viewer.value.index + by + viewerList.value.length) % viewerList.value.length;

    viewer.value = { ...viewer.value, index: next };
    zoom.value = 1;
}

function setMode(mode) {
    viewerMode.value = mode;
    viewer.value = { ...viewer.value, index: 0 };
    zoom.value = 1;
}
</script>

<template>
    <div>
        <!-- The two readings of one set of rows, and the one button that adds
             to it. Privacy is stated rather than implied: a member of staff
             photographing somebody's scalp should be able to see, on the
             screen where they do it, what happens to the photograph. -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- The same compact segment the Bookings tab uses. These are
                 two readings of one set of rows rather than two independent
                 toggles, and a pair of loose chips does not say that — a
                 track with one item lit does. -->
            <div class="sd-subnav sd-subnav--compact" role="group" :aria-label="t('title')">
                <button v-for="(label, key) in { all: t('views.all'), records: t('views.records') }" :key="key"
                        type="button" class="sd-subnav__item"
                        :aria-current="view === key ? 'page' : null"
                        @click="view = key">
                    {{ label }}
                </button>
            </div>

            <div class="flex-1"></div>

            <!-- A link to a page, not a button that opens a dialog:
                 uploading is a sitting now, and it has its own address so a
                 reader can be sent straight to it. -->
            <a v-if="can.upload" :href="urls.create"
               class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold
                      inline-flex items-center transition-colors">
                + {{ t('add') }}
            </a>
        </div>

        <p class="text-[12px] text-faint mt-2">{{ t('private_note') }}</p>

        <p v-if="error" class="sd-alert sd-alert--warn mt-3 text-[13px]" role="alert">{{ error }}</p>

        <!-- "Add more images" and "Replace file" are started from a card's
             menu and have no dialog of their own to report into, so the bar
             appears here instead. Only when no form is open — otherwise the
             same upload would be reported twice on one screen. -->
        <div v-if="progress !== null" class="mt-3">
            <UploadProgress :value="progress" :uploading-label="t('uploading')"
                                    :processing-label="t('processing')" />
        </div>

        <!-- ============================================== all files -->
        <div v-if="view === 'all'" class="mt-4">
            <!-- `sd-compact` is what makes the two the same height: it
                 sizes the combo's own trigger to 2.25rem and 13px, and the
                 search is pinned to the same so the band reads as one row of
                 controls rather than two of slightly different heights. -->
            <div class="sd-compact flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[220px]">
                    <input v-model="search" type="search" class="sd-input !h-9 !text-[13px]"
                           :placeholder="t('filters.search')" :aria-label="t('filters.search')">
                </div>

                <div class="w-full sm:w-[170px]">
                    <SingleSelect v-model="typeFilter" :options="typeOptions"
                                 :placeholder="t('filters.all')" />
                </div>
            </div>

            <p v-if="! allRows.length" class="text-[13px] text-sub mt-4">
                {{ t('empty') }}<br>
                <span class="text-[12px] text-faint">{{ t('empty_hint') }}</span>
            </p>

            <p v-else-if="! visibleRows.length" class="text-[13px] text-sub mt-4">{{ t('no_results') }}</p>

            <!-- Cards rather than a table.

                 Nine columns of a file's metadata is a horizontal scrollbar
                 on any laptop, and the profile is already a three-column
                 workspace — the Files tab has about a third of the window to
                 work in. A card puts the two things a reader actually scans
                 for, the thumbnail and the name, on the first line, and lets
                 the rest wrap under them instead of running off the side.

                 Compact on purpose: this is a filing cabinet, and a reader
                 looking for last March's consent form is scanning twenty of
                 these rather than reading one.

                 Full width, one to a row. Two to a row put the name, the
                 badges and the "service · booking" line into a column
                 narrower than the text they hold, so every one of them
                 truncated — and a list of truncated filenames is the thing
                 the card was meant to fix. -->
            <div v-else class="mt-3 space-y-2">
                <!-- Built from utilities rather than `styledesk_bookingcard`:
                     that class is `display: block` and wins over a `flex`
                     utility on source order, which folded the menu onto its
                     own line under the thumbnail. Same border, radius and
                     hover as the booking cards beside it. -->
                <article v-for="row in visibleRows" :key="row.id"
                         class="rounded-card border border-line bg-white px-3 py-2.5 flex items-start gap-2.5
                                hover:border-brand transition-colors">
                    <!-- The picture is the control: clicking what you are
                         looking at is what a reader tries first. -->
                    <button type="button" class="shrink-0" @click="openViewer(row)">
                        <img v-if="row.is_image" :src="row.url" alt=""
                             class="h-12 w-12 rounded-lg object-cover border border-line" loading="lazy">
                        <span v-else
                              class="h-12 w-12 rounded-lg border border-line flex items-center justify-center
                                     text-[10px] font-semibold uppercase text-sub">
                            {{ row.extension }}
                        </span>
                    </button>

                    <div class="min-w-0 flex-1">
                        <button type="button" class="block max-w-full text-left" @click="openViewer(row)">
                            <span class="block text-[13px] font-semibold text-head truncate hover:text-link">
                                {{ row.name }}
                            </span>
                        </button>

                        <!-- What kind of thing it is, and how it is filed.
                             Badges rather than two more labelled lines: they
                             are the two fields the type filter works on, so
                             they should look like the filter's answers. -->
                        <div class="flex flex-wrap items-center gap-1 mt-1">
                            <!-- Draft first and in its own colour: it is the
                                 one badge here that says the record is not
                                 finished, and a reader scanning the list has
                                 to be able to tell that from what kind of
                                 file it is. -->
                            <span v-if="row.is_draft || row.record?.is_draft"
                                  class="styledesk_badge styledesk_badge--attention">{{ t('draft') }}</span>
                            <span class="styledesk_badge styledesk_badge--soon">{{ t('types.' + row.kind) }}</span>
                            <span v-if="row.category_label" class="styledesk_badge styledesk_badge--info">
                                {{ row.category_label }}
                            </span>
                        </div>

                        <!-- Only what this file actually has. A card that
                             prints "Service: —" three times is three lines
                             telling the reader nothing. -->
                        <p v-if="row.service || row.booking" class="text-[12px] text-sub mt-1 truncate">
                            <span v-if="row.service">{{ row.service }}</span>
                            <span v-if="row.service && row.booking"> · </span>
                            <span v-if="row.booking" class="font-mono">{{ row.booking }}</span>
                        </p>

                        <p v-if="row.note" class="text-[12px] text-sub mt-1 line-clamp-2">{{ row.note }}</p>

                        <p class="text-[11.5px] text-faint mt-1">
                            {{ [row.uploaded_label, row.uploaded_by, row.size].filter(Boolean).join(' · ') }}
                        </p>
                    </div>

                    <!-- Everything that can be done to it, in one control.
                         Five buttons per card is a wall of chrome twenty
                         times over, and on a card the width of a third of
                         the window they wrap into a second card. -->
                    <span class="styledesk_rowmenu shrink-0" :class="menuFor === row.id ? 'is-open' : ''">
                        <button type="button" class="styledesk_rowmenu__button"
                                aria-haspopup="true" :aria-expanded="menuFor === row.id ? 'true' : 'false'"
                                :aria-label="t('actions.menu')"
                                @click.stop="toggleMenu(row, $event)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                            </svg>
                        </button>

                        <!-- Fixed and placed against the button, like every
                             other row menu in the app: a card clips its own
                             overflow, and an absolutely positioned panel on
                             the last card opens into the fold. -->
                        <span v-if="menuFor === row.id" class="styledesk_rowmenu__pop" role="menu"
                              :style="menuStyle" @click.stop>
                            <button type="button" class="styledesk_rowmenu__item" role="menuitem"
                                    @click="closeMenu(); openViewer(row)">
                                {{ t('actions.view') }}
                            </button>

                            <a :href="row.download_url" class="styledesk_rowmenu__item" role="menuitem"
                               @click="closeMenu()">
                                {{ t('actions.download') }}
                            </a>

                            <!-- A treatment photograph is edited through its
                                 record: its name, service and booking come
                                 from the treatment, and letting one image
                                 disagree with the other five is how a
                                 before-and-after stops being one thing. -->
                            <!-- An unfinished upload is picked up where it
                                 was left rather than edited field by field:
                                 what it is missing is usually the other
                                 photographs, and only the workflow can take
                                 those. -->
                            <a v-if="can.upload && (row.is_draft || row.record?.is_draft)"
                               :href="resumeUrl(row)" class="styledesk_rowmenu__item" role="menuitem">
                                {{ t('actions.continue') }}
                            </a>

                            <template v-if="can.manage && ! row.record">
                                <button type="button" class="styledesk_rowmenu__item" role="menuitem"
                                        @click="closeMenu(); editFile(row)">
                                    {{ t('actions.edit') }}
                                </button>

                                <button type="button" class="styledesk_rowmenu__item" role="menuitem"
                                        @click="closeMenu(); askReplace(row)">
                                    {{ t('actions.replace') }}
                                </button>
                            </template>

                            <button v-if="can.manage && row.record" type="button"
                                    class="styledesk_rowmenu__item" role="menuitem"
                                    @click="closeMenu(); editRecord(row.record)">
                                {{ t('actions.edit_details') }}
                            </button>

                            <!-- Destructive last, behind its own rule: a menu
                                 where Delete sits one row above Download is a
                                 menu that will eventually be misread. -->
                            <template v-if="can.manage">
                                <span class="styledesk_rowmenu__rule" role="separator"></span>

                                <button type="button" class="styledesk_rowmenu__item styledesk_rowmenu__item--danger"
                                        role="menuitem" @click="closeMenu(); confirming = { type: 'file', id: row.id }">
                                    {{ t('actions.delete') }}
                                </button>
                            </template>
                        </span>
                    </span>
                </article>
            </div>
        </div>

        <!-- ========================================== before & after -->
        <!-- Cards rather than rows: what a stylist is showing a client is the
             comparison, and a table of filenames is the one shape that cannot
             show it. -->
        <div v-else class="mt-4">
            <p v-if="! records.length" class="text-[13px] text-sub">
                {{ t('empty_records') }}<br>
                <span class="text-[12px] text-faint">{{ t('empty_hint') }}</span>
            </p>

            <div v-else class="space-y-3">
                <div v-for="record in records" :key="record.id"
                     class="rounded-card border border-line p-3.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[14px] font-semibold text-head truncate">{{ record.title }}</p>
                            <p class="text-[12px] text-sub">{{ record.date_label }}</p>
                        </div>

                        <span class="shrink-0 flex items-center gap-1">
                            <span v-if="record.is_draft"
                                  class="styledesk_badge styledesk_badge--attention">{{ t('draft') }}</span>
                            <span class="styledesk_badge styledesk_badge--soon">{{ record.count }}</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 mt-3">
                        <div v-for="side in ['before', 'after']" :key="side">
                            <p class="text-[12px] font-semibold text-sub mb-1">{{ t('sides.' + side) }}</p>

                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="image in record[side]" :key="image.id" type="button"
                                        @click="openRecord(record)">
                                    <img :src="image.url" alt=""
                                         class="h-14 w-14 rounded-lg object-cover border border-line" loading="lazy">
                                </button>

                                <p v-if="! record[side].length" class="text-[12px] text-faint py-3">
                                    {{ t('viewer.empty_side') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-3 text-[12px] text-sub space-y-0.5">
                        <div v-if="record.service" class="flex gap-1.5">
                            <dt class="font-semibold">{{ t('fields.service') }}:</dt>
                            <dd>{{ record.service }}</dd>
                        </div>
                        <div v-if="record.staff" class="flex gap-1.5">
                            <dt class="font-semibold">{{ t('fields.staff') }}:</dt>
                            <dd>{{ record.staff }}</dd>
                        </div>
                        <div v-if="record.booking" class="flex gap-1.5">
                            <dt class="font-semibold">{{ t('fields.booking') }}:</dt>
                            <dd class="font-mono">{{ record.booking }}</dd>
                        </div>
                    </dl>

                    <p v-if="record.note" class="text-[12.5px] text-ink mt-2 leading-relaxed">{{ record.note }}</p>

                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <button type="button" class="styledesk_action styledesk_action--sm"
                                @click="openRecord(record)">{{ t('actions.details') }}</button>

                        <a v-if="can.upload && record.is_draft"
                           :href="`${urls.create}?draft=${record.id}`"
                           class="styledesk_action styledesk_action--sm">{{ t('actions.continue') }}</a>

                        <button v-if="can.upload" type="button" class="styledesk_action styledesk_action--sm"
                                @click="askMore(record, 'before')">{{ t('actions.add_before') }}</button>

                        <button v-if="can.upload" type="button" class="styledesk_action styledesk_action--sm"
                                @click="askMore(record, 'after')">{{ t('actions.add_after') }}</button>

                        <button v-if="can.manage" type="button" class="styledesk_action styledesk_action--sm"
                                @click="editRecord(record)">{{ t('actions.edit_details') }}</button>

                        <button v-if="can.manage" type="button"
                                class="styledesk_action styledesk_action--sm styledesk_action--danger"
                                @click="confirming = { type: 'record', id: record.id }">
                            {{ t('actions.delete_record') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- The two file inputs the row actions reach for. Off-screen rather
             than hidden: a display:none input cannot be clicked open in every
             browser. -->
        <input ref="replaceInput" type="file" class="sr-only" @change="onReplace">
        <input ref="moreInput" type="file" accept="image/*" multiple class="sr-only" @change="onMore">

        <!-- ================================================ dialogs -->

        <!-- Editing what a file is called and what it is about. Never what it
             contains — that is Replace, and the two are different acts. -->
        <div v-if="editing" class="styledesk_modal" role="dialog" aria-modal="true" aria-labelledby="editFileTitle">
            <div class="styledesk_modal__scrim" @click="editing = null"></div>

            <div class="styledesk_modal__panel">
                <div class="styledesk_modal__head">
                    <h2 id="editFileTitle" class="text-[15px] font-semibold text-head">{{ t('actions.edit') }}</h2>
                    <button type="button" class="styledesk_modal__close" :aria-label="t('cancel')" @click="editing = null">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="styledesk_modal__body space-y-3">
                    <label class="block">
                        <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.name') }}</span>
                        <input v-model="editing.name" type="text" class="sd-input">
                    </label>

                    <label class="block">
                        <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.note') }}</span>
                        <textarea v-model="editing.note" rows="2" class="sd-input !h-auto py-2"></textarea>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.category') }}</span>
                            <SingleSelect v-model="editing.category" :options="Object.fromEntries(options.categories.map((row) => [row.id, row.name]))"
                                         :placeholder="t('fields.none')" />
                        </label>

                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.service') }}</span>
                            <SingleSelect v-model="editing.service_id" :options="Object.fromEntries(options.services.map((row) => [row.id, row.name]))"
                                         :placeholder="t('fields.none')" />
                        </label>

                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.booking') }}</span>
                            <SingleSelect v-model="editing.booking_id" :options="Object.fromEntries(options.bookings.map((row) => [row.id, row.name]))"
                                         :placeholder="t('fields.none')" />
                        </label>
                    </div>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="button" :disabled="busy"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                            @click="saveEdit">{{ t('save') }}</button>

                    <button type="button" class="styledesk_action" @click="editing = null">{{ t('cancel') }}</button>
                </div>
            </div>
        </div>

        <!-- The treatment's own details. -->
        <div v-if="editingRecord" class="styledesk_modal" role="dialog" aria-modal="true"
             aria-labelledby="editRecordTitle">
            <div class="styledesk_modal__scrim" @click="editingRecord = null"></div>

            <div class="styledesk_modal__panel">
                <div class="styledesk_modal__head">
                    <h2 id="editRecordTitle" class="text-[15px] font-semibold text-head">
                        {{ t('actions.edit_details') }}
                    </h2>
                    <button type="button" class="styledesk_modal__close" :aria-label="t('cancel')"
                            @click="editingRecord = null">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="styledesk_modal__body space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.title') }}</span>
                            <input v-model="editingRecord.title" type="text" class="sd-input">
                        </label>

                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.treatment_date') }}</span>
                            <input v-model="editingRecord.treatment_date" type="date" class="sd-input">
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.service') }}</span>
                            <SingleSelect v-model="editingRecord.service_id" :options="Object.fromEntries(options.services.map((row) => [row.id, row.name]))"
                                         :placeholder="t('fields.none')" />
                        </label>

                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.booking') }}</span>
                            <SingleSelect v-model="editingRecord.booking_id" :options="Object.fromEntries(options.bookings.map((row) => [row.id, row.name]))"
                                         :placeholder="t('fields.none')" />
                        </label>

                        <label class="block">
                            <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.staff') }}</span>
                            <SingleSelect v-model="editingRecord.staff_id" :options="Object.fromEntries(options.staff.map((row) => [row.id, row.name]))"
                                         :placeholder="t('fields.none')" />
                        </label>
                    </div>

                    <label class="block">
                        <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.note') }}</span>
                        <textarea v-model="editingRecord.note" rows="2" class="sd-input !h-auto py-2"></textarea>
                    </label>
                </div>

                <div class="styledesk_modalfoot">
                    <button type="button" :disabled="busy"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                            @click="saveRecordEdit">{{ t('save') }}</button>

                    <button type="button" class="styledesk_action" @click="editingRecord = null">{{ t('cancel') }}</button>
                </div>
            </div>
        </div>

        <!-- =============================================== the viewer -->
        <!-- Teleported to the body, like the card above it.
             This component lives inside the Files tab panel, and a panel the
             reader is not on carries `hidden` — which hides everything
             inside it, a fixed-position dialog included. Opening the viewer
             from the profile card while the Bookings tab was showing opened
             a modal nobody could see. -->
        <Teleport to="body">
        <div v-if="viewer" class="styledesk_modal" role="dialog" aria-modal="true" aria-labelledby="viewerTitle"
             @keydown.esc="closeViewer" @keydown.left="step(-1)" @keydown.right="step(1)">
            <div class="styledesk_modal__scrim" @click="closeViewer"></div>

            <!-- Wider than every other dialog here, and stated as `width`
                 rather than `max-width`: the base class sets
                 `width: min(34rem, 100%)`, so a max-width on its own leaves
                 the panel at 544px — which is not enough to put a before and
                 an after beside each other. -->
            <div class="styledesk_modal__panel" style="width: min(980px, 100%); max-width: 980px">
                <div class="styledesk_modal__head">
                    <h2 id="viewerTitle" class="text-[15px] font-semibold text-head truncate">
                        {{ viewer.type === 'record' ? currentRecord.title : viewer.file.name }}
                    </h2>

                    <button type="button" class="styledesk_modal__close" :aria-label="t('viewer.close')"
                            @click="closeViewer">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="styledesk_modal__body">
                    <!-- A treatment: side by side leads, because the
                         comparison is the thing. -->
                    <template v-if="viewer.type === 'record'">
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="mode in ['side', 'all', 'before', 'after']" :key="mode" type="button"
                                    class="styledesk_action styledesk_action--sm"
                                    :class="viewerMode === mode ? 'is-active' : ''"
                                    :aria-pressed="viewerMode === mode ? 'true' : 'false'"
                                    @click="setMode(mode)">
                                {{ { side: t('viewer.side_by_side'), all: t('viewer.all') }[mode]
                                    ?? t('viewer.' + mode + '_only') }}
                            </button>
                        </div>

                        <!-- Both halves at the step the reader is on. The
                             arrows move the pair, so Next advances the before
                             and the after together — which is the whole point
                             of looking at them side by side.

                             A fixed stage on each side, for the same reason
                             the single-image view has one: stepping between a
                             portrait and a landscape must not resize the
                             modal under the cursor. -->
                        <div v-if="viewerMode === 'side'" class="grid grid-cols-2 gap-3 mt-3">
                            <div v-for="side in ['before', 'after']" :key="side">
                                <p class="text-[12px] font-semibold text-sub mb-1.5">{{ t('sides.' + side) }}</p>

                                <div class="h-[340px] flex items-center justify-center rounded-lg bg-hover overflow-hidden">
                                    <img v-if="sideAt(side)" :src="sideAt(side).url" alt=""
                                         class="max-h-full max-w-full rounded-lg border border-line object-contain">

                                    <!-- This side has fewer photographs than
                                         the other, so at this step it has
                                         nothing to show. Said, rather than
                                         left blank. -->
                                    <p v-else class="text-[12.5px] text-faint text-center px-3">
                                        {{ t('viewer.empty_side') }}
                                    </p>
                                </div>

                                <!-- The rest of that side, where there is more
                                     than one: a colour photographed from three
                                     angles is three befores. Each jumps to its
                                     own step, and the one being shown says so.
                                     -->
                                <div v-if="currentRecord[side].length > 1" class="flex flex-wrap gap-1.5 mt-2">
                                    <button v-for="(image, index) in currentRecord[side]" :key="image.id" type="button"
                                            class="rounded overflow-hidden border-2 transition-colors"
                                            :class="index === viewer.index ? 'border-brand' : 'border-transparent'"
                                            :aria-current="index === viewer.index ? 'true' : 'false'"
                                            @click="viewer = { ...viewer, index }">
                                        <img :src="image.url" alt=""
                                             class="h-12 w-12 rounded object-cover border border-line">
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- A stage of a fixed height rather than an image
                             that sets its own: stepping between a portrait
                             and a landscape photograph otherwise resized the
                             modal under the reader's cursor, moving the Next
                             button out from under it. -->
                        <div v-else class="mt-3 h-[460px] flex items-center justify-center overflow-hidden">
                            <img v-if="viewerCurrent" :src="viewerCurrent.url" alt=""
                                 class="rounded-lg border border-line max-w-full max-h-full object-contain transition-transform"
                                 :style="{ transform: `scale(${zoom})` }">

                            <p v-else class="text-[12.5px] text-faint">{{ t('viewer.empty_side') }}</p>
                        </div>
                    </template>

                    <!-- One file. An image is shown; anything else is offered
                         rather than pretended at — a PDF drawn as a grey box
                         is worse than a link that opens it. -->
                    <template v-else>
                        <!-- The fixed stage is for images, where it stops the
                             modal resizing as the reader steps between a
                             portrait and a landscape. A document has one
                             state and no navigation, so it takes the room it
                             needs and no more. -->
                        <div class="flex flex-col items-center justify-center overflow-hidden"
                             :class="viewerCurrent?.is_image ? 'h-[460px]' : 'py-12'">
                            <img v-if="viewerCurrent?.is_image" :src="viewerCurrent.url" alt=""
                                 class="rounded-lg border border-line max-w-full max-h-full object-contain transition-transform"
                                 :style="{ transform: `scale(${zoom})` }">

                            <div v-else class="text-center">
                                <p class="text-[13px] text-sub">{{ t('viewer.no_preview') }}</p>
                                <a :href="viewer.file.url" target="_blank" rel="noopener"
                                   class="styledesk_action styledesk_action--sm mt-2 inline-flex">
                                    {{ t('viewer.open') }}
                                </a>
                            </div>
                        </div>
                    </template>

                    <!-- Where you are, and how to move. Hidden on a single
                         image, where arrows would be three controls that do
                         nothing. -->
                    <div v-if="viewerList.length > 1" class="flex items-center justify-center gap-3 mt-3">
                        <button type="button" class="styledesk_action styledesk_action--sm styledesk_action--icon"
                                :aria-label="t('viewer.previous')" @click="step(-1)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <!-- Tabular figures so the counter does not jump a
                             pixel sideways between 1 and 2, which on a
                             control the reader is clicking repeatedly reads
                             as the whole row twitching. -->
                        <span class="text-[13px] font-semibold text-ink tabular-nums">
                            {{ t('viewer.position').replace(':index', viewer.index + 1).replace(':total', viewerList.length) }}
                        </span>

                        <button type="button" class="styledesk_action styledesk_action--sm styledesk_action--icon"
                                :aria-label="t('viewer.next')" @click="step(1)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>

                    <div v-if="canZoom" class="flex items-center justify-center gap-1.5 mt-2">
                        <button type="button" class="styledesk_action styledesk_action--sm"
                                :aria-label="t('viewer.zoom_out')" @click="zoom = Math.max(1, zoom - 0.25)">−</button>
                        <button type="button" class="styledesk_action styledesk_action--sm"
                                @click="zoom = 1">{{ t('viewer.reset') }}</button>
                        <button type="button" class="styledesk_action styledesk_action--sm"
                                :aria-label="t('viewer.zoom_in')" @click="zoom = Math.min(4, zoom + 0.25)">+</button>
                    </div>

                    <!-- What it is, under what it looks like. -->
                    <dl class="mt-4 text-[12.5px] text-sub grid gap-x-4 gap-y-1 sm:grid-cols-2">
                        <template v-if="viewer.type === 'record'">
                            <div class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('fields.treatment_date') }}:</dt>
                                <dd>{{ currentRecord.date_label }}</dd>
                            </div>
                            <div v-if="currentRecord.service" class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('fields.service') }}:</dt>
                                <dd>{{ currentRecord.service }}</dd>
                            </div>
                            <div v-if="currentRecord.staff" class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('fields.staff') }}:</dt>
                                <dd>{{ currentRecord.staff }}</dd>
                            </div>
                            <div v-if="currentRecord.booking" class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('fields.booking') }}:</dt>
                                <dd class="font-mono">{{ currentRecord.booking }}</dd>
                            </div>
                        </template>

                        <template v-else>
                            <div class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('columns.uploaded_on') }}:</dt>
                                <dd>{{ viewer.file.uploaded_label }}</dd>
                            </div>
                            <div v-if="viewer.file.uploaded_by" class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('columns.uploaded_by') }}:</dt>
                                <dd>{{ viewer.file.uploaded_by }}</dd>
                            </div>
                            <div v-if="viewer.file.category_label" class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('fields.category') }}:</dt>
                                <dd>{{ viewer.file.category_label }}</dd>
                            </div>
                            <div v-if="viewer.file.size" class="flex gap-1.5">
                                <dt class="font-semibold">{{ t('columns.preview') }}:</dt>
                                <dd>{{ viewer.file.size }}</dd>
                            </div>
                        </template>
                    </dl>

                    <p v-if="(viewer.type === 'record' ? currentRecord.note : viewer.file.note)"
                       class="text-[12.5px] text-ink mt-2 leading-relaxed">
                        {{ viewer.type === 'record' ? currentRecord.note : viewer.file.note }}
                    </p>
                </div>

                <div class="styledesk_modalfoot">
                    <a v-if="viewer.type === 'file'" :href="viewer.file.download_url" class="styledesk_action">
                        {{ t('actions.download') }}
                    </a>

                    <a v-else-if="viewerCurrent" :href="viewerCurrent.download_url" class="styledesk_action">
                        {{ t('actions.download') }}
                    </a>

                    <!-- One image out of a record, without taking the record.
                         The two are separate on purpose: deleting a bad
                         photograph is not deleting the treatment. -->
                    <button v-if="can.manage && viewer.type === 'record' && viewerCurrent" type="button"
                            class="styledesk_action styledesk_action--danger"
                            @click="confirming = { type: 'file', id: viewerCurrent.id }">
                        {{ t('actions.delete') }}
                    </button>

                    <button type="button" class="styledesk_action" @click="closeViewer">{{ t('viewer.close') }}</button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- For the same reason: it is raised from the viewer, which can be
             open while this panel is hidden. -->
        <Teleport to="body">
        <ConfirmDialog :open="confirming !== null" :title="confirmTitle" :message="confirmBody"
                       :confirm-label="t('confirm')" :cancel-label="t('keep')"
                       @confirm="confirmDelete" @cancel="confirming = null" />
        </Teleport>

        <!-- ======================= the card on the profile -->
        <!-- Teleported into the right-hand column, which is a different part
             of the page but the same component — so the thumbnail opens the
             viewer above rather than a second implementation of it, and the
             card follows an upload without a page reload. -->
        <Teleport to="#client-recent-files">
            <section class="styledesk_infocard mt-5">
                <div class="flex items-start gap-2">
                    <h2 class="styledesk_infocard__title flex-1">{{ t('recent.title') }}</h2>

                    <button v-if="latest" type="button"
                            class="text-[12px] font-semibold underline shrink-0" data-open-tab="files">
                        {{ t('recent.view_all') }}
                    </button>
                </div>

                <p v-if="! latest" class="text-[13px] mt-1.5 leading-relaxed">{{ t('recent.none') }}</p>

                <!-- A treatment: the newest photograph on it, small. The card
                     is a column of a profile, not a gallery — what it owes
                     the reader is recognition, and a thumbnail the size of a
                     postage stamp does that without pushing the cards below
                     it off the screen. `object-contain` in a fixed box, so
                     nothing is cropped and nothing is stretched. -->
                <button v-else-if="latest.kind === 'record'" type="button"
                        class="w-full text-left flex items-center gap-2.5 mt-2 rounded-lg
                               hover:opacity-80 transition-opacity"
                        @click="openLatest">
                    <img v-if="latestImage" :src="latestImage.url" alt=""
                         class="h-14 w-14 shrink-0 rounded-lg object-contain bg-white/40" loading="lazy">

                    <span v-else class="h-14 w-14 shrink-0 rounded-lg border border-dashed border-current opacity-40"></span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-semibold truncate">{{ latest.record.title }}</span>
                        <span class="block text-[11.5px] opacity-75 truncate">
                            {{ [latest.record.date_label, latest.record.service].filter(Boolean).join(' · ') }}
                        </span>
                        <span class="block text-[11.5px] opacity-75">
                            {{ t('recent.latest_treatment') }} · {{ latest.record.count }}
                        </span>
                    </span>
                </button>

                <!-- A document: its type and its name, and nothing else. A
                     large preview of a PDF in a 300px column is a grey
                     rectangle; the extension and the name are what tell a
                     reader whether this is the thing they are looking for. -->
                <button v-else type="button"
                        class="w-full text-left flex items-center gap-2.5 mt-2 rounded-lg
                               hover:opacity-80 transition-opacity"
                        @click="openLatest">
                    <img v-if="latest.file.is_image" :src="latest.file.url" alt=""
                         class="h-10 w-10 shrink-0 rounded object-contain bg-white/40" loading="lazy">

                    <span v-else
                          class="h-10 w-10 shrink-0 rounded border border-current opacity-70
                                 flex items-center justify-center text-[9px] font-semibold uppercase">
                        {{ latest.file.extension }}
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-semibold truncate">{{ latest.file.name }}</span>
                        <span class="block text-[11.5px] opacity-75 truncate">
                            {{ [latest.file.uploaded_label, latest.file.size].filter(Boolean).join(' · ') }}
                        </span>
                    </span>
                </button>
            </section>
        </Teleport>
    </div>
</template>
