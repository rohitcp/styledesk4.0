<script setup>
import { nextTick, ref, watch } from 'vue';

/**
 * Confirmation before a destructive action.
 *
 * Deliberately not window.confirm(): that dialog cannot be styled, reads in
 * the browser's own voice rather than the product's, and — the reason it
 * matters here — blocks the page thread, which stalls every Vue island until
 * it is dismissed.
 *
 * The parent owns the decision. This component only asks; it does not know
 * what it is confirming, so one instance serves any row in any repeater.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Remove' },
    cancelLabel: { type: String, default: 'Cancel' },
});

const emit = defineEmits(['confirm', 'cancel']);

/**
 * Focus lands on Cancel, not Confirm.
 *
 * A dialog that opens with the destructive button focused turns a stray Enter
 * — the very keypress that may have opened it — into the deletion it was
 * supposed to guard against.
 */
const cancelButton = ref(null);

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        nextTick(() => cancelButton.value?.focus());
    }
});
</script>

<template>
    <div v-if="open" class="styledesk_modal" role="dialog" aria-modal="true"
         aria-labelledby="confirm-dialog-title" @click.self="emit('cancel')" @keydown.esc="emit('cancel')">
        <div class="styledesk_modal__dialog" style="max-width: 380px">
            <div class="styledesk_modal__head">
                <h2 id="confirm-dialog-title" class="styledesk_modal__title">{{ title }}</h2>
            </div>

            <div v-if="message" class="styledesk_modal__body">
                <p class="text-[13px] text-sub leading-relaxed">{{ message }}</p>
            </div>

            <div class="styledesk_modal__foot">
                <button ref="cancelButton" type="button" @click="emit('cancel')"
                        class="h-9 px-4 rounded-md text-[13px] font-semibold text-sub hover:bg-hover transition-colors">
                    {{ cancelLabel }}
                </button>
                <button type="button" @click="emit('confirm')"
                        class="h-9 px-4 rounded-md bg-danger hover:brightness-95 text-white text-[13px] font-semibold transition-colors">
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
