<script setup>
/**
 * Somewhere to sign.
 *
 * A canvas rather than an image upload or a typed name alone, because a
 * waiver's value is partly in the mark itself — and because a client on a
 * phone has a finger and no way to attach a photograph of a signature.
 *
 * Pointer events, not mouse and touch separately: one set of handlers covers
 * a finger, a stylus and a mouse, and the browser normalises the rest.
 *
 * The value is a PNG data URL, which is what `form_submissions.signature`
 * already holds.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    clearLabel: { type: String, default: 'Clear' },
    hint: { type: String, default: '' },
    radius: { type: String, default: '8px' },
});

const emit = defineEmits(['update:modelValue']);

const pad = ref(null);
const drawn = ref(false);

let context = null;
let drawing = false;

/**
 * Sized to the pixels it actually occupies.
 *
 * A canvas has a CSS size and a bitmap size, and leaving them to disagree is
 * why hand-rolled signature pads draw a line an inch from the finger holding
 * the stylus.
 */
function fit() {
    const canvas = pad.value;

    if (!canvas) return;

    const box = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    const existing = drawn.value ? canvas.toDataURL() : null;

    canvas.width = Math.round(box.width * ratio);
    canvas.height = Math.round(box.height * ratio);

    context = canvas.getContext('2d');
    context.scale(ratio, ratio);
    context.lineWidth = 2;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#23272f';

    /* A resize must not wipe a signature somebody has already given. */
    if (existing) {
        const image = new Image();
        image.onload = () => context.drawImage(image, 0, 0, box.width, box.height);
        image.src = existing;
    }
}

function point(event) {
    const box = pad.value.getBoundingClientRect();

    return { x: event.clientX - box.left, y: event.clientY - box.top };
}

function start(event) {
    drawing = true;
    pad.value.setPointerCapture(event.pointerId);

    const { x, y } = point(event);

    context.beginPath();
    context.moveTo(x, y);
}

function move(event) {
    if (!drawing) return;

    /* Preventing the default is what stops a finger scrolling the page
       instead of drawing on the pad. */
    event.preventDefault();

    const { x, y } = point(event);

    context.lineTo(x, y);
    context.stroke();
    drawn.value = true;
}

function end() {
    if (!drawing) return;

    drawing = false;
    emit('update:modelValue', drawn.value ? pad.value.toDataURL('image/png') : '');
}

function clear() {
    context.clearRect(0, 0, pad.value.width, pad.value.height);
    drawn.value = false;
    emit('update:modelValue', '');
}

onMounted(() => {
    fit();
    window.addEventListener('resize', fit);
});

onBeforeUnmount(() => window.removeEventListener('resize', fit));
</script>

<template>
  <div>
    <div class="relative border border-line bg-white" :style="{ borderRadius: radius }">
      <canvas ref="pad" class="block w-full h-24 touch-none cursor-crosshair"
              :style="{ borderRadius: radius }"
              @pointerdown="start" @pointermove="move" @pointerup="end" @pointerleave="end"></canvas>

      <p v-if="!drawn" class="absolute inset-0 grid place-items-center text-[12px] text-faint pointer-events-none">
        {{ hint }}
      </p>
    </div>

    <button v-if="drawn" type="button" class="styledesk_action !h-7 !px-2 !text-[12px] mt-1.5" @click="clear">
      {{ clearLabel }}
    </button>
  </div>
</template>
