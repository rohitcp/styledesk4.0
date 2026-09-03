{{-- Step three. The address is fixed rather than editable: this session
     verified one address, and it may only sign in as that one. --}}
@extends('layouts.backoffice-auth')

@section('title', __('backoffice.auth.login_title'))

@section('intro', __('backoffice.auth.login_intro'))

@section('content')


    <form method="POST" action="{{ route('backoffice.login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.email_label') }}
            </label>
            <input id="email" name="email" type="email" class="sd-input bg-hover" required readonly
                   autocomplete="username" value="{{ $email }}">
            @error('email')
                <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.password_label') }}
            </label>
            <input id="password" name="password" type="password" class="sd-input" required autofocus
                   autocomplete="current-password">
            @error('password')
                <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-[12.5px] text-sub">
                <input type="checkbox" name="remember" value="1" class="rounded border-line">
                {{ __('backoffice.auth.remember') }}
            </label>

            <a href="{{ route('backoffice.password.request') }}" class="text-[12.5px] font-semibold text-link hover:underline">
                {{ __('backoffice.auth.forgot') }}
            </a>
        </div>

        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('backoffice.auth.sign_in') }}
        </button>
    </form>
@endsection
