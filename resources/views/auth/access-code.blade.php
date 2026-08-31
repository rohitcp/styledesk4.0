@extends('layouts.auth')

@section('title', 'Access code')
@section('heading', 'Enter your access code')
@section('heading-class', 'text-[26px] sm:text-[30px]')
@section('subheading', 'StyleDesk is invitation only for now. Enter the code you were given to reach sign-in and sign-up.')

@section('form')
    {{-- One field, so the browser's own autofill is told what it is not: an
         access code is not a username and not a password, and offering a saved
         one here fills the box with something that cannot work. --}}
    <form method="POST" action="{{ route('access-code.store') }}" class="mt-7">
        @csrf

        <div>
            <label for="access_code" class="block text-[13px] font-medium text-ink mb-1.5">Access code</label>
            <input id="access_code" name="access_code" type="text"
                   @class(['sd-input', 'is-error' => $errors->has('access_code')])
                   inputmode="numeric"
                   autocomplete="off"
                   spellcheck="false"
                   maxlength="64"
                   @if ($errors->has('access_code')) aria-invalid="true" @endif
                   placeholder="e.g. 1234" required autofocus>
            {{-- A refusal is announced once, in the layout's alert at the top;
                 this line is for the empty-field case the browser catches. --}}
            <p data-error-for="access_code" role="alert" class="mt-1.5 text-[12px] text-danger" hidden></p>
        </div>

        {{-- Fires once and says so, like the sign-in button: a slow POST
             looks like nothing happening, and a second click is a second
             attempt against a limiter that counts them. --}}
        <button type="submit" data-submit-once data-busy-label="Checking…"
                class="w-full inline-flex items-center justify-center h-11 mt-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Continue
        </button>
    </form>

    <p class="text-[13px] text-sub mt-8">
        Don’t have a code? Ask whoever invited you to StyleDesk — it is the same code for everyone on your team.
    </p>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        A first look, <span class="text-[#9a93c4]">before everyone else.</span>
    </h2>

    <ul class="mt-8 space-y-5">
        @foreach ([
            'Bookings, staff and clients in one place.',
            'Set up your salon in a few minutes.',
            'Your feedback shapes what gets built next.',
        ] as $point)
            <li class="flex items-start gap-3.5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[15px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection
