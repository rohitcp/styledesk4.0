@extends('layouts.auth')

@section('title', 'Reset password')
@section('heading', 'Reset your password')
@section('subheading', 'Enter your email and we will send you a link to set a new one.')

@section('form')
    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-text-field name="email" label="Email address" type="email" autocomplete="username" required autofocus />
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
