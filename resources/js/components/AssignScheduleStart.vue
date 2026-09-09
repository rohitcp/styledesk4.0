<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import MultiSelect from './MultiSelect.vue';

/**
 * The step before the schedule: which month are we planning?
 *
 * A month is planned whole, so the only question left is which one. It opens
 * on the month the table behind is showing, stated rather than offered —
 * changing it is behind the pencil, because the common case is planning the
 * month being looked at.
 *
 * Every month arrives ready made, dates already formatted, so switching
 * between them says nothing in the browser's own language and asks the
 * server nothing.
 */
const props = defineProps({
    /** Where the focused screen lives; the range is appended as a query. */
    formUrl: { type: String, required: true },
    /**
     * Every offerable month, keyed "year-month", each with its own first
     * date, ready-made range and day count.
     */
    periods: { type: Object, default: () => ({}) },
    months: { type: Object, default: () => ({}) },
    years: { type: Object, default: () => ({}) },
    month: { type: [String, Number], required: true },
    year: { type: [String, Number], required: true },
    labels: { type: Object, default: () => ({}) },
});

const open = ref(false);
const month = ref(String(props.month));
const year = ref(String(props.year));
const editing = ref(false);

const period = computed(() => props.periods[`${year.value}-${month.value}`] ?? null);

function onKey(event) {
    if (event.key === 'Escape' && open.value) {
        close();
    }
}

/* Opened on the month the table is already showing, which makes Continue the
   only thing left to press in the common case. */
function start() {
    open.value = true;
    editing.value = false;
    month.value = String(props.month);
    year.value = String(props.year);
}

function close() {
    open.value = false;
    editing.value = false;
}

function go() {
    if (!period.value) {
        return;
    }

    /* The page this was opened from travels with the choice, so finishing
       the schedule puts the manager back on the period they were reading
       rather than on a default week. */
    const back = encodeURIComponent(window.location.pathname + window.location.search);

    /* Days rather than weeks: a month is not a whole number of them, and the
       screen has to cover the days the dialog promised. */
    window.location.href = `${props.formUrl}?days=${period.value.days}`
        + `&from=${period.value.from}&return=${back}`;
}

onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div>
        <button type="button"
                class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                @click="start">
            {{ labels.assign }}
        </button>

        <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
             role="dialog" aria-modal="true" :aria-label="labels.assign_title">
            <div class="fixed inset-0 bg-black/40" @click="close"></div>

            <div class="relative w-full max-w-[440px] bg-white rounded-card shadow-xl my-4">
                <div class="p-5 border-b border-line">
                    <h2 class="text-[16px] font-semibold text-head">{{ labels.assign_title }}</h2>
                    <p class="text-[13px] text-sub mt-1 leading-relaxed">{{ labels.duration_intro }}</p>
                </div>

                <div class="p-5">
                    <!-- The month being planned, stated with a way to change
                         it: the dialog is opened from a month, and the one on
                         screen behind it is nearly always the answer. -->
                    <p class="text-[12px] text-faint">{{ labels.period }}</p>
                    <p class="flex items-center gap-2 mt-0.5">
                        <span class="text-[14px] font-semibold text-head">{{ period?.label }}</span>
                        <button type="button" class="sd-iconbtn grid place-items-center"
                                :aria-label="labels.edit_period" :title="labels.edit_period"
                                :aria-expanded="editing" @click="editing = !editing">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 20h4L19 9l-4-4L4 16v4zM14.5 5.5l4 4"
                                      stroke="currentColor" stroke-width="1.8"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </p>

                    <div v-if="editing" class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-[12px] text-sub mb-1">{{ labels.month }}</label>
                            <MultiSelect :options="months" :model-value="[month]" name="assignMonth" single
                                         :placeholder="labels.month" :aria-label="labels.month"
                                         @update:model-value="(values) => month = values[0] ?? month" />
                        </div>

                        <div>
                            <label class="block text-[12px] text-sub mb-1">{{ labels.year }}</label>
                            <MultiSelect :options="years" :model-value="[year]" name="assignYear" single
                                         :placeholder="labels.year" :aria-label="labels.year"
                                         @update:model-value="(values) => year = values[0] ?? year" />
                        </div>
                    </div>

                    <!-- The dates themselves, under the month's name: a
                         month is a word, and "1 Aug 2026 – 31 Aug 2026" is
                         the thing being committed to. -->
                    <div v-if="period" class="mt-4 rounded-lg border border-line p-3">
                        <p class="text-[14px] font-semibold text-head">{{ period.range }}</p>
                    </div>
                </div>

                <div class="p-5 border-t border-line flex flex-wrap items-center gap-2">
                    <button type="button" :disabled="!period"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="go">
                        {{ labels.continue_label }}
                    </button>
                    <button type="button" class="styledesk_action" @click="close">{{ labels.cancel }}</button>
                </div>
            </div>
        </div>
    </div>
</template>
