<script setup>
/**
 * The form builder.
 *
 * Two tools in one screen. The left column is either what goes INTO the form
 * or how the form LOOKS, switched by a segmented control and never both at
 * once; the middle is the form itself; the right is the settings for whatever
 * is selected. The middle one is the work — a question is edited where it
 * sits rather than in a dialog over the top of it, because the thing being
 * edited IS the thing on screen.
 *
 * The unit of arrangement is a ROW, not a question. A row holds one question
 * or two side by side, which is what an intake form actually looks like:
 * first name beside last name, medical history on its own. One global column
 * count could not express that, so the layout is per row and the form-level
 * setting is only the default a new row takes.
 *
 * Saving is not a button somebody has to remember. The panel writes the
 * schema a moment after each change and says so; Save is there for the reader
 * who wants to be sure rather than as the only way through. Building a
 * twenty-question intake form and losing it to a closed tab is the failure
 * this is arranged around.
 *
 * Drag and drop is the browser's own, not a library: reordering a list is what
 * HTML drag events are for, and a dependency for it would be a dependency to
 * keep patched forever.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import FormPreview from './FormPreview.vue';
import SdCombo from './SdCombo.vue';

const props = defineProps({
    form: { type: Object, required: true },
    version: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    theme: { type: Object, default: () => ({}) },
    themeOptions: { type: Object, required: true },
    dateFormats: { type: Object, default: () => ({}) },
    uploads: { type: Object, default: () => ({}) },
    groups: { type: Array, default: () => [] },
    labels: { type: Object, default: () => ({}) },
    endpoints: { type: Object, required: true },
    can: { type: Object, default: () => ({ edit: false, publish: false }) },
});

/** The wording, looked up by path so the template reads as prose. */
const t = (path, fallback = '') =>
    path.split('.').reduce((carry, key) => carry?.[key], props.labels) ?? fallback;

/*
 * One mark per kind of question.
 *
 * Drawn here as path data rather than taken from the vendored Font Awesome
 * set: that set holds the fifty-odd icons the app's navigation needs and
 * nothing shaped like "page break" or "initials", and the bundle it would come
 * from is licensed and gitignored. These are local constants on a 24 grid,
 * stroked in currentColor like every other hand-written icon in the app, so
 * they inherit hover and selection colour for free.
 */
const ICONS = {
    text: '<path d="M4 7h16M8 12h8M6 17h12"/>',
    long_text: '<path d="M4 6h16M4 10h16M4 14h16M4 18h10"/>',
    email: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
    phone: '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
    number: '<path d="M9 4L7 20M17 4l-2 16M4 9h16M3 15h16"/>',
    date: '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
    date_of_birth: '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/><circle cx="12" cy="15" r="1.6"/>',
    time: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    yes_no: '<circle cx="8" cy="12" r="3.2"/><circle cx="16" cy="12" r="3.2"/><path d="M8 12h.01"/>',
    dropdown: '<rect x="3.5" y="6.5" width="17" height="11" rx="2"/><path d="M14 11l2.2 2.4L18.4 11"/>',
    checkbox: '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12.5l2.8 2.8L16 9.5"/>',
    multiple_choice: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.2"/>',
    multi_select: '<path d="M4 7h10M4 12h10M4 17h10"/><path d="M17 6.5l2 2 3-3.5M17 16.5l2 2 3-3.5"/>',
    consent: '<path d="M5 12.5l3.5 3.5L19 6"/><path d="M4 20h16"/>',
    initials: '<path d="M5 18V7l4 7 4-7v11"/><path d="M16 7v11h3"/>',
    signature: '<path d="M3 17c3.5 0 4.5-9 7-9s1.5 7 4 7 2.5-3 4-3"/><path d="M3 21h18"/>',
    signature_name: '<path d="M3 13c3 0 4-7 6-7s1.5 5.5 3.5 5.5S15 9 16.5 9"/><path d="M3 16.5h18"/><rect x="3" y="19" width="18" height="3.2" rx="1"/>',
    terms: '<path d="M6 3.5h9l4 4V20a.5.5 0 01-.5.5h-12A.5.5 0 016 20z"/><path d="M14.5 3.5V8h4.5"/><path d="M9 13l2 2 4-4"/>',
    heading: '<path d="M6 5v14M16 5v14M6 12h10"/>',
    paragraph: '<path d="M5 6h14M5 11h14M5 16h9"/>',
    divider: '<path d="M3 12h18"/><path d="M7 7h10M7 17h10" opacity=".4"/>',
    section: '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17"/>',
    page_break: '<path d="M3 12h18" stroke-dasharray="3 3"/><path d="M6 6h12M6 18h12"/>',
    file_upload: '<path d="M12 16V5"/><path d="M8 9l4-4 4 4"/><path d="M4 16v2.5A1.5 1.5 0 005.5 20h13a1.5 1.5 0 001.5-1.5V16"/>',
    rating: '<path d="M12 4l2.4 5 5.6.7-4 3.9 1 5.4-5-2.7-5 2.7 1-5.4-4-3.9 5.6-.7z"/>',
    scale: '<path d="M3 15h18"/><path d="M6 15V11M12 15V8M18 15v-5"/>',
    hidden: '<path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6z"/><path d="M4 20L20 4"/>',
    before_after: '<rect x="2.5" y="5" width="8.5" height="14" rx="1.5"/><rect x="13" y="5" width="8.5" height="14" rx="1.5"/><path d="M5 15l2-2.5 1.5 2M15.5 15l2-2.5 1.5 2"/>',
};

const iconFor = (type) => ICONS[type] ?? ICONS.text;

/* The two arrangements a row can take, drawn as what they are. */
const LAYOUT_ICONS = {
    single: '<rect x="3.5" y="5" width="17" height="5" rx="1.5"/><rect x="3.5" y="14" width="17" height="5" rx="1.5"/>',
    two: '<rect x="3.5" y="5" width="7.5" height="14" rx="1.5"/><rect x="13" y="5" width="7.5" height="14" rx="1.5"/>',
};

const layoutIcon = (layout) => LAYOUT_ICONS[layout] ?? LAYOUT_ICONS.single;

/* The four things done TO a question, as opposed to the kinds of question. */
const ACTION_ICONS = {
    move_up: '<path d="M12 19V6"/><path d="M6 12l6-6 6 6"/>',
    move_down: '<path d="M12 5v13"/><path d="M6 12l6 6 6-6"/>',
    duplicate: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M15 5.5A1.5 1.5 0 0013.5 4h-8A1.5 1.5 0 004 5.5v8A1.5 1.5 0 005.5 15"/>',
    delete: '<path d="M4 7h16"/><path d="M10 4h4M10 11v6M14 11v6"/><path d="M6 7l1 12.5A1.5 1.5 0 008.5 21h7a1.5 1.5 0 001.5-1.5L18 7"/>',
};

/* ------------------------------------------------------------------ state */

const rows = ref(props.rows.map((row) => ({
    key: row.key,
    layout: row.layout ?? 'single',
    split: row.split ?? '50_50',
    spacing: row.spacing ?? null,
    spacing_px: row.spacing_px ?? null,
    fields: (row.fields ?? []).map((field) => ({ ...field })),
})));

const theme = ref({ ...props.theme });

const panel = ref('elements');
const selectedKey = ref(rows.value[0]?.fields[0]?.key ?? null);
const previewing = ref(false);
const status = ref('idle');
const statusMessage = ref('');
const formStatus = ref(props.form.status);
const formStatusLabel = ref(props.form.statusLabel);
const published = ref(props.version.published);

/* Every type in one flat list, so a field can find its own traits without the
   template walking the groups each time. */
const traitsByType = computed(() => {
    const map = {};
    props.groups.forEach((group) => group.types.forEach((type) => { map[type.key] = type; }));
    return map;
});

const traitsFor = (field) => traitsByType.value[field?.type] ?? {};

const allFields = computed(() => rows.value.flatMap((row) => row.fields));
const questionCount = computed(() => allFields.value.filter((f) => !traitsFor(f).content).length);

/** Where the selected question is, or null when nothing is selected. */
const spot = computed(() => {
    for (let r = 0; r < rows.value.length; r += 1) {
        const f = rows.value[r].fields.findIndex((field) => field.key === selectedKey.value);

        if (f !== -1) return { row: r, field: f };
    }

    return null;
});

const selected = computed(() => (spot.value ? rows.value[spot.value.row].fields[spot.value.field] : null));
const selectedRow = computed(() => (spot.value ? rows.value[spot.value.row] : null));

const editing = computed(() => props.can.edit && !previewing.value);

/**
 * The columns the screen actually has.
 *
 * Declared from which rails are rendered rather than fixed, because a grid
 * keeps its columns whether anything is in them or not: with the rails hidden
 * for Preview, the canvas stayed in the first 288px column and the form came
 * out a quarter of its width. A reader holding view and not edit had the same
 * problem from the other side — no left rail, so the form sat in the slot the
 * left rail would have used.
 */
const gridClass = computed(() => {
    if (previewing.value) return 'grid-cols-1';

    return props.can.edit
        ? 'lg:grid-cols-[288px_minmax(0,1fr)_352px]'
        : 'lg:grid-cols-[minmax(0,1fr)_352px]';
});

/* ------------------------------------------------------ the live theme */

const radiusPx = computed(() => props.themeOptions.radii[theme.value.radius]?.px ?? 8);
const widthPx = computed(() => props.themeOptions.widths[theme.value.width]?.px ?? null);

const canvasStyle = computed(() => ({
    maxWidth: widthPx.value === null ? '100%' : `${widthPx.value}px`,
    /*
     * Always centred in the canvas.
     *
     * The middle column is a preview frame, not the page the client will
     * open, so where the form sits IN IT is the builder's business rather
     * than the business's: a narrow form pinned to the left of a wide column
     * reads as a mistake and leaves the reader looking at empty space.
     *
     * The theme's own alignment still decides how the form's content is set —
     * see the title block and the submit button — which is where the choice
     * is actually visible to a client.
     */
    marginLeft: 'auto',
    marginRight: 'auto',
    borderRadius: `${radiusPx.value}px`,
    backgroundColor:
        theme.value.background === 'custom' && theme.value.background_color
            ? theme.value.background_color
            : null,
}));

/** What an inert control on the canvas looks like at this field style. */
const controlStyle = computed(() => {
    const style = props.themeOptions.fieldStyles[theme.value.field_style] ?? { height: 44, text: 14 };

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

const labelsBeside = computed(() => theme.value.label_position === 'left');

/*
 * The choice lists the combos read.
 *
 * Shaped here rather than in the template so each control is one line there,
 * and so the wording comes from the same lang lookup the rest of the panel
 * uses.
 */
const choices = (keys, path) => keys.map((key) => ({ value: key, label: t(`${path}.${key}`, key) }));

const widthChoices = computed(() => choices(Object.keys(props.themeOptions.widths), 'theme.widths'));
const alignmentChoices = computed(() => choices(props.themeOptions.alignments, 'theme.alignments'));
const labelPositionChoices = computed(() => choices(props.themeOptions.labelPositions, 'theme.label_positions'));
const fieldStyleChoices = computed(() => choices(Object.keys(props.themeOptions.fieldStyles), 'theme.field_styles'));
const radiusChoices = computed(() => choices(Object.keys(props.themeOptions.radii), 'theme.radii'));
const backgroundChoices = computed(() => choices(props.themeOptions.backgrounds, 'theme.backgrounds'));
const buttonStyleChoices = computed(() => choices(props.themeOptions.buttonStyles, 'theme.button_styles'));
const buttonAlignmentChoices = computed(() =>
    choices(props.themeOptions.buttonAlignments ?? [], 'theme.button_alignments'));

/* The patterns themselves are the labels: a format is not prose. */
const dateFormatChoices = computed(() => Object.entries(props.dateFormats)
    .map(([key, spec]) => ({ value: key, label: spec.pattern })));

/**
 * Whether a question arranges its own answers.
 *
 * The three that print every answer where the client can see it. A dropdown
 * and a tag picker keep theirs in a popup, so there is nothing on the page to
 * lay out.
 *
 * Yes/No is one of them even though its two answers are not a list the
 * business typed: they are still two answers on the page, and "Yes above No"
 * is a legitimate thing to ask for.
 */
const OPTION_LAYOUT_TYPES = ['checkbox', 'multiple_choice', 'yes_no'];

const arrangesOptions = (field) => OPTION_LAYOUT_TYPES.includes(field?.type);

/*
 * Which settings a question actually has.
 *
 * Absent rather than greyed out, because a disabled control is a question the
 * reader has to answer — "why can't I set a character limit on a signature?"
 * — where a missing one is simply not part of that question.
 */

/** Layout pieces: a heading is read, not answered. */
const isContent = (field) => traitsFor(field).content;

/** Anything the client gives an answer to. */
const isQuestion = (field) => !!field && !isContent(field) && field.type !== 'hidden';

/** A single typed default only makes sense where one value is the answer. */
const PREFILLABLE = [
    'text', 'long_text', 'email', 'phone', 'number',
    'date', 'date_of_birth', 'time', 'dropdown', 'multiple_choice', 'hidden',
];

/** Counting characters is for the two questions made of them. */
const COUNTABLE = ['text', 'long_text'];

const canPrefill = (field) => PREFILLABLE.includes(field?.type);
const canCount = (field) => COUNTABLE.includes(field?.type);

/**
 * Required, hidden and disabled are one answer, not three.
 *
 * A required question the client cannot reach or cannot type in is a form
 * nobody can submit — so choosing one of the three clears the others rather
 * than leaving the panel to describe an impossible field.
 */
function setState(field, state) {
    if (!field) return;

    field.required = state === 'required';
    field.hidden = state === 'hidden';
    field.disabled = state === 'disabled';
}

const stateOf = (field) => {
    if (field?.hidden) return 'hidden';
    if (field?.disabled) return 'disabled';

    return field?.required ? 'required' : 'none';
};

/** The kinds of file the platform will actually accept. */
const fileTypeChoices = computed(() => props.uploads.types ?? []);

const toggleFileType = (field, type) => {
    const held = [...(field.file_types ?? [])];
    const at = held.indexOf(type);

    if (at === -1) held.push(type);
    else held.splice(at, 1);

    /* Never none: a question that accepts nothing is a question nobody can
       answer. */
    field.file_types = held.length ? held : [type];
};

const helpPositionChoices = computed(() => [
    { value: 'below', label: t('builder.help_positions.below') },
    { value: 'above', label: t('builder.help_positions.above') },
]);

/* One per row, or two across. The lone last answer of an odd list lands in
   the first column because that is where grid flow puts it. */
const optionColumns = (field) =>
    (field?.option_layout ?? 'single') === 'two'
        ? 'grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5'
        : 'grid gap-1.5';

const splitChoices = computed(() => Object.entries(props.themeOptions.columnSplits)
    .map(([key, widths]) => ({ value: key, label: `${widths[0]} / ${widths[1]}` })));

/* The row's own spacing, with "whatever the form says" at the top. */
const rowSpacingChoices = computed(() => [
    { value: null, label: t('theme.row_inherit') },
    ...choices(Object.keys(props.themeOptions.rowSpacings), 'theme.row_spacings'),
]);

/**
 * The gap above a row.
 *
 * A row may override the form's answer, which is why this is a margin per row
 * rather than one gap on the container: a container gap is the same everywhere
 * by definition, and "this row sits further down than the rest" is a thing an
 * intake form legitimately wants to say.
 *
 * Five pixels is the floor while editing, so two cards' hover outlines never
 * meet and read as one box with a line through it. Preview shows the real
 * number, because that is what the client will get.
 */
function spacingFor(row) {
    const key = row?.spacing ?? theme.value.row_spacing;
    const custom = row?.spacing ? row.spacing_px : theme.value.row_spacing_px;
    const px = key === 'custom'
        ? (custom ?? 16)
        : (props.themeOptions.rowSpacings[key]?.px ?? 16);

    return previewing.value ? px : Math.max(px, 5);
}

const rowStyle = (row, index) => (index === 0 ? {} : { marginTop: `${spacingFor(row)}px` });

/** The two column widths a two-column row divides into. */
const splitFor = (row) => props.themeOptions.columnSplits[row.split ?? '50_50'] ?? [50, 50];

/**
 * A two-column row, and one column on anything narrow.
 *
 * The breakpoint is in a media query rather than a Tailwind `sm:` class
 * because the widths are a 40/60 or 60/40 the business chose — they are data,
 * not one of a fixed set of classes, so they have to be an inline style, and
 * an inline style cannot carry a breakpoint. `matchMedia` is what decides
 * which of the two shapes this is.
 */
const wide = ref(true);

let watcher = null;

function twoColumnStyle(row) {
    if (!wide.value) return { gridTemplateColumns: '1fr' };

    const [left, right] = splitFor(row);

    return { gridTemplateColumns: `${left}fr ${right}fr` };
}

const buttonStyle = computed(() => ({
    borderRadius: theme.value.button_style === 'rounded' ? '999px' : `${radiusPx.value}px`,
    width: theme.value.button_style === 'full' ? '100%' : null,
    height: controlStyle.value.height,
    fontSize: controlStyle.value.fontSize,
}));

/* --------------------------------------------------------- building rows */

let sequence = allFields.value.length;
let rowSequence = rows.value.length;

/*
 * A key that is unique within this form and stable for the life of the field.
 *
 * It is what an answer is filed under, so it must not change when a question
 * is reworded — a submission that referenced the label would lose its answer
 * the first time somebody fixed a typo.
 */
function nextKey(type) {
    const taken = new Set(allFields.value.map((field) => field.key));

    do {
        sequence += 1;
    } while (taken.has(`${type}_${sequence}`));

    return `${type}_${sequence}`;
}

function nextRowKey() {
    const taken = new Set(rows.value.map((row) => row.key));

    do {
        rowSequence += 1;
    } while (taken.has(`row_${rowSequence}`));

    return `row_${rowSequence}`;
}

function blankField(type) {
    const traits = traitsByType.value[type] ?? {};
    const field = { key: nextKey(type), type, label: traits.label ?? type };

    if (traits.options) {
        field.options = [t('builder.option', 'Answer :number').replace(':number', '1')];
    }

    if (type === 'date' || type === 'date_of_birth') {
        field.format = Object.keys(props.dateFormats)[0] ?? 'mm/dd/yyyy';
    }

    if (OPTION_LAYOUT_TYPES.includes(type)) {
        field.option_layout = 'single';
    }

    if (type === 'before_after') {
        /* The two sides, named and counted, from the moment it is dropped in:
           an upload question with no cap and no labels is one the business
           has to finish before it means anything. */
        field.before_label = t('builder.before');
        field.after_label = t('builder.after');
        field.before_required = false;
        field.after_required = false;
        field.max_before = props.uploads.default_max_files ?? 10;
        field.max_after = props.uploads.default_max_files ?? 10;
        field.max_kb = props.uploads.max_kb ?? 5120;
        field.file_types = [...(props.uploads.default_types ?? [])];
    }

    if (!traits.content && !traits.hidden) {
        field.required = false;
    }

    return field;
}

function blankRow(field) {
    return {
        key: nextRowKey(),
        /* The form's default, not a hardcoded one: a business building a
           two-column intake form should not have to convert every row it
           adds. */
        layout: theme.value.default_layout ?? 'single',
        split: '50_50',
        spacing: null,
        spacing_px: null,
        fields: [field],
    };
}

/** A row left with no questions in it is not a row. */
function prune() {
    rows.value = rows.value.filter((row) => row.fields.length > 0);

    rows.value.forEach((row) => {
        if (row.fields.length < 2 && row.layout === 'two') return;
        row.layout = row.fields.length > 1 ? 'two' : 'single';
    });
}

function addField(type, at = null) {
    if (!editing.value) return;

    const field = blankField(type);
    const index = at === null ? rows.value.length : at;

    rows.value.splice(index, 0, blankRow(field));
    selectedKey.value = field.key;
}

/** Into the space beside a question, which is what makes a row two columns. */
function addBeside(type, rowIndex, side) {
    if (!editing.value) return;

    const row = rows.value[rowIndex];

    if (!row || row.fields.length >= 2) return;

    const field = blankField(type);

    row.fields.splice(side === 'left' ? 0 : row.fields.length, 0, field);
    row.layout = 'two';
    selectedKey.value = field.key;
}

function duplicateField(rowIndex, fieldIndex) {
    if (!editing.value) return;

    const original = rows.value[rowIndex].fields[fieldIndex];
    const copy = { ...original, key: nextKey(original.type) };

    if (Array.isArray(copy.options)) copy.options = [...copy.options];

    rows.value.splice(rowIndex + 1, 0, blankRow(copy));
    selectedKey.value = copy.key;
}

function removeField(rowIndex, fieldIndex) {
    if (!editing.value) return;

    const [gone] = rows.value[rowIndex].fields.splice(fieldIndex, 1);

    prune();

    if (selectedKey.value === gone.key) {
        selectedKey.value = rows.value[0]?.fields[0]?.key ?? null;
    }
}

function moveRow(index, by) {
    const to = index + by;

    if (!editing.value || to < 0 || to >= rows.value.length) return;

    const [row] = rows.value.splice(index, 1);
    rows.value.splice(to, 0, row);
}

/**
 * One column, or two.
 *
 * Going down to one does not delete the second question — it moves it into a
 * row of its own directly below. A layout control that threw away a question
 * would be a delete button wearing a different label.
 */
function setRowLayout(index, layout) {
    if (!editing.value) return;

    const row = rows.value[index];

    if (layout === 'single' && row.fields.length > 1) {
        const moved = row.fields.splice(1);

        moved.reverse().forEach((field) => rows.value.splice(index + 1, 0, blankRow(field)));
    }

    row.layout = layout;
}

function addOption(field) {
    field.options = [...(field.options ?? [])];
    field.options.push(t('builder.option', 'Answer :number').replace(':number', String(field.options.length + 1)));
}

function removeOption(field, index) {
    field.options.splice(index, 1);
}

/* ---------------------------------------------------------------- dragging

   One drop target — the canvas — rather than a row of slivers between the
   questions, and it answers two different questions from one pointer
   position: dropped in the gap between rows means a new row, dropped over the
   free half of a row that holds one question means that row becomes two
   columns.                                                                 */

const canvas = ref(null);

/** The question being moved, as {row, field}, or null on a rail drag. */
const dragFrom = ref(null);

/** The type being dragged in from the rail, or null on a reorder. */
const newType = ref(null);

/**
 * Where the drop would land.
 *
 *   { kind: 'gap',    index }              a new row at this position
 *   { kind: 'beside', row, side }          into the free half of this row
 */
const dropAt = ref(null);

function rowElements() {
    return [...(canvas.value?.querySelectorAll('[data-row]') ?? [])];
}

/**
 * What the pointer is currently over.
 *
 * Beside wins only where there is actually room — a row already holding two
 * questions cannot take a third, so the pointer falls through to the gap
 * above or below it and the drop makes a new row instead.
 */
function targetFromPoint(clientX, clientY) {
    const elements = rowElements();

    for (let i = 0; i < elements.length; i += 1) {
        const box = elements[i].getBoundingClientRect();

        if (clientY < box.top || clientY > box.bottom) continue;

        const row = rows.value[i];
        const isSource = dragFrom.value?.row === i && row?.fields.length === 1;

        /* Dropping a question beside itself is not a two-column row. */
        if (row && row.fields.length < 2 && !isSource) {
            const third = box.width / 3;

            if (clientX < box.left + third) return { kind: 'beside', row: i, side: 'left' };
            if (clientX > box.right - third) return { kind: 'beside', row: i, side: 'right' };
        }

        return { kind: 'gap', index: clientY < box.top + box.height / 2 ? i : i + 1 };
    }

    /* Above the first row, below the last, or on an empty canvas. */
    for (let i = 0; i < elements.length; i += 1) {
        const box = elements[i].getBoundingClientRect();

        if (clientY < box.top + box.height / 2) return { kind: 'gap', index: i };
    }

    return { kind: 'gap', index: elements.length };
}

function startFieldDrag(rowIndex, fieldIndex, event) {
    if (!editing.value) return;

    dragFrom.value = { row: rowIndex, field: fieldIndex };
    newType.value = null;

    /*
     * Safari and Firefox will not start a drag with nothing on the transfer,
     * and the first version of this set none — which is why dragging worked
     * in one browser and did nothing in the others.
     */
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', rows.value[rowIndex].fields[fieldIndex].key);
}

function startNewDrag(type, event) {
    if (!props.can.edit) return;

    newType.value = type;
    dragFrom.value = null;

    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('text/plain', type);
}

function onDragOver(event) {
    if (!editing.value) return;
    if (dragFrom.value === null && newType.value === null) return;

    /* Without this the browser refuses the drop and animates it back. */
    event.preventDefault();
    event.dataTransfer.dropEffect = newType.value ? 'copy' : 'move';

    dropAt.value = targetFromPoint(event.clientX, event.clientY);
}

/* Only when the pointer has actually left the canvas. dragleave fires on the
   way into every child, so the naive version flickered the indicator off and
   on across each question. */
function onDragLeave(event) {
    if (event.currentTarget.contains(event.relatedTarget)) return;

    dropAt.value = null;
}

function onDrop(event) {
    if (!editing.value) return;

    event.preventDefault();

    const target = targetFromPoint(event.clientX, event.clientY);
    const type = newType.value;
    const from = dragFrom.value;

    endDrag();

    if (type !== null) {
        if (target.kind === 'beside') addBeside(type, target.row, target.side);
        else addField(type, target.index);

        return;
    }

    if (from === null) return;

    const source = rows.value[from.row];
    const field = source.fields[from.field];

    if (!field) return;

    if (target.kind === 'beside') {
        const destination = rows.value[target.row];

        if (!destination || destination === source || destination.fields.length >= 2) return;

        source.fields.splice(from.field, 1);
        destination.fields.splice(target.side === 'left' ? 0 : destination.fields.length, 0, field);
        destination.layout = 'two';
        prune();

        return;
    }

    /* Out of its row and into a new one at the gap. The index is read before
       the removal, so a drop below the source row has to come back one. */
    let index = target.index;

    source.fields.splice(from.field, 1);

    if (source.fields.length === 0 && index > from.row) index -= 1;

    prune();
    rows.value.splice(Math.min(index, rows.value.length), 0, blankRow(field));
}

/* A drag abandoned outside the canvas still has to put the panel back. */
function endDrag() {
    dragFrom.value = null;
    newType.value = null;
    dropAt.value = null;
}

const gapActive = (index) => dropAt.value?.kind === 'gap' && dropAt.value.index === index;
const besideActive = (rowIndex, side) =>
    dropAt.value?.kind === 'beside' && dropAt.value.row === rowIndex && dropAt.value.side === side;

/* ------------------------------------------------------------- persistence */

let timer = null;
let inFlight = false;
let again = false;

const dirty = ref(false);

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function save() {
    if (!props.can.edit) return;

    if (inFlight) {
        /* One save at a time, and one more afterwards: a keystroke that lands
           while a write is in flight must not be the one that is lost. */
        again = true;

        return;
    }

    inFlight = true;
    status.value = 'saving';

    try {
        const response = await fetch(props.endpoints.schema, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ rows: rows.value, theme: theme.value }),
        });

        if (!response.ok) throw new Error(String(response.status));

        dirty.value = false;
        status.value = 'saved';
    } catch (error) {
        /* The changes are still in the panel, and the message says so. A
           builder that reported success it did not have would be worse than
           one that failed loudly. */
        status.value = 'failed';
        statusMessage.value = t('builder.save_failed');
    } finally {
        inFlight = false;

        if (again) {
            again = false;
            save();
        }
    }
}

function queueSave() {
    dirty.value = true;
    status.value = 'idle';
    window.clearTimeout(timer);
    timer = window.setTimeout(save, 1200);
}

/* The arrangement and the look are both saved, and both debounced: dragging a
   spacing slider must not be one request per pixel. */
watch(rows, queueSave, { deep: true });
watch(theme, queueSave, { deep: true });

async function publish() {
    if (!props.can.publish) return;

    /* Whatever is on screen, first. Publishing a version the server has an
       older copy of would put the wrong questions live. */
    window.clearTimeout(timer);
    await save();

    try {
        const response = await fetch(props.endpoints.publish, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        });

        const body = await response.json();

        if (!response.ok) {
            status.value = 'failed';
            statusMessage.value = body.message ?? t('builder.publish_failed');

            return;
        }

        formStatus.value = body.status;
        formStatusLabel.value = body.status_label;
        published.value = true;
        status.value = 'saved';
    } catch (error) {
        status.value = 'failed';
        statusMessage.value = t('builder.publish_failed');
    }
}

async function leave(closeWindow) {
    window.clearTimeout(timer);
    if (dirty.value) await save();

    if (!closeWindow) {
        window.location.assign(props.endpoints.back);

        return;
    }

    window.close();

    /* A tab the script did not open cannot close itself, which is most of
       them. Still on screen a moment later means it refused, so go back to the
       list rather than leaving somebody on a page with no way out. */
    window.setTimeout(() => window.location.assign(props.endpoints.back), 150);
}

/* Work in progress is worth a browser's own warning. */
function guard(event) {
    if (!dirty.value) return;

    event.preventDefault();
    event.returnValue = '';
}

onMounted(() => {
    window.addEventListener('beforeunload', guard);

    /* 640px is Tailwind's own `sm`, so the two-column rows turn over at the
       same width as everything else on the page. */
    watcher = window.matchMedia('(min-width: 640px)');
    wide.value = watcher.matches;
    watcher.addEventListener('change', (event) => { wide.value = event.matches; });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', guard);
    window.clearTimeout(timer);
    watcher = null;
});

const statusText = computed(() => {
    if (status.value === 'saving') return t('builder.saving');
    if (status.value === 'saved') return t('builder.saved');
    if (status.value === 'failed') return statusMessage.value;

    return dirty.value ? t('builder.unsaved') : '';
});
</script>

<template>
  <div class="flex flex-col min-h-0 flex-1">
    <!-- Three columns rather than a flex row, so the name sits in the middle
         of the HEADER rather than in the middle of what is left over after
         Back. The outer columns are 1fr each and therefore equal, which is
         what makes the centre column actually central however wide the two
         groups of buttons happen to be. -->
    <header class="shrink-0 h-14 px-3 sm:px-4 grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-b border-line bg-white">
      <div class="min-w-0 justify-self-start">
        <button type="button" class="styledesk_action" @click="leave(false)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          {{ t('builder.back') }}
        </button>
      </div>

      <div class="min-w-0 justify-self-center flex items-center gap-2.5">
        <span class="truncate text-[14px] font-semibold text-head">{{ form.name }}</span>
        <span class="styledesk_badge shrink-0"
              :class="formStatus === 'active' ? 'styledesk_badge--active' : 'styledesk_badge--setup'">
          {{ formStatusLabel }}
        </span>
      </div>

      <div class="min-w-0 justify-self-end flex items-center gap-2">
        <!-- The save state moved out of the centre with the name: its wording
             changes as the panel works, and a centred group that grows and
             shrinks would slide the form's name from side to side. Anchored on
             the right, it grows leftwards and the buttons stay put. -->
        <span class="min-w-0 truncate text-[12px] text-sub" :class="{ 'text-danger': status === 'failed' }"
              :title="statusText" role="status" aria-live="polite">{{ statusText }}</span>

        <button v-if="can.edit" type="button" class="styledesk_action" @click="save">{{ t('builder.save') }}</button>

        <button type="button" class="styledesk_action" @click="previewing = !previewing">
          {{ previewing ? t('builder.editing') : t('builder.preview') }}
        </button>

        <button v-if="can.publish" type="button" @click="publish"
                class="h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
          {{ published ? t('builder.published') : t('builder.publish') }}
        </button>

        <button type="button" class="styledesk_action" @click="leave(true)">{{ t('builder.close') }}</button>
      </div>
    </header>

    <!-- Wider rails. The left one holds a field name and its icon on one
         line without truncating "Terms acceptance", and the right one holds a
         label and its control without the control being narrower than the
         words above it. -->
    <div class="flex-1 min-h-0 grid" :class="gridClass">
      <!-- Left: two tools, one column. What goes into the form, or how the
           form looks — never both at once, and switching between them leaves
           the selected question and every unsaved change exactly where they
           were. -->
      <aside v-if="can.edit && !previewing"
             class="hidden lg:flex flex-col min-h-0 border-r border-line bg-white">
        <div class="shrink-0 p-3 pb-0">
          <div class="flex p-0.5 rounded-lg bg-hover" role="tablist">
            <button v-for="tab in ['elements', 'themes']" :key="tab" type="button" role="tab"
                    :aria-selected="panel === tab" @click="panel = tab"
                    class="flex-1 h-8 rounded-[6px] text-[12.5px] font-medium transition-colors"
                    :class="panel === tab ? 'bg-white text-head shadow-sm' : 'text-sub hover:text-ink'">
              {{ t('theme.tabs.' + tab) }}
            </button>
          </div>
        </div>

        <!-- ------------------------------------------------ form elements -->
        <div v-if="panel === 'elements'" class="flex-1 min-h-0 overflow-y-auto p-3">
          <div v-for="group in groups" :key="group.key" class="mb-3">
            <p class="text-[12px] font-medium text-sub px-1 mb-1.5">{{ group.label }}</p>

            <div class="grid gap-1">
              <button v-for="type in group.types" :key="type.key" type="button"
                      draggable="true"
                      @dragstart="startNewDrag(type.key, $event)"
                      @dragend="endDrag"
                      @click="addField(type.key)"
                      class="w-full flex items-center gap-2 text-left px-2.5 py-2 rounded-lg text-[13px] text-ink hover:bg-hover transition-colors cursor-grab">
                <!-- Local path constants, so nothing here is untrusted markup. -->
                <svg class="shrink-0 text-sub" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true" v-html="iconFor(type.key)"></svg>
                <span class="min-w-0 truncate">{{ type.label }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ----------------------------------------------------- ui themes -->
        <div v-else class="flex-1 min-h-0 overflow-y-auto p-3 space-y-4">
          <div>
            <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ t('theme.properties') }}</h2>

            <div class="mt-2 space-y-2.5">
              <div>
                <label for="theme-width" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.width') }}</label>
                <SdCombo id="theme-width" v-model="theme.width" :options="widthChoices"
                         :search-label="t('theme.width')" :search-placeholder="t('common.search')" />
              </div>

              <div>
                <label for="theme-align" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.alignment') }}</label>
                <SdCombo id="theme-align" v-model="theme.alignment" :options="alignmentChoices"
                         :search-label="t('theme.alignment')" />
              </div>

              <div>
                <label for="theme-label" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.label_position') }}</label>
                <SdCombo id="theme-label" v-model="theme.label_position" :options="labelPositionChoices"
                         :search-label="t('theme.label_position')" />
              </div>

              <div>
                <label for="theme-style" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.field_style') }}</label>
                <SdCombo id="theme-style" v-model="theme.field_style" :options="fieldStyleChoices"
                         :search-label="t('theme.field_style')" />
              </div>

              <div>
                <label for="theme-radius" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.radius') }}</label>
                <SdCombo id="theme-radius" v-model="theme.radius" :options="radiusChoices"
                         :search-label="t('theme.radius')" />
              </div>

              <div>
                <label for="theme-bg" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.background') }}</label>
                <SdCombo id="theme-bg" v-model="theme.background" :options="backgroundChoices"
                         :search-label="t('theme.background')" />
              </div>

              <div v-if="theme.background === 'custom'">
                <label for="theme-bg-color" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.background_color') }}</label>
                <input id="theme-bg-color" type="color" v-model="theme.background_color"
                       class="h-9 w-full rounded-md border border-stroke bg-white p-1">
              </div>

              <div>
                <label for="theme-button" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.button_style') }}</label>
                <SdCombo id="theme-button" v-model="theme.button_style" :options="buttonStyleChoices"
                         :search-label="t('theme.button_style')" />
              </div>

              <p class="text-[12px] text-sub leading-relaxed">{{ t('theme.branding_note') }}</p>
            </div>
          </div>

          <!-- The buttons at the end of the form: what they say, where they
               sit, and whether there are two of them. -->
          <div class="pt-3 border-t border-line">
            <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ t('theme.buttons') }}</h2>

            <div class="mt-2 space-y-2.5">
              <div>
                <label for="theme-submit-label" class="block text-[12px] font-medium text-ink mb-1">
                  {{ t('theme.submit_label') }}
                  <span class="text-faint font-normal">{{ t('common.optional') }}</span>
                </label>
                <input id="theme-submit-label" v-model="theme.submit_label" maxlength="40"
                       :placeholder="t('builder.submit')"
                       class="sd-input !h-9 !px-2.5 !text-[13px]">
              </div>

              <div>
                <label for="theme-button-align" class="block text-[12px] font-medium text-ink mb-1">
                  {{ t('theme.button_alignment') }}
                </label>
                <SdCombo id="theme-button-align" v-model="theme.button_alignment"
                         :options="buttonAlignmentChoices"
                         :search-label="t('theme.button_alignment')" />
              </div>

              <label class="styledesk_choice !text-[13px]">
                <input type="checkbox" class="sd-check" v-model="theme.show_cancel">
                <span class="styledesk_choice__label">{{ t('theme.show_cancel') }}</span>
              </label>

              <div v-if="theme.show_cancel">
                <label for="theme-cancel-label" class="block text-[12px] font-medium text-ink mb-1">
                  {{ t('theme.cancel_label') }}
                  <span class="text-faint font-normal">{{ t('common.optional') }}</span>
                </label>
                <input id="theme-cancel-label" v-model="theme.cancel_label" maxlength="40"
                       :placeholder="t('builder.previous')"
                       class="sd-input !h-9 !px-2.5 !text-[13px]">
              </div>

              <!-- Blank wording is the only answer that stays translated: a
                   business that types "Submit" here has written English into
                   a form its Spanish clients will read. -->
              <p class="text-[12px] text-sub leading-relaxed">{{ t('theme.label_default') }}</p>
            </div>
          </div>

          <!-- Keeping what the client has typed. A twenty-question medical
               history is not something anybody wants to type twice. -->
          <div class="pt-3 border-t border-line">
            <label class="styledesk_choice !text-[13px]">
              <input type="checkbox" class="sd-check" v-model="theme.save_progress">
              <span class="styledesk_choice__label">{{ t('theme.save_progress') }}</span>
            </label>

            <p class="mt-1.5 text-[12px] text-sub leading-relaxed">{{ t('theme.save_progress_hint') }}</p>
          </div>

          <div class="pt-3 border-t border-line">
            <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ t('theme.row_spacing') }}</h2>

            <div class="mt-2 grid gap-1">
              <label v-for="(spec, key) in themeOptions.rowSpacings" :key="key" class="styledesk_choice !text-[13px]">
                <input type="radio" class="sd-radio" name="row-spacing" :value="key" v-model="theme.row_spacing">
                <span class="styledesk_choice__label">{{ t('theme.row_spacings.' + key) }}</span>
              </label>
            </div>

            <div v-if="theme.row_spacing === 'custom'" class="mt-2 flex items-center gap-2">
              <input type="number" min="0" max="200" v-model.number="theme.row_spacing_px"
                     class="sd-input !h-9 !px-2.5 !text-[13px] !w-20"
                     :aria-label="t('theme.row_spacing_px')">
              <span class="text-[12px] text-sub">{{ t('theme.px') }}</span>
            </div>
          </div>

          <div class="pt-3 border-t border-line">
            <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ t('theme.structure') }}</h2>

            <!-- Drawn rather than described. Two arrangements are a thing to
                 look at, and a pair of diagrams says which is which faster
                 than either label does.

                 What this sets is what a NEW row starts as: the arrangement
                 belongs to each row, so a form-wide switch that forced every
                 row to match would make "first name beside last name,
                 medical history on its own" impossible. -->
            <p class="mt-2 text-[12px] font-medium text-ink">{{ t('theme.layout') }}</p>

            <div class="mt-1.5 grid grid-cols-2 gap-2">
              <button v-for="layout in themeOptions.rowLayouts" :key="layout" type="button"
                      @click="theme.default_layout = layout"
                      class="flex flex-col items-center gap-1.5 px-2 py-2.5 rounded-lg border transition-colors text-[12px]"
                      :class="(theme.default_layout ?? 'single') === layout
                        ? 'border-head bg-hover text-head font-medium'
                        : 'border-line text-sub hover:border-stroke hover:text-ink'">
                <svg width="30" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"
                     v-html="layoutIcon(layout)"></svg>
                {{ t('theme.row_layouts.' + layout) }}
              </button>
            </div>

            <p class="mt-2.5 text-[12px] text-sub leading-relaxed">{{ t('theme.drop_beside') }}</p>
            <p class="mt-1.5 text-[12px] text-sub leading-relaxed">{{ t('theme.responsive_note') }}</p>
          </div>
        </div>
      </aside>

      <!-- Centre: the form itself, wearing whatever the theme says.

           In Preview it is handed to FormPreview — the same renderer the
           public form will use — rather than to a second drawing of it. A
           preview built from different markup is one that can agree with the
           builder and still disagree with the client. -->
      <section class="overflow-y-auto bg-canvas p-4 sm:p-6">
        <FormPreview v-if="previewing"
                     :form="form" :rows="rows" :theme="theme" :theme-options="themeOptions"
                     :date-formats="dateFormats" :uploads="uploads" :labels="labels" />

        <!-- No transition on this one.
             `transition-all` animated max-width and grid-template-columns
             across every row in the form: expensive on a long form, and it
             made a width change arrive over 150ms instead of at once — which
             is the opposite of what a live preview is for. Colours may fade;
             layout lands. -->
        <div v-else :style="canvasStyle">
          <!-- The whole card takes the drop, empty or not: the one thing a new
               form must accept is the first field dragged into it. -->
          <div ref="canvas" class="sd-card p-5 sm:p-6 transition-colors"
               :class="dropAt ? 'ring-1 ring-brand' : ''"
               :style="{ borderRadius: canvasStyle.borderRadius, backgroundColor: canvasStyle.backgroundColor }"
               @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop">
            <div :class="theme.alignment === 'center' ? 'text-center' : ''">
              <h1 class="text-[20px] font-bold text-head">{{ form.name }}</h1>
              <p class="text-[13px] text-sub mt-1">{{ form.typeLabel }}</p>
            </div>

            <div v-if="!rows.length" class="mt-6">
              <p class="text-[13px] text-sub">{{ t('builder.canvas_empty') }}</p>

              <div v-if="editing"
                   class="mt-3 h-24 rounded-card border border-dashed transition-colors grid place-items-center text-[12.5px]"
                   :class="dropAt ? 'border-brand text-brand bg-brand/5' : 'border-line text-sub'">
                {{ t('builder.insert_here') }}
              </div>
            </div>

            <div v-else class="mt-5">
              <template v-for="(row, rowIndex) in rows" :key="row.key">
                <!-- The gap above this row, and the line drawn in it when the
                     pointer is there. -->
                <div class="relative" :style="rowStyle(row, rowIndex)">
                  <div class="absolute left-0 right-0 -top-[3px] h-0.5 rounded-full transition-colors"
                       :class="gapActive(rowIndex) ? 'bg-brand' : 'bg-transparent'"></div>

                  <div data-row class="relative">
                    <!-- The two halves a question can be dropped beside,
                         which is what turns a row into two columns. Only
                         while a drag is in progress, and only on a row with
                         room. -->
                    <template v-if="editing && dropAt && row.fields.length < 2">
                      <div class="absolute inset-y-0 left-0 w-1/3 rounded-l-card border-2 border-dashed transition-colors pointer-events-none"
                           :class="besideActive(rowIndex, 'left') ? 'border-brand bg-brand/5' : 'border-transparent'"></div>
                      <div class="absolute inset-y-0 right-0 w-1/3 rounded-r-card border-2 border-dashed transition-colors pointer-events-none"
                           :class="besideActive(rowIndex, 'right') ? 'border-brand bg-brand/5' : 'border-transparent'"></div>
                    </template>

                    <!-- One column or two, and two becomes one on a phone:
                         the client must never scroll sideways to read a
                         question. -->
                    <div :class="row.layout === 'two' ? 'grid grid-cols-1 gap-3' : ''"
                         :style="row.layout === 'two' ? twoColumnStyle(row) : {}">
                      <div v-for="(field, fieldIndex) in row.fields" :key="field.key"
                           data-field-row
                           @click="editing ? selectedKey = field.key : null"
                           :draggable="editing"
                           @dragstart="startFieldDrag(rowIndex, fieldIndex, $event)"
                           @dragend="endDrag"
                           class="rounded-card px-3 py-3 border transition-colors"
                           :class="[
                             editing ? 'cursor-grab hover:bg-white hover:border-head' : 'border-transparent',
                             selectedKey === field.key && editing ? 'bg-white border-head' : 'border-transparent',
                             /* Dimmed, not removed: the business still has to
                                be able to find and edit a question its
                                clients never see. */
                             field.hidden ? 'opacity-55' : '',
                           ]">
                        <!-- Layout pieces are not questions, so they render as
                             the thing they are rather than as a labelled
                             field. -->
                        <template v-if="traitsFor(field).content">
                          <h2 v-if="field.type === 'heading'" class="text-[16px] font-semibold text-head">{{ field.label }}</h2>
                          <h3 v-else-if="field.type === 'section'" class="text-[13px] font-semibold uppercase tracking-wide text-sub">{{ field.label }}</h3>
                          <p v-else-if="field.type === 'paragraph'" class="text-[13px] text-ink leading-relaxed">{{ field.label }}</p>
                          <hr v-else-if="field.type === 'divider'" class="border-line">
                          <label v-else-if="field.type === 'terms'" class="styledesk_choice">
                            <input type="checkbox" class="sd-check" disabled>
                            <span class="styledesk_choice__label">{{ field.label }}</span>
                          </label>
                          <p v-else class="text-[12px] text-sub border-t border-dashed border-line pt-2">
                            {{ t('builder.page_break_hint') }}
                          </p>
                        </template>

                        <template v-else>
                          <div :class="labelsBeside ? 'grid grid-cols-[130px_minmax(0,1fr)] gap-3 items-start' : ''">
                            <div class="min-w-0">
                              <!-- Edited where it sits. An input rather than a
                                   contenteditable: it is a value being
                                   changed, and the browser already knows how
                                   to do that accessibly.

                                   Still editable when the client will not see
                                   it: a hidden field and a nameless one are
                                   both things the business reads this answer
                                   under, so the name stays here. -->
                              <div class="flex items-center gap-1.5">
                                <input v-if="editing" v-model="field.label"
                                       class="w-full bg-transparent text-[13px] font-medium text-ink border-0 p-0 focus:ring-0 focus:outline-none"
                                       :class="field.hide_label ? 'line-through decoration-faint' : ''"
                                       :aria-label="t('builder.field_name')">
                                <p v-else class="text-[13px] font-medium text-ink">
                                  {{ field.label }}
                                  <span v-if="field.required" class="text-danger" :title="t('builder.required_mark')">*</span>
                                </p>

                                <!-- What the client will not get. Said on the
                                     canvas, because a question that never
                                     reaches anybody looks exactly like one
                                     that does. -->
                                <span v-if="field.hidden" class="styledesk_metachip shrink-0">{{ t('builder.hidden_badge') }}</span>
                                <span v-else-if="field.disabled" class="styledesk_metachip shrink-0">{{ t('builder.disabled_badge') }}</span>
                              </div>

                              <p v-if="field.description && (field.help_position ?? 'below') === 'above'"
                                 class="text-[12px] text-sub mt-0.5">{{ field.description }}</p>
                            </div>

                            <!-- What the client will be answering with, at
                                 whatever size and radius the theme says.
                                 Inert here: the canvas shows the shape of the
                                 question, and a working control would invite
                                 somebody to fill in a form they are
                                 building. -->
                            <div :class="labelsBeside ? '' : 'mt-1.5'">
                              <textarea v-if="field.type === 'long_text'" rows="2" class="sd-input !h-auto py-2" disabled
                                        :style="areaStyle" :placeholder="field.placeholder"></textarea>

                              <!-- A dropdown, drawn as the combo the client
                                   will actually get rather than as the
                                   browser's own select: the two look nothing
                                   alike, and the canvas is a picture of the
                                   real thing. -->
                              <div v-else-if="field.type === 'dropdown'"
                                   class="sd-input sd-combo-btn is-placeholder pointer-events-none"
                                   :style="controlStyle">
                                <span class="sd-combo-btn__label">
                                  {{ field.placeholder || (field.options ?? [])[0] || t('builder.option').replace(':number', '1') }}
                                </span>
                                <svg class="sd-combo-btn__caret" width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                  <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                              </div>

                              <!-- Several answers, so several tags. A multi
                                   select rendered as a tall native listbox
                                   tells the business nothing about what the
                                   client will see; chips in a box do. -->
                              <div v-else-if="field.type === 'multi_select'"
                                   class="sd-input !h-auto !py-1.5 !flex flex-wrap items-center gap-1.5 pointer-events-none"
                                   :style="{ fontSize: controlStyle.fontSize, borderRadius: controlStyle.borderRadius, minHeight: controlStyle.height }">
                                <span v-for="(option, i) in (field.options ?? []).slice(0, 3)" :key="i"
                                      class="inline-flex items-center gap-1 px-2 h-6 rounded-full bg-hover text-ink text-[12px]">
                                  {{ option }}
                                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                       stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6L6 18" />
                                  </svg>
                                </span>

                                <span v-if="!(field.options ?? []).length" class="text-faint">
                                  {{ field.placeholder || t('builder.add_option') }}
                                </span>

                                <svg class="sd-combo-btn__caret ml-auto" width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                  <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                              </div>

                              <div v-else-if="field.type === 'yes_no'" :class="optionColumns(field)">
                                <label v-for="answer in [t('common.yes'), t('common.no')]" :key="answer"
                                       class="styledesk_choice">
                                  <input type="radio" class="sd-radio" disabled>
                                  <span class="styledesk_choice__label">{{ answer }}</span>
                                </label>
                              </div>

                              <div v-else-if="field.type === 'checkbox' || field.type === 'multiple_choice'"
                                   :class="optionColumns(field)">
                                <label v-for="(option, i) in field.options ?? []" :key="i" class="styledesk_choice">
                                  <input :type="field.type === 'checkbox' ? 'checkbox' : 'radio'"
                                         :class="field.type === 'checkbox' ? 'sd-check' : 'sd-radio'" disabled>
                                  <span class="styledesk_choice__label">{{ option }}</span>
                                </label>
                              </div>

                              <label v-else-if="field.type === 'consent'" class="styledesk_choice">
                                <input type="checkbox" class="sd-check" disabled>
                                <span class="styledesk_choice__label">{{ t('builder.consent_hint') }}</span>
                              </label>

                              <div v-else-if="field.type === 'signature'"
                                   class="h-20 border border-dashed border-line grid place-items-center text-[12px] text-sub"
                                   :style="{ borderRadius: controlStyle.borderRadius }">
                                {{ t('builder.signature_hint') }}
                              </div>

                              <!-- Signed AND named. A drawn signature is hard
                                   to read back, and the point of a waiver is
                                   being able to say who signed it — so this
                                   asks for both, and the two land in the
                                   columns the submission already keeps for
                                   them. -->
                              <div v-else-if="field.type === 'signature_name'" class="space-y-1.5">
                                <div class="h-20 border border-dashed border-line grid place-items-center text-[12px] text-sub"
                                     :style="{ borderRadius: controlStyle.borderRadius }">
                                  {{ t('builder.signature_hint') }}
                                </div>

                                <input class="sd-input" disabled :style="controlStyle"
                                       :placeholder="field.placeholder || t('builder.full_name_hint')">
                              </div>

                              <div v-else-if="field.type === 'initials'"
                                   class="h-12 w-32 border border-dashed border-line grid place-items-center text-center text-[12px] text-sub px-2"
                                   :style="{ borderRadius: controlStyle.borderRadius }">
                                {{ t('builder.initials_hint') }}
                              </div>

                              <!-- Both sides on the canvas, so the business
                                   can see the shape of what it is asking
                                   for. The working uploader is in Preview. -->
                              <div v-else-if="field.type === 'before_after'" class="space-y-2">
                                <div v-for="side in ['before', 'after']" :key="side">
                                  <p class="text-[12px] font-semibold uppercase tracking-wide text-sub">
                                    {{ side === 'before'
                                        ? (field.before_label || t('builder.before'))
                                        : (field.after_label || t('builder.after')) }}
                                    <span v-if="side === 'before' ? field.before_required : field.after_required"
                                          class="text-danger">*</span>
                                  </p>

                                  <p v-if="side === 'before' ? field.before_help : field.after_help"
                                     class="text-[12px] text-sub">
                                    {{ side === 'before' ? field.before_help : field.after_help }}
                                  </p>

                                  <div class="mt-1 h-14 border border-dashed border-line grid place-items-center text-[12px] text-sub"
                                       :style="{ borderRadius: controlStyle.borderRadius }">
                                    {{ side === 'before' ? t('builder.upload_before') : t('builder.upload_after') }}
                                  </div>
                                </div>
                              </div>

                              <div v-else-if="field.type === 'file_upload'"
                                   class="h-12 border border-dashed border-line grid place-items-center text-[12px] text-sub"
                                   :style="{ borderRadius: controlStyle.borderRadius }">
                                {{ t('builder.upload_hint') }}
                              </div>

                              <div v-else-if="field.type === 'rating'" class="flex gap-1 text-faint">
                                <svg v-for="n in 5" :key="n" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.6" aria-hidden="true" v-html="ICONS.rating"></svg>
                              </div>

                              <div v-else-if="field.type === 'scale'" class="flex gap-1.5">
                                <span v-for="n in 10" :key="n"
                                      class="grid place-items-center border border-line text-[12px] text-sub"
                                      :style="{ width: '28px', height: '28px', borderRadius: controlStyle.borderRadius }">{{ n }}</span>
                              </div>

                              <p v-else-if="field.type === 'hidden'" class="text-[12px] text-sub italic">
                                {{ traitsFor(field).label }}
                              </p>

                              <!-- A date, as the client meets it: a field
                                   with a calendar behind an icon. The
                                   calendar opens in Preview — an always-open
                                   one took eight lines of the canvas to
                                   describe one question. -->
                              <div v-else-if="field.type === 'date'" class="relative max-w-[240px]">
                                <!-- A real input rather than a div wearing
                                     the input's class.

                                     `.sd-input` declares `display: block`,
                                     and it is a plain class in the same
                                     stylesheet as Tailwind's `.flex` — equal
                                     specificity, so source order decides and
                                     block wins. `items-center` then did
                                     nothing and the placeholder sat two
                                     pixels from the top of the box. An input
                                     centres its own text with no help. -->
                                <input class="sd-input has-suffix pointer-events-none" disabled
                                       :style="controlStyle"
                                       :placeholder="dateFormats[field.format ?? 'mm/dd/yyyy']?.pattern">
                                <span class="absolute inset-y-0 right-0 w-10 grid place-items-center text-sub" aria-hidden="true">
                                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                       stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3.5" y="5" width="17" height="15" rx="2" />
                                    <path d="M3.5 10h17M8 3v4M16 3v4" />
                                  </svg>
                                </span>
                              </div>

                              <!-- A date of birth is typed, in the pattern
                                   the business chose. -->
                              <input v-else-if="field.type === 'date_of_birth'" disabled
                                     class="sd-input pointer-events-none max-w-[240px]" :style="controlStyle"
                                     :placeholder="dateFormats[field.format ?? 'mm/dd/yyyy']?.pattern">

                              <input v-else class="sd-input" disabled :style="controlStyle"
                                     :type="field.type === 'time' ? 'time' : (field.type === 'number' ? 'number' : 'text')"
                                     :placeholder="field.placeholder" :value="field.default_value">

                              <p v-if="field.description && (field.help_position ?? 'below') !== 'above'"
                                 class="text-[12px] text-sub mt-1">{{ field.description }}</p>

                              <p v-if="field.show_counter && field.max_length" class="text-[12px] text-faint mt-1">
                                {{ t('builder.counter').replace(':count', '0').replace(':max', String(field.max_length)) }}
                              </p>
                            </div>
                          </div>
                        </template>

                        <!-- The row's own actions, on the selected question
                             only: a toolbar on all twenty would be twenty
                             toolbars. -->
                        <div v-if="editing && selectedKey === field.key" class="mt-2 flex flex-wrap items-center gap-1.5">
                          <button type="button" class="styledesk_action !h-7 !px-2 !text-[12px]"
                                  :disabled="rowIndex === 0" @click.stop="moveRow(rowIndex, -1)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" v-html="ACTION_ICONS.move_up"></svg>
                            {{ t('builder.move_up') }}
                          </button>

                          <button type="button" class="styledesk_action !h-7 !px-2 !text-[12px]"
                                  :disabled="rowIndex === rows.length - 1" @click.stop="moveRow(rowIndex, 1)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" v-html="ACTION_ICONS.move_down"></svg>
                            {{ t('builder.move_down') }}
                          </button>

                          <button type="button" class="styledesk_action !h-7 !px-2 !text-[12px]"
                                  @click.stop="duplicateField(rowIndex, fieldIndex)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" v-html="ACTION_ICONS.duplicate"></svg>
                            {{ t('builder.duplicate') }}
                          </button>

                          <button type="button" class="styledesk_action !h-7 !px-2 !text-[12px] !text-danger"
                                  @click.stop="removeField(rowIndex, fieldIndex)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" v-html="ACTION_ICONS.delete"></svg>
                            {{ t('builder.delete') }}
                          </button>

                          <!-- The row's layout, where the row is. One column
                               or two, and going back to one moves the second
                               question into a row of its own rather than
                               throwing it away. -->
                          <span class="mx-0.5 w-px h-5 bg-line" aria-hidden="true"></span>

                          <button v-for="layout in themeOptions.rowLayouts" :key="layout" type="button"
                                  class="styledesk_action !h-7 !px-2 !text-[12px]"
                                  :class="row.layout === layout ? '!bg-hover !text-head !border-head' : ''"
                                  @click.stop="setRowLayout(rowIndex, layout)">
                            <svg width="15" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"
                                 v-html="layoutIcon(layout)"></svg>
                            {{ t('theme.row_layouts.' + layout) }}
                          </button>
                        </div>
                      </div>

                      <!-- The empty half of a two-column row: somewhere to
                           aim at, and an explanation of what dropping there
                           does. -->
                      <div v-if="editing && row.layout === 'two' && row.fields.length === 1"
                           class="hidden sm:grid place-items-center rounded-card border border-dashed border-line text-[12px] text-sub px-2 text-center min-h-[64px]">
                        {{ t('theme.drop_beside') }}
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <!-- And the gap after the last row. No height of its own, so it
                   does not add to the last gap. -->
              <div class="relative h-0">
                <div class="absolute left-0 right-0 top-[3px] h-0.5 rounded-full transition-colors"
                     :class="gapActive(rows.length) ? 'bg-brand' : 'bg-transparent'"></div>
              </div>

              <!-- The submit button the client will press, so the button
                   style is something somebody can actually see. -->
              <div class="mt-6" :class="theme.alignment === 'center' ? 'text-center' : ''">
                <button type="button" disabled :style="buttonStyle"
                        class="px-5 bg-brand text-white font-semibold opacity-90">
                  {{ t('builder.submit') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Right: the selected question, or the form when nothing is selected.

           A settings rail, not a form the client fills in. `.sd-input` is
           44px tall, which is right for a page somebody types their address
           into and far too heavy for a column of four properties read at a
           glance — the panel was taller than the question it was describing.
           Everything here is overridden to the 36px compact size the filter
           rows already use. -->
      <!-- Light grey rather than white, so the rail reads as a panel beside
           the form rather than as more of the same sheet — and so the white
           controls inside it stand off their own background. `bg-hover` is
           the palette's light grey; the app already uses it as a surface. -->
      <aside v-if="!previewing" class="hidden lg:block overflow-y-auto border-l border-line bg-hover p-3.5">
        <template v-if="selected && can.edit">
          <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ t('builder.field_settings') }}</h2>
          <p class="text-[12px] text-sub mt-0.5">{{ traitsFor(selected).label }}</p>

          <div class="mt-2.5 space-y-2.5">
            <!-- 1. The name the client reads. -->
            <div>
              <label class="block text-[12px] font-medium text-ink mb-1" :for="'label-' + selected.key">
                {{ isContent(selected) ? t('builder.content') : t('builder.field_name') }}
              </label>
              <input :id="'label-' + selected.key" v-model="selected.label" maxlength="255"
                     class="sd-input !h-9 !px-2.5 !text-[13px]">
            </div>

            <!-- 2. One state, not three contradictory toggles. -->
            <div v-if="isQuestion(selected)">
              <p class="block text-[12px] font-medium text-ink mb-1">{{ t('builder.state') }}</p>

              <div class="grid gap-1">
                <label class="styledesk_choice !text-[13px]">
                  <input type="radio" class="sd-radio" :name="'state-' + selected.key"
                         :checked="stateOf(selected) === 'none'" @change="setState(selected, 'none')">
                  <span class="styledesk_choice__label">{{ t('common.optional') }}</span>
                </label>

                <label class="styledesk_choice !text-[13px]">
                  <input type="radio" class="sd-radio" :name="'state-' + selected.key"
                         :checked="stateOf(selected) === 'required'" @change="setState(selected, 'required')">
                  <span class="styledesk_choice__label">{{ t('builder.required') }}</span>
                </label>

                <label class="styledesk_choice !text-[13px]">
                  <input type="radio" class="sd-radio" :name="'state-' + selected.key"
                         :checked="stateOf(selected) === 'disabled'" @change="setState(selected, 'disabled')">
                  <span class="styledesk_choice__label">
                    {{ t('builder.disabled') }}
                    <span class="styledesk_choice__hint">{{ t('builder.disabled_hint') }}</span>
                  </span>
                </label>

                <label class="styledesk_choice !text-[13px]">
                  <input type="radio" class="sd-radio" :name="'state-' + selected.key"
                         :checked="stateOf(selected) === 'hidden'" @change="setState(selected, 'hidden')">
                  <span class="styledesk_choice__label">
                    {{ t('builder.hidden') }}
                    <span class="styledesk_choice__hint">{{ t('builder.hidden_hint') }}</span>
                  </span>
                </label>
              </div>
            </div>

            <!-- 3. The field without its name above it. -->
            <label v-if="isQuestion(selected)" class="styledesk_choice !text-[13px]">
              <input type="checkbox" class="sd-check" v-model="selected.hide_label">
              <span class="styledesk_choice__label">{{ t('builder.hide_label') }}</span>
            </label>

            <!-- 4. What the field starts with. -->
            <div v-if="canPrefill(selected)">
              <label class="block text-[12px] font-medium text-ink mb-1" :for="'default-' + selected.key">
                {{ t('builder.prefill') }}
                <span class="text-faint font-normal">{{ t('common.optional') }}</span>
              </label>
              <input :id="'default-' + selected.key" v-model="selected.default_value" maxlength="255"
                     class="sd-input !h-9 !px-2.5 !text-[13px]">
            </div>

            <!-- 5. What an empty field suggests. -->
            <div v-if="traitsFor(selected).placeholder">
              <label class="block text-[12px] font-medium text-ink mb-1" :for="'ph-' + selected.key">
                {{ t('builder.placeholder') }}
                <span class="text-faint font-normal">{{ t('common.optional') }}</span>
              </label>
              <input :id="'ph-' + selected.key" v-model="selected.placeholder" maxlength="120"
                     class="sd-input !h-9 !px-2.5 !text-[13px]">
            </div>

            <!-- 6 and 7. The instruction, and which side of the field it
                 belongs on. Below by default: an instruction above a field is
                 read before it is needed. -->
            <div v-if="isQuestion(selected)">
              <label class="block text-[12px] font-medium text-ink mb-1" :for="'desc-' + selected.key">
                {{ t('builder.help_text') }}
                <span class="text-faint font-normal">{{ t('common.optional') }}</span>
              </label>
              <textarea :id="'desc-' + selected.key" v-model="selected.description" rows="2" maxlength="500"
                        class="sd-input !h-auto !px-2.5 !py-1.5 !text-[13px]"></textarea>
            </div>

            <div v-if="isQuestion(selected) && selected.description">
              <label for="help-position" class="block text-[12px] font-medium text-ink mb-1">
                {{ t('builder.help_position') }}
              </label>
              <SdCombo id="help-position" v-model="selected.help_position" :options="helpPositionChoices"
                       :search-label="t('builder.help_position')" />
            </div>

            <!-- 8 and 9. A cap, and whether the client watches it approach.
                 The counter setting appears only once there is a cap to
                 count towards. -->
            <div v-if="canCount(selected)">
              <label class="block text-[12px] font-medium text-ink mb-1" :for="'max-' + selected.key">
                {{ t('builder.max_length') }}
                <span class="text-faint font-normal">{{ t('common.optional') }}</span>
              </label>
              <input :id="'max-' + selected.key" v-model.number="selected.max_length" type="number"
                     min="1" :max="5000" class="sd-input !h-9 !px-2.5 !text-[13px] !w-28">
            </div>

            <label v-if="canCount(selected) && selected.max_length" class="styledesk_choice !text-[13px]">
              <input type="checkbox" class="sd-check" v-model="selected.show_counter">
              <span class="styledesk_choice__label">{{ t('builder.show_counter') }}</span>
            </label>

            <!-- 10. What to say when the answer will not do. -->
            <div v-if="isQuestion(selected)">
              <label class="block text-[12px] font-medium text-ink mb-1" :for="'error-' + selected.key">
                {{ t('builder.error_message') }}
                <span class="text-faint font-normal">{{ t('common.optional') }}</span>
              </label>
              <input :id="'error-' + selected.key" v-model="selected.error_message" maxlength="255"
                     class="sd-input !h-9 !px-2.5 !text-[13px]">
              <p class="mt-1 text-[12px] text-sub">{{ t('builder.error_hint') }}</p>
            </div>

            <!-- The two sides of a before-and-after. Each has its own name,
                 instruction, cap and answer to "must this be filled in": a
                 treatment form legitimately requires the before photographs
                 and lets the after ones arrive later. -->
            <template v-if="selected.type === 'before_after'">
              <div class="pt-2.5 border-t border-line grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-[12px] font-medium text-ink mb-1" :for="'bl-' + selected.key">
                    {{ t('builder.before_label') }}
                  </label>
                  <input :id="'bl-' + selected.key" v-model="selected.before_label" maxlength="60"
                         class="sd-input !h-9 !px-2.5 !text-[13px]">
                </div>
                <div>
                  <label class="block text-[12px] font-medium text-ink mb-1" :for="'al-' + selected.key">
                    {{ t('builder.after_label') }}
                  </label>
                  <input :id="'al-' + selected.key" v-model="selected.after_label" maxlength="60"
                         class="sd-input !h-9 !px-2.5 !text-[13px]">
                </div>
              </div>

              <div>
                <label class="block text-[12px] font-medium text-ink mb-1" :for="'bh-' + selected.key">
                  {{ t('builder.before_help_text') }}
                  <span class="text-faint font-normal">{{ t('common.optional') }}</span>
                </label>
                <textarea :id="'bh-' + selected.key" v-model="selected.before_help" rows="2" maxlength="500"
                          class="sd-input !h-auto !px-2.5 !py-1.5 !text-[13px]"></textarea>
              </div>

              <div>
                <label class="block text-[12px] font-medium text-ink mb-1" :for="'ah-' + selected.key">
                  {{ t('builder.after_help_text') }}
                  <span class="text-faint font-normal">{{ t('common.optional') }}</span>
                </label>
                <textarea :id="'ah-' + selected.key" v-model="selected.after_help" rows="2" maxlength="500"
                          class="sd-input !h-auto !px-2.5 !py-1.5 !text-[13px]"></textarea>
              </div>

              <label class="styledesk_choice !text-[13px]">
                <input type="checkbox" class="sd-check" v-model="selected.before_required">
                <span class="styledesk_choice__label">{{ t('builder.before_required') }}</span>
              </label>

              <label class="styledesk_choice !text-[13px]">
                <input type="checkbox" class="sd-check" v-model="selected.after_required">
                <span class="styledesk_choice__label">{{ t('builder.after_required') }}</span>
              </label>

              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-[12px] font-medium text-ink mb-1" :for="'mb-' + selected.key">
                    {{ t('builder.max_before') }}
                  </label>
                  <input :id="'mb-' + selected.key" v-model.number="selected.max_before" type="number"
                         min="1" :max="uploads.max_files" class="sd-input !h-9 !px-2.5 !text-[13px]">
                </div>
                <div>
                  <label class="block text-[12px] font-medium text-ink mb-1" :for="'ma-' + selected.key">
                    {{ t('builder.max_after') }}
                  </label>
                  <input :id="'ma-' + selected.key" v-model.number="selected.max_after" type="number"
                         min="1" :max="uploads.max_files" class="sd-input !h-9 !px-2.5 !text-[13px]">
                </div>
              </div>

              <!-- Capped at what the disk takes. A bigger number here would
                   be a promise the storage layer breaks at submit time. -->
              <div>
                <label class="block text-[12px] font-medium text-ink mb-1" :for="'ms-' + selected.key">
                  {{ t('builder.max_file_size') }} ({{ t('builder.mb') }})
                </label>
                <input :id="'ms-' + selected.key" type="number" min="1"
                       :max="Math.round((uploads.max_kb ?? 5120) / 1024)"
                       :value="Math.round((selected.max_kb ?? uploads.max_kb ?? 5120) / 1024)"
                       @input="selected.max_kb = Math.min(
                         Number($event.target.value) * 1024, uploads.max_kb ?? 5120)"
                       class="sd-input !h-9 !px-2.5 !text-[13px] !w-24">
              </div>

              <div>
                <p class="block text-[12px] font-medium text-ink mb-1">{{ t('builder.file_types') }}</p>

                <div class="flex flex-wrap gap-1.5">
                  <button v-for="type in fileTypeChoices" :key="type" type="button"
                          class="styledesk_action !h-8 !px-2.5 !text-[12px]"
                          :class="(selected.file_types ?? []).includes(type) ? '!bg-hover !text-head !border-head' : ''"
                          @click="toggleFileType(selected, type)">
                    {{ type.toUpperCase() }}
                  </button>
                </div>
              </div>
            </template>

            <!-- How this question's own answers are arranged. Only where they
                 are printed on the page: a dropdown keeps its answers in a
                 popup, so there is nothing to lay out. -->
            <div v-if="arrangesOptions(selected)">
              <p class="block text-[12px] font-medium text-ink mb-1">{{ t('builder.option_layout') }}</p>

              <div class="flex gap-1.5">
                <button v-for="layout in ['single', 'two']" :key="layout" type="button"
                        class="styledesk_action !h-9 flex-1 !text-[12.5px]"
                        :class="(selected.option_layout ?? 'single') === layout ? '!bg-hover !text-head !border-head' : ''"
                        @click="selected.option_layout = layout">
                  <svg width="16" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"
                       v-html="layoutIcon(layout)"></svg>
                  {{ t('builder.option_layouts.' + layout) }}
                </button>
              </div>
            </div>

            <!-- Which way round a date is written. It decides how the answer
                 reads back, so it belongs to the question. -->
            <div v-if="selected.type === 'date' || selected.type === 'date_of_birth'">
              <label for="field-format" class="block text-[12px] font-medium text-ink mb-1">
                {{ t('builder.date_format') }}
              </label>
              <SdCombo id="field-format" v-model="selected.format" :options="dateFormatChoices"
                       :search-label="t('builder.date_format')" />
            </div>

            <div v-if="traitsFor(selected).options">
              <p class="block text-[12px] font-medium text-ink mb-1">{{ t('builder.options') }}</p>

              <div class="space-y-1">
                <div v-for="(option, i) in selected.options ?? []" :key="i" class="flex items-center gap-1.5">
                  <input v-model="selected.options[i]" maxlength="120"
                         class="sd-input !h-9 !px-2.5 !text-[13px]"
                         :aria-label="t('builder.option').replace(':number', String(i + 1))">
                  <button type="button" class="styledesk_action !h-9 !px-2 shrink-0 !text-danger"
                          :disabled="(selected.options ?? []).length < 2"
                          :aria-label="t('builder.delete')" :title="t('builder.delete')"
                          @click="removeOption(selected, i)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true" v-html="ACTION_ICONS.delete"></svg>
                  </button>
                </div>
              </div>

              <button type="button" class="styledesk_action !h-8 !px-2.5 !text-[12px] mt-2"
                      @click="addOption(selected)">{{ t('builder.add_option') }}</button>
            </div>
          </div>

          <!-- The row this question sits in. Kept apart from the question's
               own settings, because it is about the two of them: changing it
               moves the question beside it too. -->
          <div v-if="selectedRow" class="mt-4 pt-3 border-t border-line">
            <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ t('theme.row_settings') }}</h2>

            <div class="mt-2 space-y-2.5">
              <div class="flex gap-1.5">
                <button v-for="layout in themeOptions.rowLayouts" :key="layout" type="button"
                        class="styledesk_action !h-9 flex-1 !text-[12.5px]"
                        :class="selectedRow.layout === layout ? '!bg-hover !text-head !border-head' : ''"
                        @click="setRowLayout(spot.row, layout)">
                  <svg width="16" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"
                       v-html="layoutIcon(layout)"></svg>
                  {{ t('theme.row_layouts.' + layout) }}
                </button>
              </div>

              <div v-if="selectedRow.layout === 'two'">
                <label for="row-split" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.column_split') }}</label>
                <SdCombo id="row-split" v-model="selectedRow.split" :options="splitChoices"
                         :search-label="t('theme.column_split')" />
              </div>

              <div>
                <label for="row-spacing" class="block text-[12px] font-medium text-ink mb-1">{{ t('theme.row_override') }}</label>
                <SdCombo id="row-spacing" v-model="selectedRow.spacing" :options="rowSpacingChoices"
                         :search-label="t('theme.row_override')" />
              </div>

              <div v-if="selectedRow.spacing === 'custom'" class="flex items-center gap-2">
                <input type="number" min="0" max="200" v-model.number="selectedRow.spacing_px"
                       class="sd-input !h-9 !px-2.5 !text-[13px] !w-20"
                       :aria-label="t('theme.row_spacing_px')">
                <span class="text-[12px] text-sub">{{ t('theme.px') }}</span>
              </div>
            </div>
          </div>
        </template>

        <template v-else>
          <h2 class="text-[12px] font-semibold uppercase tracking-wide text-sub">{{ t('builder.form_settings') }}</h2>
          <p v-if="can.edit" class="text-[12px] text-sub mt-0.5">{{ t('builder.select_a_field') }}</p>

          <dl class="mt-2.5 space-y-2 text-[13px]">
            <div>
              <dt class="text-[12px] text-sub">{{ t('builder.form_name') }}</dt>
              <dd class="text-ink">{{ form.name }}</dd>
            </div>
            <div>
              <dt class="text-[12px] text-sub">{{ t('builder.form_type') }}</dt>
              <dd class="text-ink">{{ form.typeLabel }}</dd>
            </div>
            <div>
              <dt class="text-[12px] text-sub">{{ t('builder.layout') }}</dt>
              <dd class="text-ink">{{ form.layoutLabel }}</dd>
            </div>
            <div>
              <dt class="text-[12px] text-sub">{{ t('builder.questions') }}</dt>
              <dd class="text-ink">{{ t('builder.question_count').replace(':count', String(questionCount)) }}</dd>
            </div>
          </dl>

          <!-- The name, type and category are asked for on the form's own
               details screen. One place to change them rather than two that
               can disagree. -->
          <a :href="endpoints.details" class="styledesk_action mt-4">{{ t('builder.edit_details') }}</a>
        </template>
      </aside>
    </div>
  </div>
</template>
