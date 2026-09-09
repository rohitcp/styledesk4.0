<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
/* The cluster itself — shared with resource utilization, so the two boards
   cannot come to disagree about what 40% looks like. */
import { useBubbleBoard } from '../bubble-board';
/* Imported before the plugin on purpose — see the module's own note. */
import { $, moment } from '../daterangepicker-globals';
/* The plugin's stylesheet is imported in app.css, ahead of ours, so the
   StyleDesk rules win — see the note there. */
import 'bootstrap-daterangepicker';
import SingleSelect from './SingleSelect.vue';
import StaffUtilizationDetail from './StaffUtilizationDetail.vue';

/**
 * How much of each person's bookable day is actually booked.
 *
 * The same picture the resource board draws, asked of people instead of
 * rooms — deliberately, because they are the same question and a manager who
 * has learnt to read one should not have to learn the other. What differs is
 * what the denominator means: a room's capacity is the hours it stands open,
 * and a person's is the hours they are rostered to take clients, less their
 * breaks and anything that cannot be booked.
 *
 * Which is why somebody not scheduled shows as "not scheduled" and not as
 * 0%. A day off is not a day wasted, and a screen that says otherwise is one
 * a manager stops believing.
 */
const props = defineProps({
    urls: { type: Object, required: true },
    /** The first answer, rendered with the page so it opens filled. */
    staff: { type: Array, default: () => [] },
    /** The team's day, where the window is one. Null over a longer range. */
    day: { type: Object, default: null },
    locations: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    /* The ranges this screen offers, from App\Support\SalesPeriod — the app's
       own date-range vocabulary rather than a list invented here. */
    presets: { type: Object, default: () => ({}) },
    /** The server's today, which is the only clock this screen trusts. */
    today: { type: String, default: '' },
    /** The figure the business is aiming at, from config. */
    target: { type: Number, default: 75 },
    filters: { type: Object, default: () => ({}) },
    currency: { type: String, default: '' },
    canSeeRevenue: { type: Boolean, default: false },
    scheduleUrl: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const rows = ref([...props.staff]);
const teamDay = ref(props.day);
const range = ref(props.filters.range ?? 'today');
const from = ref(props.filters.from ?? '');
const until = ref(props.filters.until ?? '');
const locationId = ref(props.filters.location ?? '');
const roleId = ref('');
const busy = ref(false);
const openStaff = ref(null);

/**
 * Who the picture is of.
 *
 * Only people with a roster in the window. Somebody who was not scheduled has
 * no capacity, so they have no percentage — a bubble at 0% for a person on
 * annual leave is an accusation, and it drags the average down for a reason
 * that is nobody's problem. They are still in the table underneath, where the
 * status column says why.
 */
const visible = computed(() => rows.value
    .filter((row) => (! roleId.value || String(row.role_id) === String(roleId.value)))
    .filter((row) => row.available_minutes > 0));

/** Everybody the filters allow, scheduled or not — what the table shows. */
const listed = computed(() => rows.value
    .filter((row) => (! roleId.value || String(row.role_id) === String(roleId.value))));

// ------------------------------------------------------------- the bubbles

/**
 * Nine tints, and a person keeps their own.
 *
 * Keyed to the id rather than to the position in the list, so changing the
 * date range does not turn the teal stylist coral — a bubble that changes
 * colour between two clicks reads as a different person.
 */
const tintOf = (row) => `c${row.id % 9}`;

const { board, width, height, painted, hovered, narrow, roomFor } = useBubbleBoard({
    visible,
    tintOf,
    metaOf: (row) => t('used_of_short')
        .replace(':used', hours(row.booked_minutes))
        .replace(':available', hours(row.available_minutes)),
    /* A shade larger than the resource board's. There are fewer people than
       rooms in most salons, so there is room for it — and a person's name is
       longer than "Chair 01". */
    boost: 1.12,
});

// -------------------------------------------------------------- the wording

const hours = (minutes) => Math.round(((minutes ?? 0) / 60) * 10) / 10;

const money = (minor) => `${props.currency}${((minor ?? 0) / 100).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}`;

// --------------------------------------------------------------- the tooltip

const tip = ref(null);

function showTip(node, event) {
    tip.value = { node, x: event.clientX, y: event.clientY };
}

function moveTip(event) {
    if (tip.value) {
        tip.value = { ...tip.value, x: event.clientX, y: event.clientY };
    }
}

const tipStyle = computed(() => {
    if (! tip.value) {
        return {};
    }

    const flip = tip.value.x > window.innerWidth - 280;

    return {
        left: `${flip ? tip.value.x - 268 : tip.value.x + 16}px`,
        top: `${Math.min(tip.value.y + 14, window.innerHeight - 200)}px`,
    };
});

// -------------------------------------------------------------- the summary

/**
 * The figure the right-hand column is built around.
 *
 * Weighted by the hours each person was actually bookable rather than a mean
 * of the percentages: somebody rostered for two hours should not swing the
 * team's figure as hard as somebody rostered for forty.
 */
const summary = computed(() => {
    if (! visible.value.length) {
        return null;
    }

    const bookable = visible.value.reduce((total, row) => total + row.available_minutes, 0);
    const booked = visible.value.reduce((total, row) => total + row.booked_minutes, 0);
    const bookings = visible.value.reduce((total, row) => total + row.bookings, 0);

    return {
        average: bookable > 0 ? Math.min(100, Math.round((booked / bookable) * 100)) : 0,
        bookable,
        booked,
        unused: Math.max(0, bookable - booked),
        bookings,
        scheduled: visible.value.length,
        onTarget: visible.value.filter((row) => row.utilization >= props.target).length,
        under: visible.value.filter((row) => row.utilization < props.target).length,
    };
});

const summaryPeriod = computed(() => {
    if (range.value !== 'custom') {
        return props.presets[range.value] ?? '';
    }

    const start = moment(from.value, 'YYYY-MM-DD');
    const end = moment(until.value, 'YYYY-MM-DD');

    return `${start.format('D MMM')} – ${end.format('D MMM YYYY')}`;
});

/** "3% below target", "4% above target", or simply on it. */
const againstTarget = computed(() => {
    if (! summary.value) {
        return '';
    }

    const gap = summary.value.average - props.target;

    if (gap === 0) {
        return t('on_target');
    }

    return gap > 0
        ? t('above_target').replace(':count', gap)
        : t('below_target').replace(':count', Math.abs(gap));
});

// --------------------------------------------------------- hitting the target

/* Fired on the crossing rather than on the state, so changing a filter to
   another passing figure does not set it off again — and, deliberately, on
   first load when the figure already passes, because that IS news to somebody
   who has just opened the page. */
const targetMet = computed(() => Boolean(summary.value) && summary.value.average >= props.target);
const celebrating = ref(false);

let sparkTimer = null;

watch(targetMet, (met, was) => {
    if (! met || was) {
        return;
    }

    clearTimeout(sparkTimer);
    celebrating.value = true;

    sparkTimer = setTimeout(() => {
        celebrating.value = false;
    }, 1600);
}, { immediate: true });

/* Twelve, around a circle. Positions worked out here rather than written out
   as twelve CSS classes: the angle and the delay are the only things that
   differ between them. */
const sparks = Array.from({ length: 12 }, (_, index) => ({
    id: index,
    angle: (index / 12) * 360,
    delay: `${(index % 4) * 90}ms`,
}));

// -------------------------------------------------------------- the timeline

/** Minutes past the start of the day, as a share of it. */
const spanOf = (day) => Math.max(60, (day?.closes_at ?? 1080) - (day?.opens_at ?? 540));

const trackWidth = ref(880);
const track = ref(null);
let trackObserver = null;

const at = (day, minutes) => Math.round(((minutes - (day.opens_at ?? 540)) / spanOf(day)) * trackWidth.value);

const widthOf = (day, fromMinutes, toMinutes) => Math.max(2, at(day, toMinutes) - at(day, fromMinutes));

function measureTrack() {
    if (track.value) {
        trackWidth.value = Math.max(320, track.value.clientWidth);
    }
}

// --------------------------------------------------------------- the filters

/**
 * A date range is a different question of the rota, so it is asked of the
 * server. The role chips are a subset of the answer already on screen, so
 * they are not.
 */
async function reload() {
    busy.value = true;

    const query = new URLSearchParams({ range: range.value });

    if (locationId.value) {
        query.set('location', locationId.value);
    }

    if (range.value === 'custom') {
        query.set('from', from.value);
        query.set('until', until.value);
    }

    try {
        const response = await fetch(`${props.urls.data}?${query}`, { headers: { Accept: 'application/json' } });

        if (response.ok) {
            const json = await response.json();

            rows.value = json.staff ?? [];
            teamDay.value = json.day ?? null;
            from.value = json.from;
            until.value = json.until;
        }
    } finally {
        busy.value = false;
    }
}

watch(locationId, () => reload());

const locationOptions = computed(() => Object.fromEntries(
    props.locations.map((location) => [location.id, location.name]),
));

const roleOptions = computed(() => Object.fromEntries(
    props.roles.map((role) => [role.id, role.name]),
));

const statusOptions = computed(() => Object.fromEntries(
    ['high', 'on_target', 'near_target', 'low', 'very_low', 'unscheduled']
        .filter((key) => listed.value.some((row) => row.status === key))
        .map((key) => [key, t(`statuses.${key}`)]),
));

const tableSearch = ref('');
const statusFilter = ref('');
const gridBusy = ref(false);

const activeFilters = computed(() => {
    const chips = [];

    if (roleId.value) {
        chips.push({ key: 'role', label: roleOptions.value[roleId.value] });
    }

    if (locationId.value) {
        chips.push({ key: 'location', label: locationOptions.value[locationId.value] });
    }

    if (statusFilter.value) {
        chips.push({ key: 'status', label: statusOptions.value[statusFilter.value] });
    }

    if (tableSearch.value.trim().length >= 2) {
        chips.push({ key: 'search', label: `“${tableSearch.value.trim()}”` });
    }

    return chips.filter((chip) => chip.label);
});

function dropFilter(key) {
    ({
        role: () => { roleId.value = ''; },
        location: () => { locationId.value = ''; },
        status: () => { statusFilter.value = ''; },
        search: () => { tableSearch.value = ''; },
    })[key]?.();
}

function clearFilters() {
    roleId.value = '';
    locationId.value = '';
    statusFilter.value = '';
    tableSearch.value = '';
}

/**
 * The listing grid is the app's own, and the server narrows it.
 *
 * Re-pointing it at a new URL rather than filtering rows keeps the server as
 * the thing that decides which rows this reader may see — which, on a screen
 * whose whole point is that a service provider sees only themselves, is not
 * a decision to hand to the browser.
 */
let gridTimer = null;

function syncGrid() {
    clearTimeout(gridTimer);

    gridBusy.value = true;

    gridTimer = setTimeout(() => {
        const host = document.querySelector('[data-grid]');

        if (! host?.styledeskGrid) {
            gridBusy.value = false;

            return;
        }

        const query = new URLSearchParams({ range: range.value });

        if (locationId.value) {
            query.set('location', locationId.value);
        }

        if (range.value === 'custom') {
            query.set('from', from.value);
            query.set('until', until.value);
        }

        if (roleId.value) {
            query.set('role', roleId.value);
        }

        if (statusFilter.value) {
            query.set('status', statusFilter.value);
        }

        /* Under two characters is not a search: one letter matches almost
           everything and makes the list flicker as somebody types. */
        if (tableSearch.value.trim().length >= 2) {
            query.set('search', tableSearch.value.trim());
        }

        /* setData starts the list again at page one, which is what a new
           question deserves. */
        host.styledeskGrid.setData(`${props.urls.rows}?${query}`);
    }, 250);
}

watch([range, locationId, roleId, statusFilter, tableSearch], () => syncGrid());

function gridLanded() {
    gridBusy.value = false;
}

// ------------------------------------------------------------- the date picker

const picker = ref(null);

const presetLabels = computed(() => Object.fromEntries(
    Object.entries(props.presets).filter(([key]) => key !== 'custom'),
));

/**
 * Today, according to the server.
 *
 * Never `moment()`. The picker computes its own presets, and if it does that
 * from the browser's clock while the server answers from its own, the two
 * quietly disagree: by a timezone for a real salon, and by however far the
 * machine is out on a developer's.
 */
const anchor = () => (props.today ? moment(props.today, 'YYYY-MM-DD') : moment());

function spanFor(key) {
    return {
        today: [anchor(), anchor()],
        /* Forwards as well as back — the one range this screen has that the
           resource board does not, because "is tomorrow covered" is the
           question a manager opens a rota with. */
        tomorrow: [anchor().add(1, 'days'), anchor().add(1, 'days')],
        yesterday: [anchor().subtract(1, 'days'), anchor().subtract(1, 'days')],
        week: [anchor().startOf('week'), anchor().endOf('week')],
        month: [anchor().startOf('month'), anchor().endOf('month')],
    }[key] ?? [anchor(), anchor()];
}

function describe(key, start, end) {
    if (key && key !== 'custom' && props.presets[key]) {
        return props.presets[key];
    }

    return `${start.format('D MMM YYYY')} – ${end.format('D MMM YYYY')}`;
}

function mountPicker() {
    if (! picker.value) {
        return;
    }

    const ranges = {};

    Object.entries(presetLabels.value).forEach(([key, label]) => {
        ranges[label] = spanFor(key);
    });

    $(picker.value).daterangepicker({
        ranges,
        startDate: moment(from.value, 'YYYY-MM-DD'),
        endDate: moment(until.value, 'YYYY-MM-DD'),
        opens: 'left',
        /* No maxDate, unlike the resource board: a rota is a thing you read
           ahead of yourself, and the whole point of "tomorrow" is that it has
           not happened yet. */
        alwaysShowCalendars: false,
        autoUpdateInput: false,
        locale: {
            format: 'D MMM YYYY',
            separator: ' – ',
            customRangeLabel: props.presets.custom ?? 'Custom range',
            applyLabel: t('apply'),
            cancelLabel: t('cancel'),
        },
    }, (start, end, label) => {
        const key = Object.keys(presetLabels.value).find((name) => presetLabels.value[name] === label);

        range.value = key ?? 'custom';
        from.value = start.format('YYYY-MM-DD');
        until.value = end.format('YYYY-MM-DD');
        picker.value.value = describe(range.value, start, end);

        reload();
    });

    picker.value.value = describe(
        range.value,
        moment(from.value, 'YYYY-MM-DD'),
        moment(until.value, 'YYYY-MM-DD'),
    );
}

// ----------------------------------------------------------------- lifecycle

onMounted(() => {
    mountPicker();
    measureTrack();

    if (track.value) {
        trackObserver = new ResizeObserver(() => measureTrack());
        trackObserver.observe(track.value);
    }

    document.addEventListener('styledesk:grid-loaded', gridLanded);

    /* A row opens the same drill-down a bubble does. The grid navigates to a
       row's `url` when it has one; these rows deliberately have none, so the
       click is ours to answer — and what this page is about is the
       utilization detail, not the staff record. */
    const host = document.querySelector('[data-grid]');

    if (host) {
        const attach = () => host.styledeskGrid?.on('rowClick', (event, row) => {
            const found = rows.value.find((item) => item.id === row.getData().id);

            if (found) {
                openStaff.value = found;
            }
        });

        host.styledeskGrid ? attach() : setTimeout(attach, 400);
    }
});

onBeforeUnmount(() => {
    trackObserver?.disconnect();
    clearTimeout(sparkTimer);
    document.removeEventListener('styledesk:grid-loaded', gridLanded);

    /* The plugin appends its panel to <body> and binds document handlers, so
       it has to be told to go rather than left to the component's teardown. */
    if (picker.value) {
        $(picker.value).data('daterangepicker')?.remove();
    }
});
</script>

<template>
    <div>
        <!-- ================================================= the controls -->
        <div class="sd-compact flex flex-wrap items-center gap-2">
            <span v-if="busy" class="text-[12px] text-sub">{{ t('loading') }}</span>

            <!-- One control for the whole question. The presets live in its
                 own sidebar, and the calendars only open for a hand-picked
                 range — so there is nothing on screen when "Today" is the
                 answer, which it usually is. -->
            <div class="w-full sm:w-auto sm:ms-auto sm:w-[220px]">
                <input ref="picker" type="text" readonly
                       class="sd-input !h-9 cursor-pointer" :aria-label="t('period')">
            </div>
        </div>

        <!-- ================================================== the figures -->
        <div v-if="summary" class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-card border border-line bg-white px-4 py-3">
                <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.scheduled') }}</p>
                <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ summary.scheduled }}</p>
            </div>

            <div class="rounded-card border border-line bg-white px-4 py-3">
                <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.bookable') }}</p>
                <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ hours(summary.bookable) }}</p>
            </div>

            <div class="rounded-card border border-line bg-white px-4 py-3">
                <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.booked') }}</p>
                <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ hours(summary.booked) }}</p>
            </div>

            <div class="rounded-card border border-line bg-white px-4 py-3">
                <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.unused') }}</p>
                <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ hours(summary.unused) }}</p>
            </div>
        </div>

        <!-- ================================================== the bubbles -->
        <!-- Two readings of the same filtered set, side by side: who is
             booked, and how the team is doing as a whole. Eight columns and
             four, because the picture needs the room and the figure needs
             almost none.

             The summary comes FIRST in the source and is put back on the
             right from `lg` up. On a phone that ordering is the point: the
             one number a manager wants is above the fold. -->
        <div class="mt-4 grid gap-4 lg:grid-cols-12 items-stretch">

            <!-- ---------------------------------------- the overall figure -->
            <aside class="lg:col-span-4 lg:order-last rounded-card border border-line bg-white
                          px-6 py-6 flex flex-col justify-center text-center">
                <template v-if="summary">
                    <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.08em]">{{ summaryPeriod }}</p>

                    <!-- The hero, and deliberately the largest thing on the
                         screen: it answers the one question the board cannot,
                         which is how the team is doing as a whole. -->
                    <div class="relative mt-1.5">
                        <p class="text-[84px] xl:text-[102px] font-extrabold leading-[0.9] tracking-tight tabular-nums"
                           :class="targetMet ? 'text-brand' : 'text-head'">
                            {{ summary.average }}%
                        </p>

                        <!-- The moment the target is reached, and only the
                             moment: gone in a second and a half, and the
                             badge below carries the news afterwards. -->
                        <span v-if="celebrating" class="styledesk_sparks pointer-events-none" aria-hidden="true">
                            <span v-for="spark in sparks" :key="spark.id" class="styledesk_sparks__one"
                                  :style="{ '--angle': `${spark.angle}deg`, animationDelay: spark.delay }"></span>
                        </span>
                    </div>

                    <p class="text-[14px] font-semibold text-ink mt-2">{{ t('average') }}</p>

                    <!-- The fact, which stays. A reader who arrives after the
                         sparks have gone still needs to be told. -->
                    <p class="mt-2">
                        <span v-if="targetMet" class="styledesk_badge styledesk_badge--active">{{ t('target_met') }}</span>
                        <span v-else class="text-[13px] font-semibold text-sub">{{ againstTarget }}</span>
                    </p>

                    <p class="text-[12.5px] text-sub mt-3">{{ t('target').replace(':target', target) }}</p>

                    <dl class="mt-4 pt-4 border-t border-line grid grid-cols-3 gap-2 text-[12.5px]">
                        <div>
                            <dt class="text-sub">{{ t('summary.booked') }}</dt>
                            <dd class="font-semibold text-head tabular-nums">{{ hours(summary.booked) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sub">{{ t('summary.bookable') }}</dt>
                            <dd class="font-semibold text-head tabular-nums">{{ hours(summary.bookable) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sub">{{ t('summary.unused') }}</dt>
                            <dd class="font-semibold text-head tabular-nums">{{ hours(summary.unused) }}</dd>
                        </div>
                    </dl>

                    <p class="text-[12.5px] text-sub mt-3">
                        {{ summary.onTarget }} {{ t('summary.on_target').toLowerCase() }} ·
                        {{ summary.under }} {{ t('summary.under').toLowerCase() }}
                    </p>
                </template>

                <p v-else class="text-[13px] text-sub">{{ t('empty_hint') }}</p>
            </aside>

            <!-- ------------------------------------------------- the board -->
            <div ref="board" class="lg:col-span-8 rounded-card border border-line bg-white overflow-hidden">
                <!-- Nobody rostered is not a board with nothing on it; it is
                     a different screen, with the way out of it on it. -->
                <div v-if="! visible.length" class="px-6 py-12 text-center">
                    <p class="text-[15px] font-semibold text-head">{{ t('empty') }}</p>
                    <p class="text-[13px] text-sub mt-1.5 max-w-md mx-auto">{{ t('empty_hint') }}</p>
                    <a v-if="scheduleUrl" :href="scheduleUrl" class="styledesk_action mt-4">{{ t('empty_cta') }}</a>
                </div>

                <!-- A dozen circles crammed into 360px is a picture nobody
                     can read. A narrow screen gets the list instead. -->
                <ul v-else-if="narrow" class="p-3 space-y-2">
                    <li v-for="row in visible" :key="row.id">
                        <button type="button" class="w-full flex items-center gap-3 rounded-lg border border-line px-3 py-2.5 text-left hover:bg-hover"
                                @click="openStaff = row">
                            <span class="text-[20px] font-extrabold text-head tabular-nums w-14">{{ row.utilization }}%</span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-head truncate">{{ row.name }}</span>
                                <span class="block text-[12px] text-sub">
                                    {{ hours(row.booked_minutes) }} / {{ hours(row.available_minutes) }} hrs
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>

                <svg v-else :width="width" :height="height" role="list"
                     :aria-label="t('average')">
                    <g v-for="node in painted" :key="node.id"
                       class="styledesk_bubble"
                       :class="[
                           `styledesk_bubble--${node.tint}`,
                           openStaff?.id === node.id ? 'styledesk_bubble--selected' : '',
                       ]"
                       :transform="`translate(${node.x}, ${node.y})`"
                       role="button" tabindex="0"
                       :aria-label="`${node.name}, ${node.utilization}%`"
                       @click="openStaff = node"
                       @keydown.enter="openStaff = node"
                       @mouseenter="hovered = node.id; showTip(node, $event)"
                       @mousemove="moveTip"
                       @mouseleave="hovered = null; tip = null">
                        <g class="styledesk_bubble__lift">
                            <circle :r="node.r" class="styledesk_bubble__disc" />

                            <!-- The hero. Everything else in the circle is
                                 positioned relative to it. -->
                            <text text-anchor="middle" class="styledesk_bubble__value"
                                  :y="node.at.valueY"
                                  :style="{ fontSize: `${node.type.value}px` }">
                                {{ node.utilization }}%
                            </text>

                            <template v-if="roomFor(node).name">
                                <text v-for="(line, index) in node.lines" :key="index"
                                      text-anchor="middle" class="styledesk_bubble__name"
                                      :y="node.at.nameY + index * node.type.name * 1.18"
                                      :style="{ fontSize: `${node.type.name}px` }">
                                    {{ line }}
                                </text>
                            </template>

                            <text v-if="roomFor(node).hours" text-anchor="middle" class="styledesk_bubble__meta"
                                  :y="node.at.metaY" :style="{ fontSize: `${node.type.meta}px` }">
                                {{ node.hoursLabel }}
                            </text>

                            <template v-if="roomFor(node).chip && node.pill.label">
                                <rect class="styledesk_bubble__pill"
                                      :x="-node.pill.width / 2" :y="node.at.chipY"
                                      :width="node.pill.width" :height="node.pill.height"
                                      :rx="node.pill.height / 2" />
                                <text text-anchor="middle" class="styledesk_bubble__chip"
                                      :y="node.at.chipY + node.pill.height * 0.68"
                                      :style="{ fontSize: `${node.type.chip}px` }">
                                    {{ node.pill.label }}
                                </text>
                            </template>
                        </g>
                    </g>
                </svg>
            </div>
        </div>

        <!-- ================================================= the team day -->
        <!-- Only where the window IS a day. A timeline of a fortnight is a
             picture of nothing. -->
        <section v-if="teamDay && teamDay.rows.length" class="mt-5 rounded-card border border-line bg-white overflow-hidden">
            <header class="flex flex-wrap items-center gap-x-4 gap-y-1.5 px-4 py-2.5 border-b border-line">
                <p class="text-[13px] font-semibold text-head">{{ t('timeline.title') }}</p>
                <p class="text-[12px] text-sub">{{ teamDay.label }}</p>

                <span v-for="key in ['booked', 'available', 'break', 'blocked', 'off']" :key="key"
                      class="inline-flex items-center gap-1.5 text-[12px] text-sub"
                      :class="key === 'booked' ? 'sm:ms-auto' : ''">
                    <span class="styledesk_rt__swatch" :class="`styledesk_su__swatch--${key}`" aria-hidden="true"></span>
                    {{ t(`timeline.legend.${key}`) }}
                </span>
            </header>

            <div class="styledesk_rt">
                <div class="styledesk_rt__grid" :style="{ '--chart': `${trackWidth}px`, '--name': '200px' }">
                    <div class="styledesk_rt__head">
                        <div class="styledesk_rt__corner">{{ t('table.staff') }}</div>

                        <div ref="track" class="styledesk_rt__track">
                            <svg :width="trackWidth" height="34" role="presentation">
                                <g v-for="hour in teamDay.hours" :key="hour.minutes">
                                    <line :x1="at(teamDay, hour.minutes)" :x2="at(teamDay, hour.minutes)"
                                          y1="20" y2="34" class="styledesk_rt__gridline"/>
                                    <text :x="at(teamDay, hour.minutes) + 5" y="15" class="styledesk_rt__hour">
                                        {{ hour.label }}
                                    </text>
                                </g>
                            </svg>
                        </div>
                    </div>

                    <div v-for="row in teamDay.rows" :key="row.id" class="styledesk_rt__row">
                        <button type="button" class="styledesk_rt__name" @click="openStaff = row">
                            <span class="styledesk_rt__namelabel">{{ row.name }}</span>
                            <span class="styledesk_rt__namemeta">{{ row.utilization }}% · {{ row.bookings }}</span>
                        </button>

                        <div class="styledesk_rt__track">
                            <svg :width="trackWidth" height="42" role="presentation">
                                <rect v-for="(band, index) in row.timeline.bands" :key="`b${index}`"
                                      :x="at(teamDay, band.from)" y="0"
                                      :width="widthOf(teamDay, band.from, band.to)" height="42"
                                      :class="`styledesk_su__band--${band.status}`"/>

                                <line v-for="hour in teamDay.hours" :key="`h${hour.minutes}`"
                                      :x1="at(teamDay, hour.minutes)" :x2="at(teamDay, hour.minutes)"
                                      y1="0" y2="42" class="styledesk_rt__gridline"/>

                                <g v-for="(block, index) in row.timeline.blocks" :key="`k${index}`"
                                   class="styledesk_su__booking">
                                    <title>
                                        {{ block.client }} · {{ block.service }} · {{ block.from_label }}–{{ block.to_label }}
                                    </title>
                                    <rect :x="at(teamDay, block.from) + 1" y="5"
                                          :width="Math.max(2, widthOf(teamDay, block.from, block.to) - 2)"
                                          height="32" rx="4"/>
                                </g>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================================================ the list below -->
        <section class="mt-6">
            <div class="flex flex-wrap items-start gap-2">
                <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
                    <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.9"/>
                            <path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                        </svg>
                    </span>

                    <input v-model="tableSearch" type="text" autocomplete="off"
                           class="sd-input styledesk_input--prefixed pr-16"
                           :aria-label="t('search')" :placeholder="t('search')">

                    <span v-if="gridBusy" class="absolute right-9 top-1/2 -translate-y-1/2 text-faint pointer-events-none"
                          aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="animate-spin">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" opacity="0.25"/>
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </span>

                    <button v-if="tableSearch" type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 h-6 w-6 grid place-items-center
                                   rounded text-faint hover:text-ink hover:bg-hover transition-colors"
                            :aria-label="t('clear')" @click="tableSearch = ''">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
                    <div v-if="locations.length > 1" class="w-full lg:w-[170px] shrink-0">
                        <SingleSelect v-model="locationId" :options="locationOptions"
                                      :placeholder="t('all_locations')" />
                    </div>

                    <div v-if="roles.length > 1" class="w-full lg:w-[170px] shrink-0">
                        <SingleSelect v-model="roleId" :options="roleOptions"
                                      :placeholder="t('all_roles')" />
                    </div>

                    <div class="w-full lg:w-[180px] shrink-0">
                        <SingleSelect v-model="statusFilter" :options="statusOptions"
                                      :placeholder="t('all_statuses')" />
                    </div>
                </div>
            </div>

            <div v-if="activeFilters.length" class="mt-2.5 flex flex-wrap items-center gap-2">
                <span class="text-[12px] font-semibold text-sub">{{ t('filters_active') }}</span>

                <button v-for="chip in activeFilters" :key="chip.key" type="button" class="styledesk_chip"
                        @click="dropFilter(chip.key)">
                    {{ chip.label }}
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                    </svg>
                </button>

                <button type="button" class="text-[12px] font-semibold text-link hover:underline" @click="clearFilters">
                    {{ t('clear') }}
                </button>
            </div>
        </section>

        <!-- =================================================== the drill-in -->
        <!-- Teleported to the body: a panel rendered inside the board would
             be clipped by it. -->
        <Teleport to="body">
            <StaffUtilizationDetail v-if="openStaff"
                                    :member="openStaff"
                                    :url="urls.detail"
                                    :range="range"
                                    :from="from"
                                    :until="until"
                                    :target="target"
                                    :team-average="summary?.average ?? 0"
                                    :currency="currency"
                                    :can-see-revenue="canSeeRevenue"
                                    :labels="labels"
                                    @close="openStaff = null" />

            <!-- The hover card. Fixed to the viewport, following the
                 pointer. -->
            <div v-if="tip" class="styledesk_bubbletip" :style="tipStyle">
                <p class="font-semibold text-head text-[13px]">{{ tip.node.name }}</p>
                <p class="text-[12px] text-sub mt-0.5">{{ tip.node.role }}</p>
                <dl class="mt-1.5 space-y-0.5 text-[12px]">
                    <div class="flex gap-2">
                        <dt class="text-sub">{{ t('detail.utilization') }}</dt>
                        <dd class="ms-auto font-semibold text-head tabular-nums">{{ tip.node.utilization }}%</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-sub">{{ t('detail.booked') }}</dt>
                        <dd class="ms-auto font-medium text-ink tabular-nums">
                            {{ hours(tip.node.booked_minutes) }} / {{ hours(tip.node.available_minutes) }}
                        </dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-sub">{{ t('detail.bookings') }}</dt>
                        <dd class="ms-auto font-medium text-ink tabular-nums">{{ tip.node.bookings }}</dd>
                    </div>
                    <div v-if="canSeeRevenue && tip.node.revenue_minor !== undefined" class="flex gap-2">
                        <dt class="text-sub">{{ t('detail.revenue') }}</dt>
                        <dd class="ms-auto font-medium text-ink tabular-nums">{{ money(tip.node.revenue_minor) }}</dd>
                    </div>
                </dl>
            </div>
        </Teleport>
    </div>
</template>
