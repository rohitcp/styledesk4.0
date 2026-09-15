<script setup>
/**
 * A date, typed or picked.
 *
 * The field carries a calendar icon and the calendar only appears when it is
 * pressed: an always-open calendar took eight lines of a form to ask one
 * question, and a client filling in an intake form on a phone is scrolling
 * past six of them.
 *
 * The value is a string in the business's chosen pattern rather than an ISO
 * date, because the pattern is what the question asked for — a birthday
 * written MM/DD is not a date with a missing year, it is the answer to
 * "when is your birthday".
 *
 * Month and weekday names come from the browser's Intl against the document's
 * locale, not from a lang file: nineteen strings a language that it already
 * knows, and right for locales StyleDesk does not ship.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

import SdCombo from './SdCombo.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    /** One of config('forms.date_formats') — its `parts`, in order. */
    parts: { type: Array, default: () => ['month', 'day', 'year'] },
    pattern: { type: String, default: 'MM/DD/YYYY' },
    controlStyle: { type: Object, default: () => ({}) },
    monthLabel: { type: String, default: 'Month' },
    yearLabel: { type: String, default: 'Year' },
    openLabel: { type: String, default: 'Choose a date' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);

const locale = typeof document === 'undefined' ? 'en' : (document.documentElement.lang || 'en');

const today = new Date();
const month = ref(today.getMonth());
const year = ref(today.getFullYear());

const monthChoices = computed(() => {
    const format = new Intl.DateTimeFormat(locale, { month: 'long' });

    return Array.from({ length: 12 }, (unused, index) => ({
        value: index,
        label: format.format(new Date(2026, index, 1)),
    }));
});

const yearChoices = computed(() => {
    /* A hundred years back and five forward: old enough for any date of
       birth, and far enough ahead for a consent that expires. */
    return Array.from({ length: 106 }, (unused, offset) => {
        const value = today.getFullYear() + 5 - offset;

        return { value, label: String(value) };
    });
});

const weekdays = computed(() => {
    const format = new Intl.DateTimeFormat(locale, { weekday: 'narrow' });

    /* 4 January 2026 was a Sunday, where the app's own calendar starts. */
    return Array.from({ length: 7 }, (unused, day) => format.format(new Date(2026, 0, 4 + day)));
});

const days = computed(() => {
    const first = new Date(year.value, month.value, 1).getDay();
    const length = new Date(year.value, month.value + 1, 0).getDate();
    const cells = Array.from({ length: first }, () => null);

    for (let day = 1; day <= length; day += 1) cells.push(day);

    return cells;
});

const pad = (value) => String(value).padStart(2, '0');

/** The chosen day, written the way the question asked for it. */
function choose(day) {
    const written = props.parts
        .map((part) => {
            if (part === 'day') return pad(day);
            if (part === 'month') return pad(month.value + 1);

            return String(year.value);
        })
        .join('/');

    emit('update:modelValue', written);
    open.value = false;
}

function toggle() {
    if (props.disabled) return;

    open.value = !open.value;

    /* Opened onto whatever is already in the field, so somebody correcting a
       date is not sent back to this month. */
    if (open.value) nextTick(() => readFieldIntoCalendar());
}

function readFieldIntoCalendar() {
    const pieces = String(props.modelValue).split('/');

    if (pieces.length !== props.parts.length) return;

    props.parts.forEach((part, index) => {
        const value = Number(pieces[index]);

        if (Number.isNaN(value)) return;
        if (part === 'month' && value >= 1 && value <= 12) month.value = value - 1;
        if (part === 'year' && value > 999) year.value = value;
    });
}

function onOutside(event) {
    if (!open.value || root.value?.contains(event.target)) return;

    open.value = false;
}

onMounted(() => document.addEventListener('mousedown', onOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
  <div ref="root" class="relative">
    <div class="relative">
      <!-- Typed as well as picked. Somebody who knows their own date of birth
           should not have to click through a calendar to reach 1974. -->
      <input class="sd-input has-suffix" :style="controlStyle" :disabled="disabled"
             :placeholder="pattern" :value="modelValue" inputmode="numeric"
             @input="emit('update:modelValue', $event.target.value)">

      <button type="button" class="absolute inset-y-0 right-0 w-10 grid place-items-center text-sub hover:text-ink transition-colors"
              :disabled="disabled" :aria-label="openLabel" :title="openLabel"
              :aria-expanded="open" @click="toggle">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3.5" y="5" width="17" height="15" rx="2" />
          <path d="M3.5 10h17M8 3v4M16 3v4" />
        </svg>
      </button>
    </div>

    <div v-if="open" class="sd-pop !p-2.5 w-[268px]">
      <div class="flex items-center gap-1.5">
        <div class="min-w-0 flex-1">
          <SdCombo v-model="month" :options="monthChoices" :search-label="monthLabel" />
        </div>
        <div class="w-[92px] shrink-0">
          <SdCombo v-model="year" :options="yearChoices" :search-label="yearLabel" />
        </div>
      </div>

      <div class="mt-2 grid grid-cols-7 gap-0.5 text-center">
        <span v-for="(day, index) in weekdays" :key="'w' + index"
              class="text-[11px] font-medium text-faint py-1">{{ day }}</span>

        <template v-for="(day, index) in days" :key="'d' + index">
          <span v-if="day === null" class="h-7"></span>
          <button v-else type="button"
                  class="h-7 grid place-items-center text-[12px] rounded-md text-ink hover:bg-brand hover:text-white transition-colors"
                  @click="choose(day)">{{ day }}</button>
        </template>
      </div>
    </div>
  </div>
</template>
