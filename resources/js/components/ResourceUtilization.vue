<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
/* The cluster itself — shared with staff utilization, so the two boards
   cannot come to disagree about what 40% looks like. */
import { useBubbleBoard } from '../bubble-board';
/* Imported before the plugin on purpose — see the module's own note. */
import { $, moment } from '../daterangepicker-globals';
/* The plugin's stylesheet is imported in app.css, ahead of ours, so the
   StyleDesk rules win — see the note there. */
import 'bootstrap-daterangepicker';
import ResourceUtilizationDetail from './ResourceUtilizationDetail.vue';
import SingleSelect from './SingleSelect.vue';

/**
 * How the rooms and chairs are actually being used.
 *
 * One picture that answers a question an owner asks in words: which of my
 * rooms is earning its keep, and which is standing empty? A table of
 * percentages answers it too, eventually — but it has to be read row by row,
 * and the point of this screen is to be understood in a glance and then read
 * properly underneath.
 *
 * The cluster is `useBubbleBoard` — the force layout, the type scale and the
 * text fitting, shared with staff utilization. What stays here is what is
 * about resources: the tints, the filters, the summary and the table.
 */
const props = defineProps({
    urls: { type: Object, required: true },
    /** The first answer, rendered with the page so it opens filled. */
    resources: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    /* The ranges this screen offers, from App\Support\SalesPeriod — the
       app's own date-range vocabulary rather than a list invented here. */
    presets: { type: Object, default: () => ({}) },
    /** The server's today, which is the only clock this screen trusts. */
    today: { type: String, default: '' },
    /** The utilization worth celebrating, from config. */
    target: { type: Number, default: 75 },
    filters: { type: Object, default: () => ({}) },
    currency: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') => path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const rows = ref([...props.resources]);
const range = ref(props.filters.range ?? 'today');
const from = ref(props.filters.from ?? '');
const until = ref(props.filters.until ?? '');
const locationId = ref(props.filters.location ?? '');
const group = ref('all');
const busy = ref(false);
const openResource = ref(null);

/* Only the chips that have something behind them. A filter that can only
   ever return nothing is furniture, and on a salon with three chairs and no
   equipment five of the six chips are exactly that. */
const groups = computed(() => {
    const present = new Set(rows.value.map((row) => row.group));

    return ['all', 'rooms', 'stations', 'beds', 'wellness', 'equipment', 'other']
        .filter((key) => key === 'all' || present.has(key))
        .map((key) => ({ key, label: t(`groups.${key}`) }));
});

const visible = computed(() => (group.value === 'all'
    ? rows.value
    : rows.value.filter((row) => row.group === group.value)));

// ------------------------------------------------------------- the bubbles

/**
 * Nine tints, and a resource keeps its own.
 *
 * Keyed to the id rather than to the position in the list, so changing the
 * date range does not turn the teal room coral — a bubble that changes colour
 * between two clicks reads as a different room.
 */
const tintOf = (row) => (row.available_minutes <= 0 ? 'shut' : `c${row.id % 9}`);

const { board, width, height, painted, hovered, narrow, roomFor } = useBubbleBoard({
    visible,
    tintOf,
    metaOf: (row) => t('used_of_short')
        .replace(':used', hours(row.used_minutes))
        .replace(':available', hours(row.available_minutes)),
});

onMounted(() => {
    mountPicker();

    /* A row opens the same drill-down a bubble does. The grid navigates to a
       row's `url` when it has one; these rows deliberately have none, so the
       click is ours to answer — and what this page is about is the
       utilization detail, not the resource record. */
    const host = document.querySelector('[data-grid]');

    if (host) {
        const attach = () => host.styledeskGrid?.on('rowClick', (event, row) => {
            const found = rows.value.find((item) => item.id === row.getData().id);

            if (found) {
                openResource.value = found;
            }
        });

        host.styledeskGrid ? attach() : setTimeout(attach, 400);
    }
});

onBeforeUnmount(() => {
    /* The plugin appends its panel to <body> and binds document handlers,
       so it has to be told to go rather than left to the component's own
       teardown. */
    if (picker.value) {
        $(picker.value).data('daterangepicker')?.remove();
    }
});

// ------------------------------------------------------------- the filters

/**
 * A date range is a different question of the diary, so it is asked of the
 * server. The category chips are a subset of the answer already on screen,
 * so they are not.
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

            rows.value = json.resources ?? [];
            from.value = json.from;
            until.value = json.until;
        }
    } finally {
        busy.value = false;
    }
}

watch(locationId, () => reload());

/* ------------------------------------------------------- the date picker --

   bootstrap-daterangepicker (daterangepicker.com), which is a jQuery plugin
   over Moment. It is the only jQuery on the page and the only Moment: both
   are imported here rather than globally, so they travel in this component's
   own chunk and no other screen pays for them.

   The presets it shows are the app's own — built from what the server said
   this screen offers, so the picker cannot come to disagree with
   App\Support\SalesPeriod about what "Last 7 days" means. The plugin adds
   its own "Custom Range" entry, which is the fifth. */
const picker = ref(null);

/** Our preset keys against the labels the picker shows for them. */
const presetLabels = computed(() => Object.fromEntries(
    Object.entries(props.presets).filter(([key]) => key !== 'custom'),
));

/**
 * Today, according to the server.
 *
 * Never `moment()`. The picker computes its own presets, and if it does that
 * from the browser's clock while the server answers from its own, the two
 * quietly disagree: by a timezone for a real salon, and by however far the
 * machine is out on a developer's. It showed as an end date a day before the
 * start.
 */
const anchor = () => (props.today ? moment(props.today, 'YYYY-MM-DD') : moment());

/** The span each preset covers, as the plugin wants it: a pair of moments. */
function spanFor(key) {
    return {
        today: [anchor(), anchor()],
        yesterday: [anchor().subtract(1, 'days'), anchor().subtract(1, 'days')],
        last_3: [anchor().subtract(2, 'days'), anchor()],
        last_7: [anchor().subtract(6, 'days'), anchor()],
        week: [anchor().startOf('week'), anchor().endOf('week')],
        month: [anchor().startOf('month'), anchor()],
    }[key] ?? [anchor(), anchor()];
}

/**
 * What the field says.
 *
 * The name of the range where it has one — "Today" reads better than
 * "5 Sep 2026 – 5 Sep 2026", and it is what the reader chose. Only a
 * hand-picked range spells its dates out.
 */
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
        /* The server's today, not the browser's — see anchor(). */
        maxDate: anchor(),
        opens: 'right',
        /* The calendars stay shut until somebody actually wants a custom
           range: the five presets are the answer nine times out of ten, and
           two months of grid in front of them is two months to read past. */
        alwaysShowCalendars: false,
        /* The field is written by hand below, so it can say "Today" rather
           than spelling one day out as a range. */
        autoUpdateInput: false,
        locale: {
            format: 'D MMM YYYY',
            separator: ' – ',
            customRangeLabel: props.presets.custom ?? 'Custom range',
            applyLabel: t('apply'),
            cancelLabel: t('cancel'),
        },
    }, (start, end, label) => {
        /* A named preset goes back to the server as that preset, so the
           range is worked out in one place. Only a hand-picked range travels
           as two dates. */
        const key = Object.keys(presetLabels.value).find((name) => presetLabels.value[name] === label);

        range.value = key ?? 'custom';
        from.value = start.format('YYYY-MM-DD');
        until.value = end.format('YYYY-MM-DD');
        picker.value.value = describe(range.value, start, end);

        reload();
    });

    /* What it says before anybody touches it. */
    picker.value.value = describe(
        range.value,
        moment(from.value, 'YYYY-MM-DD'),
        moment(until.value, 'YYYY-MM-DD'),
    );
}

// -------------------------------------------------------------- the wording

const hours = (minutes) => Math.round(((minutes ?? 0) / 60) * 10) / 10;

const money = (minor) => `${props.currency}${((minor ?? 0) / 100).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}`;

const usedOf = (row) => t('used_of')
    .replace(':used', hours(row.used_minutes))
    .replace(':available', hours(row.available_minutes));

/* One tooltip, following the pointer. A title element per circle would be
   the browser's own tooltip: a second's delay, no styling, and no room for
   four figures. */
const tip = ref(null);

function showTip(node, event) {
    tip.value = { node, x: event.clientX, y: event.clientY };
}

function moveTip(event) {
    if (tip.value) {
        tip.value = { ...tip.value, x: event.clientX, y: event.clientY };
    }
}

const locationOptions = computed(() => Object.fromEntries(
    props.locations.map((location) => [location.id, location.name]),
));

/* ---------------------------------------------------- the table's filters --

   Search and status, above the list, the way the resources listing has them.
   Category and branch are deliberately NOT repeated here: the chips above the
   board already answer the first and the header select the second, and two
   controls doing one job is two that can disagree on screen.

   Both narrow the list only — the picture above keeps showing everything the
   chips left, because it is the summary the table is a detail of. */
const tableSearch = ref('');
const statusFilter = ref('');

/* Spinning only while a request is actually in the air. The grid says when
   it lands — a timer here would be guessing, and would guess wrong on a slow
   one. Same arrangement the resources listing uses. */
const gridBusy = ref(false);

function gridLanded() {
    gridBusy.value = false;
}

/**
 * The filters currently narrowing the list, as chips.
 *
 * Named and removable one at a time, the way the listings do it: a reader who
 * has narrowed four ways and found nothing needs to see which four, and to be
 * able to drop one rather than start again.
 */
const activeFilters = computed(() => {
    const chips = [];

    if (group.value !== 'all') {
        chips.push({ key: 'group', label: t(`groups.${group.value}`) });
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

    return chips;
});

function dropFilter(key) {
    if (key === 'group') {
        group.value = 'all';
    } else if (key === 'location') {
        locationId.value = '';
    } else if (key === 'status') {
        statusFilter.value = '';
    } else {
        tableSearch.value = '';
    }
}

function clearFilters() {
    group.value = 'all';
    locationId.value = '';
    statusFilter.value = '';
    tableSearch.value = '';
}

const statusOptions = computed(() => Object.fromEntries(
    ['busy', 'steady', 'quiet', 'closed'].map((key) => [key, t(`statuses.${key}`)]),
));

/**
 * Point the shared listing grid at the current filters.
 *
 * `setData(url)` is the same handle the app's own filter row uses on every
 * other listing — data-grid.js hangs the Tabulator instance on the element,
 * precisely so the page that owns the filters can drive it. Passing a URL
 * rather than rows keeps the server as the thing that narrows the list,
 * which is what every other listing does and what makes paging work.
 *
 * Debounced, because the search box calls it on every keystroke.
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

        if (group.value !== 'all') {
            query.set('group', group.value);
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

watch([range, locationId, group, statusFilter, tableSearch], () => syncGrid());

/** Sorted for reading rather than for drawing: the busiest first. */
const tableRows = computed(() => {
    const term = tableSearch.value.trim().toLowerCase();

    return [...visible.value]
        .filter((row) => ! statusFilter.value || row.status === statusFilter.value)
        .filter((row) => {
            /* Under two characters is not a search: a single letter matches
               almost everything and makes the list flicker as somebody
               types. */
            if (term.length < 2) {
                return true;
            }

            return [row.name, row.code, row.category, row.location]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(term));
        })
        .sort((a, b) => b.utilization - a.utilization);
});

/**
 * The sentence under the picture.
 *
 * The board answers "which"; this answers "how are we doing" — the average
 * across everything showing, and the two ends of it named. Weighted by the
 * hours each resource was open rather than a mean of the percentages: a
 * chair open two hours a week should not swing the figure as hard as a room
 * open sixty.
 */
const summary = computed(() => {
    const open = visible.value.reduce((total, row) => total + row.available_minutes, 0);
    const used = visible.value.reduce((total, row) => total + row.used_minutes, 0);
    const bookings = visible.value.reduce((total, row) => total + row.bookings, 0);

    if (! visible.value.length) {
        return null;
    }

    const ranked = [...visible.value].sort((a, b) => b.utilization - a.utilization);
    const busiest = ranked[0];
    const quietest = ranked[ranked.length - 1];

    return {
        /* Used over available across everything showing — not a mean of the
           percentages. A chair open two hours a week must not swing the
           figure as hard as a room open sixty. */
        average: open > 0 ? Math.round((used / open) * 100) : 0,
        used: hours(used),
        available: hours(open),
        bookings,
        line: used === 0
            ? t('insight_none')
            : t('insight_quiet')
                .replace(':busy', busiest.name)
                .replace(':busy_percent', busiest.utilization)
                .replace(':quiet', quietest.name),
    };
});

/**
 * What the summary is a summary OF, said above the number.
 *
 * The range in the reader's own words, so a board showing the last week never
 * has "TODAY" over it — and the date span itself when the range was picked by
 * hand, because "Custom range" names nothing.
 */
const summaryPeriod = computed(() => {
    if (range.value === 'custom') {
        return `${from.value} – ${until.value}`;
    }

    return props.presets[range.value] ?? '';
});

/* Named for the category being shown, so the figure and its label cannot
   disagree. Phrased per group in the language files rather than assembled
   from a noun here: "Average Rooms utilization" is not English, and every
   language inflects it differently. */
const summaryLabel = computed(() => t(`average_for.${group.value}`) || t('average_for.all'));

/* -------------------------------------------------------- hitting the mark --

   When the average reaches the target the panel says so, and marks the moment
   with a brief burst of sparks. Two separate things on purpose: the badge is
   the FACT and stays, the sparks are the CELEBRATION and go. A screen that
   sparkled permanently would be noise within a minute, and one that only
   sparkled would lose the news the moment the animation ended.

   Fired on the crossing rather than on the state, so changing a filter to
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

/**
 * Narrow screens get a list, not a squeezed cluster.
 *
 * A dozen circles crammed into 360px is a picture nobody can read and every
 * label truncated to nothing. The same figures as rows, each with its own
 * ring, say the same thing in the shape a phone is good at.
 */

/** The ring's dash offset: the arc drawn is the percentage. */
const ringDash = (percent) => {
    const circumference = 2 * Math.PI * 20;

    return `${(percent / 100) * circumference} ${circumference}`;
};

const perHour = (row) => (row.used_minutes > 0
    ? money(Math.round(row.revenue_minor / (row.used_minutes / 60)))
    : money(0));
</script>

<template>
    <div>
        <!-- One row: what kind of thing on the left, which branch and when
             on the right. They are four answers to the same question — what
             should this board show — and stacking them made the chips read
             as a heading for the controls under them rather than as one more
             filter.

             The right-hand group may shrink, and only takes `ms-auto` from
             `sm` up: below that it wraps to its own full-width line under
             the chips, because a gap pushing a date picker rightwards on a
             phone is a control hanging off the edge. -->
        <div class="sd-compact flex flex-wrap items-center gap-2">
            <!-- What kind of thing to look at. A track rather than loose
                 chips, because these are one choice with several answers. -->
            <div v-if="groups.length > 1" class="sd-subnav sd-subnav--compact" role="group"
                 :aria-label="t('groups.all')">
                <button v-for="option in groups" :key="option.key" type="button" class="sd-subnav__item"
                        :aria-current="group === option.key ? 'page' : null"
                        @click="group = option.key">
                    {{ option.label }}
                </button>
            </div>

            <span v-if="busy" class="text-[12px] text-sub">{{ t('loading') }}</span>

            <!-- One control for the whole question. The presets live in its
                 own sidebar, and the calendars only open for a hand-picked
                 range — so there is nothing on screen when "Today" is the
                 answer, which it usually is.

                 The branch has moved down into the listing's filter row,
                 where the resources page keeps it. What stays up here is the
                 two things that frame the picture: what kind, and when. -->
            <div class="w-full sm:w-auto sm:ms-auto sm:w-[220px]">
                <input ref="picker" type="text" readonly
                       class="sd-input !h-9 cursor-pointer" :aria-label="t('period')">
            </div>
        </div>

        <!-- ================================================ the bubbles -->
        <!-- Two readings of the same filtered set, side by side: which
             resources are being used, and how much of the whole place is.
             Eight columns and four, because the picture needs the room and
             the figure needs almost none.

             The summary comes FIRST in the source and is put back on the
             right from `lg` up. On a phone that ordering is the point: the
             one number an owner wants is above the fold, and the cluster —
             which needs width to be readable at all — follows it. -->
        <div class="mt-4 grid gap-4 lg:grid-cols-12 items-stretch">

        <!-- ------------------------------------------- the overall figure -->
        <aside class="lg:col-span-4 lg:order-last rounded-card border border-line bg-white
                      px-6 py-6 flex flex-col justify-center text-center">
            <template v-if="summary">
                <p class="text-[12px] font-semibold text-sub uppercase tracking-[0.08em]">
                    {{ summaryPeriod }}
                </p>

                <!-- The hero of this column, and deliberately the largest
                     thing on the screen: it answers the one question the
                     board cannot, which is how the place is doing as a
                     whole. -->
                <div class="relative mt-1.5">
                    <p class="text-[84px] xl:text-[102px] font-extrabold text-head leading-[0.9] tracking-tight tabular-nums"
                       :class="targetMet ? 'text-brand' : ''">
                        {{ summary.average }}%
                    </p>

                    <!-- The moment the target is reached, and only the
                         moment: they are gone in a second and a half, and
                         the badge below carries the news afterwards.
                         `pointer-events-none` because nothing here is
                         clickable and a spark over the number must not
                         swallow a click meant for it. -->
                    <span v-if="celebrating" class="styledesk_sparks pointer-events-none" aria-hidden="true">
                        <span v-for="spark in sparks" :key="spark.id" class="styledesk_sparks__one"
                              :style="{ '--angle': `${spark.angle}deg`, animationDelay: spark.delay }"></span>
                    </span>
                </div>

                <p class="text-[14px] font-semibold text-ink mt-2">{{ summaryLabel }}</p>

                <!-- The fact, which stays. A reader who arrives after the
                     sparks have gone still needs to be told. -->
                <p v-if="targetMet" class="mt-2">
                    <span class="styledesk_badge styledesk_badge--active">
                        {{ t('target_met').replace(':target', target) }}
                    </span>
                </p>

                <p class="text-[13px] text-sub mt-3 leading-relaxed">
                    {{ t('used_line').replace(':used', summary.used) }}<br>
                    {{ t('available_line').replace(':available', summary.available) }}
                </p>

                <p class="mt-3">
                    <span class="styledesk_badge styledesk_badge--soon">
                        {{ t('bookings_count').split('|').length > 1
                            ? (summary.bookings === 1
                                ? t('bookings_count').split('|')[1].replace(/^\{1\}/, '').trim()
                                : t('bookings_count').split('|')[2].replace(/^\[2,\*\]/, '').trim()
                                    .replace(':count', summary.bookings))
                            : summary.bookings }}
                    </span>
                </p>

                <!-- Which end of the list is which, in a sentence. -->
                <p class="text-[12.5px] text-sub mt-4 leading-relaxed">{{ summary.line }}</p>
            </template>

            <p v-else class="text-[13px] text-sub">{{ t('empty_state') }}</p>
        </aside>

        <!-- ------------------------------------------------- the bubbles -->
        <!-- Spacious and pale, so the colour in the bubbles is the only
             colour on the screen. The circles never touch the edges. -->
        <div ref="board" class="lg:col-span-8 rounded-card border border-line bg-white overflow-hidden px-6 py-6 sm:px-8"
             @mousemove="moveTip" @mouseleave="tip = null">
            <p v-if="! rows.length" class="text-center py-20">
                <span class="block text-[15px] font-semibold text-head">{{ t('empty_state') }}</span>
                <span class="block text-[13px] text-sub mt-1.5">{{ t('empty_hint') }}</span>
            </p>

            <p v-else-if="! visible.length" class="text-center py-20 text-[13px] text-sub">
                {{ t('no_match') }}
            </p>

            <!-- A phone gets rows with rings. The same figures, in the shape
                 a narrow screen is good at: a dozen circles crammed into
                 360px is a picture nobody can read. -->
            <ul v-else-if="narrow" class="space-y-2">
                <li v-for="row in tableRows" :key="row.id">
                    <button type="button"
                            class="w-full flex items-center gap-3 rounded-card border border-line px-3 py-2.5
                                   text-left hover:border-brand transition-colors"
                            @click="openResource = row">
                        <svg width="46" height="46" viewBox="0 0 46 46" class="shrink-0 -rotate-90" aria-hidden="true">
                            <circle cx="23" cy="23" r="20" fill="none" stroke-width="5"
                                    class="styledesk_ring__track" />
                            <circle cx="23" cy="23" r="20" fill="none" stroke-width="5"
                                    class="styledesk_ring__fill"
                                    :class="`styledesk_bubble--${tintOf(row)}`"
                                    :stroke="'currentColor'"
                                    :stroke-dasharray="ringDash(row.utilization)" />
                        </svg>

                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-bold text-head tabular-nums">
                                {{ row.utilization }}%
                            </span>
                            <span class="block text-[13px] font-semibold text-head truncate">{{ row.name }}</span>
                            <span class="block text-[12px] text-sub truncate">
                                {{ t('used_of_short').replace(':used', hours(row.used_minutes))
                                    .replace(':available', hours(row.available_minutes)) }}
                            </span>
                        </span>
                    </button>
                </li>
            </ul>

            <svg v-else :viewBox="`0 0 ${width} ${height}`" :style="{ height: `${height}px` }"
                 class="w-full block" role="img" :aria-label="t('title')"
                 :class="openResource ? 'styledesk_board--picked' : ''">
                <!-- One group per resource, moved by the simulation. The
                     hovered one is drawn last so it lifts above its
                     neighbours rather than under them — SVG has no
                     z-index, and paint order is the only thing that
                     decides which circle is on top. -->
                <!-- Arrival is a CSS animation on the group itself, and
                     departure is simply removal.
                     Not a <TransitionGroup>: Vue drives its classes with
                     `requestAnimationFrame`, which a browser does not call
                     in a background tab — the state machine stalls, so a
                     bubble filtered out while the tab was in the background
                     kept its `leave-active` class forever and stayed on the
                     board as a ghost overlapping the ones that remained.
                     A fade-out is not worth a circle that never leaves. -->
                <g v-for="node in painted" :key="node.id"
                   class="styledesk_bubble"
                   :class="[
                       `styledesk_bubble--${node.tint}`,
                       openResource?.id === node.id ? 'styledesk_bubble--selected' : '',
                   ]"
                   :transform="`translate(${node.x}, ${node.y})`"
                   role="button" tabindex="0"
                   :aria-label="`${node.name}, ${node.utilization}% ${t('space_used')}`"
                   @click="openResource = node"
                   @keydown.enter="openResource = node"
                   @mouseenter="hovered = node.id; showTip(node, $event)"
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
                              :y="node.at.metaY"
                              :style="{ fontSize: `${node.type.meta}px` }">
                            {{ node.hoursLabel }}
                        </text>

                        <!-- The category, as a pill. Drawn rather than
                             styled: SVG has no rounded background for
                             text, so the pill is a rect and the label
                             sits on it. -->
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

        <!-- Following the pointer, and never under it: a tooltip the cursor
             sits on top of is one the reader keeps moving away from. -->
        <Teleport to="body">
            <div v-if="tip" class="styledesk_bubbletip"
                 :style="{ left: `${tip.x + 14}px`, top: `${tip.y + 14}px` }" role="tooltip">
                <p class="font-semibold text-head">{{ tip.node.name }}</p>
                <p v-if="tip.node.category" class="text-sub">{{ tip.node.category }}</p>

                <!-- The percentage again, in words, because the tooltip may
                     be read without the circle it came from being legible. -->
                <p class="text-[15px] font-bold text-head mt-1.5">
                    {{ tip.node.utilization }}% {{ t('space_used') }}
                </p>

                <dl class="mt-1.5 space-y-0.5">
                    <div v-for="fact in [
                             { term: t('open_for'), value: `${hours(tip.node.open_minutes)} hrs` },
                             { term: t('used_for'), value: `${hours(tip.node.used_minutes)} hrs` },
                             { term: t('empty'), value: `${hours(tip.node.idle_minutes)} hrs` },
                             { term: t('table.bookings'), value: String(tip.node.bookings) },
                             { term: t('table.revenue'), value: money(tip.node.revenue_minor) },
                         ]" :key="fact.term" class="flex justify-between gap-4">
                        <dt>{{ fact.term }}</dt>
                        <dd class="font-semibold text-head tabular-nums">{{ fact.value }}</dd>
                    </div>
                </dl>
            </div>
        </Teleport>

        <!-- ============================================ the list underneath -->
        <!-- Only the filters live here. The list itself is the app's own
             listing grid, rendered by the blade below this island and
             re-pointed by `syncGrid()` whenever any filter moves — so the
             table looks and behaves like every other listing in StyleDesk
             rather than like one built for this screen. -->
        <section class="mt-6">
            <!-- The listing's filter row, in the shape the resources page
                 uses it: a search that narrows as you type, the combos beside
                 it, and the chips underneath saying what is currently
                 narrowing the list. -->
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

                    <!-- Spinning only while a request is in the air; the grid
                         says when it lands. -->
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

                    <div class="w-full lg:w-[170px] shrink-0">
                        <SingleSelect v-model="statusFilter" :options="statusOptions"
                                      :placeholder="t('all_statuses')" />
                    </div>
                </div>
            </div>

            <!-- What is currently narrowing the list, and a way out of each.
                 A reader who has filtered four ways and found nothing needs
                 to see which four. -->
            <div v-if="activeFilters.length" class="mt-2.5 flex flex-wrap items-center gap-2">
                <span class="text-[12px] font-semibold text-sub">{{ t('filters_active') }}</span>

                <span class="flex flex-wrap items-center gap-1.5">
                    <button v-for="chip in activeFilters" :key="chip.key" type="button"
                            class="styledesk_action styledesk_action--sm"
                            @click="dropFilter(chip.key)">
                        {{ chip.label }}
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
                        </svg>
                    </button>
                </span>

                <button type="button" class="styledesk_action styledesk_action--sm" @click="clearFilters">
                    {{ t('clear_all') }}
                </button>
            </div>
        </section>

        <ResourceUtilizationDetail v-if="openResource" :resource="openResource" :urls="urls"
                                   :filters="{ range, from, until, location: locationId }"
                                   :currency="currency" :labels="labels" @close="openResource = null" />
    </div>
</template>
