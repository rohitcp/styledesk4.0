@extends('layouts.onboarding')

@section('title', 'Services')
@section('heading', 'What do you offer?')
@section('subheading', 'Add the services clients can book. Add-ons, staff-specific pricing and packages all come later — start with the basics.')

@section('form')
    <form id="stepForm" method="POST" action="{{ route('onboarding.services.store') }}" class="mt-6">
        @csrf

        @php
            // Built in @php rather than inline in @json(): Blade's directive
            // parser cannot handle a multi-line array argument and fails at
            // compile time with "Unclosed '['".
            $repeaterProps = [
                'initial' => $services->map(fn ($s) => [
                    'name' => $s->name,
                    'category' => $s->category,
                    'duration_minutes' => $s->duration_minutes,
                    'price' => $s->priceFormatted(),
                    'description' => $s->description,
                ])->all(),
                'currency' => auth()->user()->tenant->currency,
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
        <button type="submit" form="stepForm"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Continue
        </button>

        <form method="POST" action="{{ route('onboarding.skip', 'services') }}">
            @csrf
            <button type="submit" class="h-11 px-4 rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
                I'll do this later
            </button>
        </form>
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Why we ask</h2>
    <p class="text-[13px] text-sub leading-relaxed mt-3">
        Services are what clients pick when they book, and their duration is what
        decides how your calendar fills. Two or three is plenty to start.
    </p>
@endsection
