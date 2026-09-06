<script setup>
import MultiSelect from './MultiSelect.vue';

/**
 * One answer, bound to a plain value.
 *
 * MultiSelect in `single` mode already behaves exactly like this — the
 * search, the panel, the outside-click and the keyboard handling are the ones
 * every other choice on the screen uses — but its model is a list, because
 * that is what it is for. A form with a dozen single choices in it would
 * otherwise carry a dozen copies of the same two-line adapter, and the
 * thirteenth would be the one written slightly differently.
 *
 * Deliberately not a bare <select>: a native dropdown beside an `sd-input` is
 * the one control that looks like it belongs to another form.
 */
defineProps({
    /** id => label, as MultiSelect takes them. */
    options: { type: Object, default: () => ({}) },
    modelValue: { type: [String, Number], default: '' },
    placeholder: { type: String, default: '' },
    searchPlaceholder: { type: String, default: '' },
    ariaLabel: { type: String, default: '' },
    name: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <MultiSelect :options="options"
                 :model-value="modelValue === '' || modelValue === null ? [] : [modelValue]"
                 single
                 :name="name"
                 :placeholder="placeholder"
                 :search-placeholder="searchPlaceholder || placeholder"
                 :aria-label="ariaLabel || placeholder"
                 @update:model-value="(values) => emit('update:modelValue', values[0] ?? '')" />
</template>
