@extends('layouts.app')

@section('title', 'Edit business')

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[760px]">

      @php
          $opts = config('business_profile');

          /**
           * Every combo on this page is a MultiSelect island in single mode.
           * Options come from config so the form and the read-only view read
           * the same list; a select whose options drift from the display map
           * shows a stored value as blank.
           *
           * Keys are cast to string because a JSON object key is always a
           * string, and MultiSelect compares them with includes().
           */
          $combo = function (string $name, array $options, $current, string $placeholder) {
              return [
                  'options' => collect($options)->mapWithKeys(fn ($v, $k) => [(string) $k => $v])->all(),
                  'modelValue' => $current === null || $current === '' ? [] : [(string) $current],
                  'name' => $name,
                  'single' => true,
                  'placeholder' => $placeholder,
                  'ariaLabel' => $placeholder,
              ];
          };

          /**
           * Each combo's props, built here rather than inline.
           *
           * @json() cannot take a call with nested parentheses: Blade's
           * directive parser counts brackets rather than parsing PHP, so it
           * closes the directive at the first inner ")" and the rest of the
           * expression becomes stray template text.
           */
          $dateFormatProps = $combo('date_format', $opts['date_formats'], old('date_format', $tenant->date_format), 'Choose a date format');
          $timeFormatProps = $combo('time_format', $opts['time_formats'], old('time_format', $tenant->time_format), 'Choose a time format');
          $firstDayProps = $combo('first_day_of_week', $opts['first_day_of_week'], old('first_day_of_week', $tenant->first_day_of_week), 'Choose a day');
          $durationProps = $combo('default_booking_duration', $opts['booking_durations'], old('default_booking_duration', $tenant->default_booking_duration), 'Choose a duration');
          $intervalProps = $combo('default_appointment_interval', $opts['appointment_intervals'], old('default_appointment_interval', $tenant->default_appointment_interval), 'Choose an interval');
          $taxProps = $combo('default_tax_behavior', $opts['tax_behaviors'], old('default_tax_behavior', $tenant->default_tax_behavior), 'Choose tax behaviour');
          $assignmentProps = $combo('default_staff_assignment', $opts['staff_assignment'], old('default_staff_assignment', $tenant->default_staff_assignment), 'Choose an assignment rule');
      @endphp

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.business.show') }}" class="hover:text-ink transition-colors">Business</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Edit</span>
      </nav>

      <h1 class="mt-3 text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Edit business</h1>
      <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
        Company-level details. Locations, hours, currencies and booking rules have their own settings pages.
      </p>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ $errors->first() }}</p>
        </div>
      @endif

      <form id="businessForm" method="POST" action="{{ route('settings.business.update') }}" class="mt-6">
        @csrf
        @method('PATCH')

        {{-- One column. A form is read and filled top to bottom, so two
             columns ask the eye to jump back up for the second half — and the
             sections are not independent: Regional settings and Business
             defaults only make sense after the identity above them. --}}
        <div class="space-y-5">

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">Business information</h2>

              <div>
                <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">
                  Business name <span class="text-danger">*</span>
                </label>
                <input id="name" name="name" type="text" class="sd-input" data-capitalize required
                       value="{{ old('name', $tenant->name) }}">
                @error('name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <div>
                <label for="legal_name" class="block text-[13px] font-medium text-ink mb-1.5">
                  Legal business name <span class="text-faint font-normal">(optional)</span>
                </label>
                <input id="legal_name" name="legal_name" type="text" class="sd-input" data-capitalize
                       placeholder="As registered, if different from the trading name"
                       value="{{ old('legal_name', $tenant->legal_name) }}">
                @error('legal_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <fieldset>
                <legend class="text-[13px] font-medium text-ink mb-2">Business type</legend>
                <div class="flex flex-wrap gap-2">
                  @foreach ($businessTypes as $type)
                    <label class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover cursor-pointer transition-colors text-[13px] text-ink">
                      <input type="checkbox" name="business_type_ids[]" value="{{ $type->id }}" class="sd-check"
                             @checked(in_array($type->id, old('business_type_ids', $selectedTypes), false))>
                      {{ $type->name }}
                    </label>
                  @endforeach
                </div>
              </fieldset>

              <div>
                <label for="business_category" class="block text-[13px] font-medium text-ink mb-1.5">
                  Category / specialisation <span class="text-faint font-normal">(optional)</span>
                </label>
                <input id="business_category" name="business_category" type="text" class="sd-input" data-capitalize
                       placeholder="Curly hair specialists" value="{{ old('business_category', $tenant->business_category) }}">
              </div>

              <div>
                <label for="description" class="block text-[13px] font-medium text-ink mb-1.5">
                  Description <span class="text-faint font-normal">(optional)</span>
                </label>
                <textarea id="description" name="description" rows="3" class="sd-input h-auto py-2" data-capitalize
                          placeholder="A sentence clients will read on your booking page.">{{ old('description', $tenant->description) }}</textarea>
                @error('description')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <fieldset>
                <legend class="text-[13px] font-medium text-ink mb-2">Status <span class="text-danger">*</span></legend>
                <div class="flex items-center gap-5">
                  @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                    <label class="flex items-center gap-2.5 cursor-pointer">
                      <input type="radio" name="status" value="{{ $value }}" class="sd-check"
                             @checked(old('status', $tenant->status) === $value)>
                      <span class="text-[13px] text-ink">{{ $label }}</span>
                    </label>
                  @endforeach
                </div>
                <p class="mt-1.5 text-[12px] text-sub">An inactive business is hidden from public booking.</p>
              </fieldset>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">Contact information</h2>

              <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
                <div>
                  <label for="business_email" class="block text-[13px] font-medium text-ink mb-1.5">
                    Primary email <span class="text-danger">*</span>
                  </label>
                  <input id="business_email" name="business_email" type="email" class="sd-input" required
                         value="{{ old('business_email', $tenant->business_email) }}">
                  @error('business_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="business_phone" class="block text-[13px] font-medium text-ink mb-1.5">Primary phone</label>
                  <input id="business_phone" name="business_phone" type="tel" class="sd-input"
                         value="{{ old('business_phone', $tenant->business_phone) }}">
                  @error('business_phone')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="support_email" class="block text-[13px] font-medium text-ink mb-1.5">Support email</label>
                  <input id="support_email" name="support_email" type="email" class="sd-input"
                         value="{{ old('support_email', $tenant->support_email) }}">
                  @error('support_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="booking_email" class="block text-[13px] font-medium text-ink mb-1.5">Booking contact email</label>
                  <input id="booking_email" name="booking_email" type="email" class="sd-input"
                         value="{{ old('booking_email', $tenant->booking_email) }}">
                  @error('booking_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
              </div>

              <div>
                <label for="website" class="block text-[13px] font-medium text-ink mb-1.5">Website</label>
                <input id="website" name="website" type="url" class="sd-input" placeholder="https://example.com"
                       value="{{ old('website', $tenant->website) }}">
                @error('website')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>
            </section>

            <section class="bg-white border border-line rounded-card p-5">
              <h2 class="text-[15px] font-semibold text-head">Business address</h2>
              <p class="text-[13px] text-sub mt-1.5 leading-relaxed">
                Addresses belong to locations, so they are edited there — a business with three branches has three
                addresses and no single one to put here.
              </p>
              <a href="{{ route('settings.index') }}" class="inline-flex items-center h-9 px-3.5 mt-3 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                Manage locations →
              </a>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">Regional settings</h2>
              <p class="text-[13px] text-sub">
                Languages, currencies and time zone are set in their own modules.
              </p>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">Date format</span>
                <div data-vue-component="MultiSelect" data-props='@json($dateFormatProps)'></div>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">Time format</span>
                <div data-vue-component="MultiSelect" data-props='@json($timeFormatProps)'></div>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">First day of week</span>
                <div data-vue-component="MultiSelect" data-props='@json($firstDayProps)'></div>
              </div>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">Business defaults</h2>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">Default booking duration</span>
                <div data-vue-component="MultiSelect" data-props='@json($durationProps)'></div>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">Default appointment interval</span>
                <div data-vue-component="MultiSelect" data-props='@json($intervalProps)'></div>
                <p class="mt-1.5 text-[12px] text-sub">The grid booking start times snap to.</p>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">Default tax behaviour</span>
                <div data-vue-component="MultiSelect" data-props='@json($taxProps)'></div>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">Default staff assignment</span>
                <div data-vue-component="MultiSelect" data-props='@json($assignmentProps)'></div>
              </div>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">Business presence</h2>

              @foreach ([
                  'instagram_url' => ['Instagram', 'https://instagram.com/yourbusiness'],
                  'facebook_url' => ['Facebook', 'https://facebook.com/yourbusiness'],
                  'tiktok_url' => ['TikTok', 'https://tiktok.com/@yourbusiness'],
                  'google_business_url' => ['Google Business Profile', 'https://g.page/yourbusiness'],
              ] as $field => [$label, $placeholder])
                <div>
                  <label for="{{ $field }}" class="block text-[13px] font-medium text-ink mb-1.5">{{ $label }}</label>
                  <input id="{{ $field }}" name="{{ $field }}" type="url" class="sd-input"
                         placeholder="{{ $placeholder }}" value="{{ old($field, $tenant->$field) }}">
                  @error($field)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
              @endforeach
            </section>
        </div>

        <div class="flex flex-wrap items-center gap-3 mt-6">
          <button type="submit"
                  class="h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Save changes
          </button>
          <a href="{{ route('settings.business.show') }}" data-cancel
             class="h-11 px-4 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            Cancel
          </a>
        </div>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* Warn before leaving with unsaved changes.
       Compared against a snapshot rather than tracked with a dirty flag: a
       flag set on the first keystroke fires the warning even when someone
       types a character and deletes it again. */
    (function () {
      var form = document.getElementById('businessForm');
      if (!form) return;

      function snapshot() {
        return new URLSearchParams(new FormData(form)).toString();
      }

      /* Taken after the Vue islands have mounted their hidden inputs — before
         that the combos contribute nothing and every one of them would read as
         a change the moment it appeared. */
      var initial = null;
      window.setTimeout(function () { initial = snapshot(); }, 400);

      var saving = false;
      form.addEventListener('submit', function () { saving = true; });

      window.addEventListener('beforeunload', function (e) {
        if (saving || initial === null || snapshot() === initial) return;
        e.preventDefault();
        e.returnValue = '';
      });
    }());
  </script>
@endpush
