@extends('layouts.auth')

@section('title', 'Reset password')
@section('heading', 'Reset your password')
@section('subheading', 'Enter your email and we will send you a link to set a new one.')

@section('form')
    {{-- The layout already renders session('status') as the confirmation
         itself; this is the sentence that comes after it. Only the practical
         advice, and only once there is something to advise about — a
         spam-folder hint on a form nobody has submitted is noise. --}}
    @if (session('status'))
        <p class="mt-3 text-[13px] text-sub leading-relaxed">
            The link is valid for a limited time and can be used once. If nothing
            arrives within a few minutes, check your spam folder — or ask for
            another below.
        </p>
    @endif

    {{-- The same live validation as sign-in, so a missing address is refused
         here in the same words and in the same place. --}}
    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5"
          data-validate-form
          data-validation-messages='@json(\App\Support\LiveValidation::messages())'>
        @csrf
        <x-text-field name="email" label="Email address" type="email" autocomplete="username"
                      rules="required|email" required autofocus />
        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">Email reset link</button>
    </form>

    <p class="text-center mt-7 text-[13px] text-sub">
        <a href="{{ route('login') }}" class="text-link font-medium hover:underline">Back to sign in</a>
    </p>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        Locked out? <span class="text-[#9a93c4]">Back in a minute.</span>
    </h2>
    <p class="text-[15px] text-ink leading-relaxed mt-6">
        The link works once and expires shortly after it is sent. If it has already
        been used, request a fresh one from this page.
    </p>
@endsection
