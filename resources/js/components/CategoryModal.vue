<script setup>
import { nextTick, ref, watch } from 'vue';
import { createCategory } from '../stores/categories';

/**
 * Add a service category.
 *
 * One modal for the whole repeater rather than one per service row: the dialog
 * is page-level furniture, and rendering N of them would mean N focus traps
 * competing for the same Escape key.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const name = ref('');
const error = ref('');
const saving = ref(false);
const nameBox = ref(null);

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        name.value = '';
        error.value = '';
        saving.value = false;
        nextTick(() => nameBox.value?.focus());
    }
});

async function save() {
    if (saving.value || !name.value.trim()) {
        return;
    }

    saving.value = true;
    error.value = '';

    try {
        const created = await createCategory(name.value);

        // The store already inserted it, so every picker on the page has it
        // before this modal closes — no refetch, no page reload.
        emit('created', created);
        emit('close');
    } catch (e) {
        // Stay open on failure: closing would discard what they typed and
        // hide the reason it did not save.
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div v-if="open" class="styledesk_modal" role="dialog" aria-modal="true"
         aria-labelledby="add-category-title" @click.self="emit('close')" @keydown.esc="emit('close')">
        <div class="styledesk_modal__dialog">
            <div class="styledesk_modal__head">
                <h2 id="add-category-title" class="styledesk_modal__title">Add service category</h2>
            </div>

            <div class="styledesk_modal__body">
                <label for="new-category-name" class="block text-[13px] font-medium text-ink mb-1.5">
                    Category name
                </label>
                <input id="new-category-name" ref="nameBox" v-model="name" type="text"
                       class="sd-input" v-capitalize placeholder="Ayurvedic therapy"
                       @keydown.enter.prevent="save">

                <p v-if="error" class="mt-1.5 text-[12px] text-danger">{{ error }}</p>
                <p v-else class="mt-1.5 text-[12px] text-sub">
                    Saved to your business only. Other businesses will not see it.
                </p>
            </div>

            <div class="styledesk_modal__foot">
                <button type="button" @click="emit('close')"
                        class="h-9 px-4 rounded-md text-[13px] font-semibold text-sub hover:bg-hover transition-colors">
                    Cancel
                </button>
                <button type="button" :disabled="saving || !name.trim()" @click="save"
                        class="h-9 px-4 rounded-md bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-45 disabled:pointer-events-none">
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </div>
    </div>
</template>
