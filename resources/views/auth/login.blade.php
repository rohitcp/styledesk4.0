@extends('layouts.auth')

@section('title', 'Log in')
@section('heading', 'Log in to StyleDesk')
@section('subheading', 'Welcome back. Pick up where you left off.')

{{-- The way out of a page you are on by mistake, where a reader looks for
     it: the corner. The link at the foot of the form stays — someone who has
     read to the bottom and found no account should not have to travel back
     up to the top to make one. --}}
@section('top-action')
    {{-- Hidden below sm, where the corner has room for the button and not
         the sentence. The button says what it does on its own. --}}
    <span class="hidden sm:inline text-[13px] text-sub">New to StyleDesk?</span>

    <a href="{{ route('register') }}" class="styledesk_action">Create account</a>
@endsection

@section('form')
    @if (session(\App\Http\Middleware\EnforceSessionTimeout::FLAG))
        {{-- Named, not left to be guessed. Landing on a bare login form reads
             as the product losing your session; being told it timed out reads
             as the product protecting it. --}}
        <div class="sd-alert sd-alert--warn mt-6" role="status">
            <div class="flex items-start gap-2.5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 7.5V12l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <p class="min-w-0">Your session timed out after a period of inactivity. Please sign in again.</p>
            </div>
        </div>
    @endif

    @if (session(\App\Http\Middleware\EnsureBusinessIsActive::FLAG))
        {{-- The business was switched off mid-session. Said here rather than
             left as a password that silently stopped working. --}}
        <div class="sd-alert sd-alert--warn mt-6" role="status">
            <div class="flex items-start gap-2.5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 8v4.5M12 15.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <p class="min-w-0">{{ __('auth.business_disabled') }}</p>
            </div>
        </div>
    @endif

    {{--
        Method switcher. "Magic link" has no backend yet, so the tab is
        rendered in the prototype's styling but disabled rather than wired to
        a dead panel — a control that looks live and does nothing is worse
        than one that says why it cannot be used.
    --}}
    <div class="sd-seg mt-7" role="tablist" aria-label="How you want to sign in">
        <button type="button" class="sd-seg__btn" role="tab" aria-selected="true">Password</button>
        <button type="button" class="sd-seg__btn opacity-45 cursor-not-allowed" role="tab"
                aria-selected="false" tabindex="-1" disabled
                data-tip="Magic link sign-in is not available yet">Magic link</button>
    </div>

    {{-- The same live validation as the sign-up form: the rules sit on the
         fields and resources/js/live-validation.js reads them, so both pages
         check at the same moment, in the same words, with the same error
         styling. --}}
    @php
        /* "Incorrect email or password" is one fact about a pair of fields,
           not two facts about one each — the server cannot say which of them
           is wrong, and would not say so if it could. So it is stated once,
           in the alert the layout puts at the top, and both boxes are marked
           so the reader can see where the correction goes. Printing it under
           a field as well would be the same sentence twice on one screen. */
        $credentialsRefused = $errors->has('email') || $errors->has('password');
    @endphp

    <form method="POST" action="{{ route('login.store') }}" class="mt-6"
          data-validate-form
          data-validation-messages='@json(\App\Support\LiveValidation::messages())'>
        @csrf
        <div class="space-y-5">
            <div>
                <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">Email address</label>
                <input id="email" name="email" type="email" @class(['sd-input', 'is-error' => $credentialsRefused])
                       autocomplete="username"
                       data-rules="required|email"
                       data-message-required="Email address is required."
                       @if ($credentialsRefused) aria-invalid="true" @endif
                       placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
                {{-- Left for the browser to write into while the reader
                     types. A refusal from the server is announced once, at the
                     top, rather than under each of the two fields it is
                     about. --}}
                <p data-error-for="email" role="alert" class="mt-1.5 text-[12px] text-danger" hidden></p>
            </div>

            <div>
                <label for="password" class="block text-[13px] font-medium text-ink mb-1.5">Password</label>
                <div class="relative">
                    <input id="password" name="password" type="password"
                           @class(['sd-input', 'has-suffix', 'is-error' => $credentialsRefused])
                           data-rules="required"
                           data-message-required="Password is required."
                           @if ($credentialsRefused) aria-invalid="true" @endif
                           autocomplete="current-password" required>
                    <x-password-toggle for="password" />
                </div>
                <p data-error-for="password" role="alert" class="mt-1.5 text-[12px] text-danger" hidden></p>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <label for="remember" class="flex items-center gap-2.5 cursor-pointer">
                    <input id="remember" name="remember" value="1" type="checkbox" class="sd-check">
                    <span class="text-[13px] text-ink">Remember me</span>
                </label>
                <a href="{{ route('password.request') }}" class="ml-auto text-[13px] font-medium text-link hover:underline">Forgot password?</a>
            </div>

            {{-- Fires once and says so. A slow POST looks like nothing
                 happening, and a second click is a second sign-in attempt
                 against a limiter that counts them. Restored on the way back:
                 a refused attempt is a fresh page, and one restored from the
                 back/forward cache is re-enabled by submit-once.js. --}}
            <button type="submit" data-submit-once data-busy-label="Signing in…"
                    class="w-full inline-flex items-center justify-center h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
                Log In
            </button>
        </div>
    </form>

    {{-- Social sign-in: designed, not yet implemented. Disabled for the same
         reason as the magic-link tab. Wiring these means Socialite plus OAuth
         credentials per provider. --}}
    <div class="mt-8">
        <p class="sd-or">Or continue with</p>

        <div class="mt-5 space-y-2.5" data-tip="Social sign-in is not available yet">
            <button type="button" class="sd-social opacity-45 cursor-not-allowed" disabled>
                <span class="sd-social__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#4285F4" d="M45.1 24.5c0-1.6-.1-3.2-.4-4.7H24v8.9h11.8c-.5 2.7-2 5-4.4 6.6v5.5h7.1c4.1-3.8 6.6-9.5 6.6-16.3z"/><path fill="#34A853" d="M24 46c5.9 0 10.9-2 14.5-5.2l-7.1-5.5c-2 1.3-4.5 2.1-7.4 2.1-5.7 0-10.5-3.8-12.2-9H4.5v5.7C8.1 41.3 15.4 46 24 46z"/><path fill="#FBBC05" d="M11.8 28.4A13.2 13.2 0 0111.1 24c0-1.5.3-3 .7-4.4v-5.7H4.5A22 22 0 002 24c0 3.6.9 7 2.5 10.1l7.3-5.7z"/><path fill="#EA4335" d="M24 9.5c3.2 0 6.1 1.1 8.4 3.3l6.3-6.3C34.9 2.9 29.9 1 24 1 15.4 1 8.1 5.7 4.5 13.9l7.3 5.7C13.5 14.3 18.3 9.5 24 9.5z"/></svg>
                </span>
                Continue with Google
            </button>

            <button type="button" class="sd-social opacity-45 cursor-not-allowed" disabled>
                <span class="sd-social__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24"><rect x="2" y="2" width="9.5" height="9.5" fill="#F25022"/><rect x="12.5" y="2" width="9.5" height="9.5" fill="#7FBA00"/><rect x="2" y="12.5" width="9.5" height="9.5" fill="#00A4EF"/><rect x="12.5" y="12.5" width="9.5" height="9.5" fill="#FFB900"/></svg>
                </span>
                Continue with Microsoft
            </button>

            <div class="grid grid-cols-2 gap-2.5">
                <button type="button" class="sd-social sd-social--compact opacity-45 cursor-not-allowed" disabled>
                    <span class="sd-social__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#1877F2"/><path d="M15.4 12.6l.4-2.8h-2.7V8c0-.8.4-1.6 1.6-1.6h1.2V4a15 15 0 00-2.2-.2c-2.2 0-3.7 1.4-3.7 3.9v2.1H8.2v2.8H10V20h3.1v-7.4z" fill="#fff"/></svg>
                    </span>
                    Facebook
                </button>

                <button type="button" class="sd-social sd-social--compact opacity-45 cursor-not-allowed" disabled>
                    <span class="sd-social__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24"><defs><linearGradient id="igGrad" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEDA75"/><stop offset=".35" stop-color="#FA7E1E"/><stop offset=".6" stop-color="#D62976"/><stop offset="1" stop-color="#962FBF"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="6" fill="url(#igGrad)"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="#fff" stroke-width="1.8"/><circle cx="17.2" cy="6.8" r="1.2" fill="#fff"/></svg>
                    </span>
                    Instagram
                </button>
            </div>
        </div>
    </div>

    <p class="text-[13px] text-sub mt-8">
        New to StyleDesk?
        <a href="{{ route('register') }}" class="text-link font-medium hover:underline">Create an account</a>
    </p>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        Your day, <span class="text-[#9a93c4]">already laid out.</span>
    </h2>

    <ul class="mt-8 space-y-5">
        @foreach ([
            'Today’s bookings, staff and no-shows on one calendar.',
            'Every client’s history, notes and preferences a search away.',
            'Reminders go out on their own while you work.',
        ] as $point)
            <li class="flex items-start gap-3.5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[15px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>

    {{--
        PLACEHOLDER TESTIMONIAL — an invented person and business, carried over
        from the prototype, which flags it too. Replace with a real,
        permissioned quote before launch, or remove the block entirely.
        Do not ship an invented quote attributed to a named person.
    --}}
    <div class="mt-16 rounded-card border border-line bg-white/70 p-6">
        <p class="text-[15px] text-ink leading-relaxed">
            “We stopped juggling a paper diary and three group chats. Everything the team needs
            is in one place now.”
        </p>
        <div class="flex items-center gap-3 mt-5">
            <span class="sd-avatar sd-avatar--sm" aria-hidden="true">MK</span>
            <span class="min-w-0">
                <span class="block text-[13px] font-semibold text-head">Maya Kessler</span>
                <span class="block text-[12px] text-sub">Owner, Glasshouse Studio</span>
            </span>
        </div>
    </div>
@endsection
