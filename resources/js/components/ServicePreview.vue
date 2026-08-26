<script setup>
import { computed } from 'vue';
import { onboarding } from '../stores/onboarding';

/**
 * The service menu as clients would see it, mirroring the repeater in the
 * other column through the shared store.
 */
const props = defineProps({
    currency: { type: String, default: 'USD' },
});

const named = computed(() => onboarding.services.filter((s) => (s.name || '').trim() !== ''));

const summary = computed(() => {
    if (!named.value.length) {
        return 'Nothing added yet.';
    }

    const bookable = named.value.filter((s) => s.online_booking_enabled).length;

    return `${named.value.length} ${named.value.length === 1 ? 'service' : 'services'}, ${bookable} bookable online`;
});

function price(row) {
    const value = parseFloat(row.price);

    return Number.isFinite(value) ? `${props.currency} ${value.toFixed(2)}` : '—';
}
</script>

<template>
    <div class="rounded-card border border-line bg-white shadow-sm overflow-hidden" aria-hidden="true">
        <ul class="divide-y divide-line">
            <li v-for="(row, i) in named" :key="i" class="flex items-start gap-3 px-4 py-3">
                <span class="h-2.5 w-2.5 rounded-full mt-1.5 shrink-0" :style="{ background: row.color || '#3d348b' }"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-medium text-head truncate">{{ row.name }}</span>
                    <span class="block text-[12px] text-sub">{{ row.duration_minutes }} min · {{ price(row) }}</span>
                </span>
            </li>
        </ul>

        <div class="px-4 py-2.5 bg-hover/40 border-t border-line">
            <p class="text-[12px] text-sub">{{ summary }}</p>
        </div>
    </div>
</template>
