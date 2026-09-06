<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { scaleLinear } from 'd3-scale';
/* Imported before the plugin on purpose — see the module's own note. */
import { $, moment } from '../daterangepicker-globals';
/* The plugin's stylesheet is imported in app.css, ahead of ours, so the
   StyleDesk rules win — see the note there. */
import 'bootstrap-daterangepicker';
import SingleSelect from './SingleSelect.vue';

/**
 * What every room and chair is doing today.
 *
 * A day drawn along a clock, one row per resource. It is the operational
 * counterpart to the utilization board: that one answers how the week went,
 * this one answers what a receptionist is asking with somebody in front of
 * them — what is free now, when the room they want comes back, and where the
 * next booking goes.
 *
 * The browser decides nothing about availability. The server has already
 * said, minute by minute, what each resource is doing — through the same
 * rules the booking engine refuses a slot with, preparation and cleaning
 * included — and everything here is a way of drawing that answer. A chart
 * that worked availability out for itself would eventually disagree with the
 * screen that takes the booking, and the disagreement would be invisible.
 *
 * D3 scales minutes to pixels; Vue owns the DOM. Letting d3-selection draw as
 * well would put two renderers on one page, and the sticky name column and
 * sticky hour header are HTML anyway — an SVG cannot stick to the left edge
 * of a scroller.
 */
const props = defineProps({
    urls: { type: Object, required: true },
    /** The first day, rendered with the page so it opens filled. */
    day: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    /** The server's today, which is the only clock this screen trusts. */
    today: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const board = ref({ ...props.day });
const date = ref(props.day.date);
const locationId = ref(props.filters.location ?? '');
const categoryId = ref('');
const state = ref('all');
const search = ref('');
const busy = ref(false);

/* Where the row heights and the name column are decided. Both are read by
   the template and by the scale below, so they are constants rather than
   numbers repeated in a stylesheet and a computed. */
const ROW = 46;
const NAME = 224;
/** Below this an hour is too narrow to put a service name in. */
const MIN_PX_PER_MINUTE = 1.5;

// ------------------------------------------------------------- the scale

const scroller = ref(null);
const available = ref(900);
let observer = null;

const span = computed(() => Math.max(60, (board.value.closes_at ?? 1080) - (board.value.opens_at ?? 540)));

/**
 * How many pixels an hour gets.
 *
 * The day is stretched to fill the panel when it fits and scrolled when it
 * does not — a twelve-hour day on a laptop is legible at ninety pixels an
 * hour and unreadable squeezed into six hundred.
 */
const pxPerMinute = computed(() => Math.max(MIN_PX_PER_MINUTE, available.value / span.value));

const chartWidth = computed(() => Math.round(span.value * pxPerMinute.value));

/** Minutes past midnight to pixels along the chart. */
const x = computed(() => scaleLinear()
    .domain([board.value.opens_at ?? 540, board.value.closes_at ?? 1080])
    .range([0, chartWidth.value]));

const at = (minutes) => Math.round(x.value(minutes));

const widthOf = (from, to) => Math.max(1, Math.round(x.value(to) - x.value(from)));

function measure() {
    if (! scroller.value) {
        return;
    }

    available.value = Math.max(320, scroller.value.clientWidth - NAME);
}

// ------------------------------------------------------------ the filters

const locationOptions = computed(() => Object.fromEntries(
    props.locations.map((location) => [location.id, location.name]),
));

const categoryOptions = computed(() => Object.fromEntries(
    props.categories.map((category) => [category.id, category.name]),
));

const states = computed(() => ['all', 'available', 'in-use', 'blocked']
    .map((key) => ({ key, label: t(`states.${key}`, key) })));

/**
 * The rows on screen.
 *
 * Filtered in the browser on purpose: the category, the state and the search
 * are all subsets of the answer the server has already given for this day,
 * and asking again would blank the chart for a round trip. Only the date and
 * the branch are a different question, and only those two go back.
 */
const rows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return (board.value.resources ?? []).filter((row) => {
        if (categoryId.value && String(row.category_id) !== String(categoryId.value)) {
            return false;
        }

        if (state.value !== 'all' && row.status !== state.value) {
            return false;
        }

        if (term.length < 2) {
            return true;
        }

        return [row.name, row.code, row.category, row.location]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(term));
    });
});

/** What is currently narrowing the list, and a way out of each. */
const activeFilters = computed(() => {
    const chips = [];

    if (categoryId.value) {
        chips.push({ key: 'category', label: categoryOptions.value[categoryId.value] });
    }

    if (state.value !== 'all') {
        chips.push({ key: 'state', label: t(`states.${state.value}`) });
    }

    if (search.value.trim().length >= 2) {
        chips.push({ key: 'search', label: `“${search.value.trim()}”` });
    }

    return chips.filter((chip) => chip.label);
});

function dropFilter(key) {
    if (key === 'category') {
        categoryId.value = '';
    }

    if (key === 'state') {
        state.value = 'all';
    }

    if (key === 'search') {
        search.value = '';
    }
}

// --------------------------------------------------------------- the data

/** A different day, or a different branch, is a different question. */
async function reload() {
    busy.value = true;

    const query = new URLSearchParams({ date: date.value });

    if (locationId.value) {
        query.set('location', locationId.value);
    }

    try {
        const response = await fetch(`${props.urls.data}?${query}`, { headers: { Accept: 'application/json' } });

        if (response.ok) {
            board.value = await response.json();
            date.value = board.value.date;
            syncPicker();
        }
    } finally {
        busy.value = false;
        await nextTick();
        measure();
    }
}

watch(locationId, () => reload());

function step(days) {
    date.value = moment(date.value, 'YYYY-MM-DD').add(days, 'days').format('YYYY-MM-DD');
    reload();
}

function goToday() {
    if (date.value === props.today) {
        return;
    }

    date.value = props.today;
    reload();
}

/* The current-time line only moves while the page is open on today, so it is
   re-asked rather than animated: a minute is fine-grained enough for a
   receptionist, and a browser advancing the line from its own clock would put
   it in the wrong place for a salon in another timezone. */
let clock = null;

// ------------------------------------------------------------- the picker

/**
 * One day, from the app's own date control.
 *
 * bootstrap-daterangepicker in single-date mode — the same plugin the
 * utilization board uses, so the two screens' date fields are the same
 * control rather than two that look alike.
 */
const picker = ref(null);

const anchor = () => (props.today ? moment(props.today, 'YYYY-MM-DD') : moment());

function mountPicker() {
    if (! picker.value) {
        return;
    }

    $(picker.value).daterangepicker({
        singleDatePicker: true,
        startDate: moment(date.value, 'YYYY-MM-DD'),
        autoUpdateInput: false,
        opens: 'left',
        locale: {
            format: 'D MMM YYYY',
            applyLabel: t('apply'),
            cancelLabel: t('cancel'),
        },
    }, (start) => {
        date.value = start.format('YYYY-MM-DD');
        reload();
    });

    syncPicker();
}

/** What the field says: "Today" where it is, the date otherwise. */
function syncPicker() {
    if (! picker.value) {
        return;
    }

    const chosen = moment(date.value, 'YYYY-MM-DD');

    picker.value.value = date.value === props.today
        ? `${t('today')} · ${chosen.format('D MMM')}`
        : chosen.format('ddd, D MMM YYYY');

    $(picker.value).data('daterangepicker')?.setStartDate(chosen);
}

const isToday = computed(() => date.value === props.today);

// ------------------------------------------------------------ the panels

/** The resource whose detail card is open, and the booking whose panel is. */
const openResource = ref(null);
const openBooking = ref(null);
const bookingBusy = ref(false);
const bookingFailed = ref(false);

async function showBooking(block) {
    hideTip();
    openResource.value = null;
    openBooking.value = null;
    bookingFailed.value = false;
    bookingBusy.value = true;

    try {
        const response = await fetch(props.urls.booking.replace(':booking', block.booking_id), {
            headers: { Accept: 'application/json' },
        });

        if (! response.ok) {
            bookingFailed.value = true;

            return;
        }

        openBooking.value = await response.json();
    } catch {
        bookingFailed.value = true;
    } finally {
        bookingBusy.value = false;
    }
}

const panelOpen = computed(() => Boolean(openResource.value) || Boolean(openBooking.value) || bookingBusy.value || bookingFailed.value);

function closePanel() {
    openResource.value = null;
    openBooking.value = null;
    bookingBusy.value = false;
    bookingFailed.value = false;
}

/* A panel over the page holds the scroll behind it: a reader scrolling the
   chart while a booking is open is scrolling the wrong thing. */
watch(panelOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

// ------------------------------------------------------------ the tooltip

const tip = ref(null);

function showTip(event, block, row) {
    tip.value = {
        x: event.clientX,
        y: event.clientY,
        title: block.kind === 'booked' ? block.service : t(`legend.${block.kind}`),
        lines: [
            [t('booking.client'), block.client],
            [t('booking.staff'), block.staff],
            [t('booking.time'), `${block.from_label} – ${block.to_label}`],
            [t('booking.resource'), row.name],
            [t('booking.reference'), block.reference],
        ].filter(([, value]) => value),
    };
}

function moveTip(event) {
    if (tip.value) {
        tip.value = { ...tip.value, x: event.clientX, y: event.clientY };
    }
}

const hideTip = () => { tip.value = null; };

/** Kept inside the window: a tooltip half off the right edge is unreadable. */
const tipStyle = computed(() => {
    if (! tip.value) {
        return {};
    }

    const flip = tip.value.x > window.innerWidth - 280;

    return {
        left: `${flip ? tip.value.x - 268 : tip.value.x + 16}px`,
        top: `${Math.min(tip.value.y + 14, window.innerHeight - 190)}px`,
    };
});

// -------------------------------------------------------------- the words

const clockLabel = (minutes) => {
    if (minutes === null || minutes === undefined) {
        return '';
    }

    return moment().startOf('day').add(minutes, 'minutes').format('h:mm A');
};

/**
 * A block's label, cut to the room the block actually has.
 *
 * SVG text does not wrap and does not clip — it simply keeps drawing, over
 * the next appointment and past the end of the chart. So the string is cut
 * here, at roughly the width of a character in this face, and an ellipsis
 * says that it was.
 */
const CHAR = 6.2;

const fits = (block) => widthOf(block.from, block.to) > 54;

function clip(text, block) {
    const room = Math.floor((widthOf(block.from, block.to) - 16) / CHAR);

    if (! text || text.length <= room) {
        return text;
    }

    return room < 4 ? '' : `${text.slice(0, room - 1).trimEnd()}…`;
}

const summary = computed(() => board.value.summary ?? {});

onMounted(() => {
    mountPicker();
    measure();

    observer = new ResizeObserver(() => measure());
    observer.observe(scroller.value);

    /* Once a minute, and only on today: the line is the point of the screen
       for whoever is watching the desk. */
    clock = setInterval(() => {
        if (isToday.value && ! busy.value && ! panelOpen.value) {
            reload();
        }
    }, 60000);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    clearInterval(clock);
    document.body.style.overflow = '';

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
        <!-- One row: which day on the left, what to show on the right. A day
             is stepped through far more often than it is picked, so the
             arrows come first and the picker sits between them. -->
        <div class="sd-compact flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1.5">
                <button type="button" class="styledesk_action styledesk_action--icon"
                        :aria-label="t('previous_day')" @click="step(-1)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <button type="button" class="styledesk_action"
                        :class="isToday ? 'styledesk_rt__today--on' : ''" @click="goToday">
                    {{ t('today') }}
                </button>

                <button type="button" class="styledesk_action styledesk_action--icon"
                        :aria-label="t('next_day')" @click="step(1)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2.2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <div class="w-[190px]">
                <input ref="picker" type="text" readonly class="sd-input !h-9 cursor-pointer"
                       :aria-label="t('period')">
            </div>

            <span v-if="busy" class="text-[12px] text-sub">{{ t('loading') }}</span>

            <!-- What to show. One choice with four answers, so a track
                 rather than four loose chips. -->
            <div v-if="board.is_today" class="sd-subnav sd-subnav--compact sm:ms-auto" role="group"
                 :aria-label="t('states.all')">
                <button v-for="option in states" :key="option.key" type="button" class="sd-subnav__item"
                        :aria-current="state === option.key ? 'page' : null"
                        @click="state = option.key">
                    {{ option.label }}
                </button>
            </div>
        </div>

        <!-- ================================================== the figures -->
        <!-- Four counts, above the chart, because the first question is how
             many rather than which. The chart answers "which" underneath. -->
        <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- On today these are a snapshot of this minute. On any other
                 day they cannot be — "available now" about last Tuesday is
                 not a cautious answer but a wrong one — so the same three
                 cards answer what that day CAN answer instead. -->
            <template v-if="board.is_today">
                <div class="rounded-card border border-line bg-white px-4 py-3">
                    <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.available') }}</p>
                    <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">
                        {{ summary.available ?? 0 }}<span class="text-[15px] font-semibold text-sub"> / {{ summary.total ?? 0 }}</span>
                    </p>
                </div>

                <div class="rounded-card border border-line bg-white px-4 py-3">
                    <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.in_use') }}</p>
                    <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ summary.in_use ?? 0 }}</p>
                </div>

                <div class="rounded-card border border-line bg-white px-4 py-3">
                    <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.blocked') }}</p>
                    <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ summary.blocked ?? 0 }}</p>
                </div>
            </template>

            <template v-else>
                <div class="rounded-card border border-line bg-white px-4 py-3">
                    <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.total') }}</p>
                    <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ summary.total ?? 0 }}</p>
                </div>

                <div class="rounded-card border border-line bg-white px-4 py-3">
                    <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.bookings') }}</p>
                    <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ summary.bookings ?? 0 }}</p>
                </div>

                <div class="rounded-card border border-line bg-white px-4 py-3">
                    <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">{{ t('summary.blocked_rows') }}</p>
                    <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ summary.blocked_rows ?? 0 }}</p>
                </div>
            </template>

            <div class="rounded-card border border-line bg-white px-4 py-3">
                <!-- "Today's utilization" is only today's. -->
                <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.06em]">
                    {{ board.is_today ? t('summary.utilization') : t('summary.day_utilization') }}
                </p>
                <p class="text-[26px] font-extrabold text-head leading-none mt-1.5 tabular-nums">{{ summary.utilization ?? 0 }}%</p>
            </div>
        </div>

        <!-- ================================================== the filters -->
        <div class="mt-4 flex flex-wrap items-start gap-2">
            <div class="relative w-full lg:w-auto lg:flex-1 lg:min-w-[220px]">
                <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.9"/>
                        <path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                    </svg>
                </span>

                <input v-model="search" type="text" autocomplete="off"
                       class="sd-input styledesk_input--prefixed pr-10"
                       :aria-label="t('search')" :placeholder="t('search')">

                <button v-if="search" type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 h-6 w-6 grid place-items-center
                               rounded text-faint hover:text-ink hover:bg-hover transition-colors"
                        :aria-label="t('clear')" @click="search = ''">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-start gap-2 w-full lg:w-auto">
                <div v-if="locations.length > 1" class="w-full lg:w-[180px] shrink-0">
                    <SingleSelect v-model="locationId" :options="locationOptions"
                                  :placeholder="t('all_locations')" />
                </div>

                <div v-if="categories.length > 1" class="w-full lg:w-[190px] shrink-0">
                    <SingleSelect v-model="categoryId" :options="categoryOptions"
                                  :placeholder="t('all_categories')" />
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
        </div>

        <!-- ================================================= the timeline -->
        <div class="mt-4 rounded-card border border-line bg-white overflow-hidden">
            <!-- The key, above the chart rather than below it: a reader
                 meeting a striped band needs it before they meet it. -->
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 px-4 py-2.5 border-b border-line">
                <span v-for="key in ['available', 'booked', 'prep', 'cleanup', 'blocked', 'closed']" :key="key"
                      class="inline-flex items-center gap-1.5 text-[12px] text-sub">
                    <span class="styledesk_rt__swatch" :class="`styledesk_rt__swatch--${key}`" aria-hidden="true"></span>
                    {{ t(`legend.${key}`) }}
                </span>
            </div>

            <p v-if="board.closed_all_day" class="px-4 py-6 text-[13px] text-sub">{{ t('closed_all_day') }}</p>

            <div v-else-if="! rows.length" class="px-4 py-10 text-center">
                <p class="text-[14px] font-semibold text-head">{{ t('empty') }}</p>
                <p class="text-[13px] text-sub mt-1">{{ t('empty_hint') }}</p>
            </div>

            <!-- One scroller for both directions. The name column sticks to
                 its left edge and the hour header to its top, which is why
                 the frame is HTML and only the bars are SVG. -->
            <div v-else ref="scroller" class="styledesk_rt" @scroll="hideTip">
                <div class="styledesk_rt__grid" :style="{ '--chart': `${chartWidth}px`, '--name': `${NAME}px` }">

                    <!-- the hours -->
                    <div class="styledesk_rt__head">
                        <div class="styledesk_rt__corner">{{ t('resource') }}</div>

                        <div class="styledesk_rt__track">
                            <svg :width="chartWidth" height="34" role="presentation">
                                <g v-for="hour in board.hours" :key="hour.minutes">
                                    <line :x1="at(hour.minutes)" :x2="at(hour.minutes)" y1="20" y2="34"
                                          class="styledesk_rt__gridline"/>
                                    <text :x="at(hour.minutes) + 5" y="15" class="styledesk_rt__hour">
                                        {{ hour.label }}
                                    </text>
                                </g>
                            </svg>
                        </div>
                    </div>

                    <!-- one resource -->
                    <div v-for="row in rows" :key="row.id" class="styledesk_rt__row">
                        <button type="button" class="styledesk_rt__name" @click="hideTip(); openResource = row">
                            <span class="styledesk_rt__namelabel">{{ row.name }}</span>
                            <!-- Today: where the room stands this minute.
                                 Any other day: how much of it went, which is
                                 the only thing that day can say. -->
                            <span class="styledesk_rt__namemeta">
                                <template v-if="row.status">
                                    <span class="styledesk_rt__dot" :class="`styledesk_rt__dot--${row.status}`" aria-hidden="true"></span>
                                    {{ t(`states.${row.status}`, row.status) }}
                                </template>
                                <template v-else>{{ row.utilization }}% · {{ row.bookings }}</template>
                                <template v-if="row.capacity > 1"> · {{ row.capacity }}</template>
                            </span>
                        </button>

                        <div class="styledesk_rt__track">
                            <svg :width="chartWidth" :height="ROW" role="presentation">
                                <!-- what the row is, under everything else -->
                                <rect v-for="(band, index) in row.bands" :key="`b${index}`"
                                      :x="at(band.from)" y="0"
                                      :width="widthOf(band.from, band.to)" :height="ROW"
                                      class="styledesk_rt__band" :class="`styledesk_rt__band--${band.status}`"/>

                                <!-- the hour grid, over the bands so it reads
                                     across the whole row -->
                                <line v-for="hour in board.hours" :key="`h${hour.minutes}`"
                                      :x1="at(hour.minutes)" :x2="at(hour.minutes)" y1="0" :y2="ROW"
                                      class="styledesk_rt__gridline"/>

                                <!-- and what is actually in it -->
                                <g v-for="(block, index) in row.blocks" :key="`k${index}`"
                                   class="styledesk_rt__block" :class="`styledesk_rt__block--${block.kind}`"
                                   @mouseenter="showTip($event, block, row)"
                                   @mousemove="moveTip"
                                   @mouseleave="hideTip"
                                   @click="showBooking(block)">
                                    <rect :x="at(block.from) + 1"
                                          :y="3 + block.lane * ((ROW - 6) / row.lanes)"
                                          :width="Math.max(2, widthOf(block.from, block.to) - 2)"
                                          :height="(ROW - 6) / row.lanes - 2"
                                          rx="4"/>

                                    <text v-if="block.kind === 'booked' && fits(block)"
                                          :x="at(block.from) + 8"
                                          :y="3 + block.lane * ((ROW - 6) / row.lanes) + ((ROW - 6) / row.lanes) / 2 + 4"
                                          class="styledesk_rt__blocklabel">
                                        {{ clip(block.service, block) }}
                                    </text>
                                </g>
                            </svg>
                        </div>
                    </div>

                    <!-- now, across everything -->
                    <div v-if="board.now !== null && board.now !== undefined" class="styledesk_rt__now"
                         :style="{ left: `calc(var(--name) + ${at(board.now)}px)` }" aria-hidden="true">
                        <span class="styledesk_rt__nowlabel">{{ board.now_label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================================================== the panel -->
        <!-- Teleported to the body: a panel rendered inside the scroller
             would be clipped by it and would scroll with the chart. -->
        <Teleport to="body">
            <div v-if="panelOpen" class="styledesk_rtpanel" @click.self="closePanel">
                <aside class="styledesk_rtpanel__card" role="dialog" aria-modal="true">
                    <header class="styledesk_rtpanel__head">
                        <p class="text-[15px] font-semibold text-head">
                            {{ openResource ? openResource.name : t('booking.title') }}
                        </p>

                        <button type="button" class="styledesk_drawer__close" :aria-label="t('booking.close')"
                                @click="closePanel">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </header>

                    <div class="styledesk_rtpanel__body">
                        <!-- a resource -->
                        <template v-if="openResource">
                            <p v-if="openResource.status" class="mb-4">
                                <span class="styledesk_badge" :class="`styledesk_rt__badge--${openResource.status}`">
                                    {{ t(`states.${openResource.status}`, openResource.status) }}
                                </span>
                            </p>

                            <dl class="styledesk_rtfacts">
                                <div>
                                    <dt>{{ t('detail.category') }}</dt>
                                    <dd>{{ openResource.category || '—' }}</dd>
                                </div>
                                <div v-if="openResource.location">
                                    <dt>{{ t('detail.location') }}</dt>
                                    <dd>{{ openResource.location }}</dd>
                                </div>
                                <div>
                                    <dt>{{ t('detail.capacity') }}</dt>
                                    <dd>{{ openResource.capacity }}</dd>
                                </div>
                                <!-- What is in the room and when it comes
                                     back are questions about now, so they
                                     are only asked of today. Any other day
                                     answers with what it held. -->
                                <template v-if="board.is_today">
                                    <div>
                                        <dt>{{ t('detail.current_booking') }}</dt>
                                        <dd>
                                            <template v-if="openResource.current">
                                                {{ openResource.current.service }} · {{ openResource.current.from_label }}–{{ openResource.current.to_label }}
                                            </template>
                                            <template v-else>{{ t('detail.none') }}</template>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>{{ t('detail.available_until') }}</dt>
                                        <dd>
                                            {{ openResource.available_until !== null && openResource.available_until !== undefined
                                                ? clockLabel(openResource.available_until)
                                                : (openResource.status === 'available' ? t('detail.rest_of_day') : '—') }}
                                        </dd>
                                    </div>
                                </template>

                                <div v-else>
                                    <dt>{{ t('detail.bookings') }}</dt>
                                    <dd>{{ openResource.bookings }}</dd>
                                </div>

                                <div>
                                    <dt>{{ board.is_today ? t('detail.next_booking') : t('detail.first_booking') }}</dt>
                                    <dd>
                                        <template v-if="openResource.next">
                                            {{ openResource.next.from_label }} — {{ openResource.next.service }}
                                        </template>
                                        <template v-else>{{ t('detail.none') }}</template>
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ board.is_today ? t('detail.utilization') : t('summary.day_utilization') }}</dt>
                                    <dd>{{ openResource.utilization }}%</dd>
                                </div>
                            </dl>

                            <a :href="openResource.url" class="styledesk_action mt-5 w-full">
                                {{ t('detail.view_resource') }}
                            </a>
                        </template>

                        <!-- a booking -->
                        <template v-else>
                            <p v-if="bookingBusy" class="text-[13px] text-sub">{{ t('booking.loading') }}</p>
                            <p v-else-if="bookingFailed" class="text-[13px] text-danger">{{ t('booking.failed') }}</p>

                            <template v-else-if="openBooking">
                                <p class="mb-4 flex items-center gap-2">
                                    <span class="styledesk_badge">{{ openBooking.status_label }}</span>
                                    <span class="text-[12px] font-semibold text-sub tabular-nums">{{ openBooking.reference }}</span>
                                </p>

                                <dl class="styledesk_rtfacts">
                                    <div>
                                        <dt>{{ t('booking.client') }}</dt>
                                        <dd>
                                            <a v-if="openBooking.client_url" :href="openBooking.client_url" class="sd-link">
                                                {{ openBooking.client }}
                                            </a>
                                            <template v-else>{{ openBooking.client }}</template>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>{{ t('booking.time') }}</dt>
                                        <dd>{{ openBooking.date }} · {{ openBooking.time }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ t('booking.staff') }}</dt>
                                        <dd>{{ openBooking.staff }}</dd>
                                    </div>
                                    <div v-if="openBooking.location">
                                        <dt>{{ t('booking.location') }}</dt>
                                        <dd>{{ openBooking.location }}</dd>
                                    </div>
                                </dl>

                                <ul class="mt-4 space-y-1.5">
                                    <li v-for="(line, index) in openBooking.services" :key="index"
                                        class="flex items-baseline justify-between gap-3 text-[13px]">
                                        <span class="font-semibold text-head">{{ line.name }}</span>
                                        <span class="text-sub tabular-nums shrink-0">
                                            {{ line.minutes }}m<template v-if="line.resource"> · {{ line.resource }}</template>
                                        </span>
                                    </li>
                                </ul>

                                <p v-if="openBooking.notes" class="mt-4 text-[13px] text-sub whitespace-pre-line">
                                    {{ openBooking.notes }}
                                </p>

                                <a :href="openBooking.url" class="styledesk_rt__cta mt-5">
                                    {{ t('booking.view') }}
                                </a>
                            </template>
                        </template>
                    </div>
                </aside>
            </div>

            <!-- The hover card. Fixed to the viewport and following the
                 pointer, because the chart it belongs to scrolls. -->
            <div v-if="tip" class="styledesk_rttip" :style="tipStyle">
                <p class="font-semibold text-head text-[13px]">{{ tip.title }}</p>
                <dl class="mt-1.5 space-y-0.5">
                    <div v-for="([label, value], index) in tip.lines" :key="index" class="flex gap-2 text-[12px]">
                        <dt class="text-sub shrink-0">{{ label }}</dt>
                        <dd class="text-ink font-medium ms-auto text-right">{{ value }}</dd>
                    </div>
                </dl>
            </div>
        </Teleport>
    </div>
</template>
