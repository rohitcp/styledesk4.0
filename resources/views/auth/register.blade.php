@extends('layouts.auth')

@section('title', 'Create your account')
@section('heading', 'Create your StyleDesk account')
@section('subheading', 'Set up your business and start managing bookings, clients, staff, and services.')

@section('form')
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
                <button type="button" data-reveal="password"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors"
                        aria-label="Show password" aria-pressed="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
                </button>
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
                <button type="button" data-reveal="password_confirmation"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors"
                        aria-label="Show password" aria-pressed="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7"/></svg>
                </button>
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
        Everything your business runs on, <span class="text-[#9a93c4]">in one place.</span>
    </h2>

    <ul class="mt-8 space-y-5">
        @foreach ([
            'Bookings, staff schedules and resources on one calendar.',
            'Client history, notes and preferences that follow every visit.',
            'Automatic reminders that cut no-shows without extra admin.',
        ] as $point)
            <li class="flex items-start gap-3.5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[15px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
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
