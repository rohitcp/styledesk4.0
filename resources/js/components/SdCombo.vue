<script setup>
/**
 * A searchable dropdown, for use inside a Vue island.
 *
 * The app already has one — `window.SD.combo()` upgrades a native select in
 * place — and it cannot be used here: it creates a wrapper div and MOVES the
 * select inside it. Vue patches the DOM against the parent and siblings it
 * rendered, so re-parenting a node it owns leaves it inserting later updates
 * in the wrong place or throwing outright.
 *
 * So the behaviour is reimplemented and the appearance is not: every class
 * below is the one prototype.css already defines for the native version, which
 * is what makes a dropdown in the builder look like a dropdown everywhere
 * else rather than merely similar.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    /** @type {Array<{value: string|number|null, label: string}>} */
    options: { type: Array, default: () => [] },
    searchLabel: { type: String, default: '' },
    /* What a filtered-to-nothing list says. Its own string, because the
       search box's label is a question and this is an answer. */
    emptyLabel: { type: String, default: '—' },
    searchPlaceholder: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    /*
     * How many choices before a search box appears.
     *
     * Zero, so every dropdown has one. A box above a two-item list is not
     * much help by itself, but a reader who has learned that any dropdown
     * here can be typed at should not have to find out which ones — and it
     * makes every one of them keyboard-filterable.
     */
    searchFrom: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
    /**
     * The height, text size and corner the control should take.
     *
     * Given by a form, which wears whatever its theme says; omitted by the
     * builder's own settings rails, which stay at the compact size every
     * other settings row uses. A dropdown that ignored the theme would be the
     * one control on a client's form that did.
     */
    controlStyle: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const active = ref(0);
const root = ref(null);
const searchBox = ref(null);

const searchable = computed(() => props.options.length >= props.searchFrom);

const shown = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') return props.options;

    return props.options.filter((option) => String(option.label).toLowerCase().includes(needle));
});

const chosen = computed(() => props.options.find((option) => option.value === props.modelValue) ?? null);
const label = computed(() => chosen.value?.label ?? props.placeholder);

function show() {
    if (props.disabled) return;

    open.value = true;
    query.value = '';
    active.value = Math.max(0, shown.value.findIndex((option) => option.value === props.modelValue));

    nextTick(() => searchBox.value?.focus());
}

function hide() {
    open.value = false;
}

function pick(option) {
    emit('update:modelValue', option.value);
    hide();
}

function onKey(event) {
    if (!open.value) {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
            event.preventDefault();
            show();
        }

        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        hide();

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(active.value + 1, shown.value.length - 1);

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(active.value - 1, 0);

        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const option = shown.value[active.value];

        if (option) pick(option);
    }
}

/* A dropdown left open behind a click elsewhere is a dropdown covering
   whatever the reader has moved on to. */
function onOutside(event) {
    if (!open.value || root.value?.contains(event.target)) return;

    hide();
}

watch(shown, () => { active.value = 0; });

onMounted(() => document.addEventListener('mousedown', onOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
  <div ref="root" class="relative">
    <button type="button" :id="id" class="sd-input sd-combo-btn"
            :class="[
              controlStyle ? '' : '!h-9 !px-2.5 !text-[13px]',
              { 'is-placeholder': !chosen, 'is-readonly': disabled },
            ]"
            :style="controlStyle ?? {}"
            :disabled="disabled"
            :aria-expanded="open" aria-haspopup="listbox" role="combobox"
            @click="open ? hide() : show()" @keydown="onKey">
      <span class="sd-combo-btn__label">{{ label }}</span>
      <svg class="sd-combo-btn__caret" width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <div v-if="open" class="sd-pop" @keydown="onKey">
      <input v-if="searchable" ref="searchBox" v-model="query" type="text" class="sd-pop__search"
             autocomplete="off" :aria-label="searchLabel" :placeholder="searchPlaceholder || searchLabel">

      <div class="sd-pop__list" role="listbox">
        <button v-for="(option, index) in shown" :key="String(option.value)" type="button" role="option"
                class="sd-pop__opt w-full text-left"
                :class="{ 'is-active': index === active }"
                :aria-selected="option.value === modelValue"
                @mouseenter="active = index" @click="pick(option)">
          {{ option.label }}
        </button>

        <p v-if="!shown.length" class="sd-pop__empty">{{ emptyLabel }}</p>
      </div>
    </div>
  </div>
</template>
