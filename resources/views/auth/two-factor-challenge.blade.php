@extends('layouts.auth')

@section('title', 'Two-factor authentication')
@section('heading', 'Two-factor authentication')
@section('subheading', 'Enter the code from your authenticator app to finish signing in.')

@section('form')
    <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-8 space-y-5">
        @csrf
        <x-text-field name="code" label="Authentication code" autocomplete="one-time-code" autofocus />

        <p class="text-[12px] text-sub">Lost your device? Enter one of your recovery codes instead.</p>
        <x-text-field name="recovery_code" label="Recovery code" autocomplete="one-time-code" />

        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">Continue</button>
    </form>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        Two factors, <span class="text-[#9a93c4]">one account.</span>
    </h2>
@endsection
