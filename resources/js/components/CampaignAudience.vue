<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MultiSelect from './MultiSelect.vue';
import SingleSelect from './SingleSelect.vue';

/**
 * Who a campaign goes to, and how many that is.
 *
 * The rules and the count are one control, not two: the whole point of the
 * step is watching the number move as the rules are narrowed. A screen that
 * made somebody save and come back to find out how many people they had just
 * described would be a screen nobody trusted enough to press Send on.
 *
 * The count comes from the server on every change, debounced. It is not
 * worked out here and could not be — "clients with no visit in ninety days"
 * is a question about the appointment book, and the browser does not have it.
 *
 * The three figures are deliberately not one. "1,248 clients" hides the two
 * things an owner needs before sending: how many have asked not to hear from
 * them, and how many addresses are not addresses.
 */
const props = defineProps({
    urls: { type: Object, required: true },
    /** What the campaign already says, or the default for a new one. */
    audience: { type: Object, default: () => ({ scope: 'all' }) },
    options: { type: Object, default: () => ({}) },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const scope = ref(props.audience.scope ?? 'all');
const locations = ref([...(props.audience.locations ?? [])]);
const tags = ref([...(props.audience.tags ?? [])]);
const staff = ref([...(props.audience.staff ?? [])]);
const services = ref([...(props.audience.services ?? [])]);
const notVisited = ref(props.audience.not_visited_days ?? '');
const visitedWithin = ref(props.audience.visited_within_days ?? '');
const upcoming = ref(
    props.audience.has_upcoming === true ? 'yes'
        : (props.audience.has_upcoming === false ? 'no' : ''),
);

const estimate = ref(null);
const busy = ref(false);

/** Exactly what the server stores, and what it posts back as. */
const rules = computed(() => ({
    scope: scope.value,
    locations: locations.value,
    tags: tags.value,
    staff: staff.value,
    services: services.value,
    not_visited_days: notVisited.value || null,
    visited_within_days: visitedWithin.value || null,
    has_upcoming: upcoming.value === 'yes' ? true : (upcoming.value === 'no' ? false : null),
}));

const dayOptions = computed(() => Object.fromEntries(
    (props.options.lapsed_days ?? []).map((days) => [days, t('audience.days').replace(':days', days)]),
));

const upcomingOptions = computed(() => ({
    yes: t('audience.has_upcoming'),
    no: t('audience.no_upcoming'),
}));

/**
 * Asked of the server, debounced.
 *
 * A count per keystroke on a client list of tens of thousands is a query
 * storm; a count a second after somebody stops changing things is the answer
 * they were waiting for.
 */
let timer = null;

async function count() {
    clearTimeout(timer);
    busy.value = true;

    timer = setTimeout(async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        try {
            const response = await fetch(props.urls.estimate, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token ?? '',
                },
                body: JSON.stringify({ audience: rules.value }),
            });

            if (response.ok) {
                estimate.value = await response.json();
            }
        } finally {
            busy.value = false;
        }
    }, 350);
}

watch(rules, () => count(), { deep: true, immediate: true });

onBeforeUnmount(() => clearTimeout(timer));

const number = (value) => (value ?? 0).toLocaleString();
</script>

<template>
    <section class="bg-white border border-line rounded-card p-5 sm:p-6">
        <h2 class="text-[15px] font-semibold text-head">{{ t('audience.title') }}</h2>
        <p class="text-[13px] text-sub mt-1 max-w-[620px] leading-relaxed">{{ t('audience.intro') }}</p>

        <!-- The rules travel as one JSON field rather than as a dozen inputs:
             the server whitelists what may appear in them anyway, and one
             field keeps the shape stored identical to the shape posted. -->
        <input type="hidden" name="audience" :value="JSON.stringify(rules)">

        <div class="mt-5 grid sm:grid-cols-2 gap-x-5 gap-y-5">
            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.scope') }}</label>
                <SingleSelect v-model="scope" :options="options.scopes ?? {}" />
            </div>

            <div v-if="Object.keys(options.locations ?? {}).length > 1">
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.locations') }}</label>
                <MultiSelect v-model="locations" :options="options.locations ?? {}"
                             :placeholder="t('audience.any')" :summary-label="t('audience.locations')" />
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.tags') }}</label>
                <MultiSelect v-model="tags" :options="options.tags ?? {}"
                             :placeholder="t('audience.any')" :summary-label="t('audience.tags')" />
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.staff') }}</label>
                <MultiSelect v-model="staff" :options="options.staff ?? {}"
                             :placeholder="t('audience.any')" :summary-label="t('audience.staff')" />
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.services') }}</label>
                <MultiSelect v-model="services" :options="options.services ?? {}"
                             :placeholder="t('audience.any')" :summary-label="t('audience.services')" />
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.booking') }}</label>
                <SingleSelect v-model="upcoming" :options="upcomingOptions" :placeholder="t('audience.any')" />
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.lapsed') }}</label>
                <SingleSelect v-model="notVisited" :options="dayOptions" :placeholder="t('audience.any')" />
            </div>

            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">{{ t('audience.visited') }}</label>
                <SingleSelect v-model="visitedWithin" :options="dayOptions" :placeholder="t('audience.any')" />
            </div>
        </div>

        <!-- ================================================= the estimate -->
        <div class="mt-6 pt-5 border-t border-line">
            <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('estimate.title') }}</p>

            <p class="text-[44px] font-extrabold text-head leading-none mt-1.5 tabular-nums">
                <template v-if="busy && ! estimate">—</template>
                <template v-else>{{ number(estimate?.eligible) }}</template>
            </p>

            <p class="text-[13px] font-semibold text-ink mt-1">{{ t('estimate.eligible') }}</p>

            <!-- The two facts the headline hides. Shown whether or not they
                 are zero: "nobody has unsubscribed" is worth knowing too. -->
            <dl v-if="estimate" class="mt-4 grid grid-cols-3 gap-3 max-w-[420px] text-[12.5px]">
                <div>
                    <dt class="text-sub">{{ t('estimate.total') }}</dt>
                    <dd class="font-semibold text-head tabular-nums">{{ number(estimate.total) }}</dd>
                </div>
                <div>
                    <dt class="text-sub">{{ t('estimate.unsubscribed') }}</dt>
                    <dd class="font-semibold text-head tabular-nums">{{ number(estimate.unsubscribed) }}</dd>
                </div>
                <div>
                    <dt class="text-sub">{{ t('estimate.invalid') }}</dt>
                    <dd class="font-semibold text-head tabular-nums">{{ number(estimate.invalid) }}</dd>
                </div>
            </dl>

            <p v-if="estimate && estimate.total === 0" class="mt-3 text-[12.5px] text-sub">
                {{ t('estimate.none') }}
            </p>

            <!-- The case that looks like a bug and is not: rules that match
                 people, none of whom have agreed to marketing email. -->
            <p v-else-if="estimate && estimate.eligible === 0 && estimate.unsubscribed > 0"
               class="mt-3 text-[12.5px] text-warning font-medium">
                {{ t('estimate.all_unsubscribed') }}
            </p>

            <p class="mt-3 text-[12px] text-sub">
                <span v-if="busy">{{ t('estimate.counting') }}</span>
                <span v-else>{{ t('estimate.hint') }}</span>
            </p>
        </div>
    </section>
</template>
