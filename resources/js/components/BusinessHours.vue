<script setup>
import { nextTick, onMounted, reactive, watch } from 'vue';
import TimePicker from './TimePicker.vue';

/**
 * The seven opening-hour rows for onboarding step 2.
 *
 * The whole table is one island rather than fourteen separate pickers, because
 * "Copy Monday to Tuesday–Friday" has to write across rows. Done as loose
 * pickers, that copy would mean reaching into other components' DOM and
 * setting values that Vue would then overwrite on its next render.
 *
 * Each row posts ordinary hours[day][field] inputs, so the server sees a plain
 * form submission and the existing validation is untouched.
 */
const props = defineProps({
    days: { type: Array, default: () => [] },
    initial: { type: Array, default: () => [] },
});

const DAY_LABELS = props.days.length
    ? props.days
    : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const rows = reactive(
    DAY_LABELS.map((label, day) => {
        const saved = props.initial[day] || {};

        return {
            label,
            isOpen: saved.is_open ?? day !== 0,
            opensAt: saved.opens_at || '09:00',
            closesAt: saved.closes_at || '17:00',
        };
    })
);

function copyMondayToWeek() {
    const monday = rows[1];

    [2, 3, 4, 5].forEach((day) => {
        rows[day].isOpen = monday.isOpen;
        rows[day].opensAt = monday.opensAt;
        rows[day].closesAt = monday.closesAt;
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
                <h2 class="text-[15px] font-semibold text-head">Business hours</h2>
                <p class="text-[13px] text-sub mt-0.5">
                    Your default booking availability. Staff schedules can override this later.
                </p>
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
                <div v-if="row.isOpen" class="flex items-center gap-2 ml-auto">
                    <div class="w-[124px]">
                        <TimePicker v-model="row.opensAt" :name="`hours[${day}][opens_at]`"
                                    :aria-label="`${row.label} opening time`" />
                    </div>

                    <span class="text-[13px] text-faint">to</span>

                    <div class="w-[124px]">
                        <TimePicker v-model="row.closesAt" :name="`hours[${day}][closes_at]`"
                                    :aria-label="`${row.label} closing time`" />
                    </div>
                </div>

                <span v-else class="ml-auto text-[13px] text-faint">Closed all day</span>
            </div>
        </div>
    </section>
</template>
