@extends('layouts.auth')

@section('title', 'Check your email')
@section('heading', 'Check your email')
@section('subheading', 'Verify your address to finish creating your account.')

@section('form')
    <p class="text-[15px] text-ink mt-6 leading-relaxed">
        We sent a verification link to
        <b class="break-all">{{ auth()->user()->email }}</b>.
        Click the link in the email to verify your account and continue setting up StyleDesk.
    </p>

    <div class="mt-7 space-y-4">
        {{-- Resend. Fortify throttles this route, so hammering it is harmless. --}}
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit"
                    class="styledesk_action">
                Resend verification email
            </button>
        </form>

        {{-- Change email. The address is the account identifier, so this both
             updates it and re-sends — leaving the old one verified-pending
             would strand the user. --}}
        <details class="group">
            <summary class="text-[13px] text-link font-medium hover:underline cursor-pointer list-none">
                Change email address
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

    {{-- A div, not a p: a form inside a paragraph is invalid and the parser
         closes the p early, which breaks the centring. --}}
    <div class="text-center mt-8 text-[13px] text-sub">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-link font-medium hover:underline">Log out</button>
        </form>
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
