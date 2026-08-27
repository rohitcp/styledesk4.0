{{--
    The location form's fields, shared by create and edit.

    One copy, because two would drift: a field added to the create screen and
    forgotten on the edit screen is invisible until someone tries to change it.

    $locationValue() prefers what was just typed, then the record being edited,
    then a fallback — so a failed submit never loses input, and an untouched
    edit shows what is stored.
--}}
@php
    $location = $location ?? null;

    /**
     * Named $locationValue, not $value.
     *
     * The loops below bind $value as their option key, which would shadow a
     * closure of that name inside exactly the blocks that need it — and the
     * failure would be a form field silently rendering blank.
     */
    $locationValue = fn (string $field, $fallback = null) => old($field, $location?->{$field} ?? $fallback);

    $chosenAssistants = array_map('strval', old('assistant_manager_ids', $assistantIds));
    $isPrimary = (bool) old('is_primary', $location?->is_primary ?? false);
    $status = old('status', $location?->status ?? App\Models\Location::STATUS_ACTIVE);

    // Staff as label => id for the manager combos. Built here rather than in
    // the attribute, because the json directive cannot take a call with
    // nested parentheses.
    $staffChoices = $staffOptions->mapWithKeys(fn ($member) => [
        $member->id => $member->displayName().($member->job_title ? ' — '.$member->job_title : ''),
    ]);

    /**
     * The hours the form renders, as old input if there is any.
     *
     * A failed submit must not silently reset the week to what is stored:
     * someone who split Tuesday and mistyped an email would lose the split
     * and not necessarily notice.
     */
    $submittedHours = old('hours');

    /**
     * What a new branch starts as, before anyone chooses.
     *
     * A blank required time zone on the Add screen is a field nobody can
     * guess the format of, and getting it wrong puts a branch's whole week
     * of opening hours on the wrong clock. The business's own country is the
     * best available answer; a branch abroad is the case where it is worth
     * asking someone to change it.
     */
    $formTenant = auth()->user()->tenant;
    $defaultCountry = $formTenant?->countryCode();
    $defaultTimezone = $formTenant?->timezone
        ?? $formTenant?->locations()->orderByDesc('is_primary')->value('timezone')
        ?? config('locations.country_timezones.'.$defaultCountry);
@endphp

{{-- ------------------------------------------ 1. location information --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">Location information</h2>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    <div class="sm:col-span-2">
      <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">
        Location name <span class="text-danger">*</span>
      </label>
      <input id="name" name="name" type="text" class="sd-input" data-capitalize required
             placeholder="Downtown Salon" value="{{ $locationValue('name') }}" autofocus>
      @error('name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="code" class="block text-[13px] font-medium text-ink mb-1.5">
        Location code <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="code" name="code" type="text" class="sd-input" placeholder="DT01"
             value="{{ $locationValue('code') }}">
      @error('code')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
      <p class="mt-1.5 text-[12px] text-sub">A short name that tells this branch apart on reports and receipts.</p>
    </div>

    <x-combo name="type" label="Location type" :options="$types"
             :selected="$locationValue('type')" placeholder="Not specified" />
  </div>

  <div class="pt-4 border-t border-line space-y-3">
    <label class="flex items-start gap-2.5 cursor-pointer">
      <input id="is_primary" name="is_primary" type="checkbox" value="1" class="sd-check mt-0.5" @checked($isPrimary)>
      <span class="min-w-0">
        <span class="block text-[13px] font-medium text-ink">Primary location</span>
        {{-- Says what happens rather than forbidding it. The controller
             demotes the previous primary, so the honest wording is what it
             will do, not a rule the user has to enforce themselves. --}}
        <span class="block text-[12px] text-sub">The business's main branch. Setting this here removes it from whichever location holds it now.</span>
      </span>
    </label>
  </div>

  <fieldset class="pt-4 border-t border-line">
    <legend class="text-[13px] font-medium text-ink mb-2">Status <span class="text-danger">*</span></legend>
    <div class="flex items-center gap-5">
      @foreach (config('locations.statuses') as $value => $meta)
        <label class="flex items-center gap-2.5 cursor-pointer">
          <input type="radio" name="status" value="{{ $value }}" class="sd-check" @checked($status === $value)>
          <span class="text-[13px] text-ink">{{ $meta['label'] }}</span>
        </label>
      @endforeach
    </div>
    <p class="mt-1.5 text-[12px] text-sub">
      An inactive location takes no new bookings and is hidden from online booking. Its history is kept.
    </p>
    @error('status')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
  </fieldset>
</section>

{{-- ---------------------------------------------------- 2. address --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">Address</h2>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    <div class="sm:col-span-2">
      <label for="address_line1" class="block text-[13px] font-medium text-ink mb-1.5">
        Address line 1 <span class="text-danger">*</span>
      </label>
      <input id="address_line1" name="address_line1" type="text" class="sd-input" data-capitalize required
             value="{{ $locationValue('address_line1') }}">
      @error('address_line1')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="address_line2" class="block text-[13px] font-medium text-ink mb-1.5">
        Address line 2 <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="address_line2" name="address_line2" type="text" class="sd-input" data-capitalize
             value="{{ $locationValue('address_line2') }}">
    </div>

    <div>
      <label for="suite" class="block text-[13px] font-medium text-ink mb-1.5">
        Suite / unit <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="suite" name="suite" type="text" class="sd-input" data-capitalize
             value="{{ $locationValue('suite') }}">
    </div>

    <div>
      <label for="city" class="block text-[13px] font-medium text-ink mb-1.5">
        City <span class="text-danger">*</span>
      </label>
      <input id="city" name="city" type="text" class="sd-input" data-capitalize required
             value="{{ $locationValue('city') }}">
      @error('city')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="state" class="block text-[13px] font-medium text-ink mb-1.5">
        State / province <span class="text-danger">*</span>
      </label>
      <input id="state" name="state" type="text" class="sd-input" data-capitalize required
             value="{{ $locationValue('state') }}">
      @error('state')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="postal_code" class="block text-[13px] font-medium text-ink mb-1.5">
        ZIP / postal code <span class="text-danger">*</span>
      </label>
      <input id="postal_code" name="postal_code" type="text" class="sd-input" required
             value="{{ $locationValue('postal_code') }}">
      @error('postal_code')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    {{-- The country is editable here, unlike in onboarding.
         Onboarding refuses it because the business's own country is being
         established on that screen; a second branch may genuinely be in
         another one, and a group with sites across a border is the ordinary
         case this module exists for. --}}
    <x-combo name="country" label="Country" required :options="$countries"
             :selected="$locationValue('country', $defaultCountry)"
             placeholder="Choose a country" />

    <x-combo name="timezone" label="Time zone" required :options="$timezones"
             :selected="$locationValue('timezone', $defaultTimezone)" placeholder="Choose a time zone"
             hint="Opening hours and bookings for this branch are read in this zone." />
  </div>
</section>

{{-- --------------------------------------------- 3. location manager --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">Location manager</h2>
  <p class="text-[13px] text-sub">
    Who is responsible for this branch. Naming someone here does not change what they can do in
    StyleDesk — that is decided by their role under Roles &amp; permissions.
  </p>

  @if ($staffChoices->isEmpty())
    <p class="text-[13px] text-sub">
      No active staff yet. <a href="{{ route('settings.staff.create') }}" class="text-link hover:underline">Add a staff member</a>
      and you can name a manager here.
    </p>
  @else
    <x-combo name="manager_staff_id" label="Location manager" :options="$staffChoices"
             :selected="$locationValue('manager_staff_id')" placeholder="Not assigned" />

    <fieldset>
      <legend class="text-[13px] font-medium text-ink mb-2">
        Assistant managers <span class="text-faint font-normal">(optional)</span>
      </legend>
      <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
        @foreach ($staffOptions as $member)
          <label class="flex items-center gap-2.5 cursor-pointer">
            <input type="checkbox" name="assistant_manager_ids[]" value="{{ $member->id }}" class="sd-check"
                   @checked(in_array((string) $member->id, $chosenAssistants, true))>
            <span class="text-[13px] text-ink">{{ $member->displayName() }}</span>
          </label>
        @endforeach
      </div>
      <p class="mt-1.5 text-[12px] text-sub">Anyone also chosen as the location manager above is not listed twice.</p>
    </fieldset>
  @endif
</section>

{{-- ---------------------------------------------- 4. contact details --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">Contact details</h2>
  <p class="text-[13px] text-sub">
    Used instead of the main business contact details wherever this branch is named.
  </p>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    <div>
      <label for="phone" class="block text-[13px] font-medium text-ink mb-1.5">
        Main phone number <span class="text-danger">*</span>
      </label>
      <input id="phone" name="phone" type="tel" class="sd-input" required value="{{ $locationValue('phone') }}">
      @error('phone')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="phone_secondary" class="block text-[13px] font-medium text-ink mb-1.5">
        Secondary phone <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="phone_secondary" name="phone_secondary" type="tel" class="sd-input"
             value="{{ $locationValue('phone_secondary') }}">
    </div>

    <div>
      <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">
        Location email <span class="text-danger">*</span>
      </label>
      <input id="email" name="email" type="email" class="sd-input" required value="{{ $locationValue('email') }}">
      @error('email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="booking_email" class="block text-[13px] font-medium text-ink mb-1.5">
        Booking contact email <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="booking_email" name="booking_email" type="email" class="sd-input"
             value="{{ $locationValue('booking_email') }}">
      @error('booking_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="support_email" class="block text-[13px] font-medium text-ink mb-1.5">
        Customer service email <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="support_email" name="support_email" type="email" class="sd-input"
             value="{{ $locationValue('support_email') }}">
      @error('support_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="website" class="block text-[13px] font-medium text-ink mb-1.5">
        Website <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="website" name="website" type="url" class="sd-input" placeholder="https://"
             value="{{ $locationValue('website') }}">
      @error('website')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>

    <div>
      <label for="extension" class="block text-[13px] font-medium text-ink mb-1.5">
        Internal extension <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="extension" name="extension" type="text" class="sd-input" value="{{ $locationValue('extension') }}">
    </div>

    <div>
      <label for="contact_person" class="block text-[13px] font-medium text-ink mb-1.5">
        Contact person <span class="text-faint font-normal">(optional)</span>
      </label>
      <input id="contact_person" name="contact_person" type="text" class="sd-input" data-capitalize
             value="{{ $locationValue('contact_person') }}">
    </div>
  </div>
</section>

{{-- ----------------------------------------------- 5. location hours --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4" data-hours>
  <div class="flex flex-wrap items-start gap-3">
    <div class="min-w-0 flex-1">
      <h2 class="text-[15px] font-semibold text-head">Location hours</h2>
      <p class="text-[13px] text-sub mt-1">
        When this branch is open, in its own time zone. Add a second period to a day that closes in
        the middle — for lunch, for instance.
      </p>
    </div>

    {{-- Copies the first open weekday down the rest of them. Named for what
         it does rather than "Copy hours", which would leave the user guessing
         which day it copies from and where it copies to. --}}
    <button type="button" data-copy-weekdays
            class="shrink-0 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
      Apply Monday's hours to weekdays
    </button>
  </div>

  @error('hours')<p class="text-[12px] text-danger">{{ $message }}</p>@enderror

  <div class="divide-y divide-line">
    @foreach ($weekdays as $day => $dayLabel)
      @php
          /**
           * Old input wins, then what is stored, then the default week.
           *
           * Old input arrives as arrays of strings from the request; stored
           * hours arrive as models. Both are flattened to the same shape here
           * so the markup below does not have to know which it got.
           */
          if (is_array($submittedHours)) {
              $submittedDay = $submittedHours[$day] ?? [];
              $dayIsOpen = (bool) ($submittedDay['is_open'] ?? false);
              $periods = collect($submittedDay)
                  ->except('is_open')
                  ->map(fn ($period) => [
                      'opens_at' => $period['opens_at'] ?? '',
                      'closes_at' => $period['closes_at'] ?? '',
                  ])
                  ->values();
          } else {
              $stored = $hoursByDay[$day]['periods'] ?? collect();
              $dayIsOpen = $stored->isNotEmpty();
              $periods = $stored->map(fn ($period) => [
                  'opens_at' => $period->timeValue('opens_at') ?? '',
                  'closes_at' => $period->timeValue('closes_at') ?? '',
              ])->values();
          }

          // A closed day still renders one blank period, so switching it on
          // does not require also adding a row before any time can be typed.
          if ($periods->isEmpty()) {
              $periods = collect([['opens_at' => '09:00', 'closes_at' => '17:00']]);
          }
      @endphp

      <div class="py-3" data-day="{{ $day }}">
        <div class="flex flex-wrap items-start gap-3">
          <label class="flex items-center gap-2.5 cursor-pointer w-[150px] shrink-0 pt-2">
            <input type="checkbox" name="hours[{{ $day }}][is_open]" value="1" class="sd-check"
                   data-day-toggle @checked($dayIsOpen)>
            <span class="text-[13px] font-medium text-ink">{{ $dayLabel }}</span>
          </label>

          <div class="min-w-0 flex-1 space-y-2" data-periods @if (! $dayIsOpen) hidden @endif>
            @foreach ($periods as $index => $period)
              <div class="flex flex-wrap items-center gap-2" data-period>
                <input type="time" class="sd-input styledesk_hours__time"
                       name="hours[{{ $day }}][{{ $index }}][opens_at]"
                       value="{{ $period['opens_at'] }}" aria-label="{{ $dayLabel }} opening time">
                <span class="text-[13px] text-sub">to</span>
                <input type="time" class="sd-input styledesk_hours__time"
                       name="hours[{{ $day }}][{{ $index }}][closes_at]"
                       value="{{ $period['closes_at'] }}" aria-label="{{ $dayLabel }} closing time">

                {{-- Only from the second period onwards. Removing the only
                     period is what the day's own toggle is for, and two
                     controls for one outcome is a choice nobody wants. --}}
                <button type="button" data-remove-period
                        class="h-9 w-9 inline-flex items-center justify-center rounded-lg border border-stroke bg-white hover:bg-hover text-sub transition-colors"
                        aria-label="Remove this period from {{ $dayLabel }}"
                        @if ($index === 0) hidden @endif>
                  <x-icon name="trash-can" size="14" />
                </button>
              </div>
            @endforeach

            <button type="button" data-add-period
                    class="text-[13px] font-medium text-link hover:underline">
              + Add another period
            </button>

            @php
                // Validation keys are concrete — hours.1.0.closes_at — so the
                // day's messages are gathered by prefix rather than looked up
                // by a wildcard, which matches nothing.
                $dayErrors = collect($errors->messages())
                    ->filter(fn ($messages, $key) => str_starts_with($key, 'hours.'.$day.'.'))
                    ->flatten()
                    ->unique();
            @endphp
            @foreach ($dayErrors as $dayError)
              <p class="text-[12px] text-danger">{{ $dayError }}</p>
            @endforeach
          </div>

          <p class="text-[13px] text-faint pt-2" data-closed-label @if ($dayIsOpen) hidden @endif>Closed</p>
        </div>
      </div>
    @endforeach
  </div>
</section>
