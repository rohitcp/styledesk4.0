<script setup>
/**
 * Smoke-test island proving the Blade -> Vue handoff.
 *
 * Blade renders the element and serializes props into data-props; app.js finds
 * it, resolves this component and mounts it. Delete once a real component
 * exercises the same path.
 */
defineProps({
    tenant: { type: String, default: 'central' },
});
</script>

<template>
    <p class="rounded-card border border-line bg-sel px-3 py-2">
        Vue island mounted. Tenant: <strong class="text-brand">{{ tenant }}</strong>
    </p>
</template>
