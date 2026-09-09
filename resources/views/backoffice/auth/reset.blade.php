@extends('layouts.backoffice-auth')

@section('title', __('backoffice.auth.reset_title'))

@section('content')


    <form method="POST" action="{{ route('backoffice.password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.email_label') }}
            </label>
            <input id="email" name="email" type="email" class="sd-input" required
                   autocomplete="username" value="{{ old('email', $email) }}">
            @error('email')
                <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.new_password') }}
            </label>
            <input id="password" name="password" type="password" class="sd-input" required autofocus
                   autocomplete="new-password">
            @error('password')
                <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.confirm_password') }}
            </label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="sd-input"
                   required autocomplete="new-password">
        </div>

        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('backoffice.auth.reset_submit') }}
        </button>
    </form>
@endsection
