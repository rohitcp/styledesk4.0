@extends('layouts.onboarding')

@section('title', 'Booking')
@section('heading', 'How should clients book?')
@section('subheading', 'Deposits, no-show fees and reminders are configured later in Settings.')

@section('form')
    <form id="stepForm" method="POST" action="{{ route('onboarding.booking.store') }}" class="mt-6 space-y-6">
        @csrf

        {{-- Derived from tenants.slug rather than stored, so a rename cannot
             leave the two disagreeing. --}}
        <div class="rounded-card border border-line bg-white p-4">
            <p class="text-[13px] font-medium text-ink">Your booking page</p>
            <p class="mt-1.5 text-[13px] text-link break-all">
                https://{{ $tenant->slug }}.{{ config('tenancy.tenant_domain_suffix') }}
            </p>
        </div>

        <label class="styledesk_choice">
            <input type="checkbox" name="is_enabled" value="1" class="sd-check mt-0.5"
                   @checked(old('is_enabled', $settings?->is_enabled ?? true))>
            <span class="styledesk_choice__label">Allow clients to book online</span>
        </label>

        <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">Who can book</legend>
            <div class="styledesk_choicelist">
                <label class="styledesk_choice">
                    <input type="checkbox" name="allow_new_clients" value="1" class="sd-check"
                           @checked(old('allow_new_clients', $settings?->allow_new_clients ?? true))>
                    <span class="styledesk_choice__label">New clients</span>
                </label>
                <label class="styledesk_choice">
                    <input type="checkbox" name="allow_existing_clients" value="1" class="sd-check"
                           @checked(old('allow_existing_clients', $settings?->allow_existing_clients ?? true))>
                    <span class="styledesk_choice__label">Existing clients</span>
                </label>
            </div>
        </fieldset>

        <div class="grid sm:grid-cols-3 gap-x-4 gap-y-5">
            <div>
                <label for="min_notice_minutes" class="block text-[13px] font-medium text-ink mb-1.5">Minimum notice (minutes)</label>
                <input id="min_notice_minutes" name="min_notice_minutes" type="number" min="0" class="sd-input"
                       value="{{ old('min_notice_minutes', $settings?->min_notice_minutes ?? 120) }}" required>
                @error('min_notice_minutes')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="max_advance_days" class="block text-[13px] font-medium text-ink mb-1.5">Book up to (days ahead)</label>
                <input id="max_advance_days" name="max_advance_days" type="number" min="1" max="730" class="sd-input"
                       value="{{ old('max_advance_days', $settings?->max_advance_days ?? 60) }}" required>
                @error('max_advance_days')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="cancellation_window_hours" class="block text-[13px] font-medium text-ink mb-1.5">Cancellation window (hours)</label>
                <input id="cancellation_window_hours" name="cancellation_window_hours" type="number" min="0" class="sd-input"
                       value="{{ old('cancellation_window_hours', $settings?->cancellation_window_hours ?? 24) }}" required>
                @error('cancellation_window_hours')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">Require at booking</legend>
            <div class="space-y-2">
                @foreach ([
                    'require_email' => ['Email address', true],
                    'require_phone' => ['Phone number', false],
                    'require_card' => ['Card on file', false],
                ] as $field => [$label, $default])
                    <label class="styledesk_choice">
                        <input type="checkbox" name="{{ $field }}" value="1" class="sd-check"
                               @checked(old($field, $settings?->{$field} ?? $default))>
                        <span class="styledesk_choice__label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    </form>

    <div class="flex flex-wrap items-center gap-3 pt-6">
        {{-- Fires once, like every other step. A slow POST shows the reader
             nothing, so they click again — and a second submit repeats the
             step. --}}
        <button type="submit" form="stepForm" data-submit-once data-busy-label="{{ __('common.saving') }}"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            Continue
        </button>

        <form method="POST" action="{{ route('onboarding.skip', 'booking') }}">
            @csrf
            {{-- The skip posts too, so it gets the same guard. --}}
            <button type="submit" data-submit-once
                    class="h-11 px-4 rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors disabled:opacity-60 disabled:pointer-events-none">
                I'll do this later
            </button>
        </form>

        {{-- A link, not a form post: going back only re-reads an earlier step,
             so it must not submit anything or move current_step. --}}
        @if ($previousStep)
            {{-- ml-auto: Back sits on the opposite side from Continue, so
                 the button that moves forward stays where the eye lands. --}}
            <a href="{{ route('onboarding.'.$previousStep) }}"
               class="styledesk_action ml-auto">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Back
            </a>
        @endif
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Your booking rules</h2>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed">A summary of what the settings on the left allow.</p>

    {{-- Mirrors the switches and selects. Hidden below lg, so it is never the
         only place a setting is stated. --}}
    <div id="pv-card" class="mt-5 rounded-card border border-line bg-white shadow-sm overflow-hidden" aria-hidden="true">
        <div class="px-4 py-3 border-b border-line">
            <p class="text-[11px] font-semibold text-faint uppercase tracking-wide">Booking link</p>
            <p id="pv-url" class="text-[12px] text-ink font-medium mt-1 break-all">{{ config('app.url') }}/book/{{ $tenant->slug }}</p>
        </div>
        <ul id="pv-rules" class="divide-y divide-line"></ul>
        <div class="px-4 py-2.5 bg-hover/40 border-t border-line">
            <p id="pv-status" class="text-[12px] text-sub">Online booking is on.</p>
        </div>
    </div>

    <ul class="mt-8 space-y-4">
        @foreach ([
            'Clients can only book inside the hours you set on the previous step.',
            'Minimum notice protects your prep time; the cancellation window protects the slot.',
            'Deposits, no-show fees and reminders are configured later in Settings.',
        ] as $point)
            <li class="flex items-start gap-3">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-brand mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="text-[13px] text-ink leading-relaxed">{{ $point }}</span>
            </li>
        @endforeach
    </ul>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('stepForm');
        var rules = document.getElementById('pv-rules');
        var status = document.getElementById('pv-status');

        if (!form || !rules) return;

        function on(name) {
            var el = form.querySelector('[name="' + name + '"]');
            return el ? el.checked : false;
        }

        function num(name) {
            var el = form.querySelector('[name="' + name + '"]');
            return el ? el.value : '';
        }

        function row(label, value) {
            return '<li class="flex items-center justify-between gap-3 px-4 py-2.5">' +
                   '<span class="text-[12px] text-sub">' + label + '</span>' +
                   '<span class="text-[12px] text-ink font-medium text-right">' + value + '</span></li>';
        }

        function paint() {
            var who = [];
            if (on('allow_new_clients')) who.push('New');
            if (on('allow_existing_clients')) who.push('Existing');

            var required = [];
            if (on('require_email')) required.push('Email');
            if (on('require_phone')) required.push('Phone');
            if (on('require_card')) required.push('Card');

            rules.innerHTML =
                row('Who can book', who.length ? who.join(' & ') : 'Nobody') +
                row('Minimum notice', num('min_notice_minutes') + ' min') +
                row('Books up to', num('max_advance_days') + ' days ahead') +
                row('Cancellation', num('cancellation_window_hours') + ' h before') +
                row('Required', required.length ? required.join(', ') : 'Nothing');

            status.textContent = on('is_enabled')
                ? 'Online booking is on.'
                : 'Online booking is off — the link will not accept bookings.';
        }

        form.addEventListener('input', paint);
        form.addEventListener('change', paint);
        paint();
    });
</script>
@endpush
