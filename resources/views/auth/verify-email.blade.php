@extends('layouts.auth')

@php
    /* The countdown is seeded from the server's own remaining time, so the
       button re-enables exactly when the route would start accepting again —
       a page reload cannot shorten the wait. */
    $resendIn = \App\Http\Controllers\VerificationEmailController::resendCooldownRemaining(request());
@endphp

@section('title', 'Verify your email')
@section('heading', 'Verify your email')
@section('subheading', 'One step left before you set up your business.')

{{-- The way out for someone who opened this on the wrong account. --}}
@section('top-action')
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="styledesk_action">Log out</button>
    </form>
@endsection

@section('form')
    {{-- Why this screen, before what to do on it. Someone who signed in
         expecting the dashboard needs the reason they are here named before
         the instructions make sense. --}}
    <p class="text-[15px] text-ink mt-6 leading-relaxed">
        {{ __('auth.unverified') }}
    </p>

    <p class="text-[15px] text-ink mt-3 leading-relaxed">
        We sent a verification email to <b class="break-all">{{ auth()->user()->email }}</b>.
        Please check your inbox and click the verification link to activate your account.
    </p>

    {{-- The code, for a reader whose mail client opened the link in a browser
         that is not this one — or who would rather not leave this page. --}}
    <form method="POST" action="{{ route('verification.code') }}" class="mt-7" data-code-form>
        @csrf

        <label class="block text-[13px] font-medium text-ink mb-2" id="code-label">Confirmation Code</label>

        {{-- Six visible boxes, one submitted value. The digits are collected
             into the hidden field on the way out, so a pasted code and six
             typed ones reach the server identically. --}}
        <div class="flex items-center gap-2 sm:gap-2.5" role="group" aria-labelledby="code-label">
            @for ($i = 0; $i < 6; $i++)
                <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1"
                       aria-label="Digit {{ $i + 1 }}"
                       data-code-input
                       @if ($i === 0) autofocus @endif
                       class="sd-input text-center text-[18px] font-semibold tracking-[0.02em] h-12 px-0 flex-1 min-w-0">
            @endfor
        </div>

        <input type="hidden" name="code" value="" data-code-value>

        {{-- Inline, beside the field it is about, as well as in the alert the
             layout puts at the top: the reader's eyes are on the boxes. --}}
        <p data-code-error role="alert" class="mt-2 text-[12px] text-danger"
           @unless ($errors->has('code')) hidden @endunless>{{ $errors->first('code') }}</p>

        <button type="submit" class="w-full inline-flex items-center justify-center h-11 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors"
                data-submit-once data-busy-label="Verifying…">
            Verify Account
        </button>
    </form>

    <div class="mt-7 space-y-4">
        {{-- Resend. The cooldown is enforced on the server; this only tells
             the reader what the server would say. --}}
        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="styledesk_action"
                    data-resend-button data-resend-in="{{ $resendIn }}"
                    @disabled($resendIn > 0)>
                <span data-resend-label>
                    {{ $resendIn > 0 ? "Resend available in {$resendIn} seconds" : 'Resend Email' }}
                </span>
            </button>
        </form>

        {{-- Change email. The address is the account identifier, so this both
             updates it and re-sends — leaving the old one verified-pending
             would strand the user. Open on its own when the last attempt to
             change it was refused, so the message is not hidden in a
             collapsed panel. --}}
        <details class="group" @if ($errors->has('email')) open @endif>
            <summary class="text-[13px] text-link font-medium hover:underline cursor-pointer list-none">
                Change Email
            </summary>

            <form method="POST" action="{{ route('verification.email.update') }}" class="mt-4">
                @csrf
                @method('PATCH')
                <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">New email address</label>
                <input id="email" name="email" type="email" class="sd-input" autocomplete="email"
                       value="{{ old('email') }}" required>
                @error('email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror

                <div class="flex items-center gap-2 mt-3">
                    <button type="submit" class="h-9 px-4 rounded-md bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                        Update and resend
                    </button>
                </div>
            </form>
        </details>
    </div>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        Almost there. <span class="text-[#9a93c4]">One click to go.</span>
    </h2>
    <p class="text-[15px] text-ink leading-relaxed mt-6">
        Verifying your address keeps your account recoverable and makes sure
        booking confirmations and reminders actually reach you.
    </p>
    <p class="text-[13px] text-sub leading-relaxed mt-4">
        Nothing arrived? Check your spam folder, or resend the link.
    </p>
@endsection
