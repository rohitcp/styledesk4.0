@extends('layouts.onboarding')

@section('title', 'Services')
@section('heading', 'What do you offer?')
@section('subheading', 'Add the services clients can book. Add-ons, staff-specific pricing and packages all come later — start with the basics.')

@section('form')
    <form id="stepForm" method="POST" action="{{ route('onboarding.services.store') }}" class="mt-6">
        @csrf

        @php
            $storage = app(\App\Contracts\TenantStorageContract::class);

            // Built in @php rather than inline in @json(): Blade's directive
            // parser cannot handle a multi-line array argument and fails at
            // compile time with "Unclosed '['".
            $repeaterProps = [
                'initial' => $services->map(fn ($s) => [
                    'name' => $s->name,
                    'service_category_id' => $s->service_category_id,
                    'duration_minutes' => $s->duration_minutes,
                    'prices' => collect($tenantCurrencies)->mapWithKeys(fn ($c) => [$c['code'] => $s->priceIn($c['code'])])->all(),
                    'description' => $s->description,
                    /* Already-saved pictures, so re-opening the step shows the
                       service as it stands rather than as if it had none. */
                    'images' => $s->orderedImages()->map(fn ($f) => [
                        'id' => $f->id,
                        'url' => $storage->url($f),
                        'name' => $f->original_filename,
                    ])->all(),
                    'default_image_id' => $s->image_file_id,
                ])->all(),
                'currencies' => $tenantCurrencies,
                'categories' => $categories,
                'canCreateCategory' => $canCreateCategory,
                /* Routes and copy are handed to the island rather than looked
                   up inside it: a Vue component that knew a URL would be a
                   second place routes are written down. __ID__ is replaced
                   with the file's id when one is actually removed. */
                'imageUploadUrl' => route('services.images.store'),
                'imageDeleteUrl' => route('services.images.destroy', ['storedFile' => '__ID__']),
                'maxOtherImages' => $maxOtherImages,
                /* array_merge, not +: the union operator keeps the left-hand
                   value for a duplicate key, which would hand the component
                   the two strings still carrying their :max placeholder. */
                'imageLabels' => array_merge(__('services.images'), [
                    'help' => __('services.images.help', ['max' => $maxOtherImages]),
                    'too_many' => __('services.images.too_many', ['max' => $maxOtherImages + 1]),
                ]),
            ];
        @endphp

        {{-- Vue island: Blade owns the wizard and the submit, Vue only manages
             the rows. They post as ordinary services[i][field] inputs. --}}
        <div data-vue-component="ServiceRepeater" data-props='@json($repeaterProps)'></div>

    </form>

    {{-- The skip form is a sibling, not a child: nesting one form inside
         another is invalid and browsers silently drop the inner one. The
         Continue button reaches its form by id instead. --}}
    <div class="flex flex-wrap items-center gap-3 pt-6">
        {{-- Fires once, like every other step. A slow POST shows the reader
             nothing, so they click again — and a second submit repeats the
             step. --}}
        <button type="submit" form="stepForm" data-submit-once data-busy-label="{{ __('common.saving') }}"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            Continue
        </button>

        <form method="POST" action="{{ route('onboarding.skip', 'services') }}">
            @csrf
            {{-- The skip posts too, so it gets the same guard. --}}
            <button type="submit" data-submit-once
                    class="h-11 px-4 rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors disabled:opacity-60 disabled:pointer-events-none">
                I'll do this later
            </button>
        </form>

        {{-- A link, not a form post: going back only re-reads an earlier step,
             so it must not submit anything or move current_step. --}}
        @if ($previousStep)
            {{-- ml-auto: Back sits on the opposite side from Continue, so
                 the button that moves forward stays where the eye lands. --}}
            <a href="{{ route('onboarding.'.$previousStep) }}"
               class="styledesk_action ml-auto">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Back
            </a>
        @endif
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Your service menu</h2>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed">This is what clients see when they book.</p>

    {{-- Mirrors the rows in the other column through a shared reactive store:
         they are separate Vue apps on separate subtrees, so props cannot pass
         between them. Hidden below lg, so it is never the only place a value
         appears. --}}
    <div class="mt-5" data-vue-component="ServicePreview"
         data-props='@json(["currencies" => $tenantCurrencies])'></div>

    <ul class="mt-8 space-y-4">
        @foreach ([
            'Duration decides how much of the calendar each booking takes, so keep it realistic.',
            'Only services marked bookable online appear on your booking page. The rest stay internal.',
            'Add-ons, staff-specific pricing and packages all come later — start with the basics.',
        ] as $point)
            <li class="flex items-start gap-3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[13px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection
