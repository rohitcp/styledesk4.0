@extends('layouts.auth')

@section('title', 'Create your account')
@section('heading', 'Create your StyleDesk account')
{{-- Fits on one line: 402px of the 420px column at 28px, against 462px at 32px.
     The base size still wraps on a narrow phone, which is correct — forcing one
     line there would overflow the viewport instead. --}}
@section('heading-class', 'text-[26px] sm:text-[28px]')
@section('subheading', 'Set up your business and start managing bookings, clients, staff, and services.')

@section('form')
    {{-- Duplicate address. Shown instead of creating a second account, with a
         way straight to the thing the user probably wanted. This only ever
         appears for an address the visitor just typed, so it reveals nothing
         they did not already supply. --}}
    @if ($errors->has('email') && str_contains($errors->first('email'), 'already'))
        <div class="mt-7 rounded-lg border border-danger bg-red-50/50 p-3.5" role="alert">
            <p class="flex items-start gap-2 text-[13px] text-danger font-medium">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="mt-px shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                An account already exists with this email address.
            </p>
            <a href="{{ route('login') }}"
               class="inline-flex items-center h-8 px-3 mt-2.5 ml-[23px] rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                Log in instead
            </a>
        </div>
    @endif

    <form id="signup" method="POST" action="{{ route('register.store') }}" class="mt-8 space-y-5">
        @csrf

        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">
            <div>
                <label for="first_name" class="block text-[13px] font-medium text-ink mb-1.5">First name</label>
                <input id="first_name" name="first_name" type="text" autocomplete="given-name" class="sd-input"
                       value="{{ old('first_name') }}" required autofocus>
                @error('first_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="last_name" class="block text-[13px] font-medium text-ink mb-1.5">Last name</label>
                <input id="last_name" name="last_name" type="text" autocomplete="family-name" class="sd-input"
                       value="{{ old('last_name') }}" required>
                @error('last_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" placeholder="name@yourbusiness.com"
                   class="sd-input" value="{{ old('email') }}" required>
            @error('email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="block text-[13px] font-medium text-ink mb-1.5">Password</label>
            <div class="relative">
                <input id="password" name="password" type="password" autocomplete="new-password"
                       class="sd-input has-suffix" required>
                <x-password-toggle for="password" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors" />
            </div>
            @error('password')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror

            {{-- Strength: bar plus text. The word carries the meaning and the
                 bar only reinforces it, so nothing depends on colour alone. --}}
            <div class="mt-2.5 flex items-center gap-3">
                <div id="meter" class="sd-meter flex-1" data-level="0" aria-hidden="true">
                    <span class="sd-meter__seg"></span><span class="sd-meter__seg"></span>
                    <span class="sd-meter__seg"></span><span class="sd-meter__seg"></span>
                </div>
                <span id="password-strength" class="text-[12px] font-medium text-faint w-[46px] text-right" role="status" aria-live="polite">—</span>
            </div>

            <ul id="password-rules" class="mt-2.5 grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1"></ul>
        </div>

        <div>
            <label for="password_confirmation" class="block text-[13px] font-medium text-ink mb-1.5">Confirm password</label>
            <div class="relative">
                <input id="password_confirmation" name="password_confirmation" type="password"
                       autocomplete="new-password" class="sd-input has-suffix" required>
                <x-password-toggle for="password_confirmation" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors" />
            </div>
        </div>

        {{-- Explicit acceptance, not consent implied by pressing the button.
             CreateNewUser records the timestamp. --}}
        <div>
            <label class="flex items-start gap-2.5 text-[13px] text-ink cursor-pointer leading-relaxed">
                <input id="terms" name="terms" type="checkbox" value="1" class="sd-check mt-0.5" @checked(old('terms'))>
                <span>I agree to the StyleDesk <a href="#" class="text-link font-medium hover:underline">Terms of Service</a> and <a href="#" class="text-link font-medium hover:underline">Privacy Policy</a>.</span>
            </label>
            @error('terms')<p class="mt-1.5 ml-[26px] text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        <button type="submit"
                class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Create Account
        </button>
    </form>

    <div class="flex items-center gap-3 my-6">
        <div class="h-px flex-1 bg-line"></div>
        <span class="text-[13px] text-faint">or</span>
        <div class="h-px flex-1 bg-line"></div>
    </div>

    {{-- Designed, not implemented: needs Socialite plus Google OAuth credentials. --}}
    <button type="button" disabled title="Google sign-up is not available yet"
            class="relative w-full h-11 rounded-lg border border-stroke bg-white text-[14px] font-semibold text-ink opacity-45 cursor-not-allowed">
        <span class="absolute left-4 top-1/2 -translate-y-1/2"><svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.4 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.3 13.2 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.4c-.5 2.9-2.1 5.3-4.6 7l7.1 5.5c4.1-3.8 6.4-9.4 6.4-16z"/><path fill="#FBBC05" d="M10.4 28.3c-.5-1.4-.8-2.9-.8-4.3s.3-3 .8-4.3l-7.8-6.1C.9 16.6 0 20.2 0 24s.9 7.4 2.6 10.4l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.2 0 11.5-2 15.3-5.5l-7.1-5.5c-2 1.3-4.6 2.1-8.2 2.1-6.3 0-11.7-3.7-13.6-9l-7.8 6.1C6.5 42.6 14.6 48 24 48z"/></svg></span>
        Sign up with Google
    </button>

    <p class="text-center mt-7 text-[13px] text-sub">
        Already have an account?
        <a href="{{ route('login') }}" class="text-link font-medium hover:underline">Sign in</a>
    </p>
@endsection

@section('value-panel')
    <h2 class="text-[26px] xl:text-[29px] font-bold text-head tracking-tight leading-[1.25]">
        Run the whole salon <span class="text-[#9a93c4]">from one screen.</span>
    </h2>

    <ul class="mt-8 space-y-5">
        @foreach ([
            'Let clients book themselves in, day or night, from one link.',
            'See every appointment, staff schedule and client note on one calendar.',
            'Automatic reminders and cancellation rules cut no-shows.',
        ] as $point)
            <li class="flex items-start gap-3.5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[15px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>

    {{--
        PLACEHOLDER WORDMARKS — invented businesses, not real customers, and
        the prototype flags them as such. Swap these for your own customers'
        logos, and only with permission; do not ship invented names presented
        as businesses that use StyleDesk.
    --}}
    <div class="mt-16">
        <p class="text-[13px] text-sub">Built for salons, spas and studios</p>
        <div class="mt-7 grid grid-cols-3 gap-x-6 gap-y-9 text-faint">
            @foreach ([
                ['Bella Beauty', 'text-[15px] font-bold tracking-tight text-center'],
                ['Lumen', 'text-[15px] font-semibold tracking-[0.14em] uppercase text-center'],
                ['Aster&nbsp;&amp;&nbsp;Ivy', 'text-[15px] font-bold tracking-tight text-center'],
                ['The Cutting Room', 'text-[15px] font-medium tracking-tight text-center'],
                ['Glasshouse', 'text-[15px] font-bold tracking-tight text-center'],
                ['Verve', 'text-[15px] font-semibold tracking-[0.14em] uppercase text-center'],
                ['Northside Barbers', 'text-[15px] font-medium tracking-tight text-center'],
                ['Studio Ninety', 'text-[15px] font-bold tracking-tight text-center'],
                ['Rosewood Spa', 'text-[15px] font-semibold tracking-tight text-center'],
            ] as [$wordmark, $classes])
                <span class="{{ $classes }}">{!! $wordmark !!}</span>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
<script>
    /*
     * Password strength meter, rules list and reveal toggles.
     *
     * Ported from signup.html's page-local script. The scoring itself is not
     * reimplemented here — SD.checkPassword and SD.PASSWORD_RULES come from the
     * shared prototype module, so the app and the prototype grade a password
     * the same way. The prototype's localStorage draft and its client-side
     * email-uniqueness probe are deliberately left out: the server owns both.
     */
    document.addEventListener('DOMContentLoaded', function () {
        // Must wait for DOMContentLoaded: resources/js/app.js is a deferred
        // module, so window.SD does not exist while this inline script is
        // being parsed. Running immediately would hit the guard below and
        // silently do nothing.
        var password = document.getElementById('password');
        var confirm = document.getElementById('password_confirmation');
        var meter = document.getElementById('meter');
        var strength = document.getElementById('password-strength');
        var rulesList = document.getElementById('password-rules');

        if (!password || !window.SD) return;

        var CHECK = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="currentColor"/><path d="M8 12l2.5 2.5L16 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var DOT = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/></svg>';

        rulesList.innerHTML = SD.PASSWORD_RULES.map(function (rule) {
            return '<li data-rule="' + rule.id + '" class="flex items-center gap-1.5 text-[12px] text-faint">' +
                   '<span class="shrink-0">' + DOT + '</span>' + rule.label +
                   '</li>';
        }).join('');

        function paintPassword() {
            var result = SD.checkPassword(password.value);

            result.rules.forEach(function (r) {
                var li = rulesList.querySelector('[data-rule="' + r.id + '"]');
                if (!li) return;
                li.className = 'flex items-center gap-1.5 text-[12px] ' + (r.pass ? 'text-success' : 'text-faint');
                li.firstElementChild.innerHTML = r.pass ? CHECK : DOT;
            });

            meter.setAttribute('data-level', String(result.score));
            strength.textContent = result.label || '—';
            strength.className = 'text-[12px] font-medium w-[46px] text-right ' +
                (result.score >= 4 ? 'text-success' : result.score === 3 ? 'text-link'
                 : result.score === 2 ? 'text-warning' : result.score === 1 ? 'text-danger' : 'text-faint');

            return result;
        }

        function matchCheck() {
            if (!confirm) return true;
            if (confirm.value && confirm.value !== password.value) {
                SD.setError(confirm, 'Passwords do not match.');
                return false;
            }
            SD.setError(confirm, '');
            return true;
        }

        password.addEventListener('input', function () {
            paintPassword();
            if (password.classList.contains('is-error')) SD.setError(password, '');
            if (confirm && confirm.value) matchCheck();
        });

        if (confirm) confirm.addEventListener('input', matchCheck);

        // Repaint on load so a browser-restored value is graded, not left blank.
        if (password.value) paintPassword();
    });
</script>
@endpush
