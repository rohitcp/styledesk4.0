<script setup>
import { ref } from 'vue';
import TimePicker from './TimePicker.vue';

/**
 * The named periods a business day is divided into.
 *
 * "Morning Shift, 9:00–1:00", "Evening Shift, 4:00–8:00". A Vue island rather
 * than a page: Blade still owns the form and the submit, and these rows post
 * as ordinary `periods[i][field]` inputs, so the server sees a normal form
 * submission with no JSON endpoint involved.
 *
 * Validation is the project's shared one, not a second copy: each field
 * carries data-rules and an id, and resources/js/live-validation.js checks
 * them exactly as it checks the fields on Add Client. The rows are mounted
 * after that module has bound its listeners, so each field announces itself
 * on blur through the same event the combo boxes use — which is what gives a
 * dynamically added row the same live feedback as a field that was there when
 * the page loaded.
 *
 * Whether a period falls inside the business's own working hours stays a
 * server question: the hours live on the location, not on this page.
 */
const props = defineProps({
    initial: { type: Array, default: () => [] },
    /**
     * Validation messages keyed by row index, from the server. Passed in
     * rather than looked up: the server is what refused the submission, and
     * this component only decides which row a message sits under.
     */
    errors: { type: Object, default: () => ({}) },
    breakDurations: { type: Array, default: () => [15, 30, 45, 60] },
    labels: {
        type: Object,
        default: () => ({
            name: 'Shift name',
            name_placeholder: 'Morning Shift',
            starts_at: 'Start time',
            ends_at: 'End time',
            break: 'Break',
            no_break: 'No break',
            status: 'Status',
            active: 'Active',
            inactive: 'Inactive',
            add: '+ Add Shift Period',
            remove: 'Remove this period',
            minutes: ':count min',
            empty: 'No shift periods yet. Add the first one to divide the business day.',
        }),
    },
});

const BLANK = { name: '', starts_at: '09:00', ends_at: '13:00', break_minutes: '', is_active: true };

const rows = ref(
    props.initial.length
        ? props.initial.map((row) => ({
            name: row.name ?? '',
            starts_at: row.starts_at ?? '',
            ends_at: row.ends_at ?? '',
            break_minutes: row.break_minutes ?? '',
            is_active: row.is_active ?? true,
        }))
        : [{ ...BLANK }]
);

function add() {
    /* The new row starts where the last one finished, because that is what a
       day divided into periods looks like — and it is one fewer thing to type
       for the common case. */
    const last = rows.value[rows.value.length - 1];

    rows.value.push({
        ...BLANK,
        starts_at: last?.ends_at || BLANK.starts_at,
        ends_at: '',
    });
}

function remove(index) {
    rows.value.splice(index, 1);
}

function minutesLabel(minutes) {
    return props.labels.minutes.replace(':count', minutes);
}

function errorFor(index, field) {
    return props.errors?.[`${index}.${field}`] ?? props.errors?.[String(index)] ?? '';
}

/** A stable id per field, which is what the shared validator addresses. */
function fieldId(index, field) {
    return `period-${index}-${field}`;
}

/**
 * Tell the shared validator this field has been left.
 *
 * Its blur listeners were bound before these rows existed — this island is
 * imported on demand — so the rows borrow the event the combo boxes already
 * use to say the same thing. Without it a row added by pressing Add would be
 * checked only on submit, and the ones drawn on load would be checked live:
 * two behaviours for one form.
 */
function announce(event) {
    event.target.dispatchEvent(new CustomEvent('sd:combo-change', { bubbles: true }));
}
</script>

<template>
    <div class="space-y-3">
        <!-- One row per period: name, times, break, status and the way to
             remove it, all on a single line where there is room. The status
             and the remove control used to sit on a second line, which made a
             list of three periods read as six rows. -->
        <div v-for="(row, index) in rows" :key="index"
             class="rounded-lg border border-line p-3">
            <div class="grid gap-3 items-end
                        grid-cols-1 sm:grid-cols-2
                        lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                <div class="min-w-0">
                    <label :for="fieldId(index, 'name')" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ labels.name }} <span class="text-danger">*</span>
                    </label>
                    <input :id="fieldId(index, 'name')" v-model="row.name" type="text" class="sd-input"
                           :name="`periods[${index}][name]`"
                           data-rules="required|max:80"
                           :placeholder="labels.name_placeholder" maxlength="80"
                           @blur="announce">
                    <p :data-error-for="fieldId(index, 'name')" role="alert"
                       class="mt-1.5 text-[12px] text-danger" hidden></p>
                </div>

                <div class="min-w-0">
                    <label :for="fieldId(index, 'starts_at')" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ labels.starts_at }} <span class="text-danger">*</span>
                    </label>
                    <TimePicker v-model="row.starts_at" :name="`periods[${index}][starts_at]`"
                                :field-id="fieldId(index, 'starts_at')" rules="required"
                                :aria-label="labels.starts_at" />
                    <p :data-error-for="fieldId(index, 'starts_at')" role="alert"
                       class="mt-1.5 text-[12px] text-danger" hidden></p>
                </div>

                <div class="min-w-0">
                    <label :for="fieldId(index, 'ends_at')" class="block text-[13px] font-medium text-ink mb-1.5">
                        {{ labels.ends_at }} <span class="text-danger">*</span>
                    </label>
                    <TimePicker v-model="row.ends_at" :name="`periods[${index}][ends_at]`"
                                :field-id="fieldId(index, 'ends_at')" rules="required"
                                :aria-label="labels.ends_at" />
                    <p :data-error-for="fieldId(index, 'ends_at')" role="alert"
                       class="mt-1.5 text-[12px] text-danger" hidden></p>
                </div>

                <div class="min-w-0">
                    <label class="block text-[13px] font-medium text-ink mb-1.5">{{ labels.break }}</label>
                    <select v-model="row.break_minutes" class="sd-input"
                            :name="`periods[${index}][break_minutes]`">
                        <option value="">{{ labels.no_break }}</option>
                        <option v-for="minutes in breakDurations" :key="minutes" :value="minutes">
                            {{ minutesLabel(minutes) }}
                        </option>
                    </select>
                </div>

                <!-- h-11 to match .sd-input and the time picker, both 2.75rem:
                     a control half a step shorter than its neighbours makes a
                     single row read as two. -->
                <div class="min-w-0 h-11 flex items-center">
                    <!-- The unchecked value, so a retired period says "off"
                         rather than saying nothing and leaving the server to
                         guess. -->
                    <input type="hidden" :name="`periods[${index}][is_active]`" value="0">
                    <label class="styledesk_toggle">
                        <input v-model="row.is_active" type="checkbox" class="styledesk_toggle__input"
                               :name="`periods[${index}][is_active]`" value="1">
                        <span class="styledesk_toggle__track" aria-hidden="true">
                            <span class="styledesk_toggle__knob"></span>
                        </span>
                        <span class="styledesk_toggle__label whitespace-nowrap">
                            {{ row.is_active ? labels.active : labels.inactive }}
                        </span>
                    </label>
                </div>

                <div class="h-11 flex items-center">
                    <button type="button"
                            class="styledesk_action styledesk_action--icon styledesk_action--remove shrink-0"
                            :aria-label="labels.remove" :title="labels.remove"
                            @click="remove(index)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 7h12M10 7V5.5a1 1 0 011-1h2a1 1 0 011 1V7M8 7l.7 12a1 1 0 001 1h4.6a1 1 0 001-1L16 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>

            <!-- Messages under the row they are about, so a list of three
                 periods says which one the server refused. -->
            <p v-if="errorFor(index, 'name')" class="mt-2 text-[12px] text-danger">
                {{ errorFor(index, 'name') }}
            </p>
            <p v-if="errorFor(index, 'window')" class="mt-2 text-[12px] text-danger">
                {{ errorFor(index, 'window') }}
            </p>
        </div>

        <p v-if="!rows.length" class="text-[13px] text-sub">{{ labels.empty }}</p>

        <button type="button" class="styledesk_action" @click="add">{{ labels.add }}</button>
    </div>
</template>
