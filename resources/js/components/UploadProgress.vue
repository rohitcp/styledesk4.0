<script setup>
/**
 * How far an upload has actually got.
 *
 * Its own component because three places need the same bar — the two upload
 * forms and the card menu's "add more images" / "replace" — and a bar drawn
 * three times is a bar that eventually reads differently in one of them.
 *
 * Two states, and they are different facts. While bytes are still going up
 * there is a real percentage to show. Once they are all up the server is
 * still working — writing six photographs into storage takes a moment — and
 * the honest thing to show then is that it is busy, not a bar frozen at 100%
 * that looks like it has hung.
 */
defineProps({
    /** 0–100 while uploading, null when there is nothing to report. */
    value: { type: Number, default: null },
    uploadingLabel: { type: String, default: '' },
    processingLabel: { type: String, default: '' },
});
</script>

<template>
    <div v-if="value !== null" class="w-full">
        <div class="flex items-center justify-between text-[12px] text-sub mb-1">
            <span>{{ value < 100 ? uploadingLabel : processingLabel }}</span>
            <span v-if="value < 100" class="font-mono">{{ value }}%</span>
        </div>

        <div class="h-1.5 w-full rounded-full bg-hover overflow-hidden"
             role="progressbar" :aria-valuenow="value" aria-valuemin="0" aria-valuemax="100"
             :aria-label="value < 100 ? uploadingLabel : processingLabel">
            <!-- Once the bytes are up the bar stops being a measurement, so
                 it stops pretending to be one and animates instead. -->
            <div class="h-full bg-brand transition-[width] duration-150"
                 :class="value >= 100 ? 'styledesk_progress--working' : ''"
                 :style="{ width: `${Math.max(value, 8)}%` }"></div>
        </div>
    </div>
</template>
