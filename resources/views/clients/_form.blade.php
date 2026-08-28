{{--
    The client form's fields, shared by add and edit.

    Built from the business's own configuration rather than hard-coded: a
    field switched off in App Settings is not rendered, and one marked
    required carries its asterisk and its rule. Restating the field list here
    would make that settings screen decorative.
--}}
@php
    $client = $client ?? null;

    $fieldValue = fn (string $name, $fallback = null) => old($name, $client?->{$name} ?? $fallback);

    $chosenPreferences = array_map('strval', old('preferences', $client?->preferences->pluck('id')->all() ?? []));
    $chosenTags = array_map('strval', old('tags', $client?->tags->pluck('id')->all() ?? []));

    // Which fields the business collects, keyed so the markup below can ask
    // about one without scanning the list each time.
    $enabled = collect($fields)->keyBy('key');

    /**
     * The contact rows, from whichever source is authoritative right now.
     *
     * Old input wins after a failed save, because the reader is looking at
     * what they typed and a form that quietly reverted to the stored numbers
     * would lose the correction they came back to make. Failing that, the
     * client's own rows; failing that, one empty row, so the card is a field
     * to fill in rather than a button to press first.
     *
     * Keys are tokens rather than positions: removing the second of three
     * rows must not renumber the others, or the "primary" radio would end up
     * pointing at a different number than the one that was ticked.
     */
    $phoneRows = collect(old('phones'))
        ->map(fn ($row) => [
            'number' => $row['number'] ?? '',
            'country' => $row['country'] ?? null,
            'type' => $row['type'] ?? 'mobile',
            'priority' => $row['priority'] ?? 'secondary',
        ])
        ->whenEmpty(fn () => collect($client?->phones ?? [])->mapWithKeys(fn ($phone, $i) => ['r'.$i => [
            'number' => $phone->number,
            'country' => $phone->country,
            'type' => $phone->type,
            'priority' => $phone->is_primary ? 'primary' : 'secondary',
        ]]))
        ->whenEmpty(fn () => collect(['r0' => ['number' => '', 'country' => null, 'type' => 'mobile']]));

    $emailRows = collect(old('emails'))
        ->map(fn ($row) => [
            'email' => $row['email'] ?? '',
            'type' => $row['type'] ?? 'personal',
            'priority' => $row['priority'] ?? 'secondary',
        ])
        ->whenEmpty(fn () => collect($client?->emails ?? [])->mapWithKeys(fn ($email, $i) => ['r'.$i => [
            'email' => $email->email,
            'type' => $email->type,
            'priority' => $email->is_primary ? 'primary' : 'secondary',
        ]]))
        ->whenEmpty(fn () => collect(['r0' => ['email' => '', 'type' => 'personal']]));

    /**
     * Which row is primary.
     *
     * The posted rows carry it themselves; the stored ones arrive
     * primary-first, so the first key is the answer. Falling back to the
     * first rather than to none means a record always shows one number as
     * the one to ring, which is what every screen downstream reads.
     */
    $primaryKey = function ($rows) {
        $marked = $rows->search(fn (array $row) => ($row['priority'] ?? null) === 'primary');

        return $marked === false ? $rows->keys()->first() : $marked;
    };

    $primaryPhoneKey = $primaryKey($phoneRows);
    $primaryEmailKey = $primaryKey($emailRows);

    $req = fn (string $key) => ($enabled[$key]['required'] ?? false)
        ? '<span class="text-danger">*</span>'
        : '<span class="text-faint font-normal">'.e(__('common.optional')).'</span>';
@endphp

{{-- ------------------------------------------------------- about --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('clients.module.cards.about') }}</h2>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    @foreach (['first_name', 'last_name', 'preferred_name'] as $key)
      @if ($enabled->has($key))
        <div>
          <label for="{{ $key }}" class="block text-[13px] font-medium text-ink mb-1.5">
            {{ $enabled[$key]['label'] }} {!! $req($key) !!}
          </label>
          <input id="{{ $key }}" name="{{ $key }}" type="text" class="sd-input" data-capitalize
                 value="{{ $fieldValue($key) }}" @if ($key === 'first_name') autofocus @endif>
          @error($key)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
        </div>
      @endif
    @endforeach

    @if ($enabled->has('date_of_birth'))
      {{-- The shared calendar picker, not a native date input: nobody was
           born tomorrow, and the year dropdown puts a birth year one step
           away instead of decades of paging. --}}
      <x-date-field name="date_of_birth"
                    :label="$enabled['date_of_birth']['label']"
                    :required="$enabled['date_of_birth']['required'] ?? false"
                    :optional="! ($enabled['date_of_birth']['required'] ?? false)"
                    :value="old('date_of_birth', $client?->date_of_birth?->toDateString())"
                    :max="now()->subDay()->toDateString()"
                    :min-year="1910"
                    :max-year="now()->year"
                    open-to="1990-01-01"
                    :dialog-label="__('clients.module.choose_birth_date')" />
    @endif

    @if ($enabled->has('gender'))
      <div>
        <label for="gender" class="block text-[13px] font-medium text-ink mb-1.5">
          {{ $enabled['gender']['label'] }} {!! $req('gender') !!}
        </label>
        <input id="gender" name="gender" type="text" class="sd-input" data-capitalize value="{{ $fieldValue('gender') }}">
        @error('gender')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
      </div>
    @endif
  </div>
</section>

{{-- ----------------------------------------------------- contact --}}
@if ($enabled->hasAny(['mobile', 'email', 'address', 'city', 'state', 'postal_code', 'country']))
  <section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('clients.module.cards.contact') }}</h2>

    <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
      @if ($enabled->has('mobile'))
        <x-clients.contacts kind="phone"
                            :label="$enabled['mobile']['label']"
                            :required="$enabled['mobile']['required'] ?? false"
                            :optional="! ($enabled['mobile']['required'] ?? false)"
                            :rows="$phoneRows"
                            :types="App\Support\ClientOptions::phoneTypes()"
                            :primary="$primaryPhoneKey"
                            :country="auth()->user()->tenant?->countryCode() ?? 'US'"
                            :max="config('clients.max_phones')" />
      @endif

      @if ($enabled->has('email'))
        <x-clients.contacts kind="email"
                            :label="$enabled['email']['label']"
                            :required="$enabled['email']['required'] ?? false"
                            :optional="! ($enabled['email']['required'] ?? false)"
                            :rows="$emailRows"
                            :types="App\Support\ClientOptions::emailTypes()"
                            :primary="$primaryEmailKey"
                            :max="config('clients.max_emails')" />
      @endif

      @foreach (['address', 'city', 'state', 'postal_code'] as $key)
        @if ($enabled->has($key))
          <div @if ($key === 'address') class="sm:col-span-2" @endif>
            <label for="{{ $key }}" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ $enabled[$key]['label'] }} {!! $req($key) !!}
            </label>
            <input id="{{ $key }}" name="{{ $key }}" type="text" class="sd-input"
                   @if ($key !== 'postal_code') data-capitalize @endif value="{{ $fieldValue($key) }}">
            @error($key)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
          </div>
        @endif
      @endforeach

      @if ($enabled->has('country'))
        <x-combo name="country" :label="$enabled['country']['label']"
                 :options="config('locations.countries')"
                 :selected="$fieldValue('country', auth()->user()->tenant?->countryCode())"
                 :placeholder="__('locations.placeholders.country')" />
      @endif
    </div>
  </section>
@endif

{{-- ----------------------------------------------------- booking --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('clients.module.cards.booking') }}</h2>

  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
    @if ($enabled->has('preferred_location'))
      <x-combo name="preferred_location" :label="$enabled['preferred_location']['label']"
               :options="$locations->pluck('name', 'id')"
               :selected="old('preferred_location', $client?->preferred_location_id)"
               :placeholder="__('clients.defaults.none')" />
    @endif

    @if ($enabled->has('preferred_staff'))
      <x-combo name="preferred_staff" :label="$enabled['preferred_staff']['label']"
               :options="$staff->mapWithKeys(fn ($m) => [$m->id => $m->displayName()])"
               :selected="old('preferred_staff', $client?->preferred_staff_id)"
               :placeholder="__('clients.defaults.none')" />
    @endif

    <x-combo name="status" :label="__('clients.defaults.status')" required
             :options="App\Support\ClientOptions::statuses()"
             :selected="old('status', $client?->status ?? $settings->default_status)" />
  </div>
</section>

{{-- ------------------------------------------- preferences & tags --}}
@if ($preferences->isNotEmpty() || $tags->isNotEmpty())
  <section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('clients.module.cards.preferences') }}</h2>

    @if ($preferences->isNotEmpty())
      <fieldset>
        <legend class="text-[13px] font-medium text-ink mb-2">{{ __('clients.preferences.title') }}</legend>
        <div class="styledesk_choicelist">
          @foreach ($preferences as $preference)
            {{-- Radio when the business allows one preference, checkbox when
                 it allows several: the control should not offer a choice the
                 configuration will refuse. --}}
            <x-choice name="preferences[]" :value="$preference->id"
                      :type="$settings->preferences_multiple ? 'checkbox' : 'radio'"
                      :label="$preference->label"
                      :checked="in_array((string) $preference->id, $chosenPreferences, true)" />
          @endforeach
        </div>
      </fieldset>
    @endif

    @if ($tags->isNotEmpty())
      <fieldset @if ($preferences->isNotEmpty()) class="pt-4 border-t border-line" @endif>
        <legend class="text-[13px] font-medium text-ink mb-2">{{ __('clients.tags.title') }}</legend>
        <div class="styledesk_choicelist">
          @foreach ($tags as $tag)
            <x-choice name="tags[]" :value="$tag->id"
                      :checked="in_array((string) $tag->id, $chosenTags, true)">
              <span class="inline-flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background: {{ $tag->hex() }}" aria-hidden="true"></span>
                {{ $tag->label }}
              </span>
            </x-choice>
          @endforeach
        </div>
      </fieldset>
    @endif
  </section>
@endif

{{-- ------------------------------------ communication & consent --}}
<section class="bg-white border border-line rounded-card p-5 space-y-4">
  <h2 class="text-[15px] font-semibold text-head">{{ __('clients.module.cards.communication') }}</h2>

  @php
      $marketingDefault = $settings->default_marketing === 'in';
  @endphp

  <fieldset>
    <legend class="text-[13px] font-medium text-ink mb-2">{{ __('clients.communication.methods') }}</legend>
    <div class="styledesk_choicelist">
      @foreach (['comm_email' => 'email', 'comm_sms' => 'sms', 'comm_phone' => 'phone'] as $switch => $method)
        @if ($settings->{$switch})
          <x-choice :name="$switch"
                    :label="App\Support\ClientOptions::communicationMethods()[$method]"
                    :checked="(bool) old($switch, $client?->{$switch} ?? true)" />
        @endif
      @endforeach
    </div>
  </fieldset>

  {{-- No rule between the two sets: the cards already separate them, and a
       line drawn across the legend reads as a divider that lost its way. --}}
  <fieldset class="pt-2">
    <legend class="text-[13px] font-medium text-ink mb-2">{{ __('clients.communication.marketing') }}</legend>
    <div class="styledesk_choicelist">
      @foreach (['marketing_email' => 'email', 'marketing_sms' => 'sms'] as $switch => $method)
        {{-- The business decides which marketing channels exist at all; this
             asks about the ones it has switched on. --}}
        @if ($settings->{'comm_'.$switch})
          {{-- Defaults to whatever the business set for a new client, and to
               opted-out when it said "ask" — a consent nobody has given must
               never start ticked. --}}
          <x-choice :name="$switch"
                    :label="App\Support\ClientOptions::communicationMethods()[$method]"
                    :checked="(bool) old($switch, $client?->{$switch} ?? ($client ? false : $marketingDefault))" />
        @endif
      @endforeach
    </div>

    <p class="mt-2 text-[12px] text-sub">{{ __('clients.communication.stored_separately') }}</p>
  </fieldset>
</section>

{{-- ------------------------------------------------------- notes --}}
@if ($enabled->has('notes'))
  <section class="bg-white border border-line rounded-card p-5 space-y-4">
    <h2 class="text-[15px] font-semibold text-head">{{ __('clients.module.cards.notes') }}</h2>

    <div>
      <label for="notes" class="sr-only">{{ $enabled['notes']['label'] }}</label>
      <textarea id="notes" name="notes" rows="4" class="sd-input" data-capitalize>{{ $fieldValue('notes') }}</textarea>
      @error('notes')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
    </div>
  </section>
@endif
