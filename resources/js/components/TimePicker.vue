<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Column-scroll time picker, in the shape of Ant Design's TimePicker.
 *
 * Picking 14:45 is an hour and a minute — two short columns — rather than one
 * dropdown of ninety-six entries. The columns scroll independently and the
 * chosen row is scrolled to the top, which is what makes a 24-row hour column
 * usable without a search box.
 *
 * The value is always held and emitted as 24-hour "HH:MM", whatever the
 * display format, so the server sees one format and the existing
 * date_format:H:i validation is unchanged. A hidden input carries it, so the
 * surrounding form posts normally with no JSON endpoint involved.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    name: { type: String, default: null },
    minuteStep: { type: Number, default: 5 },
    use12Hours: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Select time' },
    ariaLabel: { type: String, default: 'Select time' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const above = ref(false);
const root = ref(null);
const hourCol = ref(null);
const minuteCol = ref(null);
const meridiemCol = ref(null);

/** Draft the panel edits, so cancelling by clicking away leaves the value alone. */
const draft = ref(parse(props.modelValue));

function parse(value) {
    const match = /^(\d{1,2}):(\d{2})$/.exec((value || '').trim());

    if (!match) {
        return { hour: null, minute: null };
    }

    return { hour: Math.min(23, +match[1]), minute: Math.min(59, +match[2]) };
}

function pad(n) {
    return String(n).padStart(2, '0');
}

const hours = computed(() =>
    props.use12Hours
        ? Array.from({ length: 12 }, (_, i) => (i === 0 ? 12 : i))
        : Array.from({ length: 24 }, (_, i) => i)
);

const minutes = computed(() => {
    const step = Math.max(1, props.minuteStep);

    return Array.from({ length: Math.ceil(60 / step) }, (_, i) => i * step);
});

const meridiem = computed(() => (draft.value.hour === null ? 'AM' : draft.value.hour < 12 ? 'AM' : 'PM'));

/** The hour as shown in the column, which differs from the stored hour in 12h mode. */
const displayHour = computed(() => {
    if (draft.value.hour === null) {
        return null;
    }

    if (!props.use12Hours) {
        return draft.value.hour;
    }

    return draft.value.hour % 12 === 0 ? 12 : draft.value.hour % 12;
});

const label = computed(() => {
    if (draft.value.hour === null || draft.value.minute === null) {
        return '';
    }

    return props.use12Hours
        ? `${displayHour.value}:${pad(draft.value.minute)} ${meridiem.value}`
        : `${pad(draft.value.hour)}:${pad(draft.value.minute)}`;
});

const stored = computed(() =>
    draft.value.hour === null || draft.value.minute === null
        ? ''
        : `${pad(draft.value.hour)}:${pad(draft.value.minute)}`
);

// Keep in step when the value is changed from outside — the copy-hours action
// writes to every row at once.
watch(() => props.modelValue, (value) => {
    if (value !== stored.value) {
        draft.value = parse(value);
    }
});

function commit() {
    if (stored.value !== props.modelValue) {
        emit('update:modelValue', stored.value);
    }
}

function pickHour(shown) {
    if (!props.use12Hours) {
        draft.value = { ...draft.value, hour: shown };
    } else {
        const base = shown % 12;
        draft.value = { ...draft.value, hour: meridiem.value === 'PM' ? base + 12 : base };
    }

    if (draft.value.minute === null) {
        draft.value.minute = 0;
    }

    commit();
    scrollToSelection();
}

function pickMinute(minute) {
    draft.value = { ...draft.value, minute };

    if (draft.value.hour === null) {
        draft.value.hour = 0;
    }

    commit();
    scrollToSelection();
}

function pickMeridiem(value) {
    const hour = draft.value.hour ?? 0;
    const base = hour % 12;

    draft.value = { ...draft.value, hour: value === 'PM' ? base + 12 : base, minute: draft.value.minute ?? 0 };
    commit();
    scrollToSelection();
}

function now() {
    const date = new Date();
    const step = Math.max(1, props.minuteStep);

    draft.value = {
        hour: date.getHours(),
        minute: Math.min(59, Math.round(date.getMinutes() / step) * step) % 60,
    };

    commit();
    scrollToSelection();
}

function scrollToSelection() {
    nextTick(() => {
        [hourCol, minuteCol, meridiemCol].forEach((col) => {
            const on = col.value?.querySelector('.styledesk_timepicker__opt--on');

            if (on) {
                col.value.scrollTop = on.offsetTop - col.value.offsetTop;
            }
        });
    });
}

function toggle() {
    if (props.disabled) {
        return;
    }

    open.value = !open.value;

    if (open.value) {
        // Flip upward when the panel would otherwise fall off the viewport —
        // the hours table sits low on the page and rows near the bottom would
        // open into nothing.
        const box = root.value.getBoundingClientRect();
        above.value = window.innerHeight - box.bottom < 300 && box.top > 300;

        scrollToSelection();
    }
}

function close() {
    open.value = false;
}

function onDocumentClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        close();
    }
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        close();
        root.value.querySelector('.styledesk_timepicker__field')?.focus();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="styledesk_timepicker" :class="{ 'styledesk_timepicker--open': open }">
        <input v-if="name" type="hidden" :name="name" :value="stored">

        <button type="button" class="styledesk_timepicker__field" :disabled="disabled"
                :aria-label="ariaLabel" :aria-expanded="open" aria-haspopup="dialog" @click.stop="toggle">
            <span class="styledesk_timepicker__value" :class="{ 'styledesk_timepicker__value--empty': !label }">
                {{ label || placeholder }}
            </span>
            <svg class="styledesk_timepicker__icon" width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/>
                <path d="M12 7.5V12l3 1.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div v-if="open" class="styledesk_timepicker__panel"
             :class="{ 'styledesk_timepicker__panel--above': above }" role="dialog" :aria-label="ariaLabel">
            <div class="styledesk_timepicker__cols">
                <div ref="hourCol" class="styledesk_timepicker__col" role="listbox" aria-label="Hour">
                    <button v-for="h in hours" :key="h" type="button" role="option"
                            :aria-selected="displayHour === h"
                            class="styledesk_timepicker__opt"
                            :class="{ 'styledesk_timepicker__opt--on': displayHour === h }"
                            @click="pickHour(h)">
                        {{ use12Hours ? h : String(h).padStart(2, '0') }}
                    </button>
                </div>

                <div ref="minuteCol" class="styledesk_timepicker__col" role="listbox" aria-label="Minute">
                    <button v-for="m in minutes" :key="m" type="button" role="option"
                            :aria-selected="draft.minute === m"
                            class="styledesk_timepicker__opt"
                            :class="{ 'styledesk_timepicker__opt--on': draft.minute === m }"
                            @click="pickMinute(m)">
                        {{ String(m).padStart(2, '0') }}
                    </button>
                </div>

                <div v-if="use12Hours" ref="meridiemCol" class="styledesk_timepicker__col" role="listbox" aria-label="AM or PM">
                    <button v-for="value in ['AM', 'PM']" :key="value" type="button" role="option"
                            :aria-selected="meridiem === value"
                            class="styledesk_timepicker__opt"
                            :class="{ 'styledesk_timepicker__opt--on': meridiem === value }"
                            @click="pickMeridiem(value)">
                        {{ value }}
                    </button>
                </div>
            </div>

            <div class="styledesk_timepicker__foot">
                <button type="button" class="styledesk_timepicker__now" @click="now">Now</button>
                <button type="button" class="styledesk_timepicker__ok" @click="close">OK</button>
            </div>
        </div>
    </div>
</template>
