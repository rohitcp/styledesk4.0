@extends('layouts.onboarding')

@section('title', 'Location')
@section('heading', 'Where do you operate?')
@section('subheading', 'Your primary location and the hours you are open. Add more locations later in Settings.')

@php
    // Built once and reused by all fourteen hour selects rather than
    // regenerated per row.
    $times = [];
    for ($minutes = 0; $minutes < 24 * 60; $minutes += 15) {
        $value = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        $times[$value] = date('g:i A', mktime(0, $minutes));
    }
@endphp

@section('form')
    <form id="stepForm" method="POST" action="{{ route('onboarding.location.store') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">Location name</label>
            <input id="name" name="name" type="text" class="sd-input"
                   value="{{ old('name', $location?->name ?? 'Main Location') }}" required>
            @error('name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="address_line1" class="block text-[13px] font-medium text-ink mb-1.5">Address</label>
            <input id="address_line1" name="address_line1" type="text" class="sd-input" placeholder="128 Grand Street"
                   autocomplete="address-line1" value="{{ old('address_line1', $location?->address_line1) }}" required>
            @error('address_line1')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="address_line2" class="block text-[13px] font-medium text-ink mb-1.5">Apartment, suite, etc. <span class="text-faint font-normal">(optional)</span></label>
            <input id="address_line2" name="address_line2" type="text" class="sd-input" placeholder="Suite 4"
                   autocomplete="address-line2" value="{{ old('address_line2', $location?->address_line2) }}">
        </div>

        <div class="grid sm:grid-cols-3 gap-x-4 gap-y-5">
            <div>
                <label for="city" class="block text-[13px] font-medium text-ink mb-1.5">City</label>
                <input id="city" name="city" type="text" class="sd-input" autocomplete="address-level2"
                       value="{{ old('city', $location?->city) }}" required>
                @error('city')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="state" class="block text-[13px] font-medium text-ink mb-1.5">State / Region</label>
                <select id="state" name="state" class="sd-input" autocomplete="address-level1">
                    <option value="">Select…</option>
                    @foreach (array_keys($usStates) as $code)
                        <option value="{{ $code }}" @selected(old('state', $location?->state) === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="postal_code" class="block text-[13px] font-medium text-ink mb-1.5">Postal code</label>
                <input id="postal_code" name="postal_code" type="text" class="sd-input" autocomplete="postal-code"
                       value="{{ old('postal_code', $location?->postal_code) }}" required>
                @error('postal_code')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">
            <div>
                <label for="country" class="block text-[13px] font-medium text-ink mb-1.5">Country</label>
                <select id="country" name="country" class="sd-input" required>
                    @foreach (['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'MX' => 'Mexico', 'FR' => 'France', 'DE' => 'Germany', 'ES' => 'Spain'] as $iso => $label)
                        <option value="{{ $iso }}" @selected(old('country', $location?->country ?? 'US') === $iso)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-[12px] text-sub">Sets your currency. Changeable in Settings.</p>
            </div>
            <div>
                <label for="phone" class="block text-[13px] font-medium text-ink mb-1.5">Location phone <span class="text-faint font-normal">(optional)</span></label>
                <input id="phone" name="phone" type="tel" class="sd-input" autocomplete="tel"
                       value="{{ old('phone', $location?->phone) }}">
            </div>
        </div>

        <div>
            <label for="timezone" class="block text-[13px] font-medium text-ink mb-1.5">Timezone</label>
            <select id="timezone" name="timezone" class="sd-input" required>
                @foreach ($timezones as $identifier => $label)
                    <option value="{{ $identifier }}" @selected(old('timezone', $location?->timezone ?? 'America/New_York') === $identifier)>{{ $label }}</option>
                @endforeach
            </select>
            <p id="tz-hint" class="mt-1.5 text-[12px] text-sub" role="status" aria-live="polite">
                Bookings and reminders are scheduled against this.
            </p>
            @error('timezone')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        {{-- One row per weekday. A row per day rather than a JSON blob because
             availability gets queried when working out bookable slots. --}}
        <fieldset class="pt-2">
            <div class="flex flex-wrap items-center gap-3 mb-2.5">
                <legend class="text-[13px] font-medium text-ink">Opening hours</legend>
                <button type="button" id="copy-monday"
                        class="ml-auto h-8 px-3 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[12px] font-semibold transition-colors">
                    Copy Monday to Tuesday–Friday
                </button>
            </div>
            <div class="rounded-card border border-line divide-y divide-line">
                @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day => $label)
                    @php
                        $saved = $location?->hours->firstWhere('day_of_week', $day);
                        $isOpen = old("hours.$day.is_open", $saved?->is_open ?? ($day !== 0));
                    @endphp
                    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <label class="flex items-center gap-2.5 cursor-pointer w-[150px]">
                            <input type="checkbox" name="hours[{{ $day }}][is_open]" value="1" class="sd-check" @checked($isOpen)>
                            <span class="text-[13px] text-ink">{{ $label }}</span>
                        </label>
                        @php
                            $opensAt = old("hours.$day.opens_at", $saved?->opens_at ? substr($saved->opens_at, 0, 5) : '09:00');
                            $closesAt = old("hours.$day.closes_at", $saved?->closes_at ? substr($saved->closes_at, 0, 5) : '17:00');
                        @endphp
                        <select name="hours[{{ $day }}][opens_at]" aria-label="{{ $label }} opening time" class="sd-input w-auto">
                            @foreach ($times as $value => $display)
                                <option value="{{ $value }}" @selected($opensAt === $value)>{{ $display }}</option>
                            @endforeach
                        </select>
                        <span class="text-sub">to</span>
                        <select name="hours[{{ $day }}][closes_at]" aria-label="{{ $label }} closing time" class="sd-input w-auto">
                            @foreach ($times as $value => $display)
                                <option value="{{ $value }}" @selected($closesAt === $value)>{{ $display }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        </fieldset>

    </form>

    <div class="flex flex-wrap items-center gap-3 pt-6">
        <button type="submit" form="stepForm"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Continue
        </button>

        {{-- A link, not a form post: going back only re-reads an earlier step,
             so it must not submit anything or move current_step. --}}
        @if ($previousStep)
            <a href="{{ route('onboarding.'.$previousStep) }}"
               class="inline-flex items-center gap-1.5 h-11 px-4 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Back
            </a>
        @endif
    </div>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Your location preview</h2>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Clients see this on your booking page.</p>

    {{-- Mirrors the form. Hidden below lg, so nothing here is the only place a
         value is stated. --}}
    <div class="mt-5 rounded-card border border-line bg-white shadow-sm p-4" aria-hidden="true">
        <p id="pv-name" class="text-[14px] font-semibold text-head truncate">Main Location</p>
        <p id="pv-addr" class="text-[12px] text-sub mt-1 leading-relaxed">Add your address to see it here.</p>

        <div class="mt-4 pt-3.5 border-t border-line">
            <p class="text-[11px] font-semibold text-faint uppercase tracking-wide">Timezone</p>
            <p id="pv-tz" class="text-[12px] text-ink font-medium mt-1">—</p>
        </div>
        <div class="mt-3.5 pt-3.5 border-t border-line">
            <p class="text-[11px] font-semibold text-faint uppercase tracking-wide">Opening hours</p>
            <p id="pv-hours" class="text-[12px] text-ink font-medium mt-1">—</p>
        </div>
    </div>

    <ul class="mt-8 space-y-4">
        @foreach ([
            'Timezone drives appointments, reminders, reporting and what clients can book — worth getting right now.',
            'These hours become your default booking availability. Staff schedules can override them later.',
            'Got more than one site? Add further locations once setup is finished.',
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
    /* Copy Monday's hours across the working week (section 11). Purely a
       convenience over the same inputs the form already posts — nothing is
       stored differently as a result. */
    document.addEventListener('DOMContentLoaded', function () {
        /* ---- Every dropdown becomes a searchable combo -------------------
           SD.combo keeps the native <select> underneath as the value holder
           and hides it from the a11y tree, so the form still posts normally
           and everything below — inference, the preview, the copy action —
           keeps reading .value as if nothing had changed. */
        if (window.SD && typeof window.SD.combo === 'function') {
            SD.combo(document.getElementById('state'), { searchPlaceholder: 'Search state…', width: '220px' });
            SD.combo(document.getElementById('country'), { searchPlaceholder: 'Search country…', width: '260px' });
            SD.combo(document.getElementById('timezone'), { searchPlaceholder: 'Search timezone…', width: '320px' });

            document.querySelectorAll('#stepForm select[name^="hours"]').forEach(function (el) {
                SD.combo(el, { searchPlaceholder: 'Search time…', width: '190px' });
            });
        }

        /* ---- Timezone inferred from the state ----------------------------
           The spec asks for the timezone to be worked out from the address
           where possible. It is a suggestion, not a decision: once the user
           picks a zone themselves we stop overriding it, because silently
           changing it back would be worse than not helping at all. */
        var STATE_ZONES = @json(config('locations.us_states'));
        var stateEl = document.getElementById('state');
        var tzEl = document.getElementById('timezone');
        var tzHint = document.getElementById('tz-hint');
        var tzTouched = false;

        tzEl.addEventListener('change', function () { tzTouched = true; });

        stateEl.addEventListener('change', function () {
            var guess = STATE_ZONES[stateEl.value];

            if (!guess || tzTouched || tzEl.value === guess) return;

            tzEl.value = guess;

            // The visible control is the combo button, not the select, so it
            // has to be told the value changed underneath it.
            if (window.SD && typeof window.SD.comboRefresh === 'function') {
                SD.comboRefresh(tzEl);
            }

            tzHint.textContent = 'Set from your state — change it if that is not right.';
            paintPreview();
        });

        var button = document.getElementById('copy-monday');

        if (!button) return;

        /* ---- Location preview ------------------------------------------
           Mirrors the form into the rail. */
        var pv = {
            name: document.getElementById('pv-name'),
            addr: document.getElementById('pv-addr'),
            tz: document.getElementById('pv-tz'),
            hours: document.getElementById('pv-hours'),
        };

        function val(id) {
            var el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }

        function paintPreview() {
            pv.name.textContent = val('name') || 'Main Location';

            var parts = [val('address_line1'), val('address_line2'), val('city'), val('state'), val('postal_code')]
                .filter(Boolean);

            pv.addr.textContent = parts.length ? parts.join(', ') : 'Add your address to see it here.';
            pv.tz.textContent = val('timezone') || '—';

            var open = document.querySelectorAll('[name$="[is_open]"]:checked').length;
            pv.hours.textContent = open === 0
                ? 'Closed every day'
                : 'Open ' + open + ' ' + (open === 1 ? 'day' : 'days') + ' a week';
        }

        // Delegated: SD.combo moves each select inside a new wrapper, and a
        // listener bound to the element before that still fires, but new
        // controls it creates would not be covered by a per-element bind.
        document.getElementById('stepForm').addEventListener('input', paintPreview);
        document.getElementById('stepForm').addEventListener('change', paintPreview);

        paintPreview();

        button.addEventListener('click', function () {
            var source = {
                open: document.querySelector('[name="hours[1][is_open]"]'),
                from: document.querySelector('[name="hours[1][opens_at]"]'),
                to: document.querySelector('[name="hours[1][closes_at]"]'),
            };

            [2, 3, 4, 5].forEach(function (day) {
                var open = document.querySelector('[name="hours[' + day + '][is_open]"]');
                var from = document.querySelector('[name="hours[' + day + '][opens_at]"]');
                var to = document.querySelector('[name="hours[' + day + '][closes_at]"]');

                if (open) open.checked = source.open.checked;
                if (from) from.value = source.from.value;
                if (to) to.value = source.to.value;
            });

            // Setting .checked in script fires no change event, so the
            // preview would silently fall out of step with the form.
            paintPreview();
        });
    });
</script>
@endpush
