{{--
    The staff form's fields, shared by create and edit.

    One copy, because two would drift: a field added to the create screen and
    forgotten on the edit screen is invisible until someone tries to change it.

    $staffValue() prefers what was just typed, then the record being edited, then a
    fallback — so a failed submit never loses input, and an untouched edit
    shows what is stored.
--}}
@php
    $staff = $staff ?? null;

    /**
     * Named $staffValue, not $value.
     *
     * Two foreach loops below bind $value as their option key, which would
     * shadow a closure of that name inside exactly the blocks that need it —
     * and the failure would be a form field silently rendering blank.
     */
    $staffValue = fn (string $field, $fallback = null) => old($field, $staff?->{$field} ?? $fallback);

    // Multi-value fields, which old() returns as arrays of strings and the
    // record returns as arrays of ints. Normalised once so the checked()
    // checks below can compare without caring which they got.
    $chosenSpecialities = array_map('strval', old('specialities', $staff?->specialities ?? []));
    $chosenServices = array_map('strval', old('service_ids', $staff?->services->pluck('id')->all() ?? []));
    $chosenResources = array_map('strval', old('resource_ids', $staff?->resources->pluck('id')->all() ?? []));

    /* The chairs in use, plus any this person is already assigned to. Without
       the second half a room retired since the assignment was made would be
       missing from the list, and the chip for it would show a bare id. */
    $staffResourceOptions = $resources->pluck('name', 'id')
        ->union($staff?->resources->pluck('name', 'id') ?? collect());
    $loginEnabled = (bool) old('login_enabled', $staff?->login_enabled ?? true);
    /* On leave is stored on membership_status; is_active only says whether
       the person can be booked, and reading it alone would show someone on
       leave as plain Inactive and quietly demote them on the next save. */
    $accountStatus = old('account_status', $staff
        ? ($staff->membership_status === 'on-leave' ? 'on-leave' : ($staff->is_active ? 'active' : 'inactive'))
        : 'active');
@endphp

        {{-- ---------------------------------------------------- basics --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.cards.basic') }}</h2>

          <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
            <div>
              <label for="first_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.first_name') }} <span class="text-danger">*</span>
              </label>
              <input id="first_name" name="first_name" type="text" class="sd-input" data-capitalize required
                     value="{{ $staffValue('first_name') }}" autofocus data-rules="required|max:100">
              <p data-error-for="first_name" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('first_name')) hidden @endunless>{{ $errors->first('first_name') }}</p>
            </div>
            <div>
              <label for="last_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.last_name') }} <span class="text-danger">*</span>
              </label>
              <input id="last_name" name="last_name" type="text" class="sd-input" data-capitalize required
                     value="{{ $staffValue('last_name') }}" data-rules="required|max:100">
              <p data-error-for="last_name" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('last_name')) hidden @endunless>{{ $errors->first('last_name') }}</p>
            </div>
            <div>
              <label for="middle_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.middle_name') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="middle_name" name="middle_name" type="text" class="sd-input" data-capitalize
                     value="{{ $staffValue('middle_name') }}" data-rules="max:100">
              <p data-error-for="middle_name" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('middle_name')) hidden @endunless>{{ $errors->first('middle_name') }}</p>
            </div>
            <div>
              <label for="preferred_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.preferred_name') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="preferred_name" name="preferred_name" type="text" class="sd-input" data-capitalize
                     placeholder="{{ __('staff.fields.preferred_name_placeholder') }}" value="{{ $staffValue('preferred_name') }}" data-rules="max:100">
              <p data-error-for="preferred_name" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('preferred_name')) hidden @endunless>{{ $errors->first('preferred_name') }}</p>
            </div>
            <x-combo name="pronouns" label="{{ __('staff.fields.pronouns') }}" :options="App\Support\StaffOptions::pronouns()"
                     :selected="$staffValue('pronouns')" placeholder="{{ __('staff.not_specified') }}" />

            {{-- The shared calendar picker, not a native date input: nobody
                 was born tomorrow, and the year dropdown puts a birth year
                 one step away instead of decades of paging. --}}
            <x-date-field name="date_of_birth"
                          :label="__('staff.fields.date_of_birth')"
                          optional
                          :value="old('date_of_birth', $staff?->date_of_birth?->toDateString())"
                          :max="now()->subDay()->toDateString()"
                          :min-year="1910"
                          :max-year="now()->year"
                          open-to="1990-01-01"
                          rules="date" />
            <div>
              <label for="job_title" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('staff.fields.job_title') }}</label>
              <input id="job_title" name="job_title" type="text" class="sd-input" data-capitalize
                     placeholder="{{ __('staff.fields.job_title_placeholder') }}" value="{{ $staffValue('job_title') }}" data-rules="max:100">
              <p data-error-for="job_title" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('job_title')) hidden @endunless>{{ $errors->first('job_title') }}</p>
            </div>
            <div>
              <label for="employee_ref" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.employee_ref') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="employee_ref" name="employee_ref" type="text" class="sd-input" value="{{ $staffValue('employee_ref') }}" data-rules="max:40">
              <p data-error-for="employee_ref" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('employee_ref')) hidden @endunless>{{ $errors->first('employee_ref') }}</p>
            </div>
          </div>

          {{-- Its own row: the uploader carries a preview, a progress bar and
               an error line, none of which fit beside another field. --}}
          <x-image-upload name="avatar" label="{{ __('staff.fields.avatar') }}"
                          :endpoint="\App\Support\StaffSection::route('avatar.upload')"
                          hint="{{ __('staff.fields.avatar_hint') }}" />

          <div>
            <label for="bio" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('staff.fields.bio') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </label>
            <textarea id="bio" name="bio" rows="3" class="sd-input" data-capitalize
                      placeholder="{{ __('staff.fields.bio_placeholder') }}">{{ $staffValue('bio') }}</textarea>
            @error('bio')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
          </div>
        </section>

        {{-- --------------------------------------------------- contact --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.cards.contact') }}</h2>

          <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
            <div>
              <label for="email" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.email') }} <span class="text-danger">*</span>
              </label>
              {{-- The rules sit on the fields and resources/js/live-validation.js
                   reads them, so an address is answered beside the box while it
                   is being typed. The server holds it to the same shape, and
                   the invitation to join is sent here — an address that reaches
                   nobody is a colleague who never arrives. --}}
              <input id="email" name="email" type="email" class="sd-input" required
                     data-rules="required|email|max:255" autocomplete="email"
                     value="{{ $staffValue('email') }}">
              <p data-error-for="email" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('email')) hidden @endunless>{{ $errors->first('email') }}</p>
              <p class="mt-1.5 text-[12px] text-sub">{{ __('staff.fields.email_hint') }}</p>
            </div>
            <div>
              <label for="work_email" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.work_email') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="work_email" name="work_email" type="email" class="sd-input"
                     data-rules="email|max:255" autocomplete="email"
                     value="{{ $staffValue('work_email') }}">
              <p data-error-for="work_email" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('work_email')) hidden @endunless>{{ $errors->first('work_email') }}</p>
            </div>
            <div>
              <label for="phone" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.phone') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>

              {{-- A searchable country list beside the number, not a code
                   typed into it. resources/js/phone.js mounts the prototype's
                   picker: a search box, a filtered listbox and keyboard
                   navigation, formatting the number as it is typed and writing
                   the country into the hidden input. --}}
              <div class="relative" data-phone
                   data-phone-country="{{ $staffValue('phone_country') ?: (auth()->user()->tenant?->country_code ?? 'US') }}">
                <div class="sd-phone">
                  <button type="button" class="sd-phone__country" data-phone-toggle
                          aria-haspopup="listbox" aria-expanded="false"
                          aria-label="{{ __('staff.fields.phone') }}">
                    <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
                    <span class="font-medium" data-phone-code>+1</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <input id="phone" name="phone" type="tel" class="sd-phone__field"
                         data-phone-input data-rules="phone" autocomplete="tel-national"
                         value="{{ $staffValue('phone') }}">
                </div>
                <div class="sd-pop" data-phone-pop hidden></div>
                <input type="hidden" name="phone_country" data-phone-country-value
                       value="{{ $staffValue('phone_country') ?: (auth()->user()->tenant?->country_code ?? 'US') }}">
              </div>

              <p data-error-for="phone" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('phone')) hidden @endunless>{{ $errors->first('phone') }}</p>
            </div>

            <x-combo name="phone_type" label="{{ __('staff.fields.phone_type') }}" :options="App\Support\StaffOptions::phoneTypes()"
                     :selected="$staffValue('phone_type')" placeholder="{{ __('staff.not_specified') }}" />
            <div>
              <label for="secondary_phone" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.secondary_phone') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>

              {{-- A searchable country list beside the number, not a code
                   typed into it. resources/js/phone.js mounts the prototype's
                   picker: a search box, a filtered listbox and keyboard
                   navigation, formatting the number as it is typed and writing
                   the country into the hidden input. --}}
              <div class="relative" data-phone
                   data-phone-country="{{ $staffValue('secondary_phone_country') ?: (auth()->user()->tenant?->country_code ?? 'US') }}">
                <div class="sd-phone">
                  <button type="button" class="sd-phone__country" data-phone-toggle
                          aria-haspopup="listbox" aria-expanded="false"
                          aria-label="{{ __('staff.fields.secondary_phone') }}">
                    <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
                    <span class="font-medium" data-phone-code>+1</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <input id="secondary_phone" name="secondary_phone" type="tel" class="sd-phone__field"
                         data-phone-input data-rules="phone" autocomplete="tel-national"
                         value="{{ $staffValue('secondary_phone') }}">
                </div>
                <div class="sd-pop" data-phone-pop hidden></div>
                <input type="hidden" name="secondary_phone_country" data-phone-country-value
                       value="{{ $staffValue('secondary_phone_country') ?: (auth()->user()->tenant?->country_code ?? 'US') }}">
              </div>

              <p data-error-for="secondary_phone" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('secondary_phone')) hidden @endunless>{{ $errors->first('secondary_phone') }}</p>
            </div>

            <div>
              <label for="emergency_contact_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.emergency_contact_name') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="emergency_contact_name" name="emergency_contact_name" type="text" class="sd-input"
                     data-capitalize value="{{ $staffValue('emergency_contact_name') }}" data-rules="max:120">
              <p data-error-for="emergency_contact_name" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('emergency_contact_name')) hidden @endunless>{{ $errors->first('emergency_contact_name') }}</p>
            </div>
            <div>
              <label for="emergency_contact_phone" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.emergency_contact_phone') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>

              {{-- A searchable country list beside the number, not a code
                   typed into it. resources/js/phone.js mounts the prototype's
                   picker: a search box, a filtered listbox and keyboard
                   navigation, formatting the number as it is typed and writing
                   the country into the hidden input. --}}
              <div class="relative" data-phone
                   data-phone-country="{{ $staffValue('emergency_contact_phone_country') ?: (auth()->user()->tenant?->country_code ?? 'US') }}">
                <div class="sd-phone">
                  <button type="button" class="sd-phone__country" data-phone-toggle
                          aria-haspopup="listbox" aria-expanded="false"
                          aria-label="{{ __('staff.fields.emergency_contact_phone') }}">
                    <span class="sd-phone__flag" data-phone-flag>&#127482;&#127480;</span>
                    <span class="font-medium" data-phone-code>+1</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="text-faint shrink-0" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" class="sd-phone__field"
                         data-phone-input data-rules="phone" autocomplete="tel-national"
                         value="{{ $staffValue('emergency_contact_phone') }}">
                </div>
                <div class="sd-pop" data-phone-pop hidden></div>
                <input type="hidden" name="emergency_contact_phone_country" data-phone-country-value
                       value="{{ $staffValue('emergency_contact_phone_country') ?: (auth()->user()->tenant?->country_code ?? 'US') }}">
              </div>

              <p data-error-for="emergency_contact_phone" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('emergency_contact_phone')) hidden @endunless>{{ $errors->first('emergency_contact_phone') }}</p>
            </div>

            <div>
              <label for="emergency_contact_relationship" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('staff.fields.emergency_contact_relationship') }}</label>
              <input id="emergency_contact_relationship" name="emergency_contact_relationship" type="text"
                     class="sd-input" data-capitalize placeholder="{{ __('staff.fields.relationship_placeholder') }}" value="{{ $staffValue('emergency_contact_relationship') }}" data-rules="max:60">
              <p data-error-for="emergency_contact_relationship" role="alert" class="mt-1.5 text-[12px] text-danger"
                 @unless ($errors->has('emergency_contact_relationship')) hidden @endunless>{{ $errors->first('emergency_contact_relationship') }}</p>
            </div>
          </div>

          <div>
            <label for="address" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('staff.fields.address') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </label>
            <textarea id="address" name="address" rows="2" class="sd-input" data-capitalize
                      data-rules="max:500">{{ $staffValue('address') }}</textarea>
            <p data-error-for="address" role="alert" class="mt-1.5 text-[12px] text-danger"
               @unless ($errors->has('address')) hidden @endunless>{{ $errors->first('address') }}</p>
          </div>
        </section>

        {{-- ------------------------------------------------ employment --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.cards.employment') }}</h2>
          <p class="text-[13px] text-sub">
            {{ __('staff.cards.employment_hint') }}
          </p>

          <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
            {{-- Only roles this user may hand out are listed, per §32. --}}
            <x-combo name="role_id" label="{{ __('staff.fields.role') }}" required :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->label()])"
                     :selected="$staffValue('role_id')" placeholder="{{ __('staff.fields.role_placeholder') }}"
                     hint="{{ __('staff.fields.role_hint') }}" />
            <x-combo name="location_id" label="{{ __('staff.fields.location') }}" :options="$locations->pluck('name', 'id')"
                     :selected="$staffValue('location_id')" placeholder="{{ __('staff.all_locations') }}" />
            <x-combo name="employment_type" label="{{ __('staff.fields.employment_type') }}" :options="App\Support\StaffOptions::employmentTypes()"
                     :selected="$staffValue('employment_type')" placeholder="{{ __('staff.not_specified') }}" />
            <x-combo name="provider_type" label="{{ __('staff.fields.provider_type') }}" :options="App\Support\StaffOptions::providerTypes()"
                     :selected="$staffValue('provider_type')" placeholder="{{ __('staff.not_specified') }}" />

            {{-- Future dates allowed: somebody hired to start next month is
                 added today, and refusing that would mean adding them twice
                 or not at all. --}}
            <x-date-field name="started_on"
                          :label="__('staff.fields.started_on')"
                          optional
                          :value="old('started_on', $staff?->started_on?->toDateString())"
                          :min-year="1980"
                          rules="date" />
          </div>

          <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">
              {{ __('staff.fields.specialities') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </legend>
            <div class="styledesk_choicelist sm:grid-cols-2">
              @foreach (App\Support\StaffOptions::specialities() as $value => $label)
                <label class="styledesk_choice">
                  <input type="checkbox" name="specialities[]" value="{{ $value }}" class="sd-check"
                         @checked(in_array((string) $value, $chosenSpecialities, true))>
                  {{ $label }}
                </label>
              @endforeach
            </div>
          </fieldset>

          {{-- Services and rooms, the two halves of "what can this person
               actually be booked for".

               The searchable multi-select rather than a wall of checkboxes: a
               salon with sixty services rendered sixty boxes, and finding one
               of them meant reading all of them. Same control as everywhere
               else, chips with an x on each. --}}
          @if ($services->isNotEmpty())
            <x-combo name="service_ids" multiple
                     :label="__('staff.fields.services')"
                     :hint="__('staff.fields.services_hint')"
                     :placeholder="__('staff.fields.services_placeholder')"
                     :selected="$chosenServices"
                     :options="$services->pluck('name', 'id')" />
          @endif

          @if ($staffResourceOptions->isNotEmpty())
            <x-combo name="resource_ids" multiple
                     :label="__('staff.fields.resources')"
                     :hint="__('staff.fields.resources_hint')"
                     :placeholder="__('staff.fields.resources_placeholder')"
                     :selected="$chosenResources"
                     :options="$staffResourceOptions" />
          @endif
        </section>

        {{-- ------------------------------------------------ shift rule --}}
        {{-- Drawn only where the business uses shift rules and there is at
             least one active rule to choose. A card offering an empty
             dropdown is a question with no answers, and this form is long
             enough already. --}}
        @if ($shiftRulesOn && $shiftRules->isNotEmpty())
          @php
              /* An inactive rule already on somebody stays on them — it is new
                 assignments it is kept out of — so the current one is added
                 back to the list, or the combo would show a bare id and the
                 next save would silently drop it. */
              $ruleOptions = $shiftRules->pluck('name', 'id');

              if ($staff?->shiftRule && ! $ruleOptions->has($staff->shift_rule_id)) {
                  $ruleOptions = $ruleOptions->put($staff->shift_rule_id, $staff->shiftRule->name);
              }
          @endphp

          <section class="bg-white border border-line rounded-card p-5 space-y-4"
                   data-shift-rule-card
                   data-rule-locations='@json($shiftRuleLocations)'
                   data-mismatch-message="{{ __('staff.validation.shift_rule_unavailable') }}">
            <h2 class="text-[15px] font-semibold text-head">{{ __('staff.cards.shift_rule') }}</h2>
            <p class="text-[13px] text-sub leading-relaxed">{{ __('staff.cards.shift_rule_hint') }}</p>

            {{-- Optional, and its placeholder says so rather than leaving a
                 blank that reads as something forgotten. --}}
            <x-combo name="shift_rule_id" :label="__('staff.fields.shift_rule')"
                     :options="$ruleOptions"
                     :selected="$staffValue('shift_rule_id')"
                     :placeholder="__('staff.fields.no_shift_rule')" />
          </section>
        @endif

        {{-- ---------------------------------------------------- account --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.cards.account') }}</h2>

          <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">{{ __('staff.fields.account_status') }} <span class="text-danger">*</span></legend>
            <div class="styledesk_choicelist">
              @foreach ([
                  'active' => __('common.active'),
                  'inactive' => __('common.inactive'),
                  /* Away, not gone. Kept apart from Inactive because a rota
                     has to tell someone coming back from someone who has
                     left — the record, the services and the room all stay. */
                  'on-leave' => App\Support\StaffOptions::statusLabel('on-leave'),
              ] as $value => $label)
                <label class="styledesk_choice">
                  <input type="radio" name="account_status" value="{{ $value }}" class="sd-check"
                         @checked($accountStatus === $value)>
                  <span class="styledesk_choice__label">{{ $label }}</span>
                </label>
              @endforeach
            </div>
            <p class="mt-1.5 text-[12px] text-sub">{{ __('staff.fields.account_status_hint') }}</p>
          </fieldset>

          <div class="pt-4 border-t border-line space-y-3">
            <label class="styledesk_choice">
              <input id="login_enabled" name="login_enabled" type="checkbox" value="1" class="sd-check mt-0.5"
                     @checked($loginEnabled)>
              <span class="styledesk_choice__label min-w-0">
                <span class="block text-[13px] font-medium text-ink">{{ __('staff.fields.login_enabled') }}</span>
                <span class="block text-[12px] text-sub">{{ __('staff.fields.login_enabled_hint') }}</span>
              </span>
            </label>

            {{-- Creating only.
                 Sending an invitation is an act, not a detail of the record,
                 so it belongs to adding someone rather than to saving their
                 details. On the edit screen the checkbox did nothing at all —
                 update() never reads send_invitation — which is worse than
                 absent: a control that looks like it will do something and
                 does not.

                 Nested under login, because an invitation without a login is
                 an email inviting someone to an account they cannot have. --}}
            @if (! $staff)
            <div data-invite-block class="pl-[26px] space-y-3">
              <label class="styledesk_choice">
                <input id="send_invitation" name="send_invitation" type="checkbox" value="1" class="sd-check mt-0.5"
                       @checked($staffValue('send_invitation', true))>
                <span class="styledesk_choice__label min-w-0">
                  <span class="block text-[13px] font-medium text-ink">{{ __('staff.fields.send_invitation') }}</span>
                  <span class="block text-[12px] text-sub">{{ __('staff.fields.send_invitation_hint') }}</span>
                </span>
              </label>

              <div>
                <label for="invitation_message" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('staff.fields.invitation_message') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <textarea id="invitation_message" name="invitation_message" rows="2" class="sd-input"
                          placeholder="{{ __('staff.fields.invitation_message_placeholder') }}">{{ $staffValue('invitation_message') }}</textarea>
              </div>
            </div>
            @endif
          </div>
        </section>

