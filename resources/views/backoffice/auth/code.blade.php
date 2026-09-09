{{-- Step two. The screen says a code was sent whatever the address turned out
     to be — an address that answers differently is a staff list, offered one
     guess at a time. --}}
@extends('layouts.backoffice-auth')

@section('title', __('backoffice.auth.code_title'))

{{-- Block form, not the one-line one: Blade counts commas and brackets
     when it parses a directive's argument, so a translation call carrying a
     replacement array breaks in half and prints its own tail on the page. --}}
@section('intro')
    {{ __('backoffice.auth.code_intro', ['email' => $email]) }}
@endsection

@section('content')
 $email])">

    <form method="POST" action="{{ route('backoffice.verify.check') }}" class="space-y-4">
        @csrf

        <div>
            <label for="code" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('backoffice.auth.code_label') }}
            </label>
            <input id="code" name="code" type="text" class="sd-input font-mono tracking-[0.3em] text-center"
                   required autofocus inputmode="numeric" autocomplete="one-time-code"
                   maxlength="{{ config('backoffice.verification.code_length') }}">
            @error('code')
                <p class="mt-1.5 text-[12px] text-danger" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('backoffice.auth.verify_code') }}
        </button>
    </form>

    <div class="flex items-center justify-between gap-3 mt-4">
        <form method="POST" action="{{ route('backoffice.verify.resend') }}">
            @csrf
            <button type="submit" class="text-[12.5px] font-semibold text-link hover:underline">
                {{ __('backoffice.auth.resend_code') }}
            </button>
        </form>

        <a href="{{ route('backoffice.verify.email') }}" class="text-[12.5px] font-semibold text-sub hover:text-ink">
            {{ __('backoffice.auth.change_email') }}
        </a>
    </div>
@endsection
