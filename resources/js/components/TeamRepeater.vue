<script setup>
import { computed, ref, watch } from 'vue';
import { onboarding } from '../stores/onboarding';
import ConfirmDialog from './ConfirmDialog.vue';
import MultiSelect from './MultiSelect.vue';

/**
 * Repeatable team member rows for onboarding step 4.
 *
 * The owner is not editable here: the server seeds them as staff member one
 * from the signed-in user, so this list is only the people being invited.
 */
defineProps({
    initial: { type: Array, default: () => [] },
});

const ROLES = {
    'administrator': 'Administrator',
    'manager': 'Manager',
    'front-desk': 'Front desk',
    'service-provider': 'Service provider',
};

const blank = () => ({ first_name: '', last_name: '', email: '', phone: '', role: 'service-provider', job_title: '' });

const rows = ref([]);

watch(rows, (value) => { onboarding.team = value; }, { deep: true, immediate: true });

function add() {
    rows.value.push(blank());
}

/**
 * Which row the confirmation is asking about, or null when it is closed.
 *
 * An index rather than a boolean plus a separate "pending" variable: two
 * pieces of state can disagree, and the disagreement here would delete the
 * wrong person.
 */
const pendingRemoval = ref(null);

const pendingName = computed(() => {
    const row = pendingRemoval.value === null ? null : rows.value[pendingRemoval.value];

    if (!row) {
        return '';
    }

    return `${row.first_name} ${row.last_name}`.trim();
});

const confirmMessage = computed(() => (pendingName.value
    ? `${pendingName.value} will be taken off the list. Nothing has been sent yet, so they will not be notified.`
    : 'This row will be removed from the list.'));

function confirmRemove(index) {
    pendingRemoval.value = index;
}

function removeConfirmed() {
    if (pendingRemoval.value !== null) {
        rows.value.splice(pendingRemoval.value, 1);
    }

    pendingRemoval.value = null;
}
</script>

<template>
    <div class="space-y-3">
        <p v-if="!rows.length" class="text-[13px] text-sub">
            No one added yet. You can invite the rest of your team whenever you like.
        </p>

        <div v-for="(row, i) in rows" :key="i" class="rounded-card border border-line bg-white p-4">
            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
                <div>
                    <label :for="`member-first-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">First name</label>
                    <input :id="`member-first-${i}`" v-model="row.first_name" :name="`members[${i}][first_name]`"
                           type="text" class="sd-input" v-capitalize>
                </div>
                <div>
                    <label :for="`member-last-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Last name</label>
                    <input :id="`member-last-${i}`" v-model="row.last_name" :name="`members[${i}][last_name]`"
                           type="text" class="sd-input" v-capitalize>
                </div>
                <div>
                    <label :for="`member-title-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">
                        Job title <span class="text-faint font-normal">(optional)</span>
                    </label>
                    <input :id="`member-title-${i}`" v-model="row.job_title" :name="`members[${i}][job_title]`"
                           type="text" class="sd-input" v-capitalize placeholder="Senior Stylist">
                </div>
                <div>
                    <label :for="`member-email-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Email</label>
                    <input :id="`member-email-${i}`" v-model="row.email" :name="`members[${i}][email]`"
                           type="email" class="sd-input">
                </div>
                <div>
                    <span class="block text-[13px] font-medium text-ink mb-1.5">Role</span>
                    <!-- Single mode, so the panel marks the chosen role with a
                         tick. A checkbox here would suggest a person could
                         hold two roles at once, which the server rejects. -->
                    <MultiSelect :options="ROLES" :model-value="[row.role]" :name="`members[${i}][role]`"
                                 single placeholder="Select a role" search-placeholder="Search roles…"
                                 :aria-label="`Role for team member ${i + 1}`"
                                 @update:model-value="(value) => { row.role = value[0] ?? 'service-provider'; }" />
                </div>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="button" @click="confirmRemove(i)"
                        class="h-8 px-3 rounded-md text-[13px] font-semibold text-sub hover:text-danger hover:bg-hover transition-colors">
                    Remove
                </button>
            </div>
        </div>

        <button type="button" @click="add"
                class="styledesk_action">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 5.5v13M5.5 12h13" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
            </svg>
            Add team member
        </button>

        <!-- One dialog for the whole repeater, not one per row: N dialogs mean
             N Escape handlers competing for the same keypress. -->
        <ConfirmDialog :open="pendingRemoval !== null" title="Remove this team member?"
                       :message="confirmMessage" confirm-label="Remove"
                       @confirm="removeConfirmed" @cancel="pendingRemoval = null" />
    </div>
</template>
