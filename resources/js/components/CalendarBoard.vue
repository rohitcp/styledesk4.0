<script setup>
/**
 * The day, drawn against the clock.
 *
 * One column per staff member, one row per quarter hour, and the day's
 * appointments laid on top at the minute they start. The question this
 * answers is the one asked over the counter — who is working, who is booked,
 * what room they are in, and where the gaps are.
 *
 * Everything on screen changes the same thing, so every control refetches one
 * day rather than reloading the page: a receptionist moving a day forward
 * with somebody on hold must not lose their place.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import SingleSelect from './SingleSelect.vue';

const props = defineProps({
    dataUrl: { type: String, default: '' },
    createUrl: { type: String, default: '' },
    date: { type: String, default: '' },
    view: { type: String, default: 'day' },
    interval: { type: Number, default: 15 },
    lockedToOwnStaff: { type: Boolean, default: false },
    locationId: { type: [Number, String], default: null },
    staffId: { type: [Number, String], default: null },
    resourceId: { type: [Number, String], default: null },
    locations: { type: Array, default: () => [] },
    staffOptions: { type: Array, default: () => [] },
    resourceOptions: { type: Array, default: () => [] },
    serviceOptions: { type: Array, default: () => [] },
    serviceId: { type: [Number, String], default: null },
    currencySymbol: { type: String, default: '' },
    datePicker: { type: Object, default: () => ({}) },
    labels: { type: Object, default: () => ({}) },
});

/* How tall a quarter of an hour is. Everything on the timeline is positioned
   from this one number, so the ruler and the cards can never drift apart. */
const SLOT_HEIGHT = 22;

const date = ref(props.date);

/* Day, week or month. The desk lives in the day; the other two answer "how
   full are we" rather than "who is doing what", and they say so by changing
   what a column means rather than by drawing the same picture wider. */
const view = ref(props.view || 'day');

/* --------------------------------------------------------------- filtering

   Four combos rather than four dropdowns: a salon with sixty services and
   twenty staff cannot be filtered from a list somebody has to scroll, and the
   search is the whole point of the control.

   The keys carry a prefix on purpose. MultiSelect keeps the order it is given
   except for keys that look like numbers, which JavaScript reorders
   numerically — so bare ids would list the team by staff id instead of by
   name, and "All staff" would sink to the bottom of its own list. `chosen()`
   takes the prefix back off before anything is asked of the server. */
const ALL = 'all';

const prefixed = (kind, rows, everything) => rows.reduce(
    (options, row) => ({ ...options, [`${kind}:${row.id}`]: row.name }),
    { [ALL]: everything },
);

/** "s:12" as 12, and "all" as nothing at all. */
const chosen = (value) => (! value || value === ALL ? '' : String(value).split(':')[1]);

const ALL_LOCATIONS = 'l:all';

const locationId = ref(props.locationId ? `l:${props.locationId}` : ALL_LOCATIONS);
/* What the reader asked the ruler to be drawn in. The served step is the
   `interval` computed below — the server has the last word on it, and the
   grid follows what actually came back rather than what was requested. */
const askedInterval = ref(props.interval || 15);
const staffId = ref(props.staffId ? `s:${props.staffId}` : ALL);
const resourceId = ref(props.resourceId ? `r:${props.resourceId}` : ALL);
const serviceId = ref(props.serviceId ? `v:${props.serviceId}` : ALL);

const locationChoices = computed(() => props.locations.reduce(
    (options, place) => ({ ...options, [`l:${place.id}`]: place.name }),
    { [ALL_LOCATIONS]: props.labels.filters?.all_locations ?? '' },
));

const staffChoices = computed(() => prefixed('s', props.staffOptions, props.labels.filters?.all_staff ?? ''));
const resourceChoices = computed(() => prefixed('r', props.resourceOptions, props.labels.filters?.all_resources ?? ''));
const serviceChoices = computed(() => prefixed('v', props.serviceOptions, props.labels.filters?.all_services ?? ''));

/** What the four combos come to, as the server asks for them. */
const filters = computed(() => ({
    location_id: locationId.value === ALL_LOCATIONS ? 'all' : chosen(locationId.value),
    staff_id: chosen(staffId.value),
    resource_id: chosen(resourceId.value),
    service_id: chosen(serviceId.value),
}));

const day = ref(null);
const loading = ref(true);
const failed = ref(false);

/* A token per request, so a slower answer to yesterday cannot overwrite a
   faster answer to today — the desk clicks through days quickly. */
let request = 0;

async function load() {
    if (!props.dataUrl) {
        return;
    }

    const token = ++request;

    loading.value = true;
    failed.value = false;

    try {
        const url = new URL(props.dataUrl, window.location.origin);

        url.searchParams.set('date', date.value);
        url.searchParams.set('view', view.value);
        if (view.value === 'day') { url.searchParams.set('interval', askedInterval.value); }
        Object.entries(filters.value).forEach(([key, value]) => {
            if (value) { url.searchParams.set(key, value); }
        });

        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        if (token !== request) { return; }

        if (! response.ok) {
            failed.value = true;

            return;
        }

        day.value = await response.json();
        syncAddressBar();
    } catch (error) {
        if (token === request) { failed.value = true; }
    } finally {
        if (token === request) { loading.value = false; }
    }
}

/* The day in the address bar, so a link to Thursday is a link to Thursday.
   Replaced rather than pushed: stepping through a week should not leave
   fourteen entries in the back button. */
function syncAddressBar() {
    const url = new URL(window.location.href);

    url.searchParams.set('date', date.value);
    view.value === 'day' ? url.searchParams.delete('view') : url.searchParams.set('view', view.value);
    askedInterval.value === 15 ? url.searchParams.delete('interval') : url.searchParams.set('interval', askedInterval.value);
    Object.entries(filters.value).forEach(([key, value]) => {
        value ? url.searchParams.set(key, value) : url.searchParams.delete(key);
    });

    window.history.replaceState({}, '', url);
}

watch([date, view, askedInterval, filters], load);

/* ------------------------------------------------------------- the ruler */

const opens = computed(() => day.value?.opens ?? 8 * 60);
const closes = computed(() => day.value?.closes ?? 20 * 60);
const interval = computed(() => day.value?.interval ?? 15);

const slots = computed(() => {
    const rows = [];

    for (let minute = opens.value; minute < closes.value; minute += interval.value) {
        rows.push(minute);
    }

    return rows;
});

const bodyHeight = computed(() => slots.value.length * SLOT_HEIGHT);

/** Minutes past midnight to pixels down the column. */
function offset(minute) {
    return ((minute - opens.value) / interval.value) * SLOT_HEIGHT;
}

function clock(minute) {
    const hours = Math.floor(minute / 60);
    const mins = String(minute % 60).padStart(2, '0');

    if (! use12Hour.value) {
        return `${String(hours).padStart(2, '0')}:${mins}`;
    }

    const suffix = hours < 12 ? 'AM' : 'PM';
    const twelve = hours % 12 === 0 ? 12 : hours % 12;

    return `${twelve}:${mins} ${suffix}`;
}

/* The app decides this once, on the server, and sends it: a ruler that
   disagreed with the cards beside it would be the same appointment printed
   two ways on one screen. */
const use12Hour = computed(() => day.value?.clock12 !== false);

/** Only the hour marks carry a label. A number every quarter is a wall of digits. */
function isHour(minute) {
    return minute % 60 === 0;
}

/* -------------------------------------------------------------- the columns */

const staff = computed(() => day.value?.staff ?? []);

const bookingsByStaff = computed(() => {
    const map = {};

    (day.value?.bookings ?? []).forEach((booking) => {
        const key = String(booking.staff_id ?? 'unassigned');

        (map[key] ??= []).push(booking);
    });

    return map;
});

/* Appointments nobody is named for. Shown in their own column rather than
   dropped: "any available" is a real booking somebody has to work. */
const unassigned = computed(() => bookingsByStaff.value.unassigned ?? []);

function bookingsFor(member) {
    return packed(bookingsByStaff.value[String(member.id)] ?? []);
}

/**
 * Appointments that overlap, side by side.
 *
 * Without this a week column stacks every therapist's ten o'clock on top of
 * one another and reads as one illegible block. Each run of appointments that
 * actually clash is treated on its own, so two overlapping at noon do not
 * squeeze the one at four into a quarter of the column.
 *
 * The lanes are worked out here rather than on the server because they are a
 * fact about the drawing, not about the booking: the same appointment sits in
 * a different lane depending on what else is on screen beside it.
 */
function packed(bookings) {
    const sorted = [...bookings].sort((a, b) => a.start - b.start || b.end - a.end);
    const laid = [];
    let cluster = [];
    let clusterEnd = -1;

    const flush = () => {
        if (! cluster.length) {
            return;
        }

        const lanes = [];

        cluster.forEach((booking) => {
            let lane = lanes.findIndex((end) => end <= booking.start);

            if (lane === -1) {
                lane = lanes.length;
            }

            lanes[lane] = booking.end;
            booking.lane = lane;
        });

        cluster.forEach((booking) => {
            booking.lanes = lanes.length;
            laid.push(booking);
        });

        cluster = [];
        clusterEnd = -1;
    };

    sorted.forEach((from) => {
        const booking = { ...from };

        /* Clear of everything before it: a new run starts here. */
        if (cluster.length && booking.start >= clusterEnd) {
            flush();
        }

        cluster.push(booking);
        clusterEnd = Math.max(clusterEnd, booking.end);
    });

    flush();

    return laid;
}

/**
 * Where a card sits and how tall it is.
 *
 * Clamped to the timeline at both ends, so an appointment that starts before
 * the business opens still draws — a booking taken out of hours is exactly
 * the one somebody needs to see.
 */
function cardStyle(booking) {
    const start = Math.max(booking.start, opens.value);
    const end = Math.min(booking.end, closes.value);
    const lanes = booking.lanes ?? 1;
    const lane = booking.lane ?? 0;
    const width = 100 / lanes;

    return {
        top: `${offset(start)}px`,
        height: `${Math.max(offset(end) - offset(start), SLOT_HEIGHT)}px`,
        left: `${lane * width}%`,
        width: `${width}%`,
        borderLeftColor: booking.color,
        background: `${booking.color}14`,
    };
}

/**
 * How much room the card has, which decides how much it can say.
 *
 * Measured in pixels rather than in minutes: what fits is a question about
 * height, and an hour is a different number of lines at one row height than
 * at another. Each line is only drawn once there is room for the whole of it
 * — half a sentence clipped by a border is worse than no sentence.
 */
function cardSize(booking) {
    const start = Math.max(booking.start, opens.value);
    const end = Math.min(booking.end, closes.value);
    const height = Math.max(offset(end) - offset(start), SLOT_HEIGHT);
    /* Sharing the column with two others still leaves room for a name; with
       six it does not, and "Sa…" repeated forty times is not information.
       Past that the card says what it can with colour and length alone, and
       the reader who wants the detail opens the day. */
    const wide = (booking.lanes ?? 1) <= 3;

    return {
        wide,
        service: wide && height >= 42,
        badges: wide && height >= 60,
        time: wide && height >= 80,
        extras: wide && height >= 100,
    };
}

/**
 * The parts of a column nobody is working.
 *
 * Drawn as grey bands over the column rather than by leaving the background
 * blank: an empty slot means "free to book", and a slot outside somebody's
 * shift is not free — it is the wrong thing to click.
 */
function offDuty(member) {
    const windows = (member.windows ?? []).slice().sort((a, b) => a.opens - b.opens);
    const bands = [];
    let cursor = opens.value;

    windows.forEach((window) => {
        if (window.opens > cursor) {
            bands.push({ from: cursor, to: Math.min(window.opens, closes.value) });
        }

        cursor = Math.max(cursor, window.closes);
    });

    if (cursor < closes.value) {
        bands.push({ from: cursor, to: closes.value });
    }

    return bands
        .filter((band) => band.to > band.from)
        .map((band) => ({
            key: `${member.id}-${band.from}`,
            style: { top: `${offset(band.from)}px`, height: `${offset(band.to) - offset(band.from)}px` },
        }));
}

function isWorking(member, minute) {
    return (member.windows ?? []).some((window) => minute >= window.opens && minute < window.closes);
}

/* --------------------------------------------------------------- the now line */

const now = ref(null);
let ticker = null;
let markedAt = null;

/**
 * Where the line goes.
 *
 * Taken from the day the server sent and advanced from there, rather than
 * read off the browser. The salon's clock is the one that matters — a desk in
 * Austin checking the London branch is asking what time it is *there* — and
 * the reader's own machine can be in any timezone at all, or simply wrong.
 */
function markNow() {
    if (day.value?.now === null || day.value?.now === undefined) {
        now.value = null;
        markedAt = null;

        return;
    }

    if (markedAt === null) {
        markedAt = { at: Date.now(), minute: day.value.now };
    }

    const elapsed = Math.floor((Date.now() - markedAt.at) / 60_000);

    now.value = markedAt.minute + elapsed;
}

/* ------------------------------------------------------------------ acting */

/**
 * An empty slot is an offer to book it.
 *
 * The booking screen is opened with everything the click already decided —
 * where, when and with whom — so the desk only has to name the client and the
 * service. Nothing is written here: this is a way in, not a booking.
 */
function bookAt(member, minute) {
    if (! isWorking(member, minute)) {
        return;
    }

    const url = new URL(props.createUrl, window.location.origin);

    url.searchParams.set('date', date.value);
    url.searchParams.set('starts_at', `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`);
    if (member.id) { url.searchParams.set('staff_id', member.id); }
    if (filters.value.location_id) { url.searchParams.set('location_id', filters.value.location_id); }

    window.location.href = url.toString();
}

/* ------------------------------------------------------- the hover card

   What a card cannot fit, without opening anything. A receptionist scanning a
   full day needs "who is this and have they paid" answered in the time it
   takes to move the mouse, and a drawer for that is three clicks too many.

   Only where there is a mouse to hover with: on a touch screen the browser
   fakes hover on the first tap, so the panel would appear over the very card
   the reader is trying to open. */
const preview = ref(null);
const previewAt = ref({ x: 0, y: 0 });
let previewTimer = null;

const canHover = typeof window !== 'undefined'
    && window.matchMedia?.('(hover: hover) and (pointer: fine)').matches;

function previewOn(event, booking) {
    if (! canHover) {
        return;
    }

    place(event);

    /* A short wait, so running the mouse across a full column does not flash
       a dozen panels on the way past. */
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(() => { preview.value = booking; }, 220);
}

function previewOff() {
    window.clearTimeout(previewTimer);
    preview.value = null;
}

/**
 * Beside the card, and never off the screen.
 *
 * Measured against the viewport rather than the page, because the panel is
 * fixed: a preview that opened below the fold would be a preview of nothing.
 */
function place(event) {
    const target = event.currentTarget?.getBoundingClientRect?.();

    if (! target) {
        return;
    }

    const width = 264;
    const height = 216;
    const gap = 10;
    const right = target.right + gap;

    previewAt.value = {
        x: right + width > window.innerWidth ? Math.max(gap, target.left - width - gap) : right,
        y: Math.min(Math.max(gap, target.top), Math.max(gap, window.innerHeight - height - gap)),
    };
}

/**
 * A booking opens beside the calendar, not instead of it.
 *
 * Announced rather than rendered here: the drawer is the page's, and one
 * renderer draws the same appointment for the client profile, the leads
 * listing, Sales and this.
 */
function openBooking(booking) {
    document.dispatchEvent(new CustomEvent('styledesk:calendar-booking', {
        detail: { url: booking.drawer_url },
    }));
}

/**
 * Back and forward, by whatever the view is looking at.
 *
 * The server already worked out where the previous and next one start — it
 * knows which Monday a week begins on and what "a month before the 31st"
 * means — so the arrows read that rather than doing calendar arithmetic here
 * and getting the end of March wrong.
 */
function step(direction) {
    const to = direction < 0 ? day.value?.previous : day.value?.next;

    if (to) {
        date.value = to;
    }
}

/** Switching view keeps the day the reader is on; the server snaps it. */
function showView(next) {
    view.value = next;
}

/** A day named in the week or the month opens as the day. */
function openDay(on) {
    date.value = on;
    view.value = 'day';
}

const weekDays = computed(() => day.value?.days ?? []);

/**
 * Whether this row prints its time.
 *
 * Only when it differs from the row above. Six appointments all at nine
 * o'clock is one fact, and printing it six times is six times the ink for it
 * — the column reads as a schedule rather than as a list of times.
 */
function showsTime(bookings, index) {
    return index === 0 || bookings[index - 1].starts_label !== bookings[index].starts_label;
}

function goToday() {
    date.value = day.value?.today ?? new Date().toISOString().slice(0, 10);
}

function money(minor) {
    return props.currencySymbol + (minor / 100).toFixed(2);
}

const summary = computed(() => day.value?.summary ?? null);

/* ------------------------------------------------------------ the picker

   The design system's, driven from here rather than by the page's own upgrade
   pass: this markup is rendered by Vue and does not exist when
   `SD.datePickerAll` sweeps the document. Two-way — the picker writes the day,
   and Previous/Next/Today write it back, so the field never disagrees with the
   calendar under it. */
const dateField = ref(null);
let picker = null;

function mountPicker() {
    if (! dateField.value || ! window.SD?.datePicker) {
        return;
    }

    dateField.value.querySelector('[data-dp-input]')?.setAttribute('data-value', date.value);

    picker = window.SD.datePicker(dateField.value, { ...props.datePicker, value: date.value });

    picker?.input.addEventListener('change', () => {
        const chosenDay = picker.value();

        /* Cleared is not a day. The header always shows one, so an empty
           picker simply leaves the calendar where it was. */
        if (chosenDay) {
            date.value = chosenDay;
        } else {
            picker.set(date.value);
        }
    });
}

watch(date, (day) => {
    if (picker && picker.value() !== day) {
        picker.set(day);
    }
});

onMounted(() => {
    mountPicker();
    load();
    markNow();
    /* Once a minute. The line is a glance, not a stopwatch, and a tick a
       second would repaint the whole day for nothing. */
    ticker = window.setInterval(markNow, 60_000);
});

watch(day, () => {
    markedAt = null;
    markNow();
});

onBeforeUnmount(() => {
    if (ticker) { window.clearInterval(ticker); }
    window.clearTimeout(previewTimer);
});
</script>

<template>
    <div>
        <!-- The header: which day, which view, and whose. -->
        <header class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-[22px] sm:text-[24px] font-bold text-head tracking-tight">{{ labels.title }}</h1>
                <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ labels.subtitle }}</p>
            </div>

            <a :href="createUrl"
               class="shrink-0 h-9 px-4 inline-flex items-center rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                {{ labels.new_booking }}
            </a>
        </header>

        <div class="mt-4 flex flex-wrap items-end gap-2.5">
            <!-- Previous, the date itself, next. The date is the picker: a
                 separate calendar button beside a date nobody can click is a
                 control people hunt for. -->
            <div class="flex items-center gap-1">
                <button type="button" class="sd-iconbtn grid place-items-center" :aria-label="labels.nav?.previous"
                        @click="step(-1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15 5l-7 7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <!-- The app's own picker, not the browser's. The native
                     control renders differently in every browser and ignores
                     the field styling around it, which on a header full of
                     app-styled controls is the one that looks borrowed. -->
                <div ref="dateField" class="relative w-[168px]">
                    <input type="text" readonly data-dp-input
                           class="sd-input is-picker has-suffix !h-9 text-[13px] font-semibold"
                           :aria-label="labels.nav?.pick">

                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-faint pointer-events-none">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3 9h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </span>

                    <div class="sd-cal" data-dp-cal hidden></div>
                </div>

                <button type="button" class="sd-iconbtn grid place-items-center" :aria-label="labels.nav?.next"
                        @click="step(1)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <button type="button" class="styledesk_action ml-1" @click="goToday">{{ labels.nav?.today }}</button>
            </div>

            <!-- Which day, in words. The picker says 09/10/2026, which is a
                 value; this is the answer to "what am I looking at" and the
                 thing somebody reads out over the phone. -->
            <p v-if="day?.label" class="text-[13.5px] font-semibold text-head whitespace-nowrap">
                {{ day.label }}
            </p>

            <!-- How far the reader is looking. Three answers to one
                 question, so a segmented control rather than three buttons
                 that could all be off at once. -->
            <div class="inline-flex rounded-lg border border-line overflow-hidden">
                <button v-for="(option, index) in ['day', 'week', 'month']" :key="option" type="button"
                        class="h-9 px-3 inline-flex items-center text-[12.5px] font-semibold transition-colors"
                        :class="[
                            index > 0 ? 'border-l border-line' : '',
                            view === option ? 'bg-brand text-white' : 'text-ink hover:bg-hover',
                        ]"
                        :aria-pressed="view === option"
                        @click="showView(option)">
                    {{ labels.views?.[option] }}
                </button>
            </div>

            <!-- Searchable, not scrollable: a salon with sixty services and
                 twenty staff cannot be filtered from a list somebody has to
                 read down. Compact and unlabelled, because each one already
                 says what it is when nothing is chosen — "All staff" needs no
                 caption above it saying Staff, and four captions on a row of
                 filters make a header look like a form to fill in. The label
                 stays on the control for anybody reading it aloud. -->
            <div class="sd-compact ml-auto flex flex-wrap items-center gap-2">
                <!-- How finely the ruler is drawn. Only the day has one. -->
                <div v-if="view === 'day'" class="inline-flex rounded-lg border border-line overflow-hidden">
                    <button v-for="(step, index) in [15, 30]" :key="step" type="button"
                            class="h-9 px-2.5 inline-flex items-center text-[12px] font-medium transition-colors"
                            :class="[
                                index > 0 ? 'border-l border-line' : '',
                                askedInterval === step ? 'bg-brand text-white' : 'text-ink hover:bg-hover',
                            ]"
                            :aria-pressed="askedInterval === step"
                            :aria-label="labels.filters?.interval"
                            @click="askedInterval = step">
                        {{ (labels.filters?.minutes ?? ':count').replace(':count', step) }}
                    </button>
                </div>

                <div v-if="locations.length > 1" class="w-[150px]">
                    <SingleSelect v-model="locationId" :options="locationChoices"
                                  :placeholder="labels.filters?.location"
                                  :search-placeholder="labels.filters?.search"
                                  :aria-label="labels.filters?.location" />
                </div>

                <div v-if="! lockedToOwnStaff" class="w-[150px]">
                    <SingleSelect v-model="staffId" :options="staffChoices"
                                  :placeholder="labels.filters?.all_staff"
                                  :search-placeholder="labels.filters?.search"
                                  :aria-label="labels.filters?.staff" />
                </div>

                <div v-if="resourceOptions.length" class="w-[150px]">
                    <SingleSelect v-model="resourceId" :options="resourceChoices"
                                  :placeholder="labels.filters?.all_resources"
                                  :search-placeholder="labels.filters?.search"
                                  :aria-label="labels.filters?.resource" />
                </div>

                <div v-if="serviceOptions.length" class="w-[150px]">
                    <SingleSelect v-model="serviceId" :options="serviceChoices"
                                  :placeholder="labels.filters?.all_services"
                                  :search-placeholder="labels.filters?.search"
                                  :aria-label="labels.filters?.service" />
                </div>
            </div>
        </div>

        <!-- What the day amounts to, before anybody reads a single card. -->
        <dl v-if="summary" class="mt-4 grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 gap-2">
            <div v-for="figure in [
                     { key: 'total', value: summary.total },
                     { key: 'arrived', value: summary.arrived },
                     { key: 'completed', value: summary.completed },
                     { key: 'cancelled', value: summary.cancelled },
                     { key: 'no_show', value: summary.no_show },
                     { key: 'owing', value: summary.owing },
                     { key: 'revenue', value: money(summary.revenue_minor) },
                 ]" :key="figure.key"
                 class="rounded-lg border border-line bg-white px-3 py-2.5">
                <dt class="text-[11.5px] text-sub">{{ labels.summary?.[figure.key] }}</dt>
                <dd class="text-[17px] font-bold text-head tabular-nums leading-tight mt-0.5">{{ figure.value }}</dd>
            </div>
        </dl>

        <p v-if="day?.closed" class="mt-3 text-[13px] text-sub">{{ labels.closed }}</p>

        <p v-if="failed" class="mt-4 sd-alert sd-alert--danger text-[12.5px]" role="alert">{{ labels.loading }}</p>

        <!-- The timeline. Scrolls sideways on a tablet rather than squeezing
             eight columns into a phone; the agenda below takes over there. -->
        <div v-if="view === 'day' && staff.length" class="mt-4 hidden md:block rounded-xl border border-line bg-white overflow-hidden">
            <!-- The timeline scrolls inside its own frame rather than making
                 the page long: the date, the filters and the day's figures
                 have to stay put while the desk runs down to four o'clock. -->
            <div class="overflow-auto styledesk_scroll max-h-[calc(100vh-260px)] min-h-[420px]">
                <div class="min-w-[640px]">
                    <!-- Column heads, stuck to the top so a desk scrolled to
                         four o'clock still knows whose column is whose. -->
                    <div class="flex sticky top-0 z-20 bg-white border-b border-line">
                        <div class="w-[76px] shrink-0 border-r border-line"></div>

                        <div v-for="member in staff" :key="member.id"
                             class="flex-1 min-w-[150px] px-3 py-2.5 border-r border-line last:border-r-0">
                            <div class="flex items-center gap-2">
                                <img v-if="member.avatar" :src="member.avatar" alt=""
                                     class="w-7 h-7 rounded-full object-cover shrink-0">
                                <span v-else
                                      class="w-7 h-7 rounded-full grid place-items-center bg-brand/10 text-[11px] font-bold text-brand shrink-0">
                                    {{ member.initials }}
                                </span>

                                <span class="min-w-0">
                                    <span class="block text-[13px] font-semibold text-head truncate">{{ member.name }}</span>
                                    <span class="block text-[11px] text-sub truncate">{{ member.hours }}</span>
                                </span>
                            </div>

                            <p v-if="member.break_minutes > 0" class="text-[11px] text-faint mt-1">
                                {{ (labels.break ?? '').replace(':minutes', member.break_minutes) }}
                            </p>
                        </div>

                        <div v-if="unassigned.length" class="flex-1 min-w-[150px] px-3 py-2.5 border-l border-line">
                            <span class="block text-[13px] font-semibold text-head truncate">{{ labels.filters?.all_staff }}</span>
                        </div>
                    </div>

                    <div class="flex relative pt-2">
                        <!-- The ruler. -->
                        <div class="w-[76px] shrink-0 border-r border-line" :style="{ height: `${bodyHeight}px` }">
                            <div v-for="minute in slots" :key="minute"
                                 class="relative border-b border-line/60"
                                 :style="{ height: `${SLOT_HEIGHT}px` }">
                                <span v-if="isHour(minute)"
                                      class="absolute -top-[7px] right-2 text-[11px] font-medium text-sub bg-white px-1">
                                    {{ clock(minute) }}
                                </span>
                            </div>
                        </div>

                        <div v-for="member in staff" :key="member.id"
                             class="flex-1 min-w-[150px] relative border-r border-line last:border-r-0"
                             :style="{ height: `${bodyHeight}px` }">
                            <!-- Empty slots are the offer to book. The whole
                                 row is the target: a desk with a client in
                                 front of them is not aiming at a hairline. -->
                            <button v-for="minute in slots" :key="minute" type="button"
                                    class="block w-full border-b border-line/60 transition-colors"
                                    :class="isWorking(member, minute) ? 'hover:bg-brand/5' : 'cursor-default'"
                                    :style="{ height: `${SLOT_HEIGHT}px` }"
                                    :disabled="! isWorking(member, minute)"
                                    :aria-label="(labels.free_slot ?? '').replace(':staff', member.name).replace(':time', clock(minute))"
                                    @click="bookAt(member, minute)"></button>

                            <!-- Not rostered: grey, and not clickable. -->
                            <div v-for="band in offDuty(member)" :key="band.key"
                                 class="absolute inset-x-0 bg-hover/70 pointer-events-none"
                                 :style="band.style"></div>

                            <button v-for="booking in bookingsFor(member)" :key="booking.id" type="button"
                                    class="absolute text-left rounded-md border-l-[3px] border border-line/70 px-1.5 py-1 overflow-hidden hover:shadow-sm transition-shadow"
                                    :style="cardStyle(booking)"
                                    @click="openBooking(booking)"
                                    @mouseenter="previewOn($event, booking)"
                                    @mouseleave="previewOff"
                                    @focus="previewOn($event, booking)"
                                    @blur="previewOff">
                                <span v-if="cardSize(booking).wide"
                                      class="flex items-center gap-1 text-[11.5px] font-semibold text-head leading-tight">
                                    <span class="truncate">{{ booking.client }}</span>

                                    <!-- Something was written about this
                                         appointment. The desk needs to know
                                         before the client is in front of
                                         them, and the note itself is one
                                         hover away. -->
                                    <svg v-if="booking.has_note" width="10" height="10" viewBox="0 0 24 24" fill="none"
                                         class="shrink-0 text-sub" :aria-label="labels.card?.note" role="img">
                                        <path d="M5 4h11l3 3v13H5z" stroke="currentColor" stroke-width="2"/>
                                        <path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>

                                <span v-if="cardSize(booking).service"
                                      class="block text-[11px] text-ink truncate leading-tight">
                                    {{ booking.services }}
                                </span>

                                <span v-if="cardSize(booking).time"
                                      class="block text-[10.5px] text-sub truncate leading-tight">
                                    {{ booking.starts_label }} – {{ booking.ends_label }}
                                </span>

                                <span v-if="cardSize(booking).extras && booking.resource"
                                      class="block text-[10.5px] text-sub truncate leading-tight">
                                    {{ booking.resource }}
                                </span>

                                <!-- Status, payment and membership as words,
                                     never as colour alone: the card's colour
                                     is the service, and a reader who cannot
                                     tell two greens apart still has to know
                                     who has arrived and who owes money. -->
                                <span v-if="cardSize(booking).badges" class="flex flex-wrap items-center gap-1 mt-0.5">
                                    <span class="styledesk_badge" :class="booking.status_class">{{ booking.status_label }}</span>

                                    <span v-if="cardSize(booking).time" class="styledesk_badge" :class="booking.payment_class">
                                        {{ booking.payment_label }}
                                    </span>
                                </span>

                                <!-- The one dot on the card. A block too
                                     short for a badge still has to show that
                                     somebody has arrived or owes money, and a
                                     colour on its own would say neither. -->
                                <span v-else class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full"
                                      :class="booking.paid ? 'bg-brand' : 'bg-danger'"
                                      :title="`${booking.status_label} · ${booking.payment_label}`"></span>

                                <span v-if="booking.membership && cardSize(booking).extras"
                                      class="block text-[10.5px] font-semibold text-brand truncate leading-tight mt-0.5">
                                    {{ booking.membership }}
                                </span>
                            </button>
                        </div>

                        <div v-if="unassigned.length" class="flex-1 min-w-[150px] relative border-l border-line"
                             :style="{ height: `${bodyHeight}px` }">
                            <div v-for="minute in slots" :key="minute"
                                 class="border-b border-line/60" :style="{ height: `${SLOT_HEIGHT}px` }"></div>

                            <button v-for="booking in unassigned" :key="booking.id" type="button"
                                    class="absolute text-left rounded-md border-l-[3px] border border-line/70 px-1.5 py-1 overflow-hidden"
                                    :style="cardStyle(booking)"
                                    @click="openBooking(booking)"
                                    @mouseenter="previewOn($event, booking)"
                                    @mouseleave="previewOff"
                                    @focus="previewOn($event, booking)"
                                    @blur="previewOff">
                                <span class="block text-[11.5px] font-semibold text-head truncate">{{ booking.client }}</span>
                                <span class="block text-[11px] text-ink truncate">{{ booking.services }}</span>
                            </button>
                        </div>

                        <!-- Today only. A "now" line across next Tuesday is
                             pointing at nothing. -->
                        <div v-if="now !== null && now >= opens && now <= closes"
                             class="absolute inset-x-0 z-10 pointer-events-none flex items-center"
                             :style="{ top: `${offset(now)}px` }">
                            <span class="w-[76px] shrink-0 text-right pr-2 text-[10.5px] font-bold text-danger">
                                {{ clock(now) }}
                            </span>
                            <span class="flex-1 h-px bg-danger"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p v-else-if="view === 'day' && ! loading" class="mt-4 text-[13px] text-sub">{{ labels.no_staff }}</p>

        <!-- The phone reads a list. Eight columns on a handset is eight
             columns nobody can tap. -->
        <div v-if="view === 'day'" class="mt-4 md:hidden">
            <ul v-if="day?.bookings?.length" class="space-y-2">
                <li v-for="booking in day.bookings" :key="booking.id">
                    <button type="button"
                            class="w-full text-left rounded-lg border border-line border-l-[3px] bg-white px-3 py-2.5"
                            :style="{ borderLeftColor: booking.color }"
                            @click="openBooking(booking)">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="min-w-0 flex items-center gap-1 text-[13px] font-semibold text-head">
                                <span class="truncate">{{ booking.client }}</span>

                                <svg v-if="booking.has_note" width="11" height="11" viewBox="0 0 24 24" fill="none"
                                     class="shrink-0 text-sub" :aria-label="labels.card?.note" role="img">
                                    <path d="M5 4h11l3 3v13H5z" stroke="currentColor" stroke-width="2"/>
                                    <path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="text-[12px] text-sub shrink-0 tabular-nums">{{ booking.starts_label }}</span>
                        </span>

                        <span class="block text-[12.5px] text-ink truncate mt-0.5">{{ booking.services }}</span>
                        <span class="block text-[12px] text-sub truncate">{{ booking.staff }}</span>

                        <span class="flex flex-wrap items-center gap-1 mt-1">
                            <span class="styledesk_badge" :class="booking.status_class">{{ booking.status_label }}</span>
                            <span class="styledesk_badge" :class="booking.payment_class">{{ booking.payment_label }}</span>
                            <span v-if="booking.membership" class="text-[11px] font-semibold text-brand">{{ booking.membership }}</span>
                        </span>
                    </button>
                </li>
            </ul>

            <p v-else-if="! loading" class="text-[13px] text-sub">{{ labels.empty }}</p>
        </div>

        <!-- ================================================== the week ====

             An agenda, not a timeline. One column per day and one row per
             appointment: a spa with ten therapists takes forty bookings a
             day, and forty overlapping blocks in a column two inches wide is
             a smear rather than a schedule. The week answers "how busy is
             Thursday" and hands the reader to the day view for "at what
             time" — which is the view that can actually draw it. -->
        <div v-if="view === 'week'" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <section v-for="column in weekDays" :key="column.date"
                     class="rounded-xl border bg-white overflow-hidden flex flex-col"
                     :class="column.is_today ? 'border-brand' : 'border-line'">
                <!-- The head is the way into the day. A week is a place to
                     decide which day to look at. -->
                <button type="button"
                        class="flex items-baseline justify-between gap-2 px-3 py-2 border-b transition-colors hover:bg-hover"
                        :class="column.is_today ? 'border-brand/30 bg-brand/5' : 'border-line'"
                        @click="openDay(column.date)">
                    <span class="min-w-0">
                        <span class="block text-[11.5px] text-sub leading-tight">{{ column.weekday }}</span>
                        <span class="block text-[17px] font-bold leading-tight"
                              :class="column.is_today ? 'text-brand' : 'text-head'">{{ column.number }}</span>
                    </span>

                    <span v-if="column.bookings.length"
                          class="shrink-0 text-[11.5px] font-semibold text-sub tabular-nums">
                        {{ column.bookings.length }}
                    </span>
                </button>

                <ul v-if="column.bookings.length"
                    class="flex-1 max-h-[52vh] overflow-y-auto styledesk_scroll divide-y divide-line/70">
                    <li v-for="(booking, index) in column.bookings" :key="booking.id">
                        <button type="button"
                                class="w-full text-left px-2.5 py-1.5 border-l-[3px] hover:bg-hover transition-colors"
                                :style="{ borderLeftColor: booking.color }"
                                @click="openBooking(booking)"
                                @mouseenter="previewOn($event, booking)"
                                @mouseleave="previewOff"
                                @focus="previewOn($event, booking)"
                                @blur="previewOff">
                            <span class="flex items-baseline gap-1.5">
                                <!-- The hour, said once. Repeating 9:00 down
                                     six rows is six times the ink for one
                                     fact. -->
                                <span class="text-[11px] tabular-nums shrink-0 w-[58px] whitespace-nowrap"
                                      :class="showsTime(column.bookings, index) ? 'text-sub font-medium' : 'text-transparent'">
                                    {{ booking.starts_label }}
                                </span>

                                <span class="min-w-0 flex-1 flex items-center gap-1 text-[12px] font-semibold text-head">
                                    <span class="truncate">{{ booking.client }}</span>

                                    <svg v-if="booking.has_note" width="10" height="10" viewBox="0 0 24 24" fill="none"
                                         class="shrink-0 text-sub" :aria-label="labels.card?.note" role="img">
                                        <path d="M5 4h11l3 3v13H5z" stroke="currentColor" stroke-width="2"/>
                                        <path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>

                                <!-- Status and money as one dot each, with
                                     the words on hover: a column this narrow
                                     has no room for two badges, and a row of
                                     truncated badges says less than nothing. -->
                                <span class="shrink-0 w-1.5 h-1.5 rounded-full"
                                      :class="booking.paid ? 'bg-brand' : 'bg-danger'"
                                      :title="`${booking.status_label} · ${booking.payment_label}`"></span>
                            </span>

                            <span class="flex items-baseline gap-1.5">
                                <span class="shrink-0 w-[58px]"></span>
                                <span class="min-w-0 flex-1 text-[11px] text-sub truncate">{{ booking.services }}</span>
                            </span>

                            <span v-if="booking.membership" class="flex items-baseline gap-1.5">
                                <span class="shrink-0 w-[58px]"></span>
                                <span class="min-w-0 flex-1 text-[10.5px] font-semibold text-brand truncate">
                                    {{ booking.membership }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>

                <p v-else class="flex-1 px-3 py-4 text-[12px] text-sub">
                    {{ column.closed ? labels.closed : labels.empty }}
                </p>
            </section>
        </div>

        <!-- ================================================= the month ====

             No timeline at all. At this range the question is which days are
             busy and which are empty, and thirty vertical rulers answer it
             worse than thirty counts do. -->
        <div v-if="view === 'month'" class="mt-4 rounded-xl border border-line bg-white overflow-hidden">
            <div class="grid grid-cols-7 border-b border-line bg-hover/40">
                <span v-for="weekday in day?.weekdays ?? []" :key="weekday"
                      class="px-2 py-2 text-center text-[11.5px] font-semibold text-sub">
                    {{ weekday }}
                </span>
            </div>

            <div v-for="(week, index) in day?.weeks ?? []" :key="index"
                 class="grid grid-cols-7 border-b border-line last:border-b-0">
                <div v-for="cell in week" :key="cell.date"
                     class="min-h-[104px] border-r border-line last:border-r-0 p-1.5"
                     :class="cell.in_month ? '' : 'bg-hover/40'">
                    <!-- The number is the way into the day. A month cell is a
                         summary, and the reader who wants the detail is asking
                         for that Tuesday. -->
                    <button type="button"
                            class="w-full flex items-baseline justify-between gap-1 rounded px-1 py-0.5 hover:bg-hover transition-colors"
                            @click="openDay(cell.date)">
                        <span class="text-[12.5px] font-semibold"
                              :class="[
                                  cell.is_today ? 'text-white bg-brand rounded-full w-5 h-5 grid place-items-center' : '',
                                  cell.in_month ? 'text-head' : 'text-faint',
                              ]">{{ cell.number }}</span>

                        <span v-if="cell.count" class="text-[11px] text-sub tabular-nums">{{ cell.count }}</span>
                    </button>

                    <ul class="mt-1 space-y-0.5">
                        <li v-for="chip in cell.bookings" :key="chip.id">
                            <button type="button"
                                    class="w-full flex items-center gap-1 text-left rounded px-1 py-0.5 hover:bg-hover transition-colors"
                                    @click="openBooking(chip)"
                                    @mouseenter="previewOn($event, chip)"
                                    @mouseleave="previewOff"
                                    @focus="previewOn($event, chip)"
                                    @blur="previewOff">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: chip.color }"></span>
                                <span class="text-[10.5px] text-sub tabular-nums shrink-0">{{ chip.starts_label }}</span>
                                <span class="text-[11px] text-ink truncate">{{ chip.client }}</span>
                            </button>
                        </li>
                    </ul>

                    <p v-if="cell.more" class="mt-0.5 px-1 text-[10.5px] font-medium text-link">
                        {{ (labels.more ?? '').replace(':count', cell.more) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- What the card could not fit, without opening anything. Fixed and
             untouchable: it follows the card rather than the page, and a
             panel that could be hovered would flicker as the pointer crossed
             into it. -->
        <Teleport to="body">
            <div v-if="preview"
                 class="fixed z-[70] w-[264px] rounded-xl border border-line bg-white shadow-lg p-3 pointer-events-none"
                 :style="{ left: `${previewAt.x}px`, top: `${previewAt.y}px` }"
                 role="tooltip">
                <p class="text-[13.5px] font-semibold text-head truncate">{{ preview.client }}</p>
                <p class="text-[12.5px] text-ink mt-0.5">{{ preview.services }}</p>
                <p class="text-[12px] text-sub tabular-nums">
                    {{ preview.starts_label }} – {{ preview.ends_label }}
                </p>

                <dl class="mt-2 pt-2 border-t border-line space-y-0.5 text-[12px]">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub shrink-0">{{ labels.filters?.staff }}</dt>
                        <dd class="text-head truncate text-right">{{ preview.staff }}</dd>
                    </div>

                    <div v-if="preview.resource" class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub shrink-0">{{ labels.filters?.resource }}</dt>
                        <dd class="text-head truncate text-right">{{ preview.resource }}</dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub shrink-0">{{ labels.preview?.status }}</dt>
                        <dd class="text-right">
                            <span class="styledesk_badge" :class="preview.status_class">{{ preview.status_label }}</span>
                        </dd>
                    </div>

                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub shrink-0">{{ labels.preview?.payment }}</dt>
                        <dd class="text-right">
                            <span class="styledesk_badge" :class="preview.payment_class">{{ preview.payment_label }}</span>
                        </dd>
                    </div>

                    <div v-if="preview.membership" class="flex items-baseline justify-between gap-3">
                        <dt class="text-sub shrink-0">{{ labels.card?.membership }}</dt>
                        <!-- Allowed to wrap. It is the last line and the one
                             worth reading in full: "1 credit re…" is the half
                             of the sentence that says nothing. -->
                        <dd class="text-brand font-semibold text-right">{{ preview.membership }}</dd>
                    </div>
                </dl>

                <!-- A note is the thing a receptionist most needs to see
                     before the client is in front of them. -->
                <p v-if="preview.note" class="mt-2 pt-2 border-t border-line text-[11.5px] text-sub line-clamp-3">
                    {{ preview.note }}
                </p>
            </div>
        </Teleport>
    </div>
</template>
