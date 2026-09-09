<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { confirmAction } from '../confirm';
import MultiSelect from './MultiSelect.vue';
import TimePicker from './TimePicker.vue';

/**
 * The Assign Schedule screen.
 *
 * A day at a time for the chosen period, opened already filled in — from what
 * is already on the rota where there is something, and from the business's
 * hours and the shift rule's periods where there is not. So the ordinary case
 * is a confirmation rather than a form.
 *
 * Changing a day here changes only this person's schedule; the rule behind it
 * is never touched, which is the whole reason a rule and a schedule are
 * separate records.
 *
 * An island around an ordinary form: the rows post as days[date][periods][i]
 * and the server writes them, so there is no JSON endpoint and no second copy
 * of the validation. Messages come back keyed by date and sit against their
 * own day.
 */
const props = defineProps({
    action: { type: String, required: true },
    csrf: { type: String, required: true },
    /** The page this was opened from: where Back, Close, Cancel and a
     *  finished save all lead. */
    backUrl: { type: String, required: true },
    staffName: { type: String, default: '' },
    from: { type: String, required: true },
    until: { type: String, required: true },
    periodLabel: { type: String, default: '' },
    /** The month being planned, where the period is exactly one. */
    monthLabel: { type: String, default: null },
    durationLabel: { type: String, default: '' },
    days: { type: Array, default: () => [] },
    /**
     * The month cut into its weeks, each with the dates it holds.
     *
     * A month is thirty-one rows of time fields, and a page that long is one
     * nobody reads to the bottom. Grouped, it is six sections with an hour
     * count each — which is also the number the shift rule judges, so a week
     * that reads as too long here is the week that would be refused.
     */
    weeks: { type: Array, default: () => [] },
    /** Active rules to choose from; empty hides the field entirely. */
    rules: { type: Object, default: () => ({}) },
    selectedRule: { type: [String, Number], default: '' },
    allowSplit: { type: Boolean, default: false },
    breakDurations: { type: Array, default: () => [15, 30, 45, 60] },
    /**
     * Present when this period has been published before: the title and body
     * of the notice at the top, already worded and translated.
     */
    publishedNotice: { type: Object, default: null },
    /** Messages from a refused submission, keyed by date. */
    errors: { type: Object, default: () => ({}) },
    labels: { type: Object, default: () => ({}) },
});

const rows = ref(props.days.map((day) => ({
    date: day.date,
    label: day.label,
    today: Boolean(day.today),
    status: day.status ?? null,
    working: Boolean(day.working),
    periods: (day.periods ?? []).map((period) => ({ ...period })),
})));

const rule = ref(String(props.selectedRule ?? ''));

/* Rows by date, so a week can ask for its own days without searching the
   month for each of them. */
const byDate = computed(() => Object.fromEntries(rows.value.map((row) => [row.date, row])));

const daysOf = (week) => week.dates.map((date) => byDate.value[date]).filter(Boolean);

const weekHours = (week) => Math.round(
    daysOf(week).reduce((sum, row) => sum + (row.working
        ? row.periods.reduce((mins, period) => mins + workedMinutes(period), 0)
        : 0), 0) / 60 * 10,
) / 10;

/* Open on the week the reader is standing in, or the first one. Held as a set
   of indexes rather than a flag per week, because the weeks arrive as plain
   data and a component should not be writing into its own props. */
const openWeeks = ref(new Set([Math.max(0, props.weeks.findIndex((week) => week.current))]));

function toggleWeek(index) {
    const next = new Set(openWeeks.value);

    next.has(index) ? next.delete(index) : next.add(index);
    openWeeks.value = next;
}

/* The rule is a property of the person, not of this period: it is shown here
   so the manager can see what the days below were filled in from, and only
   opens for editing when they say so. */
const editingRule = ref(false);

const ruleName = computed(() => props.rules[rule.value] ?? props.labels.no_shift_rule);

/* Combos speak in strings — a JSON object key always is one — so the value
   crosses back as a number, which is what the row and the server hold. */
const breakOptions = computed(() => Object.fromEntries([
    ['0', props.labels.no_break],
    ...props.breakDurations.map((minutes) => [String(minutes), String(minutes)]),
]));

function breakValue(period) {
    return period.break_minutes === null || period.break_minutes === undefined
        ? []
        : [String(period.break_minutes)];
}

function setBreak(period, values) {
    period.break_minutes = values.length ? Number(values[0]) : null;
}
const publishing = ref(false);
const sending = ref(false);

/* What the form looked like when it loaded. Compared by value on the way out,
   so a manager who changed nothing is not asked to confirm leaving. */
const pristine = JSON.stringify(rows.value);
const dirty = computed(() => JSON.stringify(rows.value) !== pristine);

const hasRules = computed(() => Object.keys(props.rules).length > 0);

const problems = computed(() => Object.entries(props.errors)
    .filter(([date]) => date !== '*')
    .map(([date, messages]) => ({
        date,
        label: rows.value.find((row) => row.date === date)?.label ?? date,
        messages,
    })));

const workingDays = computed(() => rows.value.filter((row) => row.working && row.periods.length).length);

const totalHours = computed(() => {
    const minutes = rows.value
        .filter((row) => row.working)
        .flatMap((row) => row.periods)
        .reduce((sum, period) => sum + workedMinutes(period), 0);

    return Math.round((minutes / 60) * 10) / 10;
});

function workedMinutes(period) {
    const read = (time) => {
        const [hours, minutes] = String(time || '').split(':').map(Number);

        return Number.isFinite(hours) ? hours * 60 + (minutes || 0) : 0;
    };

    return Math.max(0, read(period.ends_at) - read(period.starts_at) - Number(period.break_minutes || 0));
}

function dayHours(row) {
    if (!row.working) {
        return 0;
    }

    return Math.round((row.periods.reduce((sum, period) => sum + workedMinutes(period), 0) / 60) * 10) / 10;
}

function addPeriod(row) {
    const last = row.periods[row.periods.length - 1];

    row.periods.push({
        shift_period_id: null,
        name: null,
        /* Starting where the last one finished is what a divided day looks
           like, and one fewer thing to type for the common case. */
        starts_at: last?.ends_at || '09:00',
        ends_at: '',
        break_minutes: null,
    });
}

function removePeriod(row, index) {
    row.periods.splice(index, 1);

    /* A working day with no periods is a day off, and saying so is clearer
       than leaving a row that posts nothing. */
    if (!row.periods.length) {
        row.working = false;
    }
}

function toggleWorking(row) {
    if (row.working && !row.periods.length) {
        addPeriod(row);
    }
}

/**
 * A different rule, and the days rebuilt from it.
 *
 * The rule decides what the days are proposed as and how many hours a week
 * they may come to, and both of those are worked out on the server. Choosing
 * one here without going back for them leaves a screen the guard will refuse
 * on arrival — a month of forty-eight hour weeks under a forty hour rule.
 *
 * So the screen is asked for again with the rule in the address bar. Anything
 * typed is lost, which is why it asks first.
 */
async function chooseRule(chosen) {
    if (chosen === rule.value) {
        return;
    }

    if (dirty.value && !(await confirmAction({
        title: props.labels.rule_change_title,
        message: props.labels.rule_change_confirm,
        confirm: props.labels.rule_change_label,
        dismiss: props.labels.cancel,
        tone: 'brand',
    }))) {
        return;
    }

    rule.value = chosen;

    const url = new URL(window.location.href);

    if (chosen === '') {
        url.searchParams.delete('shift_rule_id');
    } else {
        url.searchParams.set('shift_rule_id', chosen);
    }

    /* Set before navigating, or the unload guard fires on the very departure
       that has just been agreed to. */
    sending.value = true;
    window.location.href = url.toString();
}

function messagesFor(date) {
    return props.errors[date] ?? [];
}

/**
 * Marks the form as on its way, and otherwise lets the submit happen.
 *
 * Nothing is cancelled here: Save posts straight through, and Save & Publish
 * reaches this only from inside its own confirmation. What it does do is tell
 * the unload guard below that this navigation was asked for.
 */
function submit() {
    sending.value = true;
}

/**
 * The ways out, asking first when there is something to lose.
 *
 * The app's own dialog rather than window.confirm: that one cannot be styled,
 * names the action in the browser's words, and blocks the page while it waits.
 */
async function leave(url) {
    if (dirty.value && !(await confirmAction({
        title: props.labels.leave_title,
        message: props.labels.leave_confirm,
        confirm: props.labels.leave_confirm_label,
        dismiss: props.labels.cancel,
        tone: 'danger',
    }))) {
        return;
    }

    /* Set before navigating, or the guard fires on the very departure that
       has just been agreed to. */
    sending.value = true;
    window.location.href = url;
}

function guard(event) {
    if (dirty.value && !sending.value) {
        event.preventDefault();
        event.returnValue = '';
    }
}

function onKey(event) {
    if (event.key === 'Escape' && publishing.value) {
        publishing.value = false;
    }
}

onMounted(() => {
    window.addEventListener('beforeunload', guard);
    document.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', guard);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <form :action="action" method="POST" class="flex flex-col min-h-screen" @submit="submit">
        <input type="hidden" name="_token" :value="csrf">
        <input type="hidden" name="from" :value="from">
        <input type="hidden" name="until" :value="until">
        <input type="hidden" name="publish" :value="publishing ? 1 : 0">
        <input type="hidden" name="return_to" :value="backUrl">

        <!-- Header. Only the ways out and the name of the task: this screen is
             a sitting, and the surrounding navigation is an invitation to
             abandon it half-finished. -->
        <header class="sticky top-0 z-30 bg-white border-b border-line">
            <div class="w-full px-4 sm:px-6 h-14 flex items-center gap-3">
                <button type="button" class="styledesk_action styledesk_action--sm" @click="leave(backUrl)">
                    ← {{ labels.back }}
                </button>

                <div class="min-w-0 flex-1 text-center">
                    <h1 class="text-[15px] font-semibold text-head truncate">
                        {{ monthLabel ? `${labels.assign_title} — ${monthLabel}` : labels.assign_title }}
                    </h1>
                    <!-- The dates under the month, so the reader never has to
                         work out which days "August" turned into. -->
                    <p class="text-[12px] text-sub truncate">{{ periodLabel }}</p>
                </div>

                <button type="button" class="styledesk_action styledesk_action--sm styledesk_action--icon"
                        :aria-label="labels.close" @click="leave(backUrl)">×</button>
            </div>
        </header>

        <div class="flex-1 w-full px-4 sm:px-6 py-6">
            <div class="max-w-[900px] mx-auto">

                <!-- What is being planned, and for whom. Read-only: the period
                     was chosen on the way in, and a field that could change it
                     here would have to rebuild every row underneath. -->
                <dl class="grid sm:grid-cols-3 gap-3 rounded-card border border-line bg-white p-4">
                    <div>
                        <dt class="text-[12px] text-faint">{{ labels.assign_for }}</dt>
                        <dd class="text-[14px] font-semibold text-head mt-0.5">{{ staffName }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] text-faint">{{ labels.period }}</dt>
                        <dd class="text-[14px] font-semibold text-head mt-0.5">{{ periodLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-[12px] text-faint">{{ labels.confirm?.duration }}</dt>
                        <dd class="text-[14px] font-semibold text-head mt-0.5">{{ durationLabel }}</dd>
                    </div>
                </dl>

                <!-- Said before the first change rather than in the
                     confirmation after the last: a week this person has
                     already been emailed is not a blank one, and the
                     difference decides whether editing it is filling something
                     in or telling somebody something different. Informational,
                     not a barrier — the manager came here to change it. -->
                <div v-if="publishedNotice" class="sd-alert sd-alert--info mt-4" role="status">
                    <div class="flex items-start gap-2.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px"
                             aria-hidden="true">
                            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9"
                                  stroke-linecap="round"/>
                        </svg>
                        <p class="min-w-0">
                            <span class="font-semibold">{{ publishedNotice.title }}</span>
                            {{ publishedNotice.body }}
                        </p>
                    </div>
                </div>

                <!-- Why nothing was written, before the rows it happened in.
                     The period is written whole or not at all, so one bad day
                     holds up the rest and the reader needs that up front. -->
                <div v-if="problems.length" class="mt-4 rounded-card border border-danger/30 bg-danger/5 p-4">
                    <p class="text-[13px] font-semibold text-danger">{{ labels.refused_title }}</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="problem in problems" :key="problem.date" class="text-[12px] text-danger">
                            <span class="font-medium">{{ problem.label }}:</span>
                            {{ problem.messages.join(' ') }}
                        </li>
                    </ul>
                </div>

                <div v-if="hasRules" class="mt-4 rounded-card border border-line bg-white p-4">
                    <div class="flex items-center gap-3 mb-1.5">
                        <span class="text-[13px] font-medium text-ink">{{ labels.shift_rule }}</span>
                        <button v-if="!editingRule" type="button" class="text-[12px] font-semibold text-link"
                                @click="editingRule = true">{{ labels.change_rule }}</button>
                    </div>
                    <div class="max-w-[360px]">
                        <MultiSelect v-if="editingRule"
                                     :options="{ '': labels.no_shift_rule, ...rules }"
                                     :model-value="[rule]"
                                     name="shift_rule_id"
                                     single
                                     :placeholder="labels.no_shift_rule"
                                     :aria-label="labels.shift_rule"
                                     @update:model-value="(values) => chooseRule(values[0] ?? '')" />

                        <template v-else>
                            <input type="hidden" name="shift_rule_id" :value="rule">
                            <div class="sd-combo-btn is-readonly" aria-readonly="true">
                                <span class="sd-combo-btn__label">{{ ruleName }}</span>
                            </div>
                        </template>
                    </div>
                    <p class="mt-1.5 text-[12px] text-sub leading-relaxed">{{ labels.prefill_hint }}</p>
                </div>

                <p v-for="message in messagesFor('*')" :key="message"
                   class="mt-4 text-[12px] text-danger">{{ message }}</p>

                <!-- A week at a time. The header carries the hours that week
                     comes to, which is the number the shift rule judges — so a
                     week that reads as too long here is the week that would be
                     refused, and the reader can see which one before they
                     press anything. -->
                <div class="mt-4 space-y-2.5">
                  <section v-for="(week, weekIndex) in weeks" :key="week.label"
                           class="rounded-card border border-line bg-white overflow-hidden">
                    <button type="button" class="styledesk_weekhead" :aria-expanded="openWeeks.has(weekIndex)"
                            @click="toggleWeek(weekIndex)">
                      <svg class="styledesk_weekhead__caret" :class="{ 'is-open': openWeeks.has(weekIndex) }"
                           width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>

                      <span class="min-w-0 flex-1 text-left">
                        <span class="text-[13px] font-semibold text-head">{{ week.label }}</span>
                        <span class="text-[13px] text-sub"> · {{ week.range }}</span>
                      </span>

                      <span class="text-[13px] font-semibold text-head shrink-0">
                        {{ (labels.hours_short ?? ':count hrs').replace(':count', weekHours(week)) }}
                      </span>
                    </button>

                    <div v-show="openWeeks.has(weekIndex)" class="p-2.5 pt-0 space-y-2.5">
                    <div v-for="row in daysOf(week)" :key="row.date"
                         class="rounded-card border bg-white p-4"
                         :class="messagesFor(row.date).length ? 'border-danger/40' : 'border-line'">
                        <input type="hidden" :name="`days[${row.date}][working]`" :value="row.working ? 1 : 0">

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <span class="min-w-[150px] text-[13px]"
                                  :class="row.today ? 'font-semibold text-head' : 'text-ink'">
                                {{ row.label }}
                                <span v-if="row.today" class="text-[11px] text-brand font-semibold">
                                    {{ labels.today }}
                                </span>
                            </span>

                            <label class="styledesk_toggle">
                                <input v-model="row.working" type="checkbox" class="styledesk_toggle__input"
                                       @change="toggleWorking(row)">
                                <span class="styledesk_toggle__track" aria-hidden="true">
                                    <span class="styledesk_toggle__knob"></span>
                                </span>
                                <span class="styledesk_toggle__label">
                                    {{ row.working ? labels.working : labels.not_working }}
                                </span>
                            </label>

                            <span v-if="row.working && dayHours(row)" class="text-[12px] text-sub">
                                {{ dayHours(row) }} {{ labels.hours }}
                            </span>

                            <!-- Where this day has got to, for a period that is
                                 partly out already: a manager changing a week
                                 needs to see which of its days the staff member
                                 has been told about. -->
                            <span v-if="row.status" class="ml-auto styledesk_badge"
                                  :class="row.status === 'published' ? 'styledesk_badge--active' : 'styledesk_badge--setup'">
                                {{ row.status === 'published' ? labels.published_badge : labels.draft_badge }}
                            </span>
                        </div>

                        <div v-if="row.working" class="mt-3 space-y-2">
                            <div v-for="(period, index) in row.periods" :key="index"
                                 class="grid gap-2 items-end grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                                <input type="hidden" :name="`days[${row.date}][periods][${index}][shift_period_id]`"
                                       :value="period.shift_period_id ?? ''">

                                <div class="min-w-0">
                                    <label class="block text-[12px] text-sub mb-1">
                                        {{ period.name || labels.starts_at }}
                                    </label>
                                    <TimePicker v-model="period.starts_at"
                                                :name="`days[${row.date}][periods][${index}][starts_at]`"
                                                :aria-label="labels.starts_at" />
                                </div>

                                <div class="min-w-0">
                                    <label class="block text-[12px] text-sub mb-1">{{ labels.ends_at }}</label>
                                    <TimePicker v-model="period.ends_at"
                                                :name="`days[${row.date}][periods][${index}][ends_at]`"
                                                :aria-label="labels.ends_at" />
                                </div>

                                <div class="min-w-0">
                                    <label class="block text-[12px] text-sub mb-1">{{ labels.break }}</label>
                                    <MultiSelect :options="breakOptions"
                                                 :model-value="breakValue(period)"
                                                 :name="`days[${row.date}][periods][${index}][break_minutes]`"
                                                 single
                                                 :placeholder="labels.no_break"
                                                 :aria-label="labels.break"
                                                 @update:model-value="(values) => setBreak(period, values)" />
                                </div>

                                <button type="button" class="styledesk_action styledesk_action--sm h-11"
                                        :aria-label="labels.remove_period" @click="removePeriod(row, index)">×</button>
                            </div>

                            <!-- Only where the rule permits it: a second period
                                 in a day is a split shift, and the rule is what
                                 decides whether this business runs them. -->
                            <button v-if="allowSplit" type="button" class="styledesk_action styledesk_action--sm"
                                    @click="addPeriod(row)">{{ labels.add_period }}</button>
                        </div>

                        <p v-for="message in messagesFor(row.date)" :key="message"
                           class="mt-2 text-[12px] text-danger">{{ message }}</p>
                    </div>
                    </div>
                  </section>
                </div>
            </div>
        </div>

        <!-- Stays put while the days scroll. A four-week period is twenty-eight
             rows, and a footer at the bottom of that is a footer nobody reaches
             without deciding to go looking for it. -->
        <footer class="sticky bottom-0 z-30 bg-white border-t border-line">
            <div class="w-full px-4 sm:px-6 py-3 flex flex-wrap items-center gap-2">
                <button type="button"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                        @click="publishing = true">
                    {{ labels.save_and_publish }}
                </button>

                <button type="submit" class="styledesk_action" :disabled="sending">
                    {{ sending ? labels.saving : labels.save }}
                </button>

                <button type="button" class="text-[13px] text-sub hover:text-ink transition-colors px-2"
                        @click="leave(backUrl)">
                    {{ labels.cancel }}
                </button>

                <span class="ml-auto text-[12px] text-sub">
                    {{ workingDays }} {{ labels.confirm?.working_days }} · {{ totalHours }} {{ labels.hours }}
                </span>
            </div>
        </footer>

        <!-- Publishing is the one action here that leaves the building, so it
             shows what is about to be sent rather than only asking whether. -->
        <div v-if="publishing" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
             role="dialog" aria-modal="true" :aria-label="labels.confirm?.title">
            <div class="fixed inset-0 bg-black/40" @click="publishing = false"></div>

            <div class="relative w-full max-w-[520px] bg-white rounded-card shadow-xl my-4">
                <div class="p-5 border-b border-line">
                    <h2 class="text-[16px] font-semibold text-head">{{ labels.confirm?.title }}</h2>
                </div>

                <dl class="p-5">
                    <div v-for="(fact, index) in [
                             { term: labels.confirm?.staff_member, value: staffName },
                             { term: labels.confirm?.period, value: periodLabel },
                             { term: labels.confirm?.duration, value: durationLabel },
                             { term: labels.confirm?.working_days, value: String(workingDays) },
                             { term: labels.confirm?.total_hours, value: `${totalHours}` },
                         ]" :key="fact.term"
                         class="flex flex-wrap items-baseline justify-between gap-3 py-2.5"
                         :class="index < 4 ? 'border-b border-line' : ''">
                        <dt class="text-[13px] text-sub">{{ fact.term }}</dt>
                        <dd class="text-[14px] font-semibold text-head">{{ fact.value }}</dd>
                    </div>
                </dl>

                <p class="px-5 pb-1 text-[12px] text-sub leading-relaxed">{{ labels.confirm?.will_notify }}</p>

                <div class="p-5 border-t border-line flex flex-wrap items-center gap-2">
                    <!-- Not disabled from this button's own click handler.
                         Vue flushes that change in a microtask, which lands
                         before the browser dispatches the submit event — and a
                         disabled submitter never submits, so the button went
                         grey and nothing was ever posted. The form's own
                         submit handler sets it instead, which runs once the
                         submission is already under way. -->
                    <button type="submit" :disabled="sending"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60">
                        {{ sending ? labels.publishing : labels.save_and_publish }}
                    </button>
                    <button type="button" class="styledesk_action" @click="publishing = false">
                        {{ labels.cancel }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
