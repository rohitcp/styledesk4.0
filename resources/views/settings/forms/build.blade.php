@extends('layouts.focused')

@section('title', $form->name)

{{--
    The form builder.

    The focused layout, because building a form is a sitting: no nav rail, no
    account menu, nothing offering somewhere else to be. Opened in its own
    window from the list, so the list is still there when the work is done.

    Every string the panel shows travels with it. Vue has no translator, so a
    builder that read its own labels would be English inside a translated
    frame — which is the thing the lang rule exists to stop.
--}}

@php
    $fieldTypes = collect(config('forms.field_types'))
        ->map(fn (array $types, string $group) => [
            'key' => $group,
            'label' => __('forms.groups.'.$group),
            'types' => collect($types)->map(fn (array $traits, string $type) => [
                'key' => $type,
                'label' => __('forms.fields.'.$type),
                'options' => (bool) ($traits['options'] ?? false),
                'placeholder' => (bool) ($traits['placeholder'] ?? false),
                'content' => (bool) ($traits['content'] ?? false),
                'breaks' => (bool) ($traits['breaks'] ?? false),
                'scale' => (bool) ($traits['scale'] ?? false),
                'hidden' => (bool) ($traits['hidden'] ?? false),
                'accepts' => (bool) ($traits['accepts'] ?? false),
            ])->values()->all(),
        ])
        ->values()
        ->all();

    /* One object, read in the panel through a dotted lookup — the same shape
       the resource and utilisation panels take their wording in. */
    $labels = ['builder' => __('forms.builder'), 'theme' => __('forms.theme'), 'common' => [
        'yes' => __('common.yes'),
        'no' => __('common.no'),
        'optional' => __('common.optional'),
        'search' => __('common.search'),
        /* The calendar's own two dropdowns. The month and weekday NAMES come
           from the browser's Intl rather than from here — nineteen strings a
           language that it already knows and gets right. */
        'month' => __('common.month'),
        'year' => __('common.year'),
    ]];

    $props = [
        'form' => [
            'id' => $form->id,
            'name' => $form->name,
            'type' => $form->type,
            'typeLabel' => $form->typeLabel(),
            'layoutLabel' => __('forms.layouts.'.$form->layout),
            'status' => $form->status,
            'statusLabel' => $form->statusLabel(),
        ],
        'version' => [
            'number' => $version->version,
            'published' => $version->isPublished(),
        ],
        'rows' => $version->rows(),
        'theme' => $version->theme(),
        /* The vocabulary and the numbers behind it. The panel offers the
           choices and the canvas renders them, and both read the same list
           the public form will — so "spacious" cannot come to mean two
           different gaps. */
        'themeOptions' => [
            'widths' => config('forms.theme.widths'),
            'alignments' => config('forms.theme.alignments'),
            'labelPositions' => config('forms.theme.label_positions'),
            'fieldStyles' => config('forms.theme.field_styles'),
            'radii' => config('forms.theme.radii'),
            'backgrounds' => config('forms.theme.backgrounds'),
            'buttonStyles' => config('forms.theme.button_styles'),
            'buttonAlignments' => config('forms.theme.button_alignments'),
            'rowSpacings' => config('forms.theme.row_spacings'),
            'rowLayouts' => config('forms.theme.row_layouts'),
            'columnSplits' => config('forms.theme.column_splits'),
            /* The preview renders every question for real, so it needs to
               know what each type can do without asking the builder. */
            'fieldTraits' => config('forms.field_types'),
        ],
        /* Not translated, and deliberately: a format pattern is a pattern
           rather than prose, and MM/DD/YYYY reads the same in every language
           StyleDesk ships. */
        'dateFormats' => config('forms.date_formats'),
        /* What the disk will actually take. The authority is
           App\Services\Storage\FileValidator; config/forms.php mirrors it and
           a test holds the two together. */
        'uploads' => config('forms.uploads'),
        'groups' => $fieldTypes,
        'labels' => $labels,
        'endpoints' => [
            'schema' => route('settings.forms.schema', $form),
            'publish' => route('settings.forms.publish', $form),
            'back' => route('settings.forms.index'),
            'details' => route('settings.forms.edit', $form),
        ],
        'can' => ['edit' => $canEdit, 'publish' => $canPublish],
    ];
@endphp

@section('content')
  <div data-vue-component="FormBuilder" data-props='@json($props)' class="flex-1 flex flex-col min-h-0"></div>
@endsection
