<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Searchable multi-select with an explicit primary.
 *
 * The primary is simply the first in the list, because something has to decide
 * which value the rest of the app follows — which country's regions and
 * timezones, which currency services are priced in. Making that "the first
 * one" rather than a separate flag means there is always exactly one, and
 * promoting is the only way to change it: no state where two are primary or
 * none is.
 */
const props = defineProps({
    options: { type: Object, default: () => ({}) },
    modelValue: { type: Array, default: () => [] },
    name: { type: String, default: 'codes' },
    placeholder: { type: String, default: 'Search or select' },
    searchPlaceholder: { type: String, default: 'Search…' },
    hint: { type: String, default: '' },
    ariaLabel: { type: String, default: 'Selection' },
});

const emit = defineEmits(['update:modelValue', 'primary-changed']);

const selected = ref([...props.modelValue]);
const open = ref(false);
const query = ref('');
const root = ref(null);
const searchBox = ref(null);

const entries = computed(() => Object.entries(props.options).map(([code, name]) => ({ code, name })));

const matches = computed(() => {
    const q = query.value.trim().toLowerCase();

    return q
        ? entries.value.filter((c) => c.name.toLowerCase().includes(q) || c.code.toLowerCase() === q)
        : entries.value;
});

const primary = computed(() => selected.value[0] ?? null);

function nameOf(code) {
    return props.options[code] ?? code;
}

function isSelected(code) {
    return selected.value.includes(code);
}

function toggleOption(code) {
    if (isSelected(code)) {
        // Removing the primary promotes whatever is next, so the list is never
        // left without one.
        selected.value = selected.value.filter((c) => c !== code);
    } else {
        selected.value = [...selected.value, code];
    }
}

function makePrimary(code) {
    selected.value = [code, ...selected.value.filter((c) => c !== code)];
}

function remove(code) {
    selected.value = selected.value.filter((c) => c !== code);
}

watch(selected, (value) => emit('update:modelValue', [...value]), { deep: true });
watch(primary, (code) => emit('primary-changed', code));

// Follow changes made from outside — the currency list is rewritten when the
// primary country changes.
watch(() => props.modelValue, (value) => {
    if (value.join('|') !== selected.value.join('|')) {
        selected.value = [...value];
    }
});

function toggle() {
    open.value = !open.value;

    if (open.value) {
        query.value = '';
        nextTick(() => searchBox.value?.focus());
    }
}

function onDocumentClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    emit('primary-changed', primary.value);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="styledesk_timepicker">
        <!-- Order matters on submit: the server treats the first as primary. -->
        <input v-for="code in selected" :key="code" type="hidden" :name="`${name}[]`" :value="code">

        <button type="button" class="styledesk_timepicker__field" style="height: auto; min-height: 2.75rem; padding: 0.375rem 0.75rem"
                :aria-label="ariaLabel" :aria-expanded="open" aria-haspopup="listbox" @click.stop="toggle">
            <span class="styledesk_timepicker__value flex flex-wrap items-center gap-1.5">
                <span v-if="!selected.length" class="text-faint">{{ placeholder }}</span>

                <span v-for="code in selected" :key="code"
                      class="inline-flex items-center gap-1.5 h-7 pl-2.5 pr-1 rounded-md bg-sel text-ink text-[13px]">
                    {{ nameOf(code) }}
                    <span v-if="code === primary"
                          class="text-[10px] font-semibold uppercase tracking-wide text-brand">Primary</span>
                    <span role="button" tabindex="0" aria-label="Remove"
                          class="h-5 w-5 grid place-items-center rounded text-faint hover:text-danger hover:bg-hover"
                          @click.stop="remove(code)" @keydown.enter.stop="remove(code)">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                    </span>
                </span>
            </span>

            <svg class="styledesk_timepicker__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div v-if="open" class="styledesk_timepicker__panel" style="width: 320px">
            <div class="p-2 border-b border-line">
                <input ref="searchBox" v-model="query" type="text" class="sd-input h-9 text-[13px]"
                       :placeholder="searchPlaceholder" autocomplete="off">
            </div>

            <div class="max-h-[200px] overflow-y-auto">
                <button v-for="country in matches" :key="country.code" type="button" role="option"
                        :aria-selected="isSelected(country.code)"
                        class="styledesk_timepicker__opt flex items-center gap-2.5"
                        :class="{ 'styledesk_timepicker__opt--on': isSelected(country.code) }"
                        @click="toggleOption(country.code)">
                    <span class="h-4 w-4 rounded border grid place-items-center shrink-0"
                          :class="isSelected(country.code) ? 'bg-brand border-brand' : 'border-stroke'">
                        <svg v-if="isSelected(country.code)" width="10" height="10" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12l5 5 9-11" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="flex-1 text-left">{{ country.name }}</span>
                    <span v-if="isSelected(country.code) && country.code !== primary"
                          role="button" tabindex="0" class="text-[11px] font-semibold text-link"
                          @click.stop="makePrimary(country.code)" @keydown.enter.stop="makePrimary(country.code)">
                        Make primary
                    </span>
                </button>

                <p v-if="!matches.length" class="px-3 py-2.5 text-[13px] text-sub">Nothing matches “{{ query }}”.</p>
            </div>

            <div v-if="hint" class="styledesk_timepicker__foot">
                <span class="text-[12px] text-sub">{{ hint }}</span>
            </div>
        </div>
    </div>
</template>
