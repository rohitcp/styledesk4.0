<script setup>
import { nextTick, onMounted, reactive, watch } from 'vue';
import TimePicker from './TimePicker.vue';

/**
 * The seven opening-hour rows, shared by onboarding step 2 and Locations.
 *
 * The whole table is one island rather than fourteen separate pickers, because
 * "Copy Monday to Tuesday–Friday" has to write across rows. Done as loose
 * pickers, that copy would mean reaching into other components' DOM and
 * setting values that Vue would then overwrite on its next render.
 *
 * Each row posts ordinary hours[day][field] inputs, so the server sees a plain
 * form submission and the existing validation is untouched.
 *
 * One component for both screens, not two that look alike. Onboarding and
 * Location settings ask the same question, and two implementations of it drift
 * — a picker fixed in one place and not the other is a bug nobody finds until
 * a business notices its hours behave differently depending on which screen
 * they were typed into.
 */
const props = defineProps({
    days: { type: Array, default: () => [] },
    initial: { type: Array, default: () => [] },

    /**
     * More than one opening period per day.
     *
     * Off for onboarding, which asks the simplest version of the question and
     * posts hours[day][opens_at]. On for Locations, where §5 requires a day
     * that closes for lunch and the rows post hours[day][index][opens_at].
     * A prop rather than a second component: everything else — the layout,
     * the toggle, the picker, the spacing — is the same question either way.
     */
    splitPeriods: { type: Boolean, default: false },

    /**
     * 12-hour or 24-hour times, from the business's own setting.
     *
     * Passed in rather than read here: the preference lives on the tenant and
     * the server already knows it, so a component that guessed would be a
     * second answer to a question that has one.
     */
    use12Hours: { type: Boolean, default: true },

    title: { type: String, default: 'Business hours' },
    description: {
        type: String,
        default: 'Your default booking availability. Staff schedules can override this later.',
    },

    /**
     * Validation messages, keyed by day number.
     *
     * Passed in rather than looked up, because the server is what validated
     * them; the island only decides which row they sit under.
     */
    errors: { type: Object, default: () => ({}) },
});

const DAY_LABELS = props.days.length
    ? props.days
    : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const DEFAULT_PERIOD = { opensAt: '09:00', closesAt: '17:00' };

/**
 * Both shapes of `initial` are accepted.
 *
 * Onboarding sends a day as { is_open, opens_at, closes_at }; Locations sends
 * { is_open, periods: [...] }. Normalising here rather than at each caller
 * means neither screen has to know which shape the other uses.
 */
const rows = reactive(
    DAY_LABELS.map((label, day) => {
        const saved = props.initial[day] || {};

        const periods = Array.isArray(saved.periods) && saved.periods.length
            ? saved.periods.map((period) => ({
                opensAt: period.opens_at || DEFAULT_PERIOD.opensAt,
                closesAt: period.closes_at || DEFAULT_PERIOD.closesAt,
            }))
            : [{
                opensAt: saved.opens_at || DEFAULT_PERIOD.opensAt,
                closesAt: saved.closes_at || DEFAULT_PERIOD.closesAt,
            }];

        return {
            label,
            isOpen: saved.is_open ?? day !== 0,
            periods,
        };
    })
);

/**
 * Where a period's value is posted.
 *
 * The index is only in the name when split periods are on, so onboarding
 * keeps posting exactly what its controller has always read.
 */
function fieldName(day, index, field) {
    return props.splitPeriods
        ? `hours[${day}][${index}][${field}]`
        : `hours[${day}][${field}]`;
}

function addPeriod(day) {
    // Blank, not a copy of the row above it. A duplicated period is an overlap
    // the business did not ask for, and one it would have to notice before it
    // could correct it.
    rows[day].periods.push({ opensAt: '', closesAt: '' });
}

function removePeriod(day, index) {
    rows[day].periods.splice(index, 1);
}

function copyMondayToWeek() {
    const monday = rows[1];

    [2, 3, 4, 5].forEach((day) => {
        rows[day].isOpen = monday.isOpen;
        // Cloned, not shared: assigning the array itself would leave four days
        // pointing at Monday's periods, and editing Tuesday would silently
        // rewrite the rest of the week.
        rows[day].periods = monday.periods.map((period) => ({ ...period }));
    });
}

/**
 * The preview in the other column counts open days by reading checkboxes from
 * the DOM. Vue's own updates do not fire a native change event on the form, so
 * without this nudge the rail would drift out of step after a copy.
 */
function announce() {
    document.getElementById('stepForm')?.dispatchEvent(new Event('change', { bubbles: true }));
}

watch(rows, announce, { deep: true });

/**
 * Announce once on mount as well as on change.
 *
 * Islands are resolved through a dynamic import, so this component mounts
 * after the page has already run its first paint of the rail — at which point
 * none of these inputs existed and it counted zero open days. Without this the
 * rail reads "Closed every day" until the user happens to touch something.
 */
onMounted(() => nextTick(announce));
</script>

<template>
    <!-- Structure and spacing follow onboarding-location.html: a card with its
         own header, rows separated by hairlines, px-5 sm:px-6 py-3.5 per row
         and gap-x-4 gap-y-3 between controls. -->
    <section class="bg-white border border-line rounded-card overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-line flex flex-wrap items-center gap-3">
            <div class="min-w-0">
                <h2 class="text-[15px] font-semibold text-head">{{ title }}</h2>
                <p class="text-[13px] text-sub mt-0.5">{{ description }}</p>
            </div>

            <button type="button" @click="copyMondayToWeek"
                    class="ml-auto h-9 px-3.5 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold shrink-0 transition-colors">
                Copy Monday to Tue–Fri
            </button>
        </div>

        <div class="divide-y divide-line">
            <div v-for="(row, day) in rows" :key="day"
                 class="px-5 sm:px-6 py-3.5 flex flex-wrap items-center gap-x-4 gap-y-3">

                <span class="text-[14px] font-medium text-ink w-[92px] shrink-0">{{ row.label }}</span>

                <label class="flex items-center gap-2 shrink-0 cursor-pointer">
                    <input v-model="row.isOpen" type="checkbox" :name="`hours[${day}][is_open]`"
                           value="1" class="sd-switch">
                    <span class="text-[13px]" :class="row.isOpen ? 'text-ink' : 'text-faint'">
                        {{ row.isOpen ? 'Open' : 'Closed' }}
                    </span>
                </label>

                <!-- Times are removed on a closed day rather than disabled, as
                     in the prototype: a row of greyed controls invites the
                     reader to work out whether they still apply. -->
                <div v-if="row.isOpen" class="ml-auto flex flex-col items-end gap-2">
                    <div v-for="(period, index) in row.periods" :key="index"
                         class="flex items-center gap-2">
                        <div class="w-[124px]">
                            <TimePicker v-model="period.opensAt" :name="fieldName(day, index, 'opens_at')"
                                        :use12-hours="use12Hours"
                                        :aria-label="`${row.label} opening time`" />
                        </div>

                        <span class="text-[13px] text-faint">to</span>

                        <div class="w-[124px]">
                            <TimePicker v-model="period.closesAt" :name="fieldName(day, index, 'closes_at')"
                                        :use12-hours="use12Hours"
                                        :aria-label="`${row.label} closing time`" />
                        </div>

                        <!-- Only from the second period onwards. Removing the
                             only period is what the day's own toggle is for,
                             and two controls for one outcome is a choice
                             nobody wants. The placeholder keeps the pickers
                             from shifting sideways on the first row. -->
                        <button v-if="splitPeriods && index > 0" type="button"
                                class="h-9 w-9 shrink-0 inline-flex items-center justify-center rounded-md border border-stroke bg-white hover:bg-hover text-sub transition-colors"
                                :aria-label="`Remove this period from ${row.label}`"
                                @click="removePeriod(day, index)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 7h14M10 7V5.5h4V7M8 7l.7 12h6.6L16 7" stroke="currentColor"
                                      stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <span v-else-if="splitPeriods" class="h-9 w-9 shrink-0" aria-hidden="true"></span>
                    </div>

                    <button v-if="splitPeriods" type="button" @click="addPeriod(day)"
                            class="text-[13px] font-medium text-link hover:underline">
                        + Add another period
                    </button>
                </div>

                <span v-else class="ml-auto text-[13px] text-faint">Closed all day</span>

                <p v-if="errors[day]" class="w-full text-[12px] text-danger">{{ errors[day] }}</p>
            </div>
        </div>
    </section>
</template>
