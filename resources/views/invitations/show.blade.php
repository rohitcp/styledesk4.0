@extends('layouts.auth')

@section('title', 'Join the team')
@section('heading-class', 'text-[26px] sm:text-[28px]')
@section('heading', "You're invited to join")
@section('subheading', $invitation->tenant?->name)

@section('form')
    {{-- The invitation's own facts first, before any form.
         The visitor may not recognise the business by name alone, so who
         invited them and as what is the context that makes the page
         trustworthy rather than an unexplained request for a password. --}}
    <div class="mt-7 rounded-card border border-line bg-white p-4 space-y-3">
        <div class="flex items-start gap-3">
            <span class="sd-avatar sd-avatar--md shrink-0" aria-hidden="true">{{ strtoupper(mb_substr($invitation->tenant?->name ?? 'S', 0, 2)) }}</span>
            <span class="min-w-0">
                <span class="block text-[14px] font-semibold text-head truncate">{{ $invitation->tenant?->name }}</span>
                <span class="block text-[12px] text-sub truncate">Invited by {{ $invitation->inviter?->name ?? 'your new team' }}</span>
            </span>
        </div>

        <dl class="pt-3 border-t border-line grid grid-cols-[auto_1fr] gap-x-6 gap-y-2">
            <dt class="text-[13px] text-sub">Invited as</dt>
            <dd class="text-[13px] font-medium text-ink">{{ $roleLabel }}</dd>

            @if ($invitation->location)
                <dt class="text-[13px] text-sub">Location</dt>
                <dd class="text-[13px] font-medium text-ink">{{ $invitation->location->name }}</dd>
            @endif

            <dt class="text-[13px] text-sub">Email</dt>
            <dd class="text-[13px] font-medium text-ink truncate">{{ $invitation->email }}</dd>
        </dl>

        @if ($invitation->message)
            <p class="pt-3 border-t border-line text-[13px] text-ink leading-relaxed italic">“{{ $invitation->message }}”</p>
        @endif
    </div>

    @if ($isSignedIn)
        {{-- Signed in as the invited address: nothing left to collect. A POST
             rather than doing it on page load, because joining a business is a
             state change and a GET that mutates is one refresh away from
             happening twice. --}}
        <form method="POST" action="{{ route('team-invite.accept', $token) }}" class="mt-6">
            @csrf
            <button type="submit"
                    class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
                Join Team
            </button>
        </form>
    @elseif ($hasAccount)
        {{-- Already has a StyleDesk login. No second account is created; they
             sign in and come straight back here. --}}
        <div class="mt-6">
            <p class="text-[13px] text-sub leading-relaxed">
                You already have a StyleDesk account with this email address. Sign in to accept the invitation.
            </p>
            <form method="POST" action="{{ route('team-invite.login', $token) }}" class="mt-4">
                @csrf
                <button type="submit"
                        class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
                    Sign in to accept
                </button>
            </form>
        </div>
    @else
        <form method="POST" action="{{ route('team-invite.register', $token) }}" enctype="multipart/form-data" class="mt-7 space-y-5">
            @csrf

            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">
                <div>
                    <label for="first_name" class="block text-[13px] font-medium text-ink mb-1.5">First name</label>
                    <input id="first_name" name="first_name" type="text" autocomplete="given-name" class="sd-input" data-capitalize
                           value="{{ old('first_name', $invitation->first_name) }}" required autofocus>
                    @error('first_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="block text-[13px] font-medium text-ink mb-1.5">Last name</label>
                    <input id="last_name" name="last_name" type="text" autocomplete="family-name" class="sd-input" data-capitalize
                           value="{{ old('last_name', $invitation->last_name) }}" required>
                    @error('last_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Read-only and not posted. The server takes the address from the
                 invitation regardless of what arrives, so this field is a
                 statement of fact rather than an input. --}}
            <div>
                <label for="invited_email" class="block text-[13px] font-medium text-ink mb-1.5">Email</label>
                <input id="invited_email" type="email" class="sd-input bg-hover text-sub" value="{{ $invitation->email }}" readonly>
                <p class="mt-1.5 text-[12px] text-sub">This invitation can only be accepted with this address.</p>
            </div>

            <div>
                <label for="password" class="block text-[13px] font-medium text-ink mb-1.5">Password</label>
                <div class="relative">
                    <input id="password" name="password" type="password" autocomplete="new-password"
                           class="sd-input has-suffix" required>
                    <x-password-toggle for="password" class="absolute right-2.5 top-1/2 -translate-y-1/2 h-7 w-7 grid place-items-center rounded text-faint hover:text-sub hover:bg-hover transition-colors" />
                </div>
                @error('password')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror

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

            <div>
                <label for="avatar" class="block text-[13px] font-medium text-ink mb-1.5">
                    Profile image <span class="text-faint font-normal">(optional)</span>
                </label>
                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-[13px] text-sub file:mr-3 file:h-9 file:px-3.5 file:rounded-md file:border file:border-stroke file:bg-white file:text-[13px] file:font-semibold file:text-ink hover:file:bg-hover file:cursor-pointer">
                <p class="mt-1.5 text-[12px] text-sub">JPG, PNG or WEBP, up to 2 MB. You can add this later.</p>
                @error('avatar')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="flex items-start gap-2.5 text-[13px] text-ink cursor-pointer leading-relaxed">
                    <input id="terms" name="terms" type="checkbox" value="1" class="sd-check mt-0.5" @checked(old('terms'))>
                    <span>I agree to the StyleDesk <a href="#" class="text-link font-medium hover:underline">Terms of Service</a> and <a href="#" class="text-link font-medium hover:underline">Privacy Policy</a>.</span>
                </label>
                @error('terms')<p class="mt-1.5 ml-[26px] text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                    class="w-full h-11 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
                Create Account &amp; Join Team
            </button>
        </form>
    @endif

    <p class="text-center mt-7 text-[13px] text-sub">
        This invitation expires on {{ $invitation->expires_at?->format('j F Y') }}.
    </p>
@endsection

@section('value-panel')
    <h2 class="text-[24px] font-bold text-head tracking-tight leading-[1.2]">
        One place for the whole team's day
    </h2>
    <p class="text-[14px] text-sub mt-3 leading-relaxed">
        Once you join {{ $invitation->tenant?->name }}, you'll see your own schedule, your clients and the
        services you're booked for.
    </p>

    <ul class="mt-8 space-y-4">
        @foreach ([
            'Your calendar shows only your own bookings unless you are given more access.',
            'Clients book you by name, with the services assigned to you.',
            'Your role decides what you can see and change — ask the owner if you need more.',
        ] as $point)
            <li class="flex items-start gap-3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[14px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection
