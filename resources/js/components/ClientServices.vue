<script setup>
import { computed, ref } from 'vue';
import MultiSelect from './MultiSelect.vue';

/**
 * The Services tab on a client's profile.
 *
 * Two lists that must never become one.
 *
 * The history is arithmetic over the diary: six balayages, the last one in
 * August, with Mei. It answers "what does this person actually have", and
 * nothing in it was decided by anybody.
 *
 * The favourites are statements somebody made at the desk — "this is what she
 * always has" — and they stay true for a client who has never booked anything
 * here. A favourite that appeared because a service was booked twice would be
 * one nobody chose, and the receptionist who trusts the list would stop being
 * able to tell which is which. So the star is only ever set by hand.
 */
const props = defineProps({
    /** Where both lists are read from and written to. */
    urls: { type: Object, required: true },
    favorites: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    /** Every service on the price list, id => name, for marking one by hand. */
    services: { type: Object, default: () => ({}) },
    csrf: { type: String, required: true },
    canEdit: { type: Boolean, default: false },
    labels: { type: Object, default: () => ({}) },
});

const favorites = ref([...props.favorites]);
const history = ref([...props.history]);
const busy = ref(false);
const adding = ref(false);
const picked = ref([]);

const favoriteIds = computed(() => favorites.value.map((row) => row.id));

/* Only what is not already starred. Offering a favourite somebody has
   already marked is offering an action with no effect. */
const addable = computed(() => Object.fromEntries(
    Object.entries(props.services).filter(([id]) => ! favoriteIds.value.includes(Number(id))),
));

async function send(url, method, body = null) {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': props.csrf,
            },
            body: body ? JSON.stringify(body) : null,
        });

        if (! response.ok) {
            return;
        }

        /* Both lists come back from the server rather than being patched
           here: starring a service changes a row in the history too, and a
           screen doing its own bookkeeping on two lists is a screen where
           they can disagree. */
        const json = await response.json();

        favorites.value = json.favorites ?? favorites.value;
        history.value = json.history ?? history.value;
    } catch (error) {
        /* Left as it was. The lists on screen are still the server's. */
    } finally {
        busy.value = false;
    }
}

const addFavorites = async (ids) => {
    if (! ids.length) {
        return;
    }

    await send(props.urls.add, 'POST', { services: ids });
};

async function savePicked() {
    await addFavorites(picked.value.map(Number));
    picked.value = [];
    adding.value = false;
}

const removeFavorite = (id) => send(props.urls.remove.replace(':service', id), 'DELETE');

const toggle = (row) => (row.is_favorite ? removeFavorite(row.id) : addFavorites([row.id]));

/* "1 visit", not "1 visits". The count is read aloud to a client often
   enough that the grammar is worth the branch. */
const visitsLabel = (count) => (count === 1
    ? (props.labels.visits_one ?? '1 visit')
    : (props.labels.visits ?? ':count visits').replace(':count', count));
</script>

<template>
    <div class="space-y-6">
        <!-- What they are known to want. First, because it is the answer to
             "what shall I put down for her" — the history below is the
             working, and this is the conclusion somebody already drew. -->
        <section>
            <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-[13px] font-semibold text-head">{{ labels.favorites }}</h3>

                <button v-if="canEdit && !adding" type="button"
                        class="ml-auto styledesk_action" :disabled="busy"
                        @click="adding = true">
                    {{ labels.add_favorite }}
                </button>
            </div>

            <!-- Marked by hand, for the client who says "I always have the
                 balayage" before they have ever booked one here. -->
            <div v-if="adding" class="mt-3 flex flex-wrap items-end gap-2">
                <div class="min-w-[220px] flex-1">
                    <MultiSelect :options="addable"
                                 :model-value="picked"
                                 name="favorite_services"
                                 :placeholder="labels.choose_services"
                                 :search-placeholder="labels.search_services"
                                 :aria-label="labels.add_favorite"
                                 :show-primary="false"
                                 @update:model-value="(values) => picked = values" />
                </div>

                <button type="button" class="styledesk_action" :disabled="busy || !picked.length"
                        @click="savePicked">{{ labels.save }}</button>
                <button type="button" class="styledesk_action"
                        @click="adding = false; picked = []">{{ labels.cancel }}</button>
            </div>

            <ul v-if="favorites.length" class="mt-3 flex flex-wrap gap-2">
                <li v-for="row in favorites" :key="row.id"
                    class="inline-flex items-center gap-2 h-8 pl-3 pr-1.5 rounded-full border border-brand/30 bg-brand/5">
                    <span class="text-brand" aria-hidden="true">★</span>
                    <span class="text-[13px] font-semibold text-head">{{ row.name }}</span>
                    <span v-if="row.category" class="text-[12px] text-sub">{{ row.category }}</span>

                    <button v-if="canEdit" type="button" :disabled="busy"
                            class="h-6 w-6 grid place-items-center rounded-full text-faint hover:text-danger hover:bg-hover transition-colors"
                            :aria-label="(labels.remove_favorite ?? '').replace(':name', row.name)"
                            @click="removeFavorite(row.id)">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                    </button>
                </li>
            </ul>

            <div v-else class="mt-3">
                <p class="text-[13px] text-sub">{{ labels.no_favorites }}</p>
                <p class="text-[12px] text-faint mt-1">{{ labels.favorites_hint }}</p>
            </div>
        </section>

        <!-- What they have actually had. Grouped, because "how often and when
             last" is the question — a row per appointment makes the reader
             count. -->
        <section>
            <h3 class="text-[13px] font-semibold text-head">{{ labels.history }}</h3>

            <div v-if="history.length" class="mt-3 border border-line rounded-card overflow-hidden">
                <!-- A table on a wide screen, a stack of cards on a narrow
                     one: six columns squeezed onto a phone is a table nobody
                     can read either way. -->
                <div class="hidden sm:grid grid-cols-[2fr_1fr_auto_1fr_1fr_auto] gap-3 px-4 py-2.5 bg-hover
                            text-[11px] font-semibold uppercase tracking-wide text-faint">
                    <span>{{ labels.columns?.service }}</span>
                    <span>{{ labels.columns?.category }}</span>
                    <span class="text-right">{{ labels.columns?.visits }}</span>
                    <span>{{ labels.columns?.last_booked }}</span>
                    <span>{{ labels.columns?.last_provider }}</span>
                    <span class="text-right">{{ labels.columns?.favorite }}</span>
                </div>

                <ul class="divide-y divide-line">
                    <li v-for="row in history" :key="row.id"
                        class="px-4 py-3 sm:grid sm:grid-cols-[2fr_1fr_auto_1fr_1fr_auto] sm:gap-3 sm:items-center">
                        <span class="block text-[13.5px] font-semibold text-head">{{ row.name }}</span>

                        <span class="block text-[12.5px] text-sub sm:text-[13px]">{{ row.category ?? '—' }}</span>

                        <span class="block text-[12.5px] text-sub sm:text-[13px] sm:text-right sm:tabular-nums">
                            {{ visitsLabel(row.visits) }}
                        </span>

                        <span class="block text-[12.5px] text-sub sm:text-[13px]">
                            <span class="sm:hidden">{{ labels.columns?.last_booked }}: </span>{{ row.last_booked ?? '—' }}
                        </span>

                        <span class="block text-[12.5px] text-sub sm:text-[13px]">{{ row.last_provider ?? '—' }}</span>

                        <span class="mt-2 sm:mt-0 sm:text-right">
                            <!-- The star is the action, and it says which of
                                 the two states it is in. A service off the
                                 price list cannot be booked again, so it
                                 cannot be marked as what somebody wants. -->
                            <button v-if="canEdit && row.bookable" type="button" :disabled="busy"
                                    class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-[12.5px] font-semibold transition-colors"
                                    :class="row.is_favorite
                                        ? 'text-brand hover:bg-brand/5'
                                        : 'text-sub hover:text-ink hover:bg-hover'"
                                    :aria-pressed="row.is_favorite"
                                    @click="toggle(row)">
                                <span aria-hidden="true">{{ row.is_favorite ? '★' : '♡' }}</span>
                                {{ row.is_favorite ? labels.is_favorite : labels.add_to_favorites }}
                            </button>

                            <span v-else-if="row.is_favorite" class="text-brand text-[13px]" aria-hidden="true">★</span>
                        </span>
                    </li>
                </ul>
            </div>

            <p v-else class="mt-3 text-[13px] text-sub">{{ labels.no_history }}</p>
        </section>
    </div>
</template>
