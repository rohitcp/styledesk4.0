<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import CategoryPicker from './CategoryPicker.vue';
import CategoryModal from './CategoryModal.vue';
import ColorPicker from './ColorPicker.vue';
import ServiceImages from './ServiceImages.vue';
import ConfirmDialog from './ConfirmDialog.vue';
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
    /* Passed down to every row's picker rather than looked up inside it: the
       component holds no routes and no English of its own, so the same one
       serves the Services module. */
    imageUploadUrl: { type: String, default: '' },
    imageDeleteUrl: { type: String, default: '' },
    imageLabels: { type: Object, default: () => ({}) },
    maxOtherImages: { type: Number, default: 10 },
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

/**
 * A key that belongs to the row rather than to its position.
 *
 * The list used to be keyed by index, which is fine while every field is
 * v-modelled straight onto the row object. The image picker is not — it keeps
 * its own uploaded files — so an index key hands row 2's pictures to row 3
 * the moment row 2 is removed. A key that moves with the row cannot.
 */
let nextUid = 0;

const blank = () => ({
    uid: nextUid++,
    name: '',
    service_category_id: null,
    duration_minutes: 30,
    // One entry per configured currency, so every field is bound from the start.
    prices: Object.fromEntries(props.currencies.map((c) => [c.code, ''])),
    description: '',
    online_booking_enabled: true,
    taxable: true,
    color: '#3d348b',
    images: [],
    default_image_id: null,
});

const rows = ref(props.initial.length ? props.initial.map((r) => ({ ...blank(), ...r })) : [blank()]);

// Published for the preview island in the other column.
watch(rows, (value) => { onboarding.services = value; }, { deep: true, immediate: true });

function add() {
    rows.value.push(blank());
}

/**
 * Which row the confirmation is asking about, or null when it is closed.
 *
 * An index rather than a boolean plus a separate "pending" variable: two
 * pieces of state can disagree, and the disagreement here would discard the
 * wrong service.
 */
const pendingRemoval = ref(null);

const confirmMessage = computed(() => {
    const row = pendingRemoval.value === null ? null : rows.value[pendingRemoval.value];

    if (!row) {
        return '';
    }

    const name = row.name.trim();
    const subject = name ? `“${name}”` : 'This service';

    // The last row is cleared rather than deleted, so say so — otherwise the
    // row reappearing empty reads as the Remove having failed.
    return rows.value.length === 1
        ? `${subject} will be cleared. Its name, price and duration are not saved yet, so they cannot be recovered.`
        : `${subject} will be removed. Its name, price and duration are not saved yet, so they cannot be recovered.`;
});

/**
 * Whether this row holds anything a reader would mind losing.
 *
 * Duration and colour are deliberately not counted: both arrive filled in, so
 * counting them would make every row "in progress" from the moment it was
 * added and put a dialog in front of removing a row nobody had touched.
 */
function hasContent(row) {
    return Boolean(
        (row.name ?? '').trim()
        || (row.description ?? '').trim()
        || row.service_category_id
        || row.images?.length
        || Object.values(row.prices ?? {}).some((price) => String(price ?? '').trim()),
    );
}

/**
 * Ask before throwing away work; remove an untouched row on the spot.
 *
 * A confirmation for an empty row is a dialog that can only ever be answered
 * one way — it teaches the reader to dismiss the dialog without reading it,
 * which is exactly the habit that makes the confirmation on a full row
 * useless.
 */
function requestRemove(index) {
    if (hasContent(rows.value[index])) {
        pendingRemoval.value = index;

        return;
    }

    removeAt(index);
}

function removeAt(index) {
    rows.value.splice(index, 1);

    // Never leave the list empty: an empty repeater gives the user nothing
    // to type into and looks broken.
    if (!rows.value.length) {
        rows.value.push(blank());
    }
}

function removeConfirmed() {
    if (pendingRemoval.value !== null) {
        removeAt(pendingRemoval.value);
    }

    pendingRemoval.value = null;
}
</script>

<template>
    <div class="space-y-3">
        <div v-for="(row, i) in rows" :key="row.uid" class="rounded-card border border-line bg-white p-4 space-y-4">

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

                <!-- Three per row, wrapping onto the next. A fixed column count
                     rather than auto-fit keeps every field the same width
                     whether one currency is enabled or five, so the row does
                     not reflow into odd sizes as currencies are added. -->
                <div class="grid sm:grid-cols-3 gap-x-4 gap-y-3">
                    <div v-for="currency in currencies" :key="currency.code" class="min-w-0">
                        <label :for="`service-${i}-price-${currency.code}`"
                               class="flex flex-wrap items-baseline gap-x-1 text-[12px] text-sub mb-1">
                            <span class="truncate">{{ currency.code }} — {{ currency.label }}</span>
                            <span v-if="currency.primary"
                                  class="text-[10px] font-semibold uppercase tracking-wide text-brand">Primary</span>
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

            <!-- The pictures for this row. Its own name prefix, so the ids
                 arrive alongside the rest of the row rather than in a list
                 the server would have to match up by position. -->
            <div class="pt-1 border-t border-line">
                <!-- The row mirrors what the picker holds, so that removing
                     the row can ask whether there is anything in it. -->
                <ServiceImages :name="`services[${i}]`"
                               :initial="row.images"
                               :initial-default-id="row.default_image_id"
                               :max-others="maxOtherImages"
                               :upload-url="imageUploadUrl"
                               :delete-url="imageDeleteUrl"
                               :labels="imageLabels"
                               @update:images="row.images = $event"
                               @update:default-id="row.default_image_id = $event" />
            </div>

            <div class="flex justify-end pt-1">
                <!-- An icon, not the word. The card already carries a lot of
                     text and "Remove" read as one more field label; a bin in
                     the corner is where a reader looks to throw a row away.

                     data-tip is picked up by the delegated tooltip handler, so
                     a control Vue drew needs no registering. aria-label as
                     well as the tooltip: the tip is a hover affordance, and a
                     screen reader must not be told the button is called
                     nothing. -->
                <button type="button" @click="requestRemove(i)"
                        :data-tip="(row.name ?? '').trim() ? `Remove ${row.name.trim()}` : 'Remove this service'"
                        :aria-label="(row.name ?? '').trim() ? `Remove ${row.name.trim()}` : 'Remove this service'"
                        class="w-8 h-8 grid place-items-center rounded-md text-sub hover:text-danger hover:bg-hover transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4.5 7h15M9.5 7V5.5a1 1 0 011-1h3a1 1 0 011 1V7M6.5 7l.8 11.2a1.5 1.5 0 001.5 1.3h6.4a1.5 1.5 0 001.5-1.3L17.5 7"
                              stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M10.5 10.5v6M13.5 10.5v6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <button type="button" @click="add"
                class="styledesk_action">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 5.5v13M5.5 12h13" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
            </svg>
            Add another service
        </button>

        <CategoryModal :open="addingForRow !== null"
                       @created="onCategoryCreated"
                       @close="addingForRow = null" />

        <!-- One dialog for the whole repeater, not one per row: N dialogs mean
             N Escape handlers competing for the same keypress. -->
        <ConfirmDialog :open="pendingRemoval !== null" title="Remove this service?"
                       :message="confirmMessage" confirm-label="Remove"
                       @confirm="removeConfirmed" @cancel="pendingRemoval = null" />
    </div>
</template>
