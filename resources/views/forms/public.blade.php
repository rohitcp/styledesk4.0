<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $form->name }} — {{ $tenant->name }}</title>

    {{-- Not indexed. A form is sent to somebody, and one a search engine has
         listed is one anybody can find without being sent it. --}}
    <meta name="robots" content="noindex, nofollow">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- The business's palette, rendered before first paint. As far as the
         client is concerned this page is their salon's, not StyleDesk's. --}}
    <style>{!! App\Support\BrandPalette::forTenant($tenant)->css() !!}</style>
</head>
<body class="min-h-screen bg-canvas text-ink antialiased">

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
            ])->values()->all(),
        ])
        ->values()
        ->all();

    $props = [
        'form' => [
            'name' => $form->name,
            'typeLabel' => $form->typeLabel(),
        ],
        'rows' => $version->rows(),
        'theme' => $version->theme(),
        'themeOptions' => [
            'widths' => config('forms.theme.widths'),
            'fieldStyles' => config('forms.theme.field_styles'),
            'radii' => config('forms.theme.radii'),
            'rowSpacings' => config('forms.theme.row_spacings'),
            'columnSplits' => config('forms.theme.column_splits'),
            'fieldTraits' => config('forms.field_types'),
        ],
        'dateFormats' => config('forms.date_formats'),
        'uploads' => config('forms.uploads'),
        'labels' => [
            'builder' => __('forms.builder'),
            'public' => __('forms.public'),
            'common' => [
                'yes' => __('common.yes'),
                'no' => __('common.no'),
                'optional' => __('common.optional'),
                'search' => __('common.search'),
                'month' => __('common.month'),
                'year' => __('common.year'),
            ],
        ],
        /*
         * Live: the panel posts rather than keeping the answers to itself.
         *
         * Back to the address this page was served from, not to a route built
         * from the tenant's subdomain. The same form answers on two hosts —
         * the business's subdomain and the application's own domain — and a
         * page opened on one that posted to the other would be sending the
         * answers cross-origin to a host that may not even be this app. GET
         * and POST share the URI, so the current URL is the right target on
         * either.
         */
        'submitUrl' => url()->current(),
        'groups' => $fieldTypes,
    ];
@endphp

<main class="px-4 py-8 sm:py-12">
    <div data-vue-component="FormPreview" data-props='@json($props)'></div>

    <p class="mt-8 text-center text-[12px] text-faint">{{ $tenant->name }}</p>
</main>

</body>
</html>
