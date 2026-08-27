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
    $loginEnabled = (bool) old('login_enabled', $staff?->login_enabled ?? true);
    $accountStatus = old('account_status', $staff ? ($staff->is_active ? 'active' : 'inactive') : 'active');
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
                     value="{{ $staffValue('first_name') }}" autofocus>
              @error('first_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="last_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.last_name') }} <span class="text-danger">*</span>
              </label>
              <input id="last_name" name="last_name" type="text" class="sd-input" data-capitalize required
                     value="{{ $staffValue('last_name') }}">
              @error('last_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="middle_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.middle_name') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="middle_name" name="middle_name" type="text" class="sd-input" data-capitalize
                     value="{{ $staffValue('middle_name') }}">
            </div>
            <div>
              <label for="preferred_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.preferred_name') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="preferred_name" name="preferred_name" type="text" class="sd-input" data-capitalize
                     placeholder="{{ __('staff.fields.preferred_name_placeholder') }}" value="{{ $staffValue('preferred_name') }}">
            </div>
            <x-combo name="pronouns" label="{{ __('staff.fields.pronouns') }}" :options="App\Support\StaffOptions::pronouns()"
                     :selected="$staffValue('pronouns')" placeholder="{{ __('staff.not_specified') }}" />
            <div>
              <label for="job_title" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('staff.fields.job_title') }}</label>
              <input id="job_title" name="job_title" type="text" class="sd-input" data-capitalize
                     placeholder="{{ __('staff.fields.job_title_placeholder') }}" value="{{ $staffValue('job_title') }}">
            </div>
            <div>
              <label for="employee_ref" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.employee_ref') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="employee_ref" name="employee_ref" type="text" class="sd-input" value="{{ $staffValue('employee_ref') }}">
            </div>
          </div>

          {{-- Its own row: the uploader carries a preview, a progress bar and
               an error line, none of which fit beside another field. --}}
          <x-image-upload name="avatar" label="{{ __('staff.fields.avatar') }}"
                          :endpoint="route('settings.staff.avatar.upload')"
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
              <input id="email" name="email" type="email" class="sd-input" required value="{{ $staffValue('email') }}">
              @error('email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              <p class="mt-1.5 text-[12px] text-sub">{{ __('staff.fields.email_hint') }}</p>
            </div>
            <div>
              <label for="work_email" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.work_email') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="work_email" name="work_email" type="email" class="sd-input" value="{{ $staffValue('work_email') }}">
              @error('work_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
              <label for="phone" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('staff.fields.phone') }}</label>
              <input id="phone" name="phone" type="tel" class="sd-input" value="{{ $staffValue('phone') }}">
            </div>
            <x-combo name="phone_type" label="{{ __('staff.fields.phone_type') }}" :options="App\Support\StaffOptions::phoneTypes()"
                     :selected="$staffValue('phone_type')" placeholder="{{ __('staff.not_specified') }}" />
            <div>
              <label for="secondary_phone" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.secondary_phone') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="secondary_phone" name="secondary_phone" type="tel" class="sd-input" value="{{ $staffValue('secondary_phone') }}">
            </div>
            <div>
              <label for="emergency_contact_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('staff.fields.emergency_contact_name') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <input id="emergency_contact_name" name="emergency_contact_name" type="text" class="sd-input"
                     data-capitalize value="{{ $staffValue('emergency_contact_name') }}">
            </div>
            <div>
              <label for="emergency_contact_phone" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('staff.fields.emergency_contact_phone') }}</label>
              <input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" class="sd-input"
                     value="{{ $staffValue('emergency_contact_phone') }}">
            </div>
            <div>
              <label for="emergency_contact_relationship" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('staff.fields.emergency_contact_relationship') }}</label>
              <input id="emergency_contact_relationship" name="emergency_contact_relationship" type="text"
                     class="sd-input" data-capitalize placeholder="{{ __('staff.fields.relationship_placeholder') }}" value="{{ $staffValue('emergency_contact_relationship') }}">
            </div>
          </div>

          <div>
            <label for="address" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('staff.fields.address') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </label>
            <textarea id="address" name="address" rows="2" class="sd-input" data-capitalize>{{ $staffValue('address') }}</textarea>
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
          </div>

          <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">
              {{ __('staff.fields.specialities') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </legend>
            <div class="flex flex-wrap gap-2">
              @foreach (App\Support\StaffOptions::specialities() as $value => $label)
                <label class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover cursor-pointer transition-colors text-[13px] text-ink">
                  <input type="checkbox" name="specialities[]" value="{{ $value }}" class="sd-check"
                         @checked(in_array((string) $value, $chosenSpecialities, true))>
                  {{ $label }}
                </label>
              @endforeach
            </div>
          </fieldset>

          @if ($services->isNotEmpty())
            <fieldset>
              <legend class="text-[13px] font-medium text-ink mb-2">{{ __('staff.fields.services') }}</legend>
              <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
                @foreach ($services as $service)
                  <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="sd-check"
                           @checked(in_array((string) $service->id, $chosenServices, true))>
                    <span class="text-[13px] text-ink">{{ $service->name }}</span>
                  </label>
                @endforeach
              </div>
              <p class="mt-1.5 text-[12px] text-sub">{{ __('staff.fields.services_hint') }}</p>
            </fieldset>
          @endif
        </section>

        {{-- ---------------------------------------------------- account --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.cards.account') }}</h2>

          <fieldset>
            <legend class="text-[13px] font-medium text-ink mb-2">{{ __('staff.fields.account_status') }} <span class="text-danger">*</span></legend>
            <div class="flex items-center gap-5">
              @foreach (['active' => __('common.active'), 'inactive' => __('common.inactive')] as $value => $label)
                <label class="flex items-center gap-2.5 cursor-pointer">
                  <input type="radio" name="account_status" value="{{ $value }}" class="sd-check"
                         @checked($accountStatus === $value)>
                  <span class="text-[13px] text-ink">{{ $label }}</span>
                </label>
              @endforeach
            </div>
            <p class="mt-1.5 text-[12px] text-sub">{{ __('staff.fields.account_status_hint') }}</p>
          </fieldset>

          <div class="pt-4 border-t border-line space-y-3">
            <label class="flex items-start gap-2.5 cursor-pointer">
              <input id="login_enabled" name="login_enabled" type="checkbox" value="1" class="sd-check mt-0.5"
                     @checked($loginEnabled)>
              <span class="min-w-0">
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
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input id="send_invitation" name="send_invitation" type="checkbox" value="1" class="sd-check mt-0.5"
                       @checked($staffValue('send_invitation', true))>
                <span class="min-w-0">
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

