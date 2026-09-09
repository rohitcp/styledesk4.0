<script setup>
import { computed, ref } from 'vue';

/**
 * Calendar colour: nine predefined swatches plus one custom.
 *
 * The tenth circle is a real <input type="color"> made invisible and stretched
 * over the swatch, so clicking it opens the operating system's own picker.
 * Reimplementing a colour picker would mean a worse one that behaves
 * differently on every platform.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    name: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const PRESETS = [
    '#3d348b', // brand
    '#2563eb',
    '#0d9488',
    '#16a34a',
    '#ca8a04',
    '#ea580c',
    '#dc2626',
    '#db2777',
    '#6b7280',
];

// What the custom circle shows: the current value when it is not a preset,
// otherwise nothing — an empty circle, as specified.
const customColor = computed(() =>
    props.modelValue && !PRESETS.includes(props.modelValue.toLowerCase()) ? props.modelValue : ''
);

const customInput = ref(null);

function choose(color) {
    emit('update:modelValue', color);
}

function onCustom(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div class="flex items-center gap-2">
        <input v-if="name" type="hidden" :name="name" :value="modelValue">

        <button v-for="color in PRESETS" :key="color" type="button"
                class="h-6 w-6 rounded-full border transition-transform"
                :class="modelValue?.toLowerCase() === color
                    ? 'ring-2 ring-offset-2 ring-brand border-transparent scale-110'
                    : 'border-black/10 hover:scale-110'"
                :style="{ background: color }"
                :aria-label="`Calendar colour ${color}`"
                :aria-pressed="modelValue?.toLowerCase() === color"
                @click="choose(color)"></button>

        <!-- The custom circle. The input sits on top at full size and zero
             opacity, so the click target is the circle itself. -->
        <span class="relative h-6 w-6 shrink-0">
            <span class="block h-6 w-6 rounded-full border-2 border-dashed"
                  :class="customColor ? 'border-transparent ring-2 ring-offset-2 ring-brand' : 'border-stroke'"
                  :style="customColor ? { background: customColor } : {}"></span>

            <input ref="customInput" type="color" :value="customColor || '#3d348b'"
                   class="absolute inset-0 h-6 w-6 cursor-pointer opacity-0"
                   aria-label="Choose a custom calendar colour"
                   @input="onCustom">
        </span>
    </div>
</template>
