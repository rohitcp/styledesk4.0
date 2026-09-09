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
    searchPlaceholder: { type: String, default: 'Type a time' },
    ariaLabel: { type: String, default: 'Select time' },
    /**
     * Validation rules for the value this control posts, in the shared
     * live-validation syntax, and the id the message is addressed to.
     *
     * Carried on the hidden input because that is the thing that holds the
     * answer — the control itself is a button, and a button has no value to
     * check. The same arrangement MultiSelect uses.
     */
    rules: { type: String, default: '' },
    fieldId: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const above = ref(false);
const root = ref(null);
const hourCol = ref(null);
const minuteCol = ref(null);
const query = ref('');
const searchBox = ref(null);

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

const allHours = computed(() =>
    props.use12Hours
        ? Array.from({ length: 12 }, (_, i) => (i === 0 ? 12 : i))
        : Array.from({ length: 24 }, (_, i) => i)
);

const allMinutes = computed(() => {
    const step = Math.max(1, props.minuteStep);

    return Array.from({ length: Math.ceil(60 / step) }, (_, i) => i * step);
});

/**
 * What the reader typed, read as a time rather than as a filter string.
 *
 * Two short columns are quick to point at and slow to reach for the keyboard,
 * which is the whole reason this box exists: "230p" is three keystrokes and a
 * return, where the columns are a scroll, a click, a scroll and a click.
 *
 * Everything is optional, because a half-typed time still has to narrow
 * something — "9" is nine o'clock in either half of the day, and the columns
 * say so until the reader has typed enough to mean one of them.
 */
const search = computed(() => {
    const raw = query.value.trim().toLowerCase();

    if (raw === '') {
        return { hour: null, minute: null, meridiem: null, complete: false };
    }

    /* am / pm anywhere in the string, spelt with or without the m and with or
       without a space, because all four are what people type. */
    const meridiem = /p\.?m?\b|p$/.test(raw) ? 'PM' : (/a\.?m?\b|a$/.test(raw) ? 'AM' : null);

    let hour = null;
    let minute = null;

    /* A separator says where the split is, so "2:30" is two thirty rather
       than something to be worked out from how many digits were typed. */
    const parts = raw.split(/[:.\s]+/).map((part) => part.replace(/[^0-9]/g, '')).filter(Boolean);

    if (/[:.]/.test(raw) && parts.length >= 1) {
        hour = parts[0].slice(0, 2);
        minute = parts[1] !== undefined ? parts[1].slice(0, 2) : null;
    } else {
        /* No separator, so the digits decide: "9" is an hour, "930" is nine
           thirty, "0930" the same written out. */
        const digits = raw.replace(/[^0-9]/g, '');

        if (digits.length === 1 || digits.length === 2) {
            hour = digits;
        } else if (digits.length === 3) {
            hour = digits.slice(0, 1);
            minute = digits.slice(1);
        } else if (digits.length >= 4) {
            hour = digits.slice(0, 2);
            minute = digits.slice(2, 4);
        }
    }

    if (hour === '') {
        hour = null;
    }

    return {
        hour,
        minute,
        meridiem,
        complete: hour !== null && minute !== null && minute.length === 2,
    };
});

/**
 * The columns, narrowed to what is still reachable from what was typed.
 *
 * Matched on the number rather than on the text, so "9" finds 9 and 09 alike
 * and does not also find 19 — a filter that widened as you typed would be
 * worse than none.
 */
const hours = computed(() => {
    const typed = search.value.hour;

    if (typed === null) {
        return allHours.value;
    }

    const wanted = Number(typed);
    const shown = props.use12Hours && wanted > 12 ? wanted % 12 || 12 : wanted;

    const matches = allHours.value.filter((h) => h === shown);

    /* Nothing matched — an hour that does not exist. The full column is left
       standing rather than emptied: a panel with nothing in it looks broken,
       and the reader can still point at what they meant. */
    return matches.length ? matches : allHours.value;
});

const minutes = computed(() => {
    const typed = search.value.minute;

    if (typed === null) {
        return allMinutes.value;
    }

    /* One digit is a prefix — "3" means the thirties — and two are exact. */
    const matches = typed.length === 1
        ? allMinutes.value.filter((m) => String(m).padStart(2, '0').startsWith(typed))
        : allMinutes.value.filter((m) => m === Number(typed));

    return matches.length ? matches : allMinutes.value;
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

    /* The hidden input is what holds the answer and nothing focuses it, so
       there is no blur for the shared validator to hear. This is the same
       announcement the combo boxes make for the same reason. */
    nextTick(() => {
        root.value?.querySelector('input[type="hidden"]')
            ?.dispatchEvent(new CustomEvent('sd:combo-change', { bubbles: true }));
    });
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

/**
 * Take what was typed, if it is enough to be a time.
 *
 * Only on a complete answer: committing halfway would change the field under
 * somebody who is still typing.
 */
function applySearch() {
    if (!search.value.complete) {
        return;
    }

    let hour = Number(search.value.hour);
    const minute = Number(search.value.minute);

    if (Number.isNaN(hour) || Number.isNaN(minute) || minute > 59) {
        return;
    }

    /* A bare "2:30" in a 12-hour picker keeps the half of the day already
       chosen. Defaulting to AM would silently move an afternoon shift to two
       in the morning, and the reader has said nothing about which they meant. */
    if (props.use12Hours && hour <= 12) {
        const half = search.value.meridiem ?? meridiem.value;
        const base = hour % 12;

        hour = half === 'PM' ? base + 12 : base;
    }

    if (hour > 23) {
        return;
    }

    draft.value = { hour, minute };
    commit();
    close();
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
        /* The AM/PM column is not in this list. It holds two rows and is
           fixed in place — scrolling a two-row column pushed whichever one
           was not chosen out of sight. */
        [hourCol, minuteCol].forEach((col) => {
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

        /* A fresh box each time it opens: yesterday's query narrowing today's
           columns is a panel that looks broken. */
        query.value = '';

        scrollToSelection();
        nextTick(() => searchBox.value?.focus());
    }
}

function close() {
    open.value = false;
    query.value = '';
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
        <input v-if="name" type="hidden" :name="name" :value="stored"
               :id="fieldId" :data-rules="rules || null">

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
            <!-- Typing beats scrolling for a time you already know: "230p"
                 is three keystrokes and a return, where the columns are a
                 scroll, a click, a scroll and a click. -->
            <div class="styledesk_timepicker__search">
                <input ref="searchBox" v-model="query" type="text" inputmode="numeric"
                       class="sd-input h-9 text-[13px]"
                       :placeholder="searchPlaceholder" :aria-label="searchPlaceholder"
                       autocomplete="off"
                       @keydown.enter.prevent="applySearch"
                       @keydown.esc.prevent="close">
            </div>

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

                <!-- Two rows, pinned. As a scrolling column it inherited the
                     196px of bottom padding the long columns need, so choosing
                     one scrolled the other out of sight. -->
                <div v-if="use12Hours" class="styledesk_timepicker__col styledesk_timepicker__col--fixed"
                     role="listbox" aria-label="AM or PM">
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
