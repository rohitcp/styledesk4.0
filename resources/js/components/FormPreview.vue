<script setup>
/**
 * The form, as the client meets it.
 *
 * Used by the builder's Preview today and by the public form when that is
 * built: the point of a preview is that it is the same renderer, so a
 * business testing a question is testing the thing their client will answer
 * rather than a drawing of it.
 *
 * Answers are held here and go nowhere. Nothing is posted, nothing is saved,
 * and a page break really does start a new page — because "does this form
 * work" includes "can somebody get to the end of it".
 */
import { computed, onMounted, ref } from 'vue';

import SdBeforeAfter from './SdBeforeAfter.vue';
import SdCombo from './SdCombo.vue';
import SdDatePicker from './SdDatePicker.vue';
import SdSignaturePad from './SdSignaturePad.vue';

const props = defineProps({
    form: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    theme: { type: Object, default: () => ({}) },
    themeOptions: { type: Object, required: true },
    dateFormats: { type: Object, default: () => ({}) },
    uploads: { type: Object, default: () => ({}) },
    /**
     * Where the answers go, when they go anywhere.
     *
     * Absent in the builder's preview, which keeps them: a business testing
     * its own form must not fill its own records with test submissions. Set
     * on the public page, where the whole point is that they are sent.
     */
    submitUrl: { type: String, default: '' },
    labels: { type: Object, default: () => ({}) },
});

const t = (path, fallback = '') =>
    path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

const answers = ref({});
const page = ref(0);

const traits = (type) => {
    for (const group of Object.values(props.themeOptions.fieldTraits ?? {})) {
        if (group[type]) return group[type];
    }

    return {};
};

/* ------------------------------------------------------------ the look */

const radiusPx = computed(() => props.themeOptions.radii[props.theme.radius]?.px ?? 8);
const widthPx = computed(() => props.themeOptions.widths[props.theme.width]?.px ?? null);

const shellStyle = computed(() => ({
    maxWidth: widthPx.value === null ? '100%' : `${widthPx.value}px`,
    marginLeft: 'auto',
    marginRight: 'auto',
    borderRadius: `${radiusPx.value}px`,
    backgroundColor:
        props.theme.background === 'custom' && props.theme.background_color
            ? props.theme.background_color
            : null,
}));

const controlStyle = computed(() => {
    const style = props.themeOptions.fieldStyles[props.theme.field_style] ?? { height: 44, text: 14 };

    return {
        height: `${style.height}px`,
        fontSize: `${style.text}px`,
        borderRadius: `${radiusPx.value}px`,
    };
});

const areaStyle = computed(() => ({
    fontSize: controlStyle.value.fontSize,
    borderRadius: controlStyle.value.borderRadius,
}));

const labelsBeside = computed(() => props.theme.label_position === 'left');

const buttonStyle = computed(() => ({
    borderRadius: props.theme.button_style === 'rounded' ? '999px' : `${radiusPx.value}px`,
    width: props.theme.button_style === 'full' ? '100%' : null,
    height: controlStyle.value.height,
    fontSize: controlStyle.value.fontSize,
}));

function spacing(row) {
    const key = row?.spacing ?? props.theme.row_spacing;
    const custom = row?.spacing ? row.spacing_px : props.theme.row_spacing_px;

    return key === 'custom'
        ? (custom ?? 16)
        : (props.themeOptions.rowSpacings[key]?.px ?? 16);
}

const split = (row) => props.themeOptions.columnSplits[row.split ?? '50_50'] ?? [50, 50];

/*
 * How a tick-list arranges its own answers.
 *
 * Two columns collapse to one below the `sm` breakpoint, because a client
 * answering this on a phone must never read sideways — and an odd list's last
 * answer lands in the first column, which is simply where grid flow puts it.
 */
const optionColumns = (field) =>
    (field?.option_layout ?? 'single') === 'two'
        ? 'grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5'
        : 'grid gap-1.5';

/* --------------------------------------------------------------- pages */

/**
 * The form, cut at every page break.
 *
 * The break itself is not rendered — it is the cut, not a thing to read — so
 * a form with four breaks is five pages and the client never sees the seam.
 */
const pages = computed(() => {
    const out = [[]];

    props.rows.forEach((row) => {
        const isBreak = row.fields.some((field) => field.type === 'page_break');

        if (isBreak) {
            out.push([]);

            return;
        }

        out[out.length - 1].push(row);
    });

    return out.filter((rows, index) => rows.length > 0 || index === 0);
});

const current = computed(() => pages.value[Math.min(page.value, pages.value.length - 1)] ?? []);
const manyPages = computed(() => pages.value.length > 1);
const lastPage = computed(() => page.value >= pages.value.length - 1);

const progress = computed(() => t('builder.step', 'Step :current of :total')
    .replace(':current', String(page.value + 1))
    .replace(':total', String(pages.value.length)));

/* ------------------------------------------------------------- answers */

/**
 * Pre-filled where the business said so.
 *
 * Seeded once rather than read on every get, so a client who clears a
 * pre-filled field does not find it refilling itself as they type.
 */
const seeded = {};

props.rows.forEach((row) => row.fields.forEach((field) => {
    if (field.default_value) seeded[field.key] = field.default_value;
}));

answers.value = seeded;

const get = (field) => answers.value[field.key];

const set = (field, value) => {
    answers.value = { ...answers.value, [field.key]: value };
    remember();
};

/* What the client is told when an answer will not do, in the business's own
   words where they wrote some. */
const errors = ref({});
const errorFor = (field) => errors.value[field.key];

/** A question the client is actually asked. */
const asked = (field) => field.type !== 'hidden' && !field.hidden;

/** Which side of a before-and-after has nothing in it yet. */
function uploadGaps(field) {
    const held = get(field) ?? { before: [], after: [] };
    const gaps = {};

    if (field.before_required && (held.before ?? []).length === 0) {
        gaps.before = field.error_message || t('builder.before_missing')
            .replace(':label', field.before_label || t('builder.before'));
    }

    if (field.after_required && (held.after ?? []).length === 0) {
        gaps.after = field.error_message || t('builder.after_missing')
            .replace(':label', field.after_label || t('builder.after'));
    }

    return gaps;
}

const uploadErrors = ref({});

function missing(field) {
    if (field.type === 'before_after') {
        if (!asked(field)) return false;

        return Object.keys(uploadGaps(field)).length > 0;
    }

    if (!field.required || !asked(field) || field.disabled) return false;

    const value = get(field);

    if (Array.isArray(value)) return value.length === 0;
    if (value && typeof value === 'object') return !value.signature && !value.name;

    return value === undefined || value === null || String(value).trim() === '';
}

/**
 * On to the next page, or a refusal.
 *
 * Checked a page at a time, because that is the page the client can see:
 * telling somebody on page one that something on page four is missing is a
 * message they cannot act on.
 */
function advance(then) {
    const found = {};

    const gaps = {};

    current.value.forEach((row) => row.fields.forEach((field) => {
        if (field.type === 'before_after') {
            const sides = uploadGaps(field);

            /* Said beside the side it is about. One message under the whole
               question could not tell the client which half is missing. */
            if (Object.keys(sides).length) gaps[field.key] = sides;

            return;
        }

        if (missing(field)) {
            found[field.key] = field.error_message || t('builder.required_error');
        }
    }));

    errors.value = found;
    uploadErrors.value = gaps;

    if (Object.keys(found).length === 0) then();
}

/** A checkbox question holds a list, so each answer is added or taken out. */
function toggle(field, option) {
    const held = Array.isArray(get(field)) ? [...get(field)] : [];
    const at = held.indexOf(option);

    if (at === -1) held.push(option);
    else held.splice(at, 1);

    set(field, held);
}

const chosen = (field) => (Array.isArray(get(field)) ? get(field) : []);
const unchosen = (field) => (field.options ?? [])
    .filter((option) => !chosen(field).includes(option))
    .map((option) => ({ value: option, label: option }));

/** The pattern a date question was asked in. */
const formatFor = (field) => props.dateFormats[field.format ?? 'mm/dd/yyyy'] ?? props.dateFormats['mm/dd/yyyy'];

const submitted = ref(false);
const sending = ref(false);
const mounted = ref(false);
const failure = ref('');
const restored = ref(false);

/** What the buttons say. Blank falls back to StyleDesk's own wording, which
    is the only answer that stays translated. */
const submitLabel = computed(() => props.theme.submit_label || t('builder.submit'));
const cancelLabel = computed(() => props.theme.cancel_label || t('public.clear'));

/* Where they sit — a separate question from how the form's content is set. */
const buttonRow = computed(() => ({
    center: 'justify-center',
    right: 'justify-end',
}[props.theme.button_alignment] ?? 'justify-start'));

/*
 * Progress, kept in the client's own browser.
 *
 * Only where the business asked for it and only on a real form — the
 * builder's preview must not persist a business's own test answers and hand
 * them back the next time they look.
 *
 * Keyed by the address, so two forms open in one browser do not overwrite
 * each other's answers. Files are deliberately not kept: a File cannot be
 * serialised, and a half-restored upload that looks present and holds nothing
 * is worse than asking for it again.
 */
const storageKey = computed(() => `sd_form_${props.submitUrl}`);
const keepsProgress = computed(() => !!props.submitUrl && !!props.theme.save_progress);

function remember() {
    if (!keepsProgress.value) return;

    const plain = {};

    Object.entries(answers.value).forEach(([key, value]) => {
        const field = props.rows.flatMap((row) => row.fields).find((f) => f.key === key);

        if (field?.type === 'before_after' || value instanceof File) return;

        plain[key] = value;
    });

    try {
        window.localStorage.setItem(storageKey.value, JSON.stringify({ answers: plain, page: page.value }));
    } catch (error) {
        /* A private window, or storage that is full. Losing the convenience
           is not worth breaking the form over. */
    }
}

function forget() {
    try {
        window.localStorage.removeItem(storageKey.value);
    } catch (error) {
        // As above.
    }
}

function recall() {
    if (!keepsProgress.value) return;

    try {
        const held = JSON.parse(window.localStorage.getItem(storageKey.value) ?? 'null');

        if (!held || typeof held.answers !== 'object') return;

        answers.value = { ...answers.value, ...held.answers };
        page.value = Number(held.page) || 0;
        restored.value = Object.keys(held.answers).length > 0;
    } catch (error) {
        // As above.
    }
}

/**
 * Start again.
 *
 * Clears what was typed and what was kept, and says nothing was sent — a
 * client who presses Cancel wants to know they have not just submitted a
 * half-finished medical history.
 */
onMounted(() => {
    recall();
    mounted.value = true;
});

function cancel() {
    if (!window.confirm(t('public.cancel_confirm'))) return;

    answers.value = { ...seeded };
    errors.value = {};
    uploadErrors.value = {};
    page.value = 0;
    restored.value = false;
    failure.value = t('public.cancelled');
    forget();
}

/**
 * Answers, minus the files.
 *
 * A File cannot travel in json, so the uploads go as their own parts and the
 * answers carry everything else. Sending a File through JSON.stringify
 * silently produces `{}` — the answer looks present and holds nothing.
 */
function payload() {
    const body = new FormData();
    const plain = {};

    Object.entries(answers.value).forEach(([key, value]) => {
        const field = props.rows.flatMap((row) => row.fields).find((f) => f.key === key);

        if (field?.type === 'before_after' && value && typeof value === 'object') {
            ['before', 'after'].forEach((side) => {
                (value[side] ?? []).forEach((item) => {
                    if (item.file) body.append(`files[${key}][${side}][]`, item.file);
                });
            });

            return;
        }

        if (value instanceof File) {
            body.append(`files[${key}][files][]`, value);

            return;
        }

        plain[key] = value;
    });

    body.append('answers', JSON.stringify(plain));

    return body;
}

/**
 * Sent, or said why not.
 *
 * The server checks the same rules the panel does, and its answer wins: a
 * request that did not come from this panel is exactly the one that would
 * not have been checked.
 */
async function send() {
    if (sending.value) return;

    sending.value = true;
    failure.value = '';

    try {
        const response = await fetch(props.submitUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: payload(),
        });

        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            /* Laravel answers 422 with a map of field to message, which is
               the same shape this panel already paints. */
            if (body.errors) {
                const found = {};
                const gaps = {};

                Object.entries(body.errors).forEach(([key, messages]) => {
                    const [field, side] = key.split('.');

                    if (side) gaps[field] = { ...(gaps[field] ?? {}), [side]: messages[0] };
                    else found[field] = messages[0];
                });

                errors.value = found;
                uploadErrors.value = gaps;
                failure.value = '';
            } else {
                failure.value = body.message || t('public.failed');
            }

            return;
        }

        submitted.value = true;

        /* Sent, so there is nothing left to bring back. */
        forget();
    } catch (error) {
        failure.value = t('public.failed');
    } finally {
        sending.value = false;
    }
}

/** Held to the same check first, so an obvious gap never becomes a request. */
function finish() {
    advance(() => {
        if (props.submitUrl) send();
        else submitted.value = true;
    });
}
</script>

<template>
  <div :style="shellStyle">
    <!-- Done. The form is replaced rather than left on screen behind a
         message: a client who has just sent their medical history should not
         be looking at a Submit button they might press again. -->
    <div v-if="submitted && submitUrl" class="sd-card p-6 sm:p-8 text-center"
         :style="{ borderRadius: shellStyle.borderRadius }">
      <div class="mx-auto w-11 h-11 rounded-full bg-brand/10 text-brand grid place-items-center">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M5 12.5l4.5 4.5L19 7.5" />
        </svg>
      </div>

      <h1 class="mt-3 text-[18px] font-semibold text-head">{{ t('public.submitted') }}</h1>
      <p class="mt-1.5 text-[13.5px] text-sub">{{ t('public.thanks') }}</p>
    </div>

    <template v-else>
    <p v-if="!submitUrl" class="mb-4 text-[12.5px] text-sub bg-hover rounded-lg px-3 py-2">{{ t('builder.preview_note') }}</p>

    <div class="sd-card p-5 sm:p-6"
         :style="{ borderRadius: shellStyle.borderRadius, backgroundColor: shellStyle.backgroundColor }">
      <div :class="theme.alignment === 'center' ? 'text-center' : ''">
        <h1 class="text-[20px] font-bold text-head">{{ form.name }}</h1>
        <p class="text-[13px] text-sub mt-1">{{ form.typeLabel }}</p>
      </div>

      <!-- Where the client is, when there is more than one page to be. -->
      <div v-if="manyPages" class="mt-4">
        <div class="flex items-center justify-between text-[12px] text-sub">
          <span>{{ progress }}</span>
        </div>
        <div class="mt-1.5 h-1 rounded-full bg-hover overflow-hidden">
          <div class="h-full bg-brand transition-all"
               :style="{ width: ((page + 1) / pages.length * 100) + '%' }"></div>
        </div>
      </div>

      <!-- Said plainly. Finding a form already filled in is unsettling
           unless somebody explains why. -->
      <div v-if="restored" class="mt-4 flex flex-wrap items-center gap-2 text-[12.5px] text-sub bg-hover rounded-lg px-3 py-2">
        <span class="min-w-0">{{ t('public.restored') }}</span>
        <button type="button" class="styledesk_action !h-7 !px-2 !text-[12px] ml-auto" @click="cancel">
          {{ t('public.clear') }}
        </button>
      </div>

      <div class="mt-5">
        <div v-for="(row, index) in current" :key="row.key"
             :style="index === 0 ? {} : { marginTop: spacing(row) + 'px' }">
          <div :class="row.layout === 'two' ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : ''">
            <template v-for="field in row.fields" :key="field.key">
              <!-- A hidden field is hidden — the type, and the state of any
                   other type. It carries a value the client is never asked
                   for, so a preview that showed one would be showing
                   something no client will ever see. -->
              <div v-if="asked(field)">
                <!-- Layout pieces read as themselves. -->
                <template v-if="['heading', 'section', 'paragraph', 'divider'].includes(field.type)">
                  <h2 v-if="field.type === 'heading'" class="text-[17px] font-semibold text-head">{{ field.label }}</h2>
                  <h3 v-else-if="field.type === 'section'" class="text-[13px] font-semibold uppercase tracking-wide text-sub">{{ field.label }}</h3>
                  <p v-else-if="field.type === 'paragraph'" class="text-[13.5px] text-ink leading-relaxed">{{ field.label }}</p>
                  <hr v-else class="border-line">
                </template>

                <!-- Consent and terms are their own wording plus a tick. -->
                <label v-else-if="field.type === 'consent' || field.type === 'terms'" class="styledesk_choice">
                  <input type="checkbox" class="sd-check" :checked="!!get(field)"
                         @change="set(field, $event.target.checked)">
                  <span class="styledesk_choice__label">
                    {{ field.label }}
                    <span v-if="field.required" class="text-danger">*</span>
                  </span>
                </label>

                <div v-else :class="labelsBeside ? 'grid grid-cols-[130px_minmax(0,1fr)] gap-3 items-start' : ''">
                  <!-- The name, unless the business asked for the field on
                       its own. Hidden with aria-hidden rather than dropped:
                       the control still needs something to be labelled by. -->
                  <label class="block text-[13.5px] font-medium text-ink" :for="'p-' + field.key"
                         :class="[labelsBeside ? 'pt-2' : 'mb-1.5', field.hide_label ? 'sr-only' : '']">
                    {{ field.label }}
                    <span v-if="field.required" class="text-danger">*</span>
                  </label>

                  <div class="min-w-0">
                    <p v-if="field.description && (field.help_position ?? 'below') === 'above'"
                       class="text-[12px] text-sub mb-1.5">
                      {{ field.description }}
                    </p>

                    <textarea v-if="field.type === 'long_text'" :id="'p-' + field.key" rows="3"
                              class="sd-input !h-auto py-2" :style="areaStyle"
                              :placeholder="field.placeholder" :disabled="field.disabled"
                              :maxlength="field.max_length || null"
                              :value="get(field)" @input="set(field, $event.target.value)"></textarea>

                    <!-- A calendar behind an icon, not an open calendar. -->
                    <SdDatePicker v-else-if="field.type === 'date'"
                                  :model-value="get(field) ?? ''"
                                  @update:model-value="set(field, $event)"
                                  :parts="formatFor(field).parts" :pattern="formatFor(field).pattern"
                                  :control-style="controlStyle"
                                  :month-label="t('common.month')" :year-label="t('common.year')"
                                  :open-label="t('common.choose_a_date', 'Choose a date')" />

                    <!-- A date of birth is typed. Somebody knows their own
                         birthday and should not click through a calendar to
                         reach 1974. -->
                    <input v-else-if="field.type === 'date_of_birth'" :id="'p-' + field.key"
                           class="sd-input" :style="controlStyle" inputmode="numeric"
                           :placeholder="formatFor(field).pattern"
                           :value="get(field)" @input="set(field, $event.target.value)">

                    <SdCombo v-else-if="field.type === 'dropdown'" :id="'p-' + field.key"
                             :model-value="get(field) ?? null"
                             @update:model-value="set(field, $event)"
                             :options="(field.options ?? []).map(o => ({ value: o, label: o }))"
                             :placeholder="field.placeholder || t('builder.choose_option')"
                             :search-label="field.label" :search-placeholder="t('common.search')"
                             :empty-label="t('builder.no_matches')"
                             :control-style="controlStyle" />

                    <!-- Several answers, so several tags. -->
                    <div v-else-if="field.type === 'multi_select'">
                      <div v-if="chosen(field).length" class="flex flex-wrap gap-1.5 mb-1.5">
                        <span v-for="option in chosen(field)" :key="option"
                              class="inline-flex items-center gap-1 px-2 h-7 rounded-full bg-hover text-ink text-[12.5px]">
                          {{ option }}
                          <button type="button" class="text-sub hover:text-ink" :aria-label="option"
                                  @click="toggle(field, option)">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                              <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                          </button>
                        </span>
                      </div>

                      <SdCombo v-if="unchosen(field).length" :model-value="null"
                               @update:model-value="toggle(field, $event)"
                               :options="unchosen(field)"
                               :placeholder="field.placeholder || t('builder.add_option')"
                               :search-label="field.label" :search-placeholder="t('common.search')"
                               :empty-label="t('builder.no_matches')"
                               :control-style="controlStyle" />
                    </div>

                    <div v-else-if="field.type === 'yes_no'" :class="optionColumns(field)">
                      <label v-for="option in [t('common.yes'), t('common.no')]" :key="option" class="styledesk_choice">
                        <input type="radio" class="sd-radio" :name="'p-' + field.key" :value="option"
                               :checked="get(field) === option" @change="set(field, option)">
                        <span class="styledesk_choice__label">{{ option }}</span>
                      </label>
                    </div>

                    <div v-else-if="field.type === 'checkbox'" :class="optionColumns(field)">
                      <label v-for="option in field.options ?? []" :key="option" class="styledesk_choice">
                        <input type="checkbox" class="sd-check" :checked="chosen(field).includes(option)"
                               @change="toggle(field, option)">
                        <span class="styledesk_choice__label">{{ option }}</span>
                      </label>
                    </div>

                    <div v-else-if="field.type === 'multiple_choice'" :class="optionColumns(field)">
                      <label v-for="option in field.options ?? []" :key="option" class="styledesk_choice">
                        <input type="radio" class="sd-radio" :name="'p-' + field.key" :value="option"
                               :checked="get(field) === option" @change="set(field, option)">
                        <span class="styledesk_choice__label">{{ option }}</span>
                      </label>
                    </div>

                    <SdSignaturePad v-else-if="field.type === 'signature' || field.type === 'initials'"
                                    :model-value="get(field) ?? ''"
                                    @update:model-value="set(field, $event)"
                                    :clear-label="t('builder.clear')"
                                    :hint="field.type === 'initials' ? t('builder.initials_hint') : t('builder.signature_hint')"
                                    :radius="controlStyle.borderRadius" />

                    <!-- Signed and named. The mark alone cannot say who gave
                         it, which is the whole point of a waiver. -->
                    <div v-else-if="field.type === 'signature_name'" class="space-y-1.5">
                      <SdSignaturePad :model-value="get(field)?.signature ?? ''"
                                      @update:model-value="set(field, { ...(get(field) ?? {}), signature: $event })"
                                      :clear-label="t('builder.clear')" :hint="t('builder.signature_hint')"
                                      :radius="controlStyle.borderRadius" />

                      <input class="sd-input" :style="controlStyle"
                             :placeholder="field.placeholder || t('builder.full_name_hint')"
                             :value="get(field)?.name ?? ''"
                             @input="set(field, { ...(get(field) ?? {}), name: $event.target.value })">
                    </div>

                    <div v-else-if="field.type === 'rating'" class="flex gap-1">
                      <button v-for="star in 5" :key="star" type="button"
                              class="transition-colors"
                              :class="(get(field) ?? 0) >= star ? 'text-brand' : 'text-faint hover:text-sub'"
                              :aria-label="String(star)" @click="set(field, star)">
                        <svg width="24" height="24" viewBox="0 0 24 24"
                             :fill="(get(field) ?? 0) >= star ? 'currentColor' : 'none'"
                             stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                          <path d="M12 4l2.4 5 5.6.7-4 3.9 1 5.4-5-2.7-5 2.7 1-5.4-4-3.9 5.6-.7z" />
                        </svg>
                      </button>
                    </div>

                    <div v-else-if="field.type === 'scale'" class="flex flex-wrap gap-1.5">
                      <button v-for="step in 10" :key="step" type="button"
                              class="w-8 h-8 grid place-items-center border text-[12.5px] transition-colors"
                              :style="{ borderRadius: controlStyle.borderRadius }"
                              :class="get(field) === step
                                ? 'bg-brand border-brand text-white'
                                : 'border-line text-ink hover:border-stroke'"
                              @click="set(field, step)">{{ step }}</button>
                    </div>

                    <SdBeforeAfter v-else-if="field.type === 'before_after'"
                                   :field="field" :labels="labels"
                                   :types="uploads.default_types ?? []"
                                   :max-kb="uploads.max_kb ?? 5120"
                                   :max-files="uploads.default_max_files ?? 10"
                                   :control-style="controlStyle"
                                   :errors="uploadErrors[field.key] ?? {}"
                                   @update:files="set(field, $event)" />

                    <input v-else-if="field.type === 'file_upload'" :id="'p-' + field.key" type="file"
                           class="block w-full text-[13px] text-sub file:mr-3 file:h-8 file:px-3 file:rounded-md
                                  file:border file:border-line file:bg-white file:text-ink file:text-[12.5px]">

                    <input v-else :id="'p-' + field.key" class="sd-input" :style="controlStyle"
                           :type="field.type === 'time' ? 'time' : (field.type === 'number' ? 'number'
                             : (field.type === 'email' ? 'email' : (field.type === 'phone' ? 'tel' : 'text')))"
                           :placeholder="field.placeholder" :disabled="field.disabled"
                           :maxlength="field.max_length || null"
                           :value="get(field)" @input="set(field, $event.target.value)">

                    <p v-if="field.description && (field.help_position ?? 'below') !== 'above'"
                       class="text-[12px] text-sub mt-1">
                      {{ field.description }}
                    </p>

                    <!-- How much room is left, where the business wants it
                         shown all the time. -->
                    <p v-if="field.show_counter && field.max_length" class="text-[12px] text-faint mt-1">
                      {{ t('builder.counter')
                           .replace(':count', String(String(get(field) ?? '').length))
                           .replace(':max', String(field.max_length)) }}
                    </p>

                    <p v-if="errorFor(field)" class="text-[12px] text-danger mt-1" role="alert">
                      {{ errorFor(field) }}
                    </p>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Through the pages, and then a submit that does not submit. -->
      <div class="mt-6 flex flex-wrap items-center gap-2" :class="buttonRow">
        <button v-if="manyPages && page > 0" type="button" class="styledesk_action" @click="page -= 1">
          {{ t('builder.previous') }}
        </button>

        <button v-if="!lastPage" type="button" :style="buttonStyle"
                class="px-5 bg-brand text-white font-semibold" @click="advance(() => { page += 1; remember() })">
          {{ t('builder.continue') }}
        </button>

        <button v-else type="button" :style="buttonStyle" :disabled="sending"
                class="px-5 bg-brand text-white font-semibold disabled:opacity-60" @click="finish">
          {{ sending ? t('public.sending') : submitLabel }}
        </button>

        <!-- A way out, where the business asked for one. Off by default: a
             client who opened an intake link is there to finish it. -->
        <button v-if="theme.show_cancel" type="button" class="styledesk_action" @click="cancel">
          {{ cancelLabel }}
        </button>
      </div>

      <p v-if="submitted && !submitUrl" class="mt-3 text-[12.5px] text-sub">{{ t('builder.preview_note') }}</p>

      <p v-if="failure" class="mt-3 text-[13px] text-danger" role="alert">{{ failure }}</p>
    </div>
    </template>
  </div>
</template>
