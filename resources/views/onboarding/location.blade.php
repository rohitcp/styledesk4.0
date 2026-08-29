@extends('layouts.onboarding')

@section('title', 'Location')
@section('heading', 'Where do you operate?')
@section('subheading', 'Your primary location and the hours you are open. Add more locations later in Settings.')

@section('form')
    <form id="stepForm" method="POST" action="{{ route('onboarding.location.store') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">Location name</label>
            {{-- No default. "Main Location" arrived already filled in, so a
                 business with one salon called something else kept it without
                 noticing it had been answered for them. The placeholder still
                 suggests the shape of an answer. --}}
            <input id="name" name="name" type="text" class="sd-input" data-capitalize
                   placeholder="Main Location"
                   value="{{ old('name', $location?->name) }}" required>
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

            @php
                $selectedCountry = $countryCode;
                $selectedState = old('state', $location?->state);
                $countryRegions = $regions[$selectedCountry] ?? [];
            @endphp

        {{-- Country first: the region list below is derived from it, so
             choosing the country is the step that makes the next field
             meaningful. --}}
        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">
            <div>
                <label class="block text-[13px] font-medium text-ink mb-1.5">Country</label>
                {{-- Read-only: chosen on the business step and carried through,
                     so the two screens cannot disagree about where the business
                     operates. The value is not posted at all — the server reads
                     it from the tenant. --}}
                <div class="sd-input styledesk_readonlyfield text-sub">
                    <span>{{ $countryName }}</span>
                    <a href="{{ route('onboarding.business') }}" class="text-[12px] text-link font-medium hover:underline">Change</a>
                </div>
                <p class="mt-1.5 text-[12px] text-sub">Set on the business step. Regions and timezones below follow it.</p>
            </div>
            <div>
                <label for="{{ $countryRegions ? 'state' : 'state_text' }}" class="block text-[13px] font-medium text-ink mb-1.5">
                    State / Region
                </label>

                {{-- Both controls are rendered and one is disabled, rather than
                     swapping elements in and out. A disabled control is not
                     submitted, so exactly one `state` value ever posts, and the
                     combo does not have to be torn down and rebuilt when the
                     country changes. --}}
                <div id="state-select-wrap" @class(['hidden' => ! $countryRegions])>
                    <select id="state" name="state" class="sd-input" autocomplete="address-level1"
                            @disabled(! $countryRegions)>
                        <option value="">Select…</option>
                        @foreach ($countryRegions as $code => $name)
                            <option value="{{ $code }}" @selected($selectedState === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="state-text-wrap" @class(['hidden' => (bool) $countryRegions])>
                    <input id="state_text" name="state" type="text" class="sd-input" data-capitalize
                           autocomplete="address-level1" value="{{ $countryRegions ? '' : $selectedState }}"
                           placeholder="State, province or region" @disabled((bool) $countryRegions)>
                </div>

                @error('state')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5">
            <div>
                <label for="city" class="block text-[13px] font-medium text-ink mb-1.5">City</label>
                <input id="city" name="city" type="text" class="sd-input" data-capitalize autocomplete="address-level2"
                       value="{{ old('city', $location?->city) }}" required>
                @error('city')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="postal_code" class="block text-[13px] font-medium text-ink mb-1.5">Postal code</label>
                <input id="postal_code" name="postal_code" type="text" class="sd-input" autocomplete="postal-code"
                       value="{{ old('postal_code', $location?->postal_code) }}" required>
                @error('postal_code')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="phone" class="block text-[13px] font-medium text-ink mb-1.5">Location phone <span class="text-faint font-normal">(optional)</span></label>
            {{-- Digits and the punctuation a phone number is written with;
                 letters are refused as they are typed rather than at submit. --}}
            <input id="phone" name="phone" type="tel" class="sd-input" autocomplete="tel"
                   inputmode="tel" data-digits-only
                   value="{{ old('phone', $location?->phone) }}">
        </div>

        <div>
            <label for="timezone" class="block text-[13px] font-medium text-ink mb-1.5">Timezone</label>
            {{-- Empty until chosen. It used to arrive as America/New_York,
                 which is a guess a business outside that zone could accept
                 without ever seeing it — and every booking and reminder is
                 scheduled against this. --}}
            @php $selectedTimezone = old('timezone', $location?->timezone); @endphp

            <select id="timezone" name="timezone" class="sd-input" required>
                <option value="" @selected($selectedTimezone === null || $selectedTimezone === '')>Search or select a timezone</option>

                @foreach ($timezones as $identifier => $label)
                    <option value="{{ $identifier }}" @selected($selectedTimezone === $identifier)>{{ $label }}</option>
                @endforeach
            </select>
            <p id="tz-hint" class="mt-1.5 text-[12px] text-sub" role="status" aria-live="polite">
                Bookings and reminders are scheduled against this.
            </p>
            @error('timezone')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>

        {{-- Business hours are one Vue island rather than fourteen loose
             pickers: "Copy Monday to Tuesday–Friday" writes across rows, and
             that is only clean when one component owns them all. The rows
             still post ordinary hours[day][field] inputs. --}}
        @php
            $hoursInitial = collect(range(0, 6))->map(function (int $day) use ($location) {
                $saved = $location?->hours->firstWhere('day_of_week', $day);

                return [
                    'is_open' => (bool) old("hours.$day.is_open", $saved?->is_open ?? ($day !== 0)),
                    'opens_at' => old("hours.$day.opens_at", $saved?->opens_at ? substr($saved->opens_at, 0, 5) : '09:00'),
                    'closes_at' => old("hours.$day.closes_at", $saved?->closes_at ? substr($saved->closes_at, 0, 5) : '17:00'),
                ];
            })->all();

            /**
             * Onboarding gets the same labels as every other mount.
             *
             * Not split-period mode — that is Locations' need, not this
             * screen's — but the day names and the copy button are the same
             * words and should read the same way.
             */
            $hoursProps = [
                'initial' => $hoursInitial,
                'days' => array_values(App\Support\LocationOptions::weekdays()),
                'labels' => __('locations.hours_editor'),
            ];
        @endphp

        {{-- Divider before the hours: everything above describes where the
             business is, everything below describes when it is open. The rule
             lives on a wrapper rather than the island's own element, which Vue
             takes over on mount. --}}
        <div class="pt-5 border-t border-line">
            <div data-vue-component="BusinessHours" data-props='@json($hoursProps)'></div>
        </div>

    </form>

    <div class="flex flex-wrap items-center gap-3 pt-6">
        {{-- Fires once, like every other step. A slow POST shows the reader
             nothing, so they click again — and a second submit repeats the
             step. --}}
        <button type="submit" form="stepForm" data-submit-once data-busy-label="{{ __('common.saving') }}"
                class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            Continue
        </button>

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
    <h2 class="text-[15px] font-semibold text-head">Your location preview</h2>
    <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Clients see this on your booking page.</p>

    {{-- Mirrors the form. Hidden below lg, so nothing here is the only place a
         value is stated. --}}
    <div class="mt-5 rounded-card border border-line bg-white shadow-sm p-4" aria-hidden="true">
        <p id="pv-name" class="text-[14px] font-semibold text-head truncate">—</p>
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
            SD.combo(document.getElementById('timezone'), { searchPlaceholder: 'Search timezone…', width: '320px' });

            document.querySelectorAll('#stepForm select[name^="hours"]').forEach(function (el) {
                SD.combo(el, { searchPlaceholder: 'Search time…', width: '190px' });
            });
        }

        /* ---- Location preview -------------------------------------------
           Mirrors the form into the rail in the other column. */
        var pv = {
            name: document.getElementById('pv-name'),
            addr: document.getElementById('pv-addr'),
            tz: document.getElementById('pv-tz'),
            hours: document.getElementById('pv-hours'),
        };

        function val(id) {
            var el = document.getElementById(id);

            return el && !el.disabled ? el.value.trim() : '';
        }

        function paintPreview() {
            pv.name.textContent = val('name') || '—';

            // state_text is the fallback field for countries without a region
            // list; only one of the two is ever enabled, so reading both and
            // dropping the blank is enough.
            var parts = [
                val('address_line1'), val('address_line2'), val('city'),
                val('state') || val('state_text'), val('postal_code'),
            ].filter(Boolean);

            pv.addr.textContent = parts.length ? parts.join(', ') : 'Add your address to see it here.';
            pv.tz.textContent = val('timezone') || '—';

            var open = document.querySelectorAll('[name$="[is_open]"]:checked').length;
            pv.hours.textContent = open === 0
                ? 'Closed every day'
                : 'Open ' + open + ' ' + (open === 1 ? 'day' : 'days') + ' a week';
        }

        /* ---- Timezone suggested for this country -------------------------
           The country is fixed by the business step, so there is nothing to
           repaint here — only the region-level refinement below. */
        var REGION_ZONES = @json($regionTimezones);
        var COUNTRY_ZONES = @json($countryTimezones);
        var COUNTRY = @json($countryCode);

        var stateEl = document.getElementById('state');
        var tzEl = document.getElementById('timezone');
        var tzHint = document.getElementById('tz-hint');
        var tzTouched = false;

        tzEl.addEventListener('change', function () { tzTouched = true; });

        function applyZone(zone, note) {
            if (!zone || tzTouched || tzEl.value === zone) return;

            tzEl.value = zone;

            if (window.SD && typeof window.SD.comboRefresh === 'function') {
                SD.comboRefresh(tzEl);
            }

            tzHint.textContent = note;
            paintPreview();
        }

        /* ---- Timezone refined by the region -------------------------------
           The spec asks for the timezone to be worked out from the address
           where possible. It is a suggestion, not a decision: once the user
           picks a zone themselves we stop overriding it, because silently
           changing it back would be worse than not helping at all. */
        if (stateEl) {
            stateEl.addEventListener('change', function () {
                var byRegion = (REGION_ZONES[COUNTRY] || {})[stateEl.value];

                applyZone(byRegion, 'Set from your state — change it if that is not right.');
            });
        }

        // Delegated: SD.combo moves each select inside a new wrapper, and a
        // listener bound to the element before that still fires, but new
        // controls it creates would not be covered by a per-element bind.
        document.getElementById('stepForm').addEventListener('input', paintPreview);
        document.getElementById('stepForm').addEventListener('change', paintPreview);

        paintPreview();

    });
</script>
@endpush
