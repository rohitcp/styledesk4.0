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

        <label class="flex items-start gap-2.5 cursor-pointer">
            <input type="checkbox" name="is_enabled" value="1" class="sd-check mt-0.5"
                   @checked(old('is_enabled', $settings?->is_enabled ?? true))>
            <span class="text-[13px] text-ink">Allow clients to book online</span>
        </label>

        <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">Who can book</legend>
            <div class="space-y-2">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="allow_new_clients" value="1" class="sd-check"
                           @checked(old('allow_new_clients', $settings?->allow_new_clients ?? true))>
                    <span class="text-[13px] text-ink">New clients</span>
                </label>
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="allow_existing_clients" value="1" class="sd-check"
                           @checked(old('allow_existing_clients', $settings?->allow_existing_clients ?? true))>
                    <span class="text-[13px] text-ink">Existing clients</span>
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
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="{{ $field }}" value="1" class="sd-check"
                               @checked(old($field, $settings?->{$field} ?? $default))>
                        <span class="text-[13px] text-ink">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    </form>

    <div class="flex flex-wrap items-center gap-3 pt-6">
        <button type="submit" form="stepForm"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Continue
        </button>

        <form method="POST" action="{{ route('onboarding.skip', 'booking') }}">
            @csrf
            <button type="submit" class="h-11 px-4 rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
                I'll do this later
            </button>
        </form>
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Why we ask</h2>
    <p class="text-[13px] text-sub leading-relaxed mt-3">
        These rules decide which slots appear on your public booking page and how
        late someone can cancel. All of it is editable in Settings.
    </p>
@endsection
