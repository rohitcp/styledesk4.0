<script setup>
import { computed } from 'vue';
import { onboarding } from '../stores/onboarding';

/**
 * Who ends up in the workspace: the owner, who is always there, plus whoever
 * is being invited in the repeater.
 */
const props = defineProps({
    ownerName: { type: String, default: '' },
    ownerInitials: { type: String, default: '' },
});

const invited = computed(() =>
    onboarding.team.filter((m) => `${m.first_name || ''}${m.last_name || ''}`.trim() !== '')
);

const summary = computed(() =>
    invited.value.length
        ? `You and ${invited.value.length} ${invited.value.length === 1 ? 'other' : 'others'}`
        : 'Just you for now.'
);

function initials(member) {
    return ((member.first_name || '').charAt(0) + (member.last_name || '').charAt(0)).toUpperCase();
}

function label(member) {
    return `${member.first_name || ''} ${member.last_name || ''}`.trim();
}
</script>

<template>
    <div class="rounded-card border border-line bg-white shadow-sm overflow-hidden" aria-hidden="true">
        <ul class="divide-y divide-line">
            <li class="flex items-center gap-3 px-4 py-3">
                <span class="sd-avatar sd-avatar--sm">{{ ownerInitials }}</span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-medium text-head truncate">{{ ownerName }}</span>
                    <span class="block text-[12px] text-sub">Owner</span>
                </span>
            </li>

            <li v-for="(member, i) in invited" :key="i" class="flex items-center gap-3 px-4 py-3">
                <span class="sd-avatar sd-avatar--sm">{{ initials(member) }}</span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-medium text-head truncate">{{ label(member) }}</span>
                    <span class="block text-[12px] text-sub capitalize">{{ (member.role || '').replace('-', ' ') }}</span>
                </span>
            </li>
        </ul>

        <div class="px-4 py-2.5 bg-hover/40 border-t border-line">
            <p class="text-[12px] text-sub">{{ summary }}</p>
        </div>
    </div>
</template>
