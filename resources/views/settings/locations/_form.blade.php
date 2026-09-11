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

    /**
     * The business's own website, offered to a new branch.
     *
     * Captured on the first onboarding step and almost always the right
     * answer: most branches share one site, and the ones that do not are the
     * case worth typing. A suggestion only — it is an ordinary editable
     * field, and clearing it saves it cleared.
     *
     * Only on Add. On Edit the stored value is the answer, including when
     * that answer is "none": re-suggesting the business site to a branch
     * somebody had deliberately cleared would undo the clearing every time
     * the form was opened.
     */
    $defaultWebsite = $location ? null : $formTenant?->website;
@endphp

{{-- ------------------------------------------ 1. location information --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('locations.cards.information') }}</h2>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    <div class="sm:col-span-2">
      <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.name') }} <span class="text-danger">*</span>
      </label>
      {{-- The rules sit on the fields and resources/js/live-validation.js
           reads them, so a blank is answered beside the box while the form is
           being filled in rather than after a submission the reader has to
           redo. The server checks the same things again. --}}
      <input id="name" name="name" type="text" class="sd-input" data-capitalize required
             data-rules="required|max:255"
             placeholder="{{ __('locations.placeholders.name') }}" value="{{ $locationValue('name') }}" autofocus>
      <p data-error-for="name" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('name')) hidden @endunless>{{ $errors->first('name') }}</p>
    </div>

    <div>
      <label for="code" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.code') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      {{-- Checked against this business's other branches while it is typed:
           the code is what tells two of them apart on a receipt, so finding
           out it is taken after pressing Save is finding out too late. --}}
      <input id="code" name="code" type="text" class="sd-input" placeholder="{{ __('locations.placeholders.code') }}"
             data-rules="max:20"
             data-remote-check="{{ route('settings.locations.code-in-use', array_filter(['ignore' => $location?->id])) }}"
             value="{{ $locationValue('code') }}">
      <p data-error-for="code" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('code')) hidden @endunless>{{ $errors->first('code') }}</p>
      <p class="mt-1.5 text-[12px] text-sub">{{ __('locations.fields.code_hint') }}</p>
    </div>

    <x-combo name="type" label="{{ __('locations.fields.type') }}" :options="App\Support\LocationOptions::types()"
             :selected="$locationValue('type')" placeholder="{{ __('locations.placeholders.type') }}" />
  </div>

  <div class="pt-4 border-t border-line space-y-3">
    <label class="styledesk_choice">
      <input id="is_primary" name="is_primary" type="checkbox" value="1" class="sd-check mt-0.5" @checked($isPrimary)>
      <span class="styledesk_choice__label min-w-0">
        <span class="block text-[13px] font-medium text-ink">{{ __('locations.fields.primary') }}</span>
        {{-- Says what happens rather than forbidding it. The controller
             demotes the previous primary, so the honest wording is what it
             will do, not a rule the user has to enforce themselves. --}}
        <span class="block text-[12px] text-sub">{{ __('locations.fields.primary_hint') }}</span>
      </span>
    </label>
  </div>

  <fieldset class="pt-4 border-t border-line">
    <legend class="text-[13px] font-medium text-ink mb-2">{{ __('locations.fields.status') }} <span class="text-danger">*</span></legend>
    <div class="styledesk_choicelist">
      @foreach (App\Support\LocationOptions::statuses() as $value => $statusLabel)
        <label class="styledesk_choice">
          <input type="radio" name="status" value="{{ $value }}" class="sd-check" @checked($status === $value)>
          <span class="styledesk_choice__label">{{ $statusLabel }}</span>
        </label>
      @endforeach
    </div>
    <p class="mt-1.5 text-[12px] text-sub">
      {{ __('locations.fields.status_hint') }}
    </p>
    @error('status')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
  </fieldset>
</section>

{{-- ---------------------------------------------------- 2. address --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('locations.cards.address') }}</h2>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    <div class="sm:col-span-2">
      <label for="address_line1" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.address_line1') }} <span class="text-danger">*</span>
      </label>
      <input id="address_line1" name="address_line1" type="text" class="sd-input" data-capitalize required
             value="{{ $locationValue('address_line1') }}" data-rules="required|max:255">
      <p data-error-for="address_line1" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('address_line1')) hidden @endunless>{{ $errors->first('address_line1') }}</p>
    </div>

    <div>
      <label for="address_line2" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.address_line2') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      <input id="address_line2" name="address_line2" type="text" class="sd-input" data-capitalize
             value="{{ $locationValue('address_line2') }}" data-rules="max:255">
      <p data-error-for="address_line2" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('address_line2')) hidden @endunless>{{ $errors->first('address_line2') }}</p>
    </div>

    <div>
      <label for="suite" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.suite') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      <input id="suite" name="suite" type="text" class="sd-input" data-capitalize
             value="{{ $locationValue('suite') }}" data-rules="max:60">
      <p data-error-for="suite" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('suite')) hidden @endunless>{{ $errors->first('suite') }}</p>
    </div>

    <div>
      <label for="city" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.city') }} <span class="text-danger">*</span>
      </label>
      <input id="city" name="city" type="text" class="sd-input" data-capitalize required
             value="{{ $locationValue('city') }}" data-rules="required|max:120">
      <p data-error-for="city" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('city')) hidden @endunless>{{ $errors->first('city') }}</p>
    </div>

    {{-- The region list follows the country chosen below, and countries differ
         on whether they have one. A country with regions gets the searchable
         list; one without gets a free-text box, which is the honest control
         for a place with no meaningful subdivision — Singapore, Hong Kong.

         Both are rendered and one is disabled rather than swapped in and out.
         A disabled control does not post, so exactly one state value ever
         reaches the server, and the list is never torn down and rebuilt. --}}
    @php
      $stateValue = $locationValue('state');
      $stateCountry = $locationValue('country', $defaultCountry);
      $countryRegions = $regions[$stateCountry] ?? [];
    @endphp

    <div data-state-field>
      <div data-state-combo @unless ($countryRegions) hidden @endunless>
        <x-combo name="state" label="{{ __('locations.fields.state') }}" required
                 :options="$countryRegions"
                 :selected="$countryRegions ? $stateValue : null"
                 placeholder="{{ __('locations.placeholders.state') }}" rules="required" />
      </div>

      <div data-state-text @if ($countryRegions) hidden @endif>
        <label for="state" class="block text-[13px] font-medium text-ink mb-1.5">
          {{ __('locations.fields.state') }} <span class="text-danger">*</span>
        </label>
        {{-- Plain `state`, not `state_text`: live validation paints into
             [data-error-for="<the field's id>"], and the combo's own input is
             `state-value`, so the two cannot collide. --}}
        <input id="state" name="state" type="text" class="sd-input" data-capitalize
               value="{{ $countryRegions ? '' : $stateValue }}"
               data-rules="required|max:120" @disabled((bool) $countryRegions)>
        <p data-error-for="state" role="alert" class="mt-1.5 text-[12px] text-danger"
           @unless ($errors->has('state')) hidden @endunless>{{ $errors->first('state') }}</p>
      </div>
    </div>

    <div>
      <label for="postal_code" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.postal_code') }} <span class="text-danger">*</span>
      </label>
      <input id="postal_code" name="postal_code" type="text" class="sd-input" required
             value="{{ $locationValue('postal_code') }}" data-rules="required|max:20">
      <p data-error-for="postal_code" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('postal_code')) hidden @endunless>{{ $errors->first('postal_code') }}</p>
    </div>

    {{-- The country is editable here, unlike in onboarding.
         Onboarding refuses it because the business's own country is being
         established on that screen; a second branch may genuinely be in
         another one, and a group with sites across a border is the ordinary
         case this module exists for. --}}
    <x-combo name="country" label="{{ __('locations.fields.country') }}" required :options="$countries"
             :selected="$locationValue('country', $defaultCountry)"
             placeholder="{{ __('locations.placeholders.country') }}" />

    <x-combo name="timezone" label="{{ __('locations.fields.timezone') }}" required :options="$timezones"
             :selected="$locationValue('timezone', $defaultTimezone)" placeholder="{{ __('locations.placeholders.timezone') }}"
             hint="{{ __('locations.fields.timezone_hint') }}" />
  </div>
</section>

{{-- --------------------------------------------- 3. location manager --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('locations.cards.manager') }}</h2>
  <p class="text-[13px] text-sub">
    {{ __('locations.cards.manager_hint') }}
  </p>

  @if ($staffChoices->isEmpty())
    <p class="text-[13px] text-sub">
      {{ __('locations.no_active_staff') }}
      <a href="{{ route('settings.staff.create') }}" class="text-link hover:underline">{{ __('locations.no_active_staff_link') }}</a>
      {{ __('locations.no_active_staff_tail') }}
    </p>
  @else
    <x-combo name="manager_staff_id" label="{{ __('locations.fields.manager') }}" :options="$staffChoices"
             :selected="$locationValue('manager_staff_id')" placeholder="{{ __('locations.placeholders.manager') }}" />

    <fieldset>
      <legend class="text-[13px] font-medium text-ink mb-2">
        {{ __('locations.fields.assistants') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </legend>
      <div class="styledesk_choicelist sm:grid-cols-2">
        @foreach ($staffOptions as $member)
          <label class="styledesk_choice">
            <input type="checkbox" name="assistant_manager_ids[]" value="{{ $member->id }}" class="sd-check"
                   @checked(in_array((string) $member->id, $chosenAssistants, true))>
            <span class="styledesk_choice__label">{{ $member->displayName() }}</span>
          </label>
        @endforeach
      </div>
      <p class="mt-1.5 text-[12px] text-sub">{{ __('locations.fields.assistants_hint') }}</p>
    </fieldset>
  @endif
</section>

{{-- ---------------------------------------------- 4. contact details --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('locations.cards.contact') }}</h2>
  <p class="text-[13px] text-sub">
    {{ __('locations.cards.contact_hint') }}
  </p>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    <div>
      <label for="phone" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.phone') }} <span class="text-danger" aria-hidden="true">*</span>
      </label>

      {{-- The dialling code beside the number, not typed into it: the same
           control as the client, staff and business screens. phone.js formats
           as you type and writes the country into the hidden input, which is
           what the column beside the number has always wanted. --}}
      <div class="relative" data-phone
           data-phone-country="{{ $locationValue('phone_country') ?: $locationValue('country') ?: 'US' }}">
        <div class="sd-phone">
          <button type="button" class="sd-phone__country" data-phone-toggle
                  aria-haspopup="listbox" aria-expanded="false"
                  aria-label="{{ __('locations.fields.phone') }}">
            <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
            <span class="font-medium" data-phone-code>+1</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <input id="phone" name="phone" type="tel" class="sd-phone__field"
                 data-phone-input data-rules="required|phone" autocomplete="tel-national"
                 required value="{{ $locationValue('phone') }}">
        </div>
        <div class="sd-pop" data-phone-pop hidden></div>
        <input type="hidden" name="phone_country" data-phone-country-value
               value="{{ $locationValue('phone_country') ?: $locationValue('country') ?: 'US' }}">
      </div>

      <p data-error-for="phone" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('phone')) hidden @endunless>{{ $errors->first('phone') }}</p>
    </div>

    <div>
      <label for="phone_secondary" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.phone_secondary') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>

      {{-- The dialling code beside the number, not typed into it: the same
           control as the client, staff and business screens. phone.js formats
           as you type and writes the country into the hidden input, which is
           what the column beside the number has always wanted. --}}
      <div class="relative" data-phone
           data-phone-country="{{ $locationValue('phone_secondary_country') ?: $locationValue('country') ?: 'US' }}">
        <div class="sd-phone">
          <button type="button" class="sd-phone__country" data-phone-toggle
                  aria-haspopup="listbox" aria-expanded="false"
                  aria-label="{{ __('locations.fields.phone_secondary') }}">
            <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
            <span class="font-medium" data-phone-code>+1</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <input id="phone_secondary" name="phone_secondary" type="tel" class="sd-phone__field"
                 data-phone-input data-rules="phone" autocomplete="tel-national"
                  value="{{ $locationValue('phone_secondary') }}">
        </div>
        <div class="sd-pop" data-phone-pop hidden></div>
        <input type="hidden" name="phone_secondary_country" data-phone-country-value
               value="{{ $locationValue('phone_secondary_country') ?: $locationValue('country') ?: 'US' }}">
      </div>

      <p data-error-for="phone_secondary" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('phone_secondary')) hidden @endunless>{{ $errors->first('phone_secondary') }}</p>
    </div>

    <div>
      <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.email') }} <span class="text-danger" aria-hidden="true">*</span>
      </label>
      <input id="email" name="email" type="email" class="sd-input" autocomplete="email"
             data-rules="required|email|max:255" required value="{{ $locationValue('email') }}">
      <p data-error-for="email" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('email')) hidden @endunless>{{ $errors->first('email') }}</p>
    </div>

    <div>
      <label for="booking_email" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.booking_email') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      <input id="booking_email" name="booking_email" type="email" class="sd-input" autocomplete="email"
             data-rules="email|max:255"  value="{{ $locationValue('booking_email') }}">
      <p data-error-for="booking_email" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('booking_email')) hidden @endunless>{{ $errors->first('booking_email') }}</p>
    </div>

    <div>
      <label for="support_email" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.support_email') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      <input id="support_email" name="support_email" type="email" class="sd-input" autocomplete="email"
             data-rules="email|max:255"  value="{{ $locationValue('support_email') }}">
      <p data-error-for="support_email" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('support_email')) hidden @endunless>{{ $errors->first('support_email') }}</p>
    </div>

    <div>
      <label for="website" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.website') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      {{-- The whole address here, scheme included, so the rule is `url` — the
           scheme-and-host pair is the Business Settings pattern and this field
           has always held one string. Checked as it is typed against the same
           shape the server enforces. --}}
      <input id="website" name="website" type="url" class="sd-input" placeholder="https://example.com"
             data-rules="url|max:255" inputmode="url" spellcheck="false" autocapitalize="none"
             value="{{ $locationValue('website', $defaultWebsite) }}">
      <p data-error-for="website" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('website')) hidden @endunless>{{ $errors->first('website') }}</p>
    </div>

    <div>
      <label for="extension" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.extension') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      <input id="extension" name="extension" type="text" class="sd-input" value="{{ $locationValue('extension') }}" data-rules="max:20">
      <p data-error-for="extension" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('extension')) hidden @endunless>{{ $errors->first('extension') }}</p>
    </div>

    <div>
      <label for="contact_person" class="block text-[13px] font-medium text-ink mb-1.5">
        {{ __('locations.fields.contact_person') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
      </label>
      <input id="contact_person" name="contact_person" type="text" class="sd-input" data-capitalize
             value="{{ $locationValue('contact_person') }}" data-rules="max:120">
      <p data-error-for="contact_person" role="alert" class="mt-1.5 text-[12px] text-danger"
         @unless ($errors->has('contact_person')) hidden @endunless>{{ $errors->first('contact_person') }}</p>
    </div>
  </div>
</section>

{{-- ----------------------------------------------- 5. location hours --}}
@php
    /**
     * The same island onboarding step 2 uses, in split-period mode.
     *
     * Not a second hours editor that looks like it. The two screens ask the
     * same question, and two implementations drift — a picker fixed in one
     * place and not the other becomes a business whose hours behave
     * differently depending on which screen they were typed into.
     *
     * `splitPeriods` is what §5 needs and onboarding does not: with it on the
     * rows post hours[day][index][field], which is what syncHours() reads.
     */
    $submittedHours = old('hours');

    $hoursInitial = collect(config('locations.weekdays'))->map(function ($label, $day) use ($submittedHours, $hoursByDay) {
        /**
         * Old input wins, then what is stored.
         *
         * A failed submit must not silently reset the week: someone who split
         * Tuesday and mistyped an email would lose the split and not
         * necessarily notice.
         */
        if (is_array($submittedHours)) {
            $day_ = $submittedHours[$day] ?? [];

            return [
                'is_open' => (bool) ($day_['is_open'] ?? false),
                'periods' => collect($day_)->except('is_open')->map(fn ($period) => [
                    'opens_at' => $period['opens_at'] ?? '',
                    'closes_at' => $period['closes_at'] ?? '',
                ])->values()->all(),
            ];
        }

        $stored = $hoursByDay[$day]['periods'] ?? collect();

        return [
            'is_open' => $stored->isNotEmpty(),
            'periods' => $stored->map(fn ($period) => [
                'opens_at' => $period->timeValue('opens_at'),
                'closes_at' => $period->timeValue('closes_at'),
            ])->values()->all(),
        ];
    })->values()->all();

    /**
     * Validation messages gathered per day.
     *
     * Validation keys are concrete — hours.1.0.closes_at — so a day's messages
     * are collected by prefix rather than looked up by a wildcard, which
     * matches nothing.
     */
    $hoursErrors = [];

    foreach ($errors->messages() as $key => $messages) {
        if (str_starts_with($key, 'hours.')) {
            $hoursErrors[(int) explode('.', $key)[1]] ??= $messages[0];
        }
    }

    $hoursProps = [
        'initial' => $hoursInitial,
        'splitPeriods' => true,
        // The business's own 12/24-hour choice, so every screen agrees.
        'use12Hours' => App\Support\TimeFormat::use12Hours(),
        'days' => array_values(App\Support\LocationOptions::weekdays()),
        'title' => __('locations.hours_card'),
        'description' => __('locations.hours_card_hint'),
        'errors' => (object) $hoursErrors,
        'labels' => __('locations.hours_editor'),
    ];
@endphp

<div data-vue-component="BusinessHours" data-props='@json($hoursProps)'></div>
