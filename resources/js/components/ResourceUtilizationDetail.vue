<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * One room, and what happened in it.
 *
 * The bubble says how busy; this says why. Four questions an owner asks next
 * — what was done in here, on which days, at what times of day, and what did
 * it earn — and a timeline of the day itself, which is the only view that
 * shows a gap where a gap actually is.
 *
 * Its own component because it fetches. The board holds every resource's
 * summary already; nobody needs a day-by-day breakdown of twelve rooms they
 * have not asked about.
 */
const props = defineProps({
    resource: { type: Object, required: true },
    urls: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    currency: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const detail = ref(null);
const loading = ref(true);

const hours = (minutes) => Math.round(((minutes ?? 0) / 60) * 10) / 10;

const money = (minor) => `${props.currency}${((minor ?? 0) / 100).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}`;

async function load() {
    const query = new URLSearchParams({ range: props.filters.range ?? 'today' });

    if (props.filters.location) {
        query.set('location', props.filters.location);
    }

    if (props.filters.range === 'custom') {
        query.set('from', props.filters.from ?? '');
        query.set('until', props.filters.until ?? '');
    }

    try {
        const response = await fetch(
            `${props.urls.detail.replace(':resource', props.resource.id)}?${query}`,
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            detail.value = await response.json();
        }
    } finally {
        loading.value = false;
    }
}

/* The page behind must not scroll while this is open. */
onMounted(() => {
    document.body.style.overflow = 'hidden';
    load();
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});

/**
 * The day drawn to scale, from opening to closing.
 *
 * Positioned against the hours the place was actually open rather than
 * against midnight: a salon open ten to six should not spend two thirds of
 * its timeline showing the night.
 */
const dayStart = computed(() => toMinutes(detail.value?.timeline?.opens_at) ?? 8 * 60);
const dayEnd = computed(() => toMinutes(detail.value?.timeline?.closes_at) ?? 20 * 60);
const daySpan = computed(() => Math.max(60, dayEnd.value - dayStart.value));

function toMinutes(time) {
    if (! time) {
        return null;
    }

    const [h, m] = String(time).split(':');

    return Number(h) * 60 + Number(m || 0);
}

const place = (block) => ({
    left: `${Math.max(0, ((block.from_minutes - dayStart.value) / daySpan.value) * 100)}%`,
    width: `${Math.max(1.2, (block.minutes / daySpan.value) * 100)}%`,
});

/** The hour marks under the bar, every two hours so they do not collide. */
const ticks = computed(() => {
    const out = [];

    for (let m = Math.ceil(dayStart.value / 120) * 120; m <= dayEnd.value; m += 120) {
        out.push({ at: `${((m - dayStart.value) / daySpan.value) * 100}%`, label: `${String(Math.floor(m / 60)).padStart(2, '0')}:00` });
    }

    return out;
});

/* Day-by-day and time-of-day are both bar charts of one number, so they are
   drawn the same way: a track, and a fill that is a percentage of it. Tall
   enough to compare, short enough that twelve of them fit. */
const busiestHour = computed(() => Math.max(1, ...(detail.value?.by_hour ?? []).map((h) => h.minutes)));

/* Only the hours the place is ever open. Twenty-four bars, eighteen of them
   empty, is a chart that hides its own answer. */
const workingHours = computed(() => (detail.value?.by_hour ?? []).filter(
    (row) => row.hour * 60 >= dayStart.value - 60 && row.hour * 60 <= dayEnd.value + 60,
));
</script>

<template>
    <Teleport to="body">
        <div class="styledesk_modal" role="dialog" aria-modal="true" aria-labelledby="resourceDetailTitle"
             @keydown.esc="emit('close')">
            <div class="styledesk_modal__scrim" @click="emit('close')"></div>

            <!-- Wider than a form dialog: a timeline squeezed into 34rem is a
                 timeline nobody can read the gaps in. -->
            <div class="styledesk_modal__panel" style="width: min(1040px, 100%); max-width: 1040px">
                <div class="styledesk_modal__head">
                    <div class="min-w-0">
                        <h2 id="resourceDetailTitle" class="text-[15px] font-semibold text-head truncate">
                            {{ resource.name }}
                        </h2>
                        <p class="text-[12px] text-sub truncate">
                            {{ [resource.category, resource.location].filter(Boolean).join(' · ') }}
                        </p>
                    </div>

                    <button type="button" class="styledesk_modal__close" :aria-label="t('detail.close')"
                            @click="emit('close')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="styledesk_modal__body">
                    <p v-if="loading" class="text-[13px] text-sub py-10 text-center">{{ t('loading') }}</p>

                    <template v-else-if="detail">
                        <!-- The sentence the whole screen is written from,
                             said in figures. -->
                        <div class="grid gap-2.5 sm:grid-cols-4">
                            <div v-for="stat in [
                                     { label: t('open_for'), value: `${hours(detail.open_minutes)} hrs` },
                                     { label: t('used_for'), value: `${hours(detail.used_minutes)} hrs` },
                                     { label: t('empty'), value: `${hours(detail.idle_minutes)} hrs` },
                                     { label: t('space_used'), value: `${detail.utilization}%` },
                                 ]" :key="stat.label"
                                 class="rounded-card border border-line px-3.5 py-3">
                                <p class="text-[11.5px] text-sub uppercase tracking-wide">{{ stat.label }}</p>
                                <p class="text-[19px] font-bold text-head mt-0.5 tabular-nums">{{ stat.value }}</p>
                            </div>
                        </div>

                        <div class="grid gap-2.5 sm:grid-cols-3 mt-2.5">
                            <div v-for="stat in [
                                     { label: t('detail.bookings'), value: String(detail.bookings) },
                                     { label: t('detail.revenue'), value: money(detail.revenue_minor) },
                                     { label: t('detail.capacity'), value: String(detail.capacity) },
                                 ]" :key="stat.label"
                                 class="rounded-card border border-line px-3.5 py-3">
                                <p class="text-[11.5px] text-sub uppercase tracking-wide">{{ stat.label }}</p>
                                <p class="text-[16px] font-semibold text-head mt-0.5 tabular-nums">{{ stat.value }}</p>
                            </div>
                        </div>

                        <!-- ============================ the day itself -->
                        <section class="mt-5">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <h3 class="text-[14px] font-semibold text-head">{{ t('detail.timeline') }}</h3>
                                <span class="text-[12px] text-sub">{{ detail.timeline.label }}</span>
                            </div>

                            <p class="text-[12px] text-faint mt-0.5">{{ t('detail.timeline_hint') }}</p>

                            <!-- The bar is the empty day; the blocks are what
                                 was put in it. Drawn that way round so a gap
                                 reads as a gap rather than as missing data. -->
                            <div class="relative mt-3 h-11 rounded-lg bg-hover border border-line overflow-hidden">
                                <div v-for="(block, index) in detail.timeline.blocks" :key="index"
                                     class="absolute inset-y-0 rounded-md border"
                                     :class="block.kind === 'blocked'
                                         ? 'bg-hover border-line styledesk_timeline__blocked'
                                         : 'bg-brand border-brand'"
                                     :style="place(block)"
                                     :title="`${block.label} · ${block.from}–${block.to}${
                                         block.kind === 'booking' ? ' · ' + money(block.revenue_minor) : ''}`">
                                </div>

                                <p v-if="! detail.timeline.blocks.length"
                                   class="absolute inset-0 grid place-items-center text-[12px] text-sub">
                                    {{ t('detail.no_bookings') }}
                                </p>
                            </div>

                            <div class="relative h-4 mt-1">
                                <span v-for="tick in ticks" :key="tick.label"
                                      class="absolute text-[10.5px] text-faint -translate-x-1/2 tabular-nums"
                                      :style="{ left: tick.at }">{{ tick.label }}</span>
                            </div>

                            <div class="flex flex-wrap gap-3 mt-2 text-[11.5px] text-sub">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-sm bg-brand"></span>{{ t('used_for') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-sm bg-hover border border-line"></span>{{ t('detail.available_block') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-sm styledesk_timeline__blocked border border-line"></span>{{ t('detail.blocked_block') }}
                                </span>
                            </div>
                        </section>

                        <!-- ======================= the trend and the clock -->
                        <div class="grid gap-5 lg:grid-cols-2 mt-6">
                            <section>
                                <h3 class="text-[14px] font-semibold text-head">{{ t('detail.daily') }}</h3>

                                <!-- `h-full` on the column, not just on the
                                     row: a percentage height resolves against
                                     the parent's OWN height, and a flex item
                                     with auto height gives every bar nought.
                                     -->
                                <div class="flex items-end gap-1 h-28 mt-3">
                                    <div v-for="day in detail.daily" :key="day.date"
                                         class="flex-1 min-w-0 h-full flex flex-col justify-end items-center gap-1"
                                         :title="`${day.label} · ${day.utilization}%`">
                                        <div class="w-full rounded-t bg-brand shrink-0"
                                             :style="{ height: `${Math.max(2, day.utilization)}%` }"></div>
                                        <span class="text-[9.5px] text-faint truncate w-full text-center shrink-0">
                                            {{ detail.daily.length > 14 ? '' : day.weekday }}
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 class="text-[14px] font-semibold text-head">{{ t('detail.by_hour') }}</h3>

                                <div class="flex items-end gap-1 h-28 mt-3">
                                    <div v-for="row in workingHours" :key="row.hour"
                                         class="flex-1 min-w-0 h-full flex flex-col justify-end items-center gap-1"
                                         :title="`${row.label} · ${t('detail.minutes').replace(':count', row.minutes)}`">
                                        <div class="w-full rounded-t bg-brand/70 shrink-0"
                                             :style="{ height: `${Math.max(2, (row.minutes / busiestHour) * 100)}%` }"></div>
                                        <span class="text-[9.5px] text-faint tabular-nums shrink-0">{{ row.hour }}</span>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <!-- ============================ what was done here -->
                        <section class="mt-6">
                            <h3 class="text-[14px] font-semibold text-head">{{ t('detail.services') }}</h3>

                            <p v-if="! detail.services.length" class="text-[13px] text-sub mt-2">
                                {{ t('detail.no_services') }}
                            </p>

                            <table v-else class="w-full text-[13px] mt-2">
                                <tbody>
                                    <tr v-for="service in detail.services" :key="service.name"
                                        class="border-b border-line last:border-0">
                                        <td class="py-2 pr-3 font-medium text-head">{{ service.name }}</td>
                                        <td class="py-2 px-3 text-right text-sub tabular-nums">
                                            {{ service.bookings }}
                                        </td>
                                        <td class="py-2 px-3 text-right text-sub tabular-nums">
                                            {{ hours(service.minutes) }} hrs
                                        </td>
                                        <td class="py-2 pl-3 text-right font-semibold text-head tabular-nums">
                                            {{ money(service.revenue_minor) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    </template>
                </div>

                <div class="styledesk_modalfoot">
                    <a :href="urls.resource.replace(':resource', resource.id)" class="styledesk_action">
                        {{ resource.name }}
                    </a>

                    <button type="button" class="styledesk_action" @click="emit('close')">
                        {{ t('detail.close') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
