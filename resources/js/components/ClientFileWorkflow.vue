<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { confirmAction } from '../confirm';
import SingleSelect from './SingleSelect.vue';
import UploadProgress from './UploadProgress.vue';

/**
 * Adding files to a client, as a page rather than a dialog.
 *
 * A dialog was the wrong shape for what is actually being done here: several
 * files, two sets of thumbnails, six fields of treatment detail and previews
 * of all of it. That does not fit a 34rem panel, and a panel that scrolls
 * internally while the page scrolls behind it is the worst of both. So this
 * is a sitting, like assigning a schedule — the focused layout, no
 * navigation to wander off into, and a footer that stays put while the body
 * moves.
 *
 * Two forms in one page because they share a frame and nothing else: the
 * header, the footer, the unsaved-changes guard and the way the upload is
 * posted are identical, and the fields are not. Splitting them into two
 * pages would be two copies of everything except the part that differs.
 *
 * The whole form posts in one request whether it is the first save or the
 * fourth. "What is on this upload now" is one answer rather than three
 * requests that can half-succeed and leave a treatment with an after and no
 * before.
 */
const props = defineProps({
    urls: { type: Object, required: true },
    csrf: { type: String, required: true },
    /** 'standard' | 'record', or null to ask which. */
    kind: { type: String, default: null },
    /** The draft being reopened, where one is. */
    draft: { type: Object, default: null },
    options: { type: Object, default: () => ({ services: [], bookings: [], staff: [], categories: [] }) },
    /** The largest a file of each kind may be, in KB, from FileValidator. */
    limits: { type: Object, default: () => ({ photo: 5120, document: 20480 }) },
    clientName: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const optionMap = (list) => Object.fromEntries((list ?? []).map((row) => [row.id, row.name]));

/* Which step the page is on. The choice is a step rather than a separate
   page so Back means "the question before this one" — which is what a reader
   who picked the wrong one expects it to mean. */
const kind = ref(props.draft?.kind ?? props.kind ?? null);

// ------------------------------------------------------------- the two forms

/* Files already on this upload, as the server knows them, and files chosen
   in the browser and not yet sent. Kept apart because they can only be
   removed in different ways: one is a row to delete, the other is an entry to
   drop from a list. */
const held = ref(props.draft?.files ? [...props.draft.files] : []);
const removed = ref([]);

const standard = ref({
    batch: props.draft?.kind === 'standard' ? props.draft.batch : '',
    name: props.draft?.kind === 'standard' ? (props.draft.name ?? '') : '',
    note: props.draft?.note ?? '',
    category: props.draft?.category ?? '',
    service_id: props.draft?.service_id ?? '',
    booking_id: props.draft?.booking_id ?? '',
    files: [],
});

const treatment = ref({
    record_id: props.draft?.kind === 'record' ? props.draft.id : '',
    title: props.draft?.kind === 'record' ? (props.draft.title ?? '') : '',
    note: props.draft?.note ?? '',
    service_id: props.draft?.service_id ?? '',
    booking_id: props.draft?.booking_id ?? '',
    staff_id: props.draft?.staff_id ?? '',
    before: [],
    after: [],
});

/* ------------------------------------------------- finding the booking --

   Fifty references in one list is a list nobody reads: "BK-20260901-00020"
   says nothing a person recognises, so the way anybody finds the right one is
   by when it was. Year and month narrow it down first, and all three are the
   same searchable combo — a reader who does remember the reference can still
   type it.

   The lists are built from the bookings themselves rather than from a
   calendar, so neither can offer a month with no appointment in it. */
const bookingYear = ref(props.draft?.kind === 'record' && props.draft.date
    ? String(props.draft.date).slice(0, 4)
    : '');

const bookingMonth = ref(props.draft?.kind === 'record' && props.draft.date
    ? String(props.draft.date).slice(5, 7)
    : '');

const allBookings = computed(() => props.options.bookings ?? []);

const bookingYears = computed(() => Object.fromEntries(
    [...new Set(allBookings.value.map((row) => row.year).filter(Boolean))]
        .sort()
        .reverse()
        .map((year) => [year, year]),
));

/* Only the months of the chosen year, in calendar order rather than in the
   order the bookings happen to arrive. */
const bookingMonths = computed(() => {
    const within = allBookings.value.filter((row) => ! bookingYear.value || row.year === bookingYear.value);
    const seen = new Map();

    within.forEach((row) => {
        if (row.month && ! seen.has(row.month)) {
            seen.set(row.month, row.month_label ?? row.month);
        }
    });

    return Object.fromEntries([...seen.entries()].sort((a, b) => a[0].localeCompare(b[0])));
});

const bookingOptions = computed(() => optionMap(allBookings.value.filter((row) => {
    if (bookingYear.value && row.year !== bookingYear.value) {
        return false;
    }

    return ! bookingMonth.value || row.month === bookingMonth.value;
})));

/* Narrowing past the chosen booking clears it. Leaving it selected while it
   is no longer in the list would show a control whose value is not one of its
   own options — and quietly attach the treatment to a visit the reader can no
   longer see. */
watch([bookingYear, bookingMonth], () => {
    if (treatment.value.booking_id && ! (treatment.value.booking_id in bookingOptions.value)) {
        treatment.value.booking_id = '';
    }
});

/**
 * Choosing the appointment fills in what it already knows.
 *
 * A treatment photographed at a booking was that booking's service, done by
 * that booking's stylist — asking the reader to restate both is asking them
 * to copy something the record already holds, and to get it wrong on the
 * days they are in a hurry.
 *
 * It overwrites rather than fills the blanks: picking a booking is a
 * statement about which visit this belongs to, and a service left over from
 * the one chosen before it would be the single combination nobody meant.
 * Both stay editable afterwards — a booking of four services has a lead one,
 * and only the person who did the work knows which.
 *
 * Clearing the booking leaves them alone: "not attached to a visit" says
 * nothing about what was done.
 */
function chooseBooking(id) {
    treatment.value.booking_id = id;

    const booking = (props.options.bookings ?? []).find((row) => String(row.id) === String(id));

    if (! booking) {
        return;
    }

    treatment.value.service_id = booking.service_id ?? '';
    treatment.value.staff_id = booking.staff_id ?? '';
}

/* The photographs a reopened treatment already has, by side. */
const heldSide = (side) => (props.draft?.kind === 'record' ? props.draft[side] ?? [] : [])
    .filter((image) => ! removed.value.includes(image.id));

const heldFiles = computed(() => held.value.filter((file) => ! removed.value.includes(file.id)));

// ------------------------------------------------------------------ choosing

/**
 * Files chosen, however they were chosen.
 *
 * The same handler behind the button and the drop target: a reader who drags
 * and a reader who browses have done the same thing, and two paths into one
 * list is two places for the count to go wrong.
 */
const previews = ref({});

const fileKey = (file) => `${file.name}:${file.size}:${file.lastModified}`;

function addChosen(target, key, incoming, imagesOnly = false) {
    const offered = Array.from(incoming ?? []).filter((file) => ! imagesOnly || file.type.startsWith('image/'));

    /* Turned away as it is chosen, and named.
     *
     * The disk refuses an over-large file anyway, but only after it has been
     * uploaded — and its answer is "that file is larger than 5 MB", which
     * with four files across two sides does not say which. A phone photograph
     * is routinely over the limit, so this is the common case rather than the
     * odd one. The limit comes from the server; nothing here decides it. */
    const cap = (imagesOnly ? props.limits.photo : props.limits.document) * 1024;
    const tooBig = offered.filter((file) => file.size > cap);
    const chosen = offered.filter((file) => file.size <= cap);

    if (tooBig.length) {
        error.value = t('too_large')
            .replace(':files', tooBig.map((file) => file.name).join(', '))
            .replace(':size', `${Math.round((cap / 1024 / 1024) * 10) / 10} MB`);
    }

    target[key] = [...target[key], ...chosen].slice(0, 10);

    /* A local preview, so the reader sees what they picked before it is
       anywhere near the server. */
    previews.value = {
        ...previews.value,
        ...Object.fromEntries(
            chosen.filter((file) => file.type.startsWith('image/'))
                .map((file) => [fileKey(file), URL.createObjectURL(file)]),
        ),
    };
}

function removeChosen(target, key, index) {
    target[key] = target[key].filter((_, at) => at !== index);
}

/* A file already on the record is marked for removal rather than deleted
   here: nothing is destroyed until the reader presses Save, so Cancel really
   does undo everything on this page. */
function removeHeld(id) {
    removed.value = [...removed.value, id];
}

const dragging = ref('');

function onDrop(zone, target, key, event, imagesOnly = false) {
    dragging.value = '';
    addChosen(target, key, event.dataTransfer?.files, imagesOnly);
}

// ------------------------------------------------------------------- saving

const busy = ref(false);
const error = ref('');
const progress = ref(null);

/* Whether there is anything to lose. Checked by Back, Close and Cancel alike
   — they are three doors out of the same room, and a guard on one of them is
   a guard somebody walks around. */
const dirty = computed(() => {
    if (kind.value === null) {
        return false;
    }

    if (removed.value.length) {
        return true;
    }

    if (kind.value === 'standard') {
        const form = standard.value;

        return form.files.length > 0
            || [form.name, form.note, form.category, form.service_id, form.booking_id]
                .some((value, index) => String(value ?? '') !== String(originalStandard[index] ?? ''));
    }

    const form = treatment.value;

    return form.before.length > 0 || form.after.length > 0
        || [form.title, form.note, form.service_id, form.booking_id, form.staff_id]
            .some((value, index) => String(value ?? '') !== String(originalRecord[index] ?? ''));
});

/* What the form held when the page opened, so "changed" means changed by the
   reader rather than "not empty" — a reopened draft is full of values nobody
   has touched this sitting. */
const originalStandard = [
    standard.value.name, standard.value.note, standard.value.category,
    standard.value.service_id, standard.value.booking_id,
];

const originalRecord = [
    treatment.value.title, treatment.value.note, treatment.value.service_id,
    treatment.value.booking_id, treatment.value.staff_id,
];

/** Set the moment a save or an agreed departure starts, so the browser's own
    "leave site?" prompt does not fire on top of ours. */
const leaving = ref(false);

/**
 * How far each side has got, worked out from the one request.
 *
 * There is only ever one upload — the record and both sets of photographs go
 * together or not at all — so there is only one stream of bytes to measure.
 * But the reader is looking at two boxes, and "43%" under the footer does not
 * tell them whether their afters are up.
 *
 * The two sides are appended in order, before then after, so the byte count
 * can be split honestly: everything up to the size of the before images is
 * the before images, and everything past it is the afters. No guessing, and
 * no second request to make the numbers separable.
 */
const sideBytes = ref({ before: 0, after: 0 });
const sideProgress = ref({ before: null, after: null });

function shareOf(loaded, side) {
    const own = sideBytes.value[side];

    if (! own) {
        return null;
    }

    const start = side === 'before' ? 0 : sideBytes.value.before;
    const done = Math.min(Math.max(loaded - start, 0), own);

    return Math.round((done / own) * 100);
}

function send(url, body) {
    busy.value = true;
    error.value = '';
    progress.value = 0;

    const bytes = (files) => files.reduce((total, file) => total + file.size, 0);

    sideBytes.value = kind.value === 'record'
        ? { before: bytes(treatment.value.before), after: bytes(treatment.value.after) }
        : { before: 0, after: 0 };

    sideProgress.value = {
        before: sideBytes.value.before ? 0 : null,
        after: sideBytes.value.after ? 0 : null,
    };

    return new Promise((resolve) => {
        const request = new XMLHttpRequest();

        const finish = () => {
            busy.value = false;
            progress.value = null;
            sideProgress.value = { before: null, after: null };
        };

        request.open('POST', url, true);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-CSRF-TOKEN', props.csrf);

        request.upload.addEventListener('progress', (event) => {
            if (! event.lengthComputable) {
                return;
            }

            progress.value = Math.round((event.loaded / event.total) * 100);

            /* The fields come before the files in the body, so the offset is
               taken off the loaded count before it is split — otherwise the
               before bar would start part-filled. */
            const offset = Math.max(event.total - sideBytes.value.before - sideBytes.value.after, 0);
            const intoFiles = Math.max(event.loaded - offset, 0);

            sideProgress.value = {
                before: shareOf(intoFiles, 'before'),
                after: shareOf(intoFiles, 'after'),
            };
        });

        /* The bytes are up but the answer has not come back: the server is
           still writing the files. */
        request.upload.addEventListener('load', () => {
            progress.value = 100;

            sideProgress.value = {
                before: sideBytes.value.before ? 100 : null,
                after: sideBytes.value.after ? 100 : null,
            };
        });

        request.addEventListener('load', () => {
            let json = {};

            try {
                json = JSON.parse(request.responseText);
            } catch (exception) {
                /* A response that is not JSON is a failure whatever it says. */
            }

            if (request.status < 200 || request.status >= 300) {
                /* The first thing the server actually objected to. A list of
                   every field is a wall the reader has to translate back into
                   "the name is missing". */
                error.value = Object.values(json.errors ?? {})[0]?.[0] ?? json.message ?? t('failed');
                finish();

                return resolve(null);
            }

            finish();
            resolve(json);
        });

        request.addEventListener('error', () => {
            error.value = t('failed');
            finish();
            resolve(null);
        });

        request.send(body);
    });
}

async function save(asDraft = false) {
    if (busy.value) {
        return;
    }

    const body = new FormData();

    body.append('draft', asDraft ? '1' : '0');
    removed.value.forEach((id) => body.append('remove[]', id));

    if (kind.value === 'standard') {
        const form = standard.value;

        if (form.batch) {
            body.append('batch', form.batch);
        }

        body.append('name', form.name ?? '');
        body.append('note', form.note ?? '');
        body.append('category', form.category ?? '');
        body.append('service_id', form.service_id ?? '');
        body.append('booking_id', form.booking_id ?? '');
        form.files.forEach((file) => body.append('files[]', file));
    } else {
        const form = treatment.value;

        if (form.record_id) {
            body.append('record_id', form.record_id);
        }

        body.append('title', form.title ?? '');
        body.append('note', form.note ?? '');
        body.append('service_id', form.service_id ?? '');
        body.append('booking_id', form.booking_id ?? '');
        body.append('staff_id', form.staff_id ?? '');
        form.before.forEach((file) => body.append('before[]', file));
        form.after.forEach((file) => body.append('after[]', file));
    }

    const result = await send(
        kind.value === 'standard' ? props.urls.store : props.urls.storeRecord,
        body,
    );

    if (result) {
        /* Back to the tab this came from. Set before navigating, or the
           unsaved-changes guard fires on the very departure that has just
           succeeded. */
        leaving.value = true;
        window.location.href = props.urls.back;
    }
}

// ------------------------------------------------------------------- leaving

/**
 * Back, Close and Cancel, which are three doors out of one room.
 *
 * Back goes to the question before this one where there is one — a reader who
 * picked the wrong kind of upload wants the choice again, not the client's
 * profile. Everything else leaves.
 */
async function leave(url) {
    if (dirty.value && ! await confirmAction({
        title: t('discard_title'),
        message: t('discard_body'),
        confirm: t('discard_confirm'),
        dismiss: t('keep_editing'),
        tone: 'danger',
    })) {
        return;
    }

    leaving.value = true;
    window.location.href = url;
}

async function back() {
    /* The choice is a step, so Back from a form returns to it — unless the
       reader arrived on a form directly, reopening a draft, in which case
       there is no earlier step to go back to. */
    if (kind.value !== null && props.kind === null && props.draft === null) {
        if (dirty.value && ! await confirmAction({
            title: t('discard_title'),
            message: t('discard_body'),
            confirm: t('discard_confirm'),
            dismiss: t('keep_editing'),
            tone: 'danger',
        })) {
            return;
        }

        reset();

        return;
    }

    leave(props.urls.back);
}

function reset() {
    kind.value = null;
    standard.value.files = [];
    treatment.value.before = [];
    treatment.value.after = [];
    removed.value = [];
    error.value = '';
}

/* The browser's own guard, for the ways out this page does not own — the
   address bar, the tab's close button. */
function guard(event) {
    if (dirty.value && ! leaving.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}

onMounted(() => window.addEventListener('beforeunload', guard));
onBeforeUnmount(() => window.removeEventListener('beforeunload', guard));

const heading = computed(() => {
    if (kind.value === 'standard') {
        return t('kinds.standard');
    }

    if (kind.value === 'record') {
        return t('kinds.record');
    }

    return t('workflow.title');
});
</script>

<template>
    <div class="flex flex-col min-h-screen">
        <!-- Header. Only the ways out and the name of the task: this screen is
             a sitting, and the surrounding navigation is an invitation to
             abandon it half-finished. -->
        <header class="sticky top-0 z-30 bg-white border-b border-line">
            <div class="w-full px-4 sm:px-6 h-14 flex items-center gap-3">
                <button type="button" class="styledesk_action styledesk_action--sm" @click="back">
                    ← {{ t('workflow.back') }}
                </button>

                <div class="min-w-0 flex-1 text-center">
                    <h1 class="text-[15px] font-semibold text-head truncate">{{ heading }}</h1>
                    <!-- Whose record this is going on. A page with no
                         navigation still has to say what it is attached to. -->
                    <p class="text-[12px] text-sub truncate">{{ clientName }}</p>
                </div>

                <button type="button" class="styledesk_action styledesk_action--sm styledesk_action--icon"
                        :aria-label="t('workflow.close')" @click="leave(urls.back)">×</button>
            </div>
        </header>

        <!-- Wider than a single-column form needs, because the treatment
             page is two columns of it. `max-w` rather than full bleed: a form
             stretched across a 27-inch monitor is a form whose labels and
             fields are a hand-span apart. -->
        <div class="flex-1 w-full px-4 sm:px-6 py-6">
            <div class="max-w-[1120px] mx-auto">
                <p v-if="error" class="sd-alert sd-alert--warn mb-4 text-[13px]" role="alert">{{ error }}</p>

                <!-- ================================== which of the two -->
                <!-- Asked before the form rather than inside it: the two
                     share almost no fields, and one form carrying both would
                     be half greyed out whichever the reader wanted. -->
                <div v-if="kind === null" class="space-y-3">
                    <p class="text-[13px] text-sub">{{ t('workflow.choose') }}</p>

                    <button v-for="option in ['standard', 'record']" :key="option" type="button"
                            class="w-full text-left rounded-card border border-line bg-white px-4 py-4
                                   hover:border-brand transition-colors"
                            @click="kind = option">
                        <p class="text-[14px] font-semibold text-head">{{ t('kinds.' + option) }}</p>
                        <p class="text-[13px] text-sub mt-1">{{ t('kinds.' + option + '_hint') }}</p>
                    </button>
                </div>

                <!-- ================================== standard upload -->
                <template v-else-if="kind === 'standard'">
                    <section class="rounded-card border border-line bg-white p-4 sm:p-5">
                        <h2 class="text-[14px] font-semibold text-head">{{ t('workflow.details') }}</h2>

                        <div class="grid gap-4 sm:grid-cols-2 mt-3.5">
                            <label class="block sm:col-span-2">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.name') }}</span>
                                <input v-model="standard.name" type="text" class="sd-input"
                                       :placeholder="t('fields.name_placeholder')">
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.note') }}</span>
                                <textarea v-model="standard.note" rows="3" class="sd-input !h-auto py-2.5"
                                          :placeholder="t('fields.note_placeholder')"></textarea>
                            </label>

                            <label class="block">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.category') }}</span>
                                <SingleSelect v-model="standard.category" :options="optionMap(options.categories)"
                                              :placeholder="t('fields.none')" />
                            </label>

                            <label class="block">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.service') }}</span>
                                <SingleSelect v-model="standard.service_id" :options="optionMap(options.services)"
                                              :placeholder="t('fields.none')" />
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.booking') }}</span>
                                <SingleSelect v-model="standard.booking_id" :options="optionMap(options.bookings)"
                                              :placeholder="t('fields.none')" />
                            </label>
                        </div>
                    </section>

                    <!-- The upload area gets the room a modal could not give
                         it: a drop target the size of a postage stamp is one
                         people miss and then use the browse link instead. -->
                    <section class="rounded-card border border-line bg-white p-4 sm:p-5 mt-4">
                        <h2 class="text-[14px] font-semibold text-head">{{ t('fields.files') }}</h2>

                        <div class="rounded-lg border-2 border-dashed px-4 py-10 text-center mt-3 transition-colors"
                             :class="dragging === 'standard' ? 'border-brand bg-hover' : 'border-line'"
                             @dragover.prevent="dragging = 'standard'"
                             @dragleave.prevent="dragging = ''"
                             @drop.prevent="onDrop('standard', standard, 'files', $event)">
                            <p class="text-[13.5px] text-ink">
                                {{ t('drop') }}
                                <label class="text-link font-semibold cursor-pointer underline">
                                    {{ t('browse') }}
                                    <input type="file" multiple class="sr-only"
                                           @change="addChosen(standard, 'files', $event.target.files); $event.target.value = ''">
                                </label>
                            </p>
                            <p class="text-[12px] text-faint mt-1.5">{{ t('accepted') }}</p>
                        </div>

                        <!-- Already on this upload, from a draft saved
                             earlier. Marked for removal rather than deleted
                             now, so Cancel really does undo everything. -->
                        <ul v-if="heldFiles.length" class="mt-3 space-y-1.5">
                            <li v-for="file in heldFiles" :key="file.id"
                                class="flex items-center gap-2.5 rounded-lg border border-line px-3 py-2">
                                <img v-if="file.is_image" :src="file.url" alt=""
                                     class="h-10 w-10 rounded object-cover border border-line" loading="lazy">
                                <span v-else
                                      class="h-10 w-10 rounded border border-line flex items-center justify-center
                                             text-[10px] font-semibold uppercase text-sub">
                                    {{ file.extension }}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] text-ink truncate">{{ file.name }}</span>
                                    <span class="block text-[12px] text-faint">{{ file.size }}</span>
                                </span>

                                <button type="button" class="text-[12px] text-sub hover:text-danger"
                                        @click="removeHeld(file.id)">{{ t('remove') }}</button>
                            </li>
                        </ul>

                        <ul v-if="standard.files.length" class="mt-3 space-y-1.5">
                            <li v-for="(file, index) in standard.files" :key="index"
                                class="flex items-center gap-2.5 rounded-lg border border-line px-3 py-2">
                                <img v-if="previews[fileKey(file)]" :src="previews[fileKey(file)]" alt=""
                                     class="h-10 w-10 rounded object-cover border border-line">
                                <span v-else
                                      class="h-10 w-10 rounded border border-line flex items-center justify-center
                                             text-[10px] font-semibold uppercase text-sub">
                                    {{ file.name.split('.').pop() }}
                                </span>

                                <span class="min-w-0 flex-1 text-[13px] text-ink truncate">{{ file.name }}</span>

                                <button type="button" class="text-[12px] text-sub hover:text-danger"
                                        @click="removeChosen(standard, 'files', index)">{{ t('remove') }}</button>
                            </li>
                        </ul>
                    </section>
                </template>

                <!-- ================================== before & after -->
                <!-- Two columns: what the treatment was on the left, what
                     it looked like on the right. The details are a form to
                     fill in and the photographs are a thing to look at, and
                     stacking them meant scrolling past every field to reach
                     the drop targets — then scrolling back to check the name
                     before saving.

                     `items-start` so the two columns keep their own heights:
                     stretched, the details card would grow to match two sets
                     of thumbnails and sit in a field of white space. -->
                <template v-else>
                  <div class="grid gap-4 lg:grid-cols-2 items-start">
                    <section class="rounded-card border border-line bg-white p-4 sm:p-5">
                        <h2 class="text-[14px] font-semibold text-head">{{ t('workflow.treatment_details') }}</h2>

                        <div class="grid gap-4 sm:grid-cols-2 mt-3.5">
                            <!-- The whole row. It is the one field on this
                                 form somebody types rather than picks, and
                                 the name of a treatment is longer than half
                                 a column. -->
                            <label class="block sm:col-span-2">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.title') }}</span>
                                <input v-model="treatment.title" type="text" class="sd-input"
                                       :placeholder="t('fields.title_placeholder')">
                            </label>

                            <!-- Second, and before the two it fills in. The
                                 appointment already knows what was done and
                                 who did it, so choosing it answers the next
                                 two questions — and a field that fills other
                                 fields has to sit above them, or the reader
                                 watches their own answers be overwritten.

                                 Found by when it was rather than by its
                                 reference: nobody recognises
                                 "BK-20260901-00020", and fifty of them in
                                 one list is a list nobody reads. -->
                            <div class="sm:col-span-2 grid grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.year') }}</span>
                                    <SingleSelect v-model="bookingYear" :options="bookingYears"
                                                  :placeholder="t('fields.all_years')" />
                                </label>

                                <label class="block">
                                    <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.month') }}</span>
                                    <SingleSelect v-model="bookingMonth" :options="bookingMonths"
                                                  :placeholder="t('fields.all_months')" />
                                </label>
                            </div>

                            <label class="block sm:col-span-2">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.booking') }}</span>
                                <SingleSelect :model-value="treatment.booking_id"
                                              :options="bookingOptions"
                                              :placeholder="t('fields.none')"
                                              @update:model-value="chooseBooking" />
                            </label>

                            <label class="block">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.service') }}</span>
                                <SingleSelect v-model="treatment.service_id" :options="optionMap(options.services)"
                                              :placeholder="t('fields.none')" />
                            </label>

                            <label class="block">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.staff') }}</span>
                                <SingleSelect v-model="treatment.staff_id" :options="optionMap(options.staff)"
                                              :placeholder="t('fields.none')" />
                            </label>

                            <label class="block sm:col-span-2">
                                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ t('fields.note') }}</span>
                                <textarea v-model="treatment.note" rows="3" class="sd-input !h-auto py-2.5"
                                          :placeholder="t('fields.note_placeholder')"></textarea>
                            </label>
                        </div>
                    </section>

                    <!-- The second column. Two sections, kept visually apart:
                         which photograph belongs to which end of the
                         treatment is the one thing this screen must never
                         leave ambiguous — a before filed as an after is a
                         comparison that says the opposite of what happened. -->
                    <div class="space-y-4">
                    <section v-for="side in ['before', 'after']" :key="side"
                             class="rounded-card border border-line bg-white p-4 sm:p-5">
                        <div class="flex items-baseline gap-2">
                            <h2 class="text-[14px] font-semibold text-head">{{ t('fields.' + side) }}</h2>
                            <span class="text-[12px] text-faint">
                                {{ heldSide(side).length + treatment[side].length }}
                            </span>
                        </div>

                        <div class="rounded-lg border-2 border-dashed px-4 py-8 text-center mt-3 transition-colors"
                             :class="dragging === side ? 'border-brand bg-hover' : 'border-line'"
                             @dragover.prevent="dragging = side"
                             @dragleave.prevent="dragging = ''"
                             @drop.prevent="onDrop(side, treatment, side, $event, true)">
                            <p class="text-[13.5px] text-ink">
                                {{ t('drop_images') }}
                                <label class="text-link font-semibold cursor-pointer underline">
                                    {{ t('browse') }}
                                    <!-- The types the disk actually takes,
                                         rather than `image/*`: a photo
                                         straight off a phone is HEIC, which
                                         is an image by the browser's
                                         reckoning and not by the storage
                                         layer's — offering it here only
                                         moved the refusal to the Save
                                         button. -->
                                    <input type="file" accept="image/jpeg,image/png,image/webp"
                                           multiple class="sr-only"
                                           @change="addChosen(treatment, side, $event.target.files, true); $event.target.value = ''">
                                </label>
                            </p>
                            <p class="text-[12px] text-faint mt-1.5">{{ t('accepted_images') }}</p>
                        </div>

                        <div v-if="heldSide(side).length || treatment[side].length"
                             class="flex flex-wrap gap-2 mt-3">
                            <!-- Already on the record. -->
                            <div v-for="image in heldSide(side)" :key="image.id" class="relative">
                                <img :src="image.url" alt=""
                                     class="h-20 w-20 rounded-lg object-cover border border-line" loading="lazy">
                                <button type="button" :aria-label="t('remove')"
                                        class="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-danger text-white
                                               text-[11px] leading-none"
                                        @click="removeHeld(image.id)">×</button>
                            </div>

                            <!-- Chosen now, not yet sent. -->
                            <div v-for="(file, index) in treatment[side]" :key="index" class="relative">
                                <img :src="previews[fileKey(file)]" alt=""
                                     class="h-20 w-20 rounded-lg object-cover border border-line">
                                <button type="button" :aria-label="t('remove')"
                                        class="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-danger text-white
                                               text-[11px] leading-none"
                                        @click="removeChosen(treatment, side, index)">×</button>
                            </div>
                        </div>

                        <!-- This side's own share of the upload. There is one
                             request — the record and both sets of photographs
                             go together or not at all — but the reader is
                             looking at two boxes, and a single figure under
                             the footer does not tell them whether their
                             afters are up. The two sides are sent in order,
                             so the byte count splits honestly. -->
                        <div v-if="sideProgress[side] !== null" class="mt-3">
                            <UploadProgress :value="sideProgress[side]"
                                            :uploading-label="t('uploading')"
                                            :processing-label="t('processing')" />
                        </div>
                    </section>
                  </div>
                  </div>
                </template>
            </div>
        </div>

        <!-- Stays put while the form scrolls. A treatment with two sets of
             thumbnails is a long page, and a footer at the bottom of it is a
             footer nobody reaches without going looking. -->
        <footer v-if="kind !== null" class="sticky bottom-0 z-30 bg-white border-t border-line">
            <div class="w-full px-4 sm:px-6 py-3 max-w-[1120px] mx-auto">
                <div v-if="progress !== null" class="mb-2.5">
                    <UploadProgress :value="progress" :uploading-label="t('uploading')"
                                    :processing-label="t('processing')" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" :disabled="busy"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold
                                   transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                            @click="save(false)">
                        {{ busy ? t('uploading') : t('save') }}
                    </button>

                    <!-- Parking the work is not the same as finishing it, so
                         it is its own button rather than a Save that guesses.
                         Nothing here is required to press it. -->
                    <button type="button" class="styledesk_action" :disabled="busy" @click="save(true)">
                        {{ t('workflow.save_draft') }}
                    </button>

                    <button v-if="! busy" type="button"
                            class="text-[13px] text-sub hover:text-ink transition-colors px-2 ml-auto"
                            @click="leave(urls.back)">
                        {{ t('cancel') }}
                    </button>
                </div>
            </div>
        </footer>
    </div>
</template>
