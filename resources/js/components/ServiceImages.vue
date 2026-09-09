<script setup>
import { computed, ref } from 'vue';

/**
 * The pictures on one service: a default and a gallery.
 *
 * Shared by onboarding step 3 (inside each repeater row) and the Services
 * module's create and edit forms, because both need the same thing — pick
 * files now, attach them to a service that may not exist yet.
 *
 * Uploads happen as the reader chooses them rather than on submit. A service
 * form already carries a dozen fields; adding several megabytes to that POST
 * means a save that appears to hang, and a failed upload that takes the whole
 * form's answers down with it. What the form finally posts is a list of ids.
 *
 * The ids post as ordinary hidden inputs — `name[]` for the gallery, one more
 * for the default — so the server reads a normal form submission and the
 * component needs no endpoint of its own at save time.
 */
const props = defineProps({
    /**
     * The prefix the ids post under, e.g. "services[0]" in the wizard's
     * repeater. Empty on a form that owns the whole page, where they post as
     * plain images[] and default_image_id.
     */
    name: { type: String, default: '' },
    /** [{ id, url, name }], the pictures already on this service. */
    initial: { type: Array, default: () => [] },
    /** Which of them leads. */
    initialDefaultId: { type: [Number, String], default: null },
    /** How many may be added beside the default. */
    maxOthers: { type: Number, default: 10 },
    uploadUrl: { type: String, required: true },
    deleteUrl: { type: String, required: true },
    /** Copy, passed in so the component holds no English of its own. */
    labels: { type: Object, default: () => ({}) },
});

/**
 * The picker keeps its own list, and tells the row above what is in it.
 *
 * It has to keep its own — the hidden inputs it posts are rendered from it —
 * but a row that does not know it has pictures cannot answer "is there
 * anything in this row worth confirming before we throw it away".
 */
const emit = defineEmits(['update:images', 'update:defaultId']);

const images = ref(props.initial.map((image) => ({ ...image })));
const defaultId = ref(props.initialDefaultId === null ? null : Number(props.initialDefaultId));

/* Announced after every change rather than watched from outside: a watcher on
   the prop would re-seed this list from a value it had just published. */
function publish() {
    emit('update:images', images.value.map((image) => ({ ...image })));
    emit('update:defaultId', effectiveDefaultId.value);
}
const error = ref('');
const uploading = ref(0);
const input = ref(null);

const t = (key, fallback = '') => props.labels[key] ?? fallback;

/* Built rather than interpolated at each use: an empty prefix must give
   `images[]`, not `[images][]`, which PHP reads as a field literally named
   "[images]" and the server never sees. */
const galleryName = computed(() => (props.name ? `${props.name}[images][]` : 'images[]'));
const defaultName = computed(() => (props.name ? `${props.name}[default_image_id]` : 'default_image_id'));

/** The default plus the others it leads. */
const limit = computed(() => props.maxOthers + 1);
const isFull = computed(() => images.value.length >= limit.value);

/* The default is whichever id is marked, or simply the first picture — a
   gallery with something in it always has one leading, so the card is never
   drawn blank while a service does in fact have pictures. */
const effectiveDefaultId = computed(() => {
    if (defaultId.value !== null && images.value.some((image) => image.id === defaultId.value)) {
        return defaultId.value;
    }

    return images.value.length ? images.value[0].id : null;
});

/* Shown in the order they are stored, except that the default leads —
   the same order the server renders them in, so the form and the page it
   saves to agree. */
const ordered = computed(() => {
    const lead = effectiveDefaultId.value;

    return [...images.value].sort((a, b) => (a.id === lead ? -1 : b.id === lead ? 1 : 0));
});

function pick() {
    error.value = '';
    input.value?.click();
}

async function onFiles(event) {
    const files = Array.from(event.target.files ?? []);

    /* Cleared immediately so choosing the same file twice in a row still
       fires a change event — the second pick is otherwise silently ignored. */
    event.target.value = '';

    for (const file of files) {
        if (isFull.value) {
            error.value = t('too_many', 'Too many images.');
            break;
        }

        await upload(file);
    }
}

async function upload(file) {
    const body = new FormData();
    body.append('image', file);

    uploading.value += 1;

    try {
        const response = await fetch(props.uploadUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body,
        });

        if (!response.ok) {
            /* Laravel answers a refused upload with 422 and the message the
               server chose; anything else has no message worth showing. */
            const payload = response.status === 422 ? await response.json().catch(() => null) : null;

            error.value = payload?.errors?.image?.[0] ?? payload?.message ?? t('failed', 'Upload failed.');

            return;
        }

        const image = await response.json();

        images.value.push(image);

        if (defaultId.value === null) {
            defaultId.value = image.id;
        }

        publish();
    } catch (e) {
        error.value = t('failed', 'Upload failed.');
    } finally {
        uploading.value -= 1;
    }
}

function makeDefault(image) {
    defaultId.value = image.id;
    publish();
}

async function remove(image) {
    images.value = images.value.filter((candidate) => candidate.id !== image.id);

    if (defaultId.value === image.id) {
        defaultId.value = images.value.length ? images.value[0].id : null;
    }

    publish();

    /* Only a picture that was never attached is deleted here. One that came
       in through `initial` belongs to a saved service, and taking it off is a
       change the save makes — so a reader who removes a picture and then
       abandons the form still has the service they left. */
    if (props.initial.some((existing) => existing.id === image.id)) {
        return;
    }

    try {
        await fetch(props.deleteUrl.replace('__ID__', image.id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });
    } catch (e) {
        /* A file left on disk is a tidiness problem, not the reader's: the
           form no longer names it, so it will never be attached. */
    }
}
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-3">
            <span class="block text-[13px] font-medium text-ink">{{ t('label', 'Images') }}</span>
            <span class="text-[12px] text-faint">{{ images.length }}/{{ limit }}</span>
        </div>

        <p class="text-[12px] text-sub mt-1 leading-relaxed">{{ t('help') }}</p>

        <ul v-if="ordered.length" class="mt-3 grid grid-cols-3 sm:grid-cols-4 gap-2.5">
            <li v-for="image in ordered" :key="image.id" class="relative group">
                <div
                    class="aspect-square rounded-lg overflow-hidden border bg-hover"
                    :class="image.id === effectiveDefaultId ? 'border-brand ring-1 ring-brand' : 'border-line'">
                    <img :src="image.url" :alt="image.name ?? ''" class="w-full h-full object-cover">
                </div>

                <span v-if="image.id === effectiveDefaultId"
                      class="absolute top-1 left-1 px-1.5 h-5 inline-flex items-center rounded bg-brand text-white text-[10px] font-semibold">
                    {{ t('default', 'Default') }}
                </span>

                <button v-else type="button" @click="makeDefault(image)"
                        class="absolute top-1 left-1 px-1.5 h-5 inline-flex items-center rounded bg-white/90 border border-line text-[10px] font-semibold text-sub hover:text-ink opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity">
                    {{ t('make_default', 'Make default') }}
                </button>

                <button type="button" @click="remove(image)"
                        :aria-label="(t('remove_named', 'Remove :name')).replace(':name', image.name ?? '')"
                        class="absolute top-1 right-1 w-5 h-5 grid place-items-center rounded bg-white/90 border border-line text-sub hover:text-danger transition-colors">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                </button>

                <input type="hidden" :name="galleryName" :value="image.id">
            </li>
        </ul>

        <p v-else class="text-[12px] text-faint mt-3">{{ t('empty', 'No images yet.') }}</p>

        <input type="hidden" :name="defaultName" :value="effectiveDefaultId ?? ''">

        <div class="flex items-center gap-3 mt-3">
            <button type="button" @click="pick" :disabled="isFull || uploading > 0"
                    class="styledesk_action disabled:opacity-60 disabled:pointer-events-none">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                {{ t('add', 'Add image') }}
            </button>

            <span v-if="uploading > 0" class="text-[12px] text-sub">{{ t('uploading', 'Uploading…') }}</span>
        </div>

        <p v-if="error" role="alert" class="mt-2 text-[12px] text-danger">{{ error }}</p>

        <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden"
               @change="onFiles">
    </div>
</template>
