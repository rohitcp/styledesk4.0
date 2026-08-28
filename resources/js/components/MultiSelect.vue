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
    /**
     * Single mode: one value, no chips, no primary. Kept in this component
     * rather than duplicated into a second one, because the search, the panel,
     * the outside-click handling and the keyboard behaviour are identical —
     * only the selection rule differs.
     */
    single: { type: Boolean, default: false },
    /**
     * Values to hide from the list — the primary, when this is the matching
     * secondary field. Filtering the options is what makes "the primary must
     * not appear as a secondary" true by construction rather than by a
     * validation message after the fact.
     */
    exclude: { type: Array, default: () => [] },
    /**
     * Whether the first entry is meaningfully "the primary".
     *
     * False on a secondary field, where the first chip is simply the first
     * secondary — badging it Primary would claim the opposite of what the
     * field means.
     */
    showPrimary: { type: Boolean, default: true },
    /**
     * Filter mode: the control reports how many are chosen rather than
     * listing them, and the choices themselves are shown as chips elsewhere
     * on the page. A toolbar of controls that each grow with their selections
     * reflows every time one is touched.
     *
     * Empty string keeps the chips inside the control, which is what the
     * onboarding and settings screens want.
     */
    summaryLabel: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'primary-changed']);

const selected = ref([...props.modelValue]);
const open = ref(false);
const query = ref('');
const root = ref(null);
const searchBox = ref(null);

const entries = computed(() =>
    Object.entries(props.options)
        .filter(([code]) => !props.exclude.includes(code))
        .map(([code, name]) => ({ code, name }))
);

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
    if (props.single) {
        selected.value = [code];
        open.value = false;

        return;
    }

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

watch(selected, (value) => {
    emit('update:modelValue', [...value]);

    /**
     * Announced on the DOM as well as to Vue, so the parts of the page that
     * are not Vue — the active-filter chips, the grid — can follow a
     * selection without either side importing the other.
     */
    root.value?.dispatchEvent(new CustomEvent('styledesk:selection', {
        bubbles: true,
        detail: { name: props.name, values: [...value] },
    }));
}, { deep: true });
watch(primary, (code) => emit('primary-changed', code));

// Follow changes made from outside — the currency list is rewritten when the
// primary country changes.
watch(() => props.modelValue, (value) => {
    if (value.join('|') !== selected.value.join('|')) {
        selected.value = [...value];
    }
});

/** What the control is currently showing, for the tooltip. */
const buttonLabel = computed(() => {
    if (props.single) {
        return selected.value.length ? nameOf(selected.value[0]) : props.placeholder;
    }

    if (props.summaryLabel) {
        return selected.value.length
            ? `${props.summaryLabel} (${selected.value.length})`
            : props.placeholder;
    }

    return selected.value.length
        ? selected.value.map((code) => nameOf(code)).join(', ')
        : props.placeholder;
});

function toggle() {
    open.value = !open.value;

    if (open.value) {
        /**
         * Only one panel open at a time.
         *
         * The button below carries `@click.stop`, so a click on this control
         * never reaches the document listener that closes the others — which
         * left every dropdown the reader had opened standing open behind the
         * one they were using. Announced rather than reached for: a control
         * has no handle on its siblings, and two on the same page may not
         * even belong to the same form.
         */
        document.dispatchEvent(new CustomEvent('styledesk:combo-open', {
            detail: { source: root.value },
        }));

        query.value = '';
        nextTick(() => searchBox.value?.focus());
    }
}

/**
 * Another control has opened; this one gets out of the way.
 *
 * Told apart by its own root element rather than by a number handed out on
 * mount. Each island is mounted as a Vue app of its own, so a module-level
 * counter is not the shared thing it looks like — every control called itself
 * 1, decided the announcement was its own, and stayed open.
 */
function onOtherOpen(event) {
    if (open.value && event.detail?.source !== root.value) {
        open.value = false;
    }
}

function onDocumentClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

/* A chip removed elsewhere on the page is still this control's selection to
   drop: the input remains the owner of what is chosen. */
function onExternalRemove(event) {
    const { name, value } = event.detail ?? {};

    if (name === props.name) {
        remove(value);
    }
}

/* A card elsewhere on the page choosing this control's value — "show me the
   inactive ones". Set through the control rather than around it, so its
   button and its list agree with what the grid was asked for. */
function onExternalSet(event) {
    const { name, values } = event.detail ?? {};

    if (name === props.name) {
        selected.value = props.single ? values.slice(0, 1) : [...values];
    }
}

function onExternalClear() {
    if (selected.value.length) {
        selected.value = [];
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
    document.addEventListener('styledesk:filter-remove', onExternalRemove);
    document.addEventListener('styledesk:filter-clear', onExternalClear);
    document.addEventListener('styledesk:filter-set', onExternalSet);
    document.addEventListener('styledesk:combo-open', onOtherOpen);
    emit('primary-changed', primary.value);

    /**
     * Announced on mount as well as on change.
     *
     * The islands mount after the page's own scripts have run, so a filter
     * row that only listened for changes would start empty on a page loaded
     * with filters already in the address bar — the chips would appear only
     * once something was touched.
     */
    root.value?.dispatchEvent(new CustomEvent('styledesk:selection', {
        bubbles: true,
        detail: { name: props.name, values: [...selected.value], initial: true },
    }));
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('styledesk:filter-remove', onExternalRemove);
    document.removeEventListener('styledesk:filter-clear', onExternalClear);
    document.removeEventListener('styledesk:filter-set', onExternalSet);
    document.removeEventListener('styledesk:combo-open', onOtherOpen);
});
</script>

<template>
    <div ref="root" class="styledesk_timepicker">
        <!-- Single mode posts one value; multi posts an array whose order
             matters, because the server treats the first as primary. -->
        <input v-if="single" type="hidden" :name="name" :value="selected[0] ?? ''">
        <template v-else>
            <input v-for="code in selected" :key="code" type="hidden" :name="`${name}[]`" :value="code">
        </template>

        <!-- The label is trimmed to one line by the stylesheet, so the full
             text is put where a reader can still get at it. -->
        <button type="button" class="styledesk_timepicker__field"
                :style="single || summaryLabel ? {} : { height: 'auto', minHeight: '2.75rem', padding: '0.375rem 0.75rem' }"
                :title="buttonLabel"
                :aria-label="ariaLabel" :aria-expanded="open" aria-haspopup="listbox" @click.stop="toggle">
            <span v-if="single" class="styledesk_timepicker__value"
                  :class="{ 'styledesk_timepicker__value--empty': !selected.length }">
                {{ selected.length ? nameOf(selected[0]) : placeholder }}
            </span>

            <!-- Filter mode: how many, not which. Which is shown as chips
                 below the toolbar, where they have room to be read. -->
            <span v-else-if="summaryLabel" class="styledesk_timepicker__value"
                  :class="{ 'styledesk_timepicker__value--empty': !selected.length }">
                {{ selected.length ? `${summaryLabel} (${selected.length})` : placeholder }}
            </span>

            <!-- Three per row, each the same width. A grid rather than
                 wrapping flex: chips sized to their own text give ragged rows
                 that shift every time a selection changes, and "United Arab
                 Emirates" next to "Spain" reads as two different controls. -->
            <span v-else class="styledesk_timepicker__value styledesk_chips">
                <span v-if="!selected.length" class="text-faint" style="grid-column: 1 / -1">{{ placeholder }}</span>

                <span v-for="code in selected" :key="code"
                      class="flex items-center gap-1 h-7 min-w-0 pl-2.5 pr-1 rounded-md bg-sel text-ink text-[13px]">
                    <span class="truncate" :title="nameOf(code)">{{ nameOf(code) }}</span>
                    <span v-if="showPrimary && code === primary"
                          class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-brand">Primary</span>
                    <span role="button" tabindex="0" aria-label="Remove"
                          class="ml-auto h-5 w-5 grid place-items-center shrink-0 rounded text-faint hover:text-danger hover:bg-hover"
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
                    <!-- A checkbox is a promise that more than one can be
                         ticked. In single mode that promise is false, so the
                         selected row is marked with a tick alone. -->
                    <span v-if="!single" class="h-4 w-4 rounded border grid place-items-center shrink-0"
                          :class="isSelected(country.code) ? 'bg-brand border-brand' : 'border-stroke'">
                        <svg v-if="isSelected(country.code)" width="10" height="10" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12l5 5 9-11" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="flex-1 text-left">{{ country.name }}</span>
                    <span v-if="!single && showPrimary && isSelected(country.code) && country.code !== primary"
                          role="button" tabindex="0" class="text-[11px] font-semibold text-link"
                          @click.stop="makePrimary(country.code)" @keydown.enter.stop="makePrimary(country.code)">
                        Make primary
                    </span>
                    <svg v-if="single && isSelected(country.code)" class="shrink-0 text-brand"
                         width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 12l5 5 9-11" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <p v-if="!matches.length" class="px-3 py-2.5 text-[13px] text-sub">Nothing matches “{{ query }}”.</p>
            </div>

            <div v-if="hint" class="styledesk_timepicker__foot">
                <span class="text-[12px] text-sub">{{ hint }}</span>
            </div>
        </div>
    </div>
</template>
