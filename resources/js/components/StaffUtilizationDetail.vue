<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * One person's utilization, opened from a bubble, a row or a timeline.
 *
 * The board answers "who"; this answers "and what should I do about it".
 * Which is why the gaps are here and are the part worth reading: a manager
 * who has just learnt that somebody is at 51% wants to know where the holes
 * in their day are and what would fit in them, not another percentage.
 */
const props = defineProps({
    member: { type: Object, required: true },
    url: { type: String, required: true },
    range: { type: String, default: 'today' },
    from: { type: String, default: '' },
    until: { type: String, default: '' },
    target: { type: Number, default: 75 },
    teamAverage: { type: Number, default: 0 },
    currency: { type: String, default: '' },
    canSeeRevenue: { type: Boolean, default: false },
    labels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const detail = ref(null);
const busy = ref(true);

const hours = (minutes) => Math.round(((minutes ?? 0) / 60) * 10) / 10;

const money = (minor) => `${props.currency}${((minor ?? 0) / 100).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}`;

/** A signed difference, said the way a person says it. */
const gapTo = (value, against) => {
    const gap = (value ?? 0) - (against ?? 0);

    return gap === 0 ? t('on_target') : `${gap > 0 ? '+' : '−'}${Math.abs(gap)}%`;
};

// ------------------------------------------------------------- the timeline

const track = ref(null);
const trackWidth = ref(560);
let observer = null;

const day = computed(() => detail.value?.timeline ?? null);

const span = computed(() => Math.max(60, (day.value?.closes_at ?? 1080) - (day.value?.opens_at ?? 540)));

const at = (minutes) => Math.round(((minutes - (day.value?.opens_at ?? 540)) / span.value) * trackWidth.value);

const widthOf = (from, to) => Math.max(2, at(to) - at(from));

/** The most productive band of the day, as a share of what it could hold. */
const bars = computed(() => {
    const days = detail.value?.daily ?? [];
    const most = Math.max(1, ...days.map((entry) => entry.utilization));

    return days.map((entry) => ({ ...entry, share: Math.round((entry.utilization / most) * 100) }));
});

const averageValue = computed(() => {
    if (! detail.value?.bookings || detail.value.revenue_minor === undefined) {
        return null;
    }

    return money(detail.value.revenue_minor / detail.value.bookings);
});

function measure() {
    if (track.value) {
        trackWidth.value = Math.max(240, track.value.clientWidth);
    }
}

async function load() {
    busy.value = true;

    const query = new URLSearchParams({ range: props.range });

    if (props.range === 'custom') {
        query.set('from', props.from);
        query.set('until', props.until);
    }

    try {
        const response = await fetch(`${props.url.replace(':staff', props.member.id)}?${query}`, {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            detail.value = await response.json();
        }
    } finally {
        busy.value = false;
        requestAnimationFrame(() => measure());
    }
}

function onKey(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => {
    load();
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';

    observer = new ResizeObserver(() => measure());

    if (track.value) {
        observer.observe(track.value);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
    observer?.disconnect();
});
</script>

<template>
    <div class="styledesk_rtpanel" @click.self="emit('close')">
        <aside class="styledesk_rtpanel__card styledesk_rtpanel__card--wide" role="dialog" aria-modal="true">
            <header class="styledesk_rtpanel__head">
                <div class="min-w-0">
                    <p class="text-[15px] font-semibold text-head truncate">{{ member.name }}</p>
                    <p class="text-[12px] text-sub">{{ member.role }}<template v-if="member.location"> · {{ member.location }}</template></p>
                </div>

                <button type="button" class="styledesk_drawer__close" :aria-label="t('detail.close')" @click="emit('close')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    </svg>
                </button>
            </header>

            <div class="styledesk_rtpanel__body">
                <p v-if="busy" class="text-[13px] text-sub">{{ t('loading') }}</p>

                <template v-else-if="detail">
                    <!-- The figure, and the two things it is worth comparing
                         against: the target, and everybody else. A percentage
                         on its own is not yet a judgement. -->
                    <div class="flex items-end gap-4">
                        <p class="text-[52px] font-extrabold leading-[0.9] tracking-tight tabular-nums"
                           :class="detail.utilization >= target ? 'text-brand' : 'text-head'">
                            {{ detail.utilization }}%
                        </p>

                        <dl class="text-[12.5px] pb-1.5 space-y-0.5">
                            <div class="flex gap-2">
                                <dt class="text-sub">{{ t('detail.vs_target') }}</dt>
                                <dd class="font-semibold text-head tabular-nums">{{ gapTo(detail.utilization, target) }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="text-sub">{{ t('detail.vs_team') }}</dt>
                                <dd class="font-semibold text-head tabular-nums">{{ gapTo(detail.utilization, teamAverage) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-[12.5px]">
                        <div class="flex gap-2 border-b border-line pb-1.5">
                            <dt class="text-sub">{{ t('detail.bookable') }}</dt>
                            <dd class="ms-auto font-semibold text-head tabular-nums">{{ hours(detail.available_minutes) }}</dd>
                        </div>
                        <div class="flex gap-2 border-b border-line pb-1.5">
                            <dt class="text-sub">{{ t('detail.booked') }}</dt>
                            <dd class="ms-auto font-semibold text-head tabular-nums">{{ hours(detail.booked_minutes) }}</dd>
                        </div>
                        <div class="flex gap-2 border-b border-line pb-1.5">
                            <dt class="text-sub">{{ t('detail.unused') }}</dt>
                            <dd class="ms-auto font-semibold text-head tabular-nums">{{ hours(detail.idle_minutes) }}</dd>
                        </div>
                        <div class="flex gap-2 border-b border-line pb-1.5">
                            <dt class="text-sub">{{ t('detail.bookings') }}</dt>
                            <dd class="ms-auto font-semibold text-head tabular-nums">{{ detail.bookings }}</dd>
                        </div>
                        <div v-if="canSeeRevenue && detail.revenue_minor !== undefined" class="flex gap-2 border-b border-line pb-1.5">
                            <dt class="text-sub">{{ t('detail.revenue') }}</dt>
                            <dd class="ms-auto font-semibold text-head tabular-nums">{{ money(detail.revenue_minor) }}</dd>
                        </div>
                        <div v-if="averageValue" class="flex gap-2 border-b border-line pb-1.5">
                            <dt class="text-sub">{{ t('detail.average_value') }}</dt>
                            <dd class="ms-auto font-semibold text-head tabular-nums">{{ averageValue }}</dd>
                        </div>
                    </dl>

                    <!-- ------------------------------------------ the day -->
                    <template v-if="day && day.bands.length">
                        <h3 class="text-[13px] font-semibold text-head mt-6">{{ t('detail.day') }}</h3>

                        <div ref="track" class="mt-2">
                            <svg :width="trackWidth" height="46" role="presentation">
                                <rect v-for="(band, index) in day.bands" :key="`b${index}`"
                                      :x="at(band.from)" y="12" :width="widthOf(band.from, band.to)" height="22"
                                      :class="`styledesk_su__band--${band.status}`"/>

                                <g v-for="(block, index) in day.blocks" :key="`k${index}`" class="styledesk_su__booking">
                                    <title>{{ block.client }} · {{ block.service }} · {{ block.from_label }}–{{ block.to_label }}</title>
                                    <rect :x="at(block.from) + 1" y="12"
                                          :width="Math.max(2, widthOf(block.from, block.to) - 2)" height="22" rx="4"/>
                                </g>

                                <text v-for="hour in day.hours" :key="`h${hour.minutes}`"
                                      :x="at(hour.minutes)" y="45" class="styledesk_rt__hour">
                                    {{ hour.label }}
                                </text>
                            </svg>
                        </div>
                    </template>

                    <!-- ----------------------------------------- the gaps -->
                    <h3 class="text-[13px] font-semibold text-head mt-6">{{ t('detail.gaps') }}</h3>
                    <p class="text-[12px] text-sub mt-0.5">{{ t('detail.gaps_hint') }}</p>

                    <ul v-if="detail.gaps.length" class="mt-2 space-y-2">
                        <li v-for="(gap, index) in detail.gaps" :key="index"
                            class="rounded-lg border border-line px-3 py-2">
                            <p class="text-[13px] font-semibold text-head">
                                {{ gap.from_label }} – {{ gap.to_label }}
                                <span class="text-sub font-medium">· {{ t('detail.minutes').replace(':count', gap.minutes) }}</span>
                            </p>

                            <p v-if="gap.services.length" class="mt-1 flex flex-wrap gap-1.5">
                                <span v-for="service in gap.services" :key="service.name" class="styledesk_chip">
                                    {{ service.name }} · {{ service.minutes }}m
                                </span>
                            </p>
                        </li>
                    </ul>

                    <p v-else class="text-[12.5px] text-sub mt-2">{{ t('detail.gaps_none') }}</p>

                    <!-- ------------------------------------ day by day -->
                    <template v-if="bars.length > 1">
                        <h3 class="text-[13px] font-semibold text-head mt-6">{{ t('detail.daily') }}</h3>

                        <ul class="mt-2 space-y-1">
                            <li v-for="entry in bars" :key="entry.date" class="flex items-center gap-2 text-[12px]">
                                <span class="w-14 shrink-0 text-sub">{{ entry.weekday }} {{ entry.label }}</span>
                                <span class="flex-1 h-2.5 rounded-full bg-hover overflow-hidden">
                                    <span class="block h-full rounded-full bg-brand" :style="{ width: `${entry.share}%` }"></span>
                                </span>
                                <span class="w-10 text-right font-semibold text-head tabular-nums">{{ entry.utilization }}%</span>
                            </li>
                        </ul>
                    </template>

                    <!-- ------------------------------------ what they did -->
                    <h3 class="text-[13px] font-semibold text-head mt-6">{{ t('detail.services') }}</h3>

                    <ul v-if="detail.services.length" class="mt-2 space-y-1.5">
                        <li v-for="line in detail.services" :key="line.name"
                            class="flex items-baseline justify-between gap-3 text-[12.5px] border-b border-line pb-1.5">
                            <span class="font-semibold text-head">{{ line.name }}</span>
                            <span class="text-sub tabular-nums shrink-0">
                                {{ line.bookings }} · {{ hours(line.minutes) }} hrs<template
                                    v-if="canSeeRevenue && line.revenue_minor !== undefined"> · {{ money(line.revenue_minor) }}</template>
                            </span>
                        </li>
                    </ul>

                    <p v-else class="text-[12.5px] text-sub mt-2">{{ t('detail.no_bookings') }}</p>
                </template>
            </div>
        </aside>
    </div>
</template>
