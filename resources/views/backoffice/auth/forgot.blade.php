@extends('layouts.backoffice-auth')

@section('title', __('backoffice.auth.forgot_title'))

@section('intro', __('backoffice.auth.forgot_intro'))

@section('content')


    <form method="POST" action="{{ route('backoffice.password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.email_label') }}
            </label>
            <input id="email" name="email" type="email" class="sd-input" required autofocus
                   autocomplete="username" value="{{ old('email') }}">
            @error('email')
                <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('backoffice.auth.send_reset') }}
        </button>
    </form>

    <a href="{{ route('backoffice.verify.email') }}" class="block mt-4 text-[12.5px] font-semibold text-sub hover:text-ink">
        {{ __('backoffice.auth.back_to_sign_in') }}
    </a>
@endsection
