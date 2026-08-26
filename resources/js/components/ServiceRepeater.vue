<script setup>
import { ref, watch } from 'vue';
import { onboarding } from '../stores/onboarding';

/**
 * Repeatable service rows for onboarding step 3.
 *
 * A Vue island rather than a full page: Blade still owns the wizard, the form
 * and the submit. This component only manages the rows, and posts them as
 * ordinary `services[i][field]` inputs so the server sees a normal form
 * submission with no JSON endpoint involved.
 */
const props = defineProps({
    initial: { type: Array, default: () => [] },
    currency: { type: String, default: 'USD' },
});

const blank = () => ({
    name: '',
    category: '',
    duration_minutes: 30,
    price: '',
    description: '',
    online_booking_enabled: true,
    taxable: true,
    color: '#3d348b',
});

const rows = ref(props.initial.length ? props.initial.map((r) => ({ ...blank(), ...r })) : [blank()]);

// Published for the preview island in the other column.
watch(rows, (value) => { onboarding.services = value; }, { deep: true, immediate: true });

function add() {
    rows.value.push(blank());
}

function remove(index) {
    rows.value.splice(index, 1);

    // Never leave the list empty: an empty repeater gives the user nothing to
    // type into and looks broken.
    if (!rows.value.length) {
        rows.value.push(blank());
    }
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="(row, i) in rows" :key="i" class="rounded-card border border-line bg-white p-4">
            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
                <div>
                    <label :for="`service-name-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Service name</label>
                    <input :id="`service-name-${i}`" v-model="row.name" :name="`services[${i}][name]`"
                           type="text" class="sd-input" data-capitalize placeholder="Women's Cut &amp; Finish">
                </div>
                <div>
                    <label :for="`service-category-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Category</label>
                    <input :id="`service-category-${i}`" v-model="row.category" :name="`services[${i}][category]`"
                           type="text" class="sd-input" data-capitalize placeholder="Hair">
                </div>
                <div>
                    <label :for="`service-duration-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Duration (minutes)</label>
                    <input :id="`service-duration-${i}`" v-model="row.duration_minutes" :name="`services[${i}][duration_minutes]`"
                           type="number" min="1" max="1440" class="sd-input">
                </div>
                <div>
                    <label :for="`service-price-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Price ({{ currency }})</label>
                    <input :id="`service-price-${i}`" v-model="row.price" :name="`services[${i}][price]`"
                           type="number" step="0.01" min="0" class="sd-input" placeholder="0.00">
                </div>
            </div>

            <div class="mt-4">
                <label :for="`service-description-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">
                    Description <span class="text-faint font-normal">(optional)</span>
                </label>
                <textarea :id="`service-description-${i}`" v-model="row.description" :name="`services[${i}][description]`"
                          rows="2" class="sd-input h-auto py-2"></textarea>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input v-model="row.online_booking_enabled" :name="`services[${i}][online_booking_enabled]`"
                           type="checkbox" value="1" class="sd-check">
                    <span class="text-[13px] text-ink">Bookable online</span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input v-model="row.taxable" :name="`services[${i}][taxable]`"
                           type="checkbox" value="1" class="sd-check">
                    <span class="text-[13px] text-ink">Taxable</span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <span class="text-[13px] text-ink">Calendar colour</span>
                    <input v-model="row.color" :name="`services[${i}][color]`"
                           type="color" class="h-8 w-10 rounded border border-stroke bg-white p-0.5">
                </label>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="button" @click="remove(i)"
                        class="h-8 px-3 rounded-md text-[13px] font-semibold text-sub hover:text-danger hover:bg-hover transition-colors">
                    Remove
                </button>
            </div>
        </div>

        <button type="button" @click="add"
                class="h-10 px-4 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            Add another service
        </button>
    </div>
</template>
