<script setup>
import { reactive, watch } from 'vue';
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
watch(rows, () => {
    document.getElementById('stepForm')?.dispatchEvent(new Event('change', { bubbles: true }));
}, { deep: true });
</script>

<template>
    <fieldset class="pt-2">
        <div class="flex flex-wrap items-center gap-3 mb-2.5">
            <legend class="text-[13px] font-medium text-ink">Opening hours</legend>
            <button type="button" @click="copyMondayToWeek"
                    class="ml-auto h-8 px-3 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[12px] font-semibold transition-colors">
                Copy Monday to Tuesday–Friday
            </button>
        </div>

        <div class="rounded-card border border-line divide-y divide-line">
            <div v-for="(row, day) in rows" :key="day" class="flex flex-wrap items-center gap-3 px-4 py-3">
                <label class="flex items-center gap-2.5 cursor-pointer w-[150px]">
                    <input v-model="row.isOpen" type="checkbox" :name="`hours[${day}][is_open]`" value="1" class="sd-check">
                    <span class="text-[13px] text-ink">{{ row.label }}</span>
                </label>

                <div class="w-[150px]">
                    <TimePicker v-model="row.opensAt" :name="`hours[${day}][opens_at]`" :disabled="!row.isOpen"
                                :aria-label="`${row.label} opening time`" />
                </div>

                <span class="text-sub">to</span>

                <div class="w-[150px]">
                    <TimePicker v-model="row.closesAt" :name="`hours[${day}][closes_at]`" :disabled="!row.isOpen"
                                :aria-label="`${row.label} closing time`" />
                </div>
            </div>
        </div>
    </fieldset>
</template>
