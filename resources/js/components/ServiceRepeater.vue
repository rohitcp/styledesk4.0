<script setup>
import { onMounted, ref, watch } from 'vue';
import CategoryPicker from './CategoryPicker.vue';
import CategoryModal from './CategoryModal.vue';
import ColorPicker from './ColorPicker.vue';
import { setCategories } from '../stores/categories';
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
    currencies: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    canCreateCategory: { type: Boolean, default: false },
});

// Seed the shared store once; every picker on the page reads from it.
onMounted(() => setCategories(props.categories));

/**
 * Which row asked for the modal.
 *
 * One dialog serves every row, so it has to remember who opened it in order to
 * select the new category back onto that row rather than the first one.
 */
const addingForRow = ref(null);

function openCategoryModal(index) {
    addingForRow.value = index;
}

function onCategoryCreated(category) {
    if (addingForRow.value !== null && rows.value[addingForRow.value]) {
        rows.value[addingForRow.value].service_category_id = category.id;
    }
}

const blank = () => ({
    name: '',
    service_category_id: null,
    duration_minutes: 30,
    // One entry per configured currency, so every field is bound from the start.
    prices: Object.fromEntries(props.currencies.map((c) => [c.code, ''])),
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
        <div v-for="(row, i) in rows" :key="i" class="rounded-card border border-line bg-white p-4 space-y-4">

            <!-- Name takes the full width: it is the longest value on the card
                 and the one people scan the list by. -->
            <div>
                <label :for="`service-name-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Service name</label>
                <input :id="`service-name-${i}`" v-model="row.name" :name="`services[${i}][name]`"
                       type="text" class="sd-input" v-capitalize placeholder="Women's Cut &amp; Finish">
            </div>

            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
                <div>
                    <label class="block text-[13px] font-medium text-ink mb-1.5">Service category</label>
                    <CategoryPicker v-model="row.service_category_id"
                                    :name="`services[${i}][service_category_id]`"
                                    :aria-label="`Service ${i + 1} category`" />

                    <!-- Outside the combo, so the dropdown lists categories and
                         nothing else. -->
                    <button v-if="canCreateCategory" type="button" class="styledesk_addlink"
                            @click="openCategoryModal(i)">
                        + Add Category
                    </button>
                </div>

                <div>
                    <label :for="`service-duration-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Duration (minutes)</label>
                    <input :id="`service-duration-${i}`" v-model="row.duration_minutes" :name="`services[${i}][duration_minutes]`"
                           type="number" min="1" max="1440" class="sd-input">
                </div>
            </div>

            <div>
                <p class="block text-[13px] font-medium text-ink mb-1.5">Pricing</p>

                <div class="grid sm:grid-cols-2 gap-x-4 gap-y-3">
                    <div v-for="currency in currencies" :key="currency.code">
                        <label :for="`service-${i}-price-${currency.code}`" class="block text-[12px] text-sub mb-1">
                            {{ currency.code }} — {{ currency.label }}
                            <span v-if="currency.primary"
                                  class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-brand">Primary</span>
                        </label>
                        <div class="relative">
                            <span class="styledesk_input__prefix">{{ currency.symbol }}</span>
                            <input :id="`service-${i}-price-${currency.code}`"
                                   v-model="row.prices[currency.code]"
                                   :name="`services[${i}][prices][${currency.code}]`"
                                   type="number" step="0.01" min="0" class="sd-input styledesk_input--prefixed" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label :for="`service-description-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">
                    Description <span class="text-faint font-normal">(optional)</span>
                </label>
                <textarea :id="`service-description-${i}`" v-model="row.description" :name="`services[${i}][description]`"
                          rows="2" class="sd-input h-auto py-2"></textarea>
            </div>

            <!-- Each switch on its own row with its label above it, so the two
                 settings read as separate decisions rather than one pair. -->
            <div>
                <p class="block text-[13px] font-medium text-ink mb-1.5">Bookable online</p>
                <label class="flex items-center gap-2.5 cursor-pointer w-fit">
                    <input v-model="row.online_booking_enabled" :name="`services[${i}][online_booking_enabled]`"
                           type="checkbox" value="1" class="sd-switch">
                    <span class="text-[13px]" :class="row.online_booking_enabled ? 'text-ink' : 'text-faint'">
                        {{ row.online_booking_enabled ? 'Shown on your booking page' : 'Internal only' }}
                    </span>
                </label>
            </div>

            <div>
                <p class="block text-[13px] font-medium text-ink mb-1.5">Taxable</p>
                <label class="flex items-center gap-2.5 cursor-pointer w-fit">
                    <input v-model="row.taxable" :name="`services[${i}][taxable]`"
                           type="checkbox" value="1" class="sd-switch">
                    <span class="text-[13px]" :class="row.taxable ? 'text-ink' : 'text-faint'">
                        {{ row.taxable ? 'Tax applies' : 'No tax' }}
                    </span>
                </label>
            </div>

            <div>
                <p class="block text-[13px] font-medium text-ink mb-1.5">Calendar colour</p>
                <ColorPicker v-model="row.color" :name="`services[${i}][color]`" />
            </div>

            <div class="flex justify-end pt-1">
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

        <CategoryModal :open="addingForRow !== null"
                       @created="onCategoryCreated"
                       @close="addingForRow = null" />
    </div>
</template>
