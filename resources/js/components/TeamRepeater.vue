<script setup>
import { ref, watch } from 'vue';
import { onboarding } from '../stores/onboarding';

/**
 * Repeatable team member rows for onboarding step 4.
 *
 * The owner is not editable here: the server seeds them as staff member one
 * from the signed-in user, so this list is only the people being invited.
 */
defineProps({
    initial: { type: Array, default: () => [] },
});

const blank = () => ({ first_name: '', last_name: '', email: '', phone: '', role: 'service-provider', job_title: '' });

const rows = ref([]);

watch(rows, (value) => { onboarding.team = value; }, { deep: true, immediate: true });

function add() {
    rows.value.push(blank());
}

function remove(index) {
    rows.value.splice(index, 1);
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
                           type="text" class="sd-input" data-capitalize>
                </div>
                <div>
                    <label :for="`member-last-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Last name</label>
                    <input :id="`member-last-${i}`" v-model="row.last_name" :name="`members[${i}][last_name]`"
                           type="text" class="sd-input" data-capitalize>
                </div>
                <div>
                    <label :for="`member-title-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">
                        Job title <span class="text-faint font-normal">(optional)</span>
                    </label>
                    <input :id="`member-title-${i}`" v-model="row.job_title" :name="`members[${i}][job_title]`"
                           type="text" class="sd-input" data-capitalize placeholder="Senior Stylist">
                </div>
                <div>
                    <label :for="`member-email-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Email</label>
                    <input :id="`member-email-${i}`" v-model="row.email" :name="`members[${i}][email]`"
                           type="email" class="sd-input">
                </div>
                <div>
                    <label :for="`member-role-${i}`" class="block text-[13px] font-medium text-ink mb-1.5">Role</label>
                    <select :id="`member-role-${i}`" v-model="row.role" :name="`members[${i}][role]`" class="sd-input">
                        <option value="administrator">Administrator</option>
                        <option value="manager">Manager</option>
                        <option value="front-desk">Front desk</option>
                        <option value="service-provider">Service provider</option>
                    </select>
                </div>
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
            Add team member
        </button>
    </div>
</template>
