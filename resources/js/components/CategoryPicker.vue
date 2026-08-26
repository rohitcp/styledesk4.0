<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { categories, createCategory } from '../stores/categories';

/**
 * Searchable service-category picker with an inline "add new" step.
 *
 * The list is shared across every row through the categories store, so a
 * category created on one service row appears immediately on all of them
 * without a page reload.
 */
const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    name: { type: String, default: null },
    canCreate: { type: Boolean, default: false },
    ariaLabel: { type: String, default: 'Service category' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const adding = ref(false);
const query = ref('');
const draftName = ref('');
const error = ref('');
const saving = ref(false);
const root = ref(null);
const searchBox = ref(null);
const draftBox = ref(null);

const selected = computed(() => categories.items.find((c) => String(c.id) === String(props.modelValue)) || null);

const matches = computed(() => {
    const q = query.value.trim().toLowerCase();

    return q ? categories.items.filter((c) => c.name.toLowerCase().includes(q)) : categories.items;
});

function toggle() {
    open.value = !open.value;

    if (open.value) {
        adding.value = false;
        query.value = '';
        nextTick(() => searchBox.value?.focus());
    }
}

function choose(category) {
    emit('update:modelValue', category.id);
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    open.value = false;
}

function startAdding() {
    adding.value = true;
    // Seed it with whatever was typed: the usual reason for adding is that a
    // search came back empty.
    draftName.value = query.value.trim();
    error.value = '';
    nextTick(() => draftBox.value?.focus());
}

async function save() {
    if (saving.value) {
        return;
    }

    saving.value = true;
    error.value = '';

    try {
        const created = await createCategory(draftName.value);
        emit('update:modelValue', created.id);
        open.value = false;
        adding.value = false;
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

function onDocumentClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
        adding.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        open.value = false;
        adding.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="styledesk_timepicker">
        <input v-if="name" type="hidden" :name="name" :value="modelValue ?? ''">

        <button type="button" class="styledesk_timepicker__field" :aria-label="ariaLabel"
                :aria-expanded="open" aria-haspopup="listbox" @click.stop="toggle">
            <span class="styledesk_timepicker__value" :class="{ 'styledesk_timepicker__value--empty': !selected }">
                {{ selected ? selected.name : 'Search or select category' }}
            </span>
            <svg class="styledesk_timepicker__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div v-if="open" class="styledesk_timepicker__panel" style="width: 280px">
            <template v-if="!adding">
                <div class="p-2 border-b border-line">
                    <input ref="searchBox" v-model="query" type="text" class="sd-input h-9 text-[13px]"
                           placeholder="Search category…" autocomplete="off">
                </div>

                <div class="max-h-[196px] overflow-y-auto">
                    <button v-if="selected" type="button" class="styledesk_timepicker__opt text-sub" @click="clear">
                        Clear selection
                    </button>

                    <button v-for="category in matches" :key="category.id" type="button" role="option"
                            :aria-selected="String(category.id) === String(modelValue)"
                            class="styledesk_timepicker__opt"
                            :class="{ 'styledesk_timepicker__opt--on': String(category.id) === String(modelValue) }"
                            @click="choose(category)">
                        {{ category.name }}
                    </button>

                    <p v-if="!matches.length" class="px-3 py-2.5 text-[13px] text-sub">
                        No category matches “{{ query }}”.
                    </p>
                </div>

                <div v-if="canCreate" class="styledesk_timepicker__foot">
                    <button type="button" class="styledesk_timepicker__now" @click="startAdding">
                        + Add New Category
                    </button>
                </div>
            </template>

            <template v-else>
                <div class="p-3 space-y-2">
                    <label class="block text-[13px] font-medium text-ink">Category name</label>
                    <input ref="draftBox" v-model="draftName" type="text" class="sd-input h-9 text-[13px]"
                           data-capitalize placeholder="Ayurvedic therapy" @keydown.enter.prevent="save">
                    <p v-if="error" class="text-[12px] text-danger">{{ error }}</p>
                </div>

                <div class="styledesk_timepicker__foot">
                    <button type="button" class="styledesk_timepicker__now" @click="adding = false">Cancel</button>
                    <button type="button" class="styledesk_timepicker__ok" :disabled="saving || !draftName.trim()" @click="save">
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
