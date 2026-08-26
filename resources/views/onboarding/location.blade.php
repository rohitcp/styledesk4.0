@extends('layouts.onboarding')

@section('title', 'Location')
@section('heading', 'Where do you operate?')
@section('subheading', 'Your primary location and the hours you are open. Add more locations later in Settings.')

@section('form')
    <form method="POST" action="{{ route('onboarding.location.store') }}" class="mt-6 space-y-5">
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
                <input id="state" name="state" type="text" class="sd-input" autocomplete="address-level1"
                       value="{{ old('state', $location?->state) }}">
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
                @foreach (DateTimeZone::listIdentifiers() as $tz)
                    <option value="{{ $tz }}" @selected(old('timezone', $location?->timezone ?? 'America/New_York') === $tz)>{{ $tz }}</option>
                @endforeach
            </select>
            <p class="mt-1.5 text-[12px] text-sub">Bookings and reminders are scheduled against this.</p>
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
                        <input type="time" name="hours[{{ $day }}][opens_at]" class="sd-input w-auto"
                               value="{{ old("hours.$day.opens_at", $saved?->opens_at ? substr($saved->opens_at, 0, 5) : '09:00') }}">
                        <span class="text-sub">to</span>
                        <input type="time" name="hours[{{ $day }}][closes_at]" class="sd-input w-auto"
                               value="{{ old("hours.$day.closes_at", $saved?->closes_at ? substr($saved->closes_at, 0, 5) : '17:00') }}">
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div class="pt-2">
            <button type="submit" class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
                Continue
            </button>
        </div>
    </form>
@endsection

@section('rail')
    <h2 class="text-[15px] font-semibold text-head">Why we ask</h2>
    <p class="text-[13px] text-sub leading-relaxed mt-3">
        Your address and hours decide which appointment slots clients can pick.
        The timezone matters most — reminders go out against it.
    </p>
@endsection

@push('scripts')
<script>
    /* Copy Monday's hours across the working week (section 11). Purely a
       convenience over the same inputs the form already posts — nothing is
       stored differently as a result. */
    document.addEventListener('DOMContentLoaded', function () {
        var button = document.getElementById('copy-monday');

        if (!button) return;

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
        });
    });
</script>
@endpush
