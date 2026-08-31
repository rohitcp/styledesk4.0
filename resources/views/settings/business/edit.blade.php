@extends('layouts.app')

@section('title', __('business.edit_title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="styledesk_form">

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
          /**
           * Options come from BusinessProfile, not straight from the config.
           *
           * The config decides which options exist — validation reads the same
           * list — and BusinessProfile decides what each is called. A dropdown
           * whose label is Spanish and whose options are English is the same
           * half-translated screen, one level in.
           */
          $profile = App\Support\BusinessProfile::class;

          $dateFormatProps = $combo('date_format', $profile::dateFormats(), old('date_format', $tenant->date_format), __('business.choose.date_format'));
          /* Preselected rather than left empty: 12-hour is what the app
             actually uses until somebody says otherwise (App\Support\TimeFormat),
             and a blank dropdown claims the business has no clock. */
          $timeFormatProps = $combo('time_format', $profile::timeFormats(), old('time_format', $tenant->time_format ?? App\Support\TimeFormat::DEFAULT), __('business.choose.time_format'));
          $firstDayProps = $combo('first_day_of_week', $profile::firstDayOfWeek(), old('first_day_of_week', $tenant->first_day_of_week), __('business.choose.day'));
          $durationProps = $combo('default_booking_duration', $profile::bookingDurations(), old('default_booking_duration', $tenant->default_booking_duration), __('business.choose.duration'));
          $intervalProps = $combo('default_appointment_interval', $profile::appointmentIntervals(), old('default_appointment_interval', $tenant->default_appointment_interval), __('business.choose.interval'));
          $taxProps = $combo('default_tax_behavior', $profile::taxBehaviors(), old('default_tax_behavior', $tenant->default_tax_behavior), __('business.choose.tax'));
          $assignmentProps = $combo('default_staff_assignment', $profile::staffAssignment(), old('default_staff_assignment', $tenant->default_staff_assignment), __('business.choose.assignment'));
          $sessionProps = $combo('session_timeout_minutes', $profile::sessionTimeouts(), old('session_timeout_minutes', $tenant->session_timeout_minutes), __('business.choose.session_timeout'));
      @endphp

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.business.show') }}" class="hover:text-ink transition-colors">{{ __('business.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('common.edit') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('business.edit_title') }}</h1>
        </div>

        {{-- A link, not a button: going back does not submit anything, so it
             must not sit inside the form or look like it might save. The
             unsaved-changes guard intercepts it. --}}
        <a href="{{ route('settings.business.show') }}" data-back
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>
      <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
        {{ __('business.edit_intro') }}
      </p>

      {{-- The no-JavaScript path still needs somewhere to report a failure;
           with JavaScript the same information arrives as a centred toast plus
           a message under each offending field. --}}
      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('business.correct_fields') }}</p>
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
              <h2 class="text-[15px] font-semibold text-head">{{ __('business.cards.information') }}</h2>

              <div>
                <label for="name" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('business.fields.name') }} <span class="text-danger">*</span>
                </label>
                <input id="name" name="name" type="text" class="sd-input" data-capitalize required
                       value="{{ old('name', $tenant->name) }}">
                @error('name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <div>
                <label for="legal_name" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('business.fields.legal_name') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <input id="legal_name" name="legal_name" type="text" class="sd-input" data-capitalize
                       placeholder="{{ __('business.placeholders.legal_name') }}"
                       value="{{ old('legal_name', $tenant->legal_name) }}">
                @error('legal_name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <fieldset>
                <legend class="text-[13px] font-medium text-ink mb-2">{{ __('business.fields.business_type') }}</legend>
                <div class="flex flex-wrap gap-2">
                  @foreach ($businessTypes as $type)
                    <label class="styledesk_choice">
                      <input type="checkbox" name="business_type_ids[]" value="{{ $type->id }}" class="sd-check"
                             @checked(in_array($type->id, old('business_type_ids', $selectedTypes), false))>
                      {{ $type->label() }}
                    </label>
                  @endforeach
                </div>
              </fieldset>

              <div>
                <label for="business_category" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('business.fields.category') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <input id="business_category" name="business_category" type="text" class="sd-input" data-capitalize
                       placeholder="{{ __('business.placeholders.category') }}" value="{{ old('business_category', $tenant->business_category) }}">
              </div>

              <div>
                <label for="description" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('business.fields.description') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <textarea id="description" name="description" rows="3" class="sd-input" data-capitalize
                          placeholder="{{ __('business.placeholders.description') }}">{{ old('description', $tenant->description) }}</textarea>
                @error('description')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <fieldset>
                <legend class="text-[13px] font-medium text-ink mb-2">{{ __('business.fields.status') }} <span class="text-danger">*</span></legend>
                <div class="flex items-center gap-5">
                  @foreach (['active' => __('common.active'), 'inactive' => __('common.inactive')] as $value => $label)
                    <label class="styledesk_choice">
                      <input type="radio" name="status" value="{{ $value }}" class="sd-check"
                             @checked(old('status', $tenant->status) === $value)>
                      <span class="styledesk_choice__label">{{ $label }}</span>
                    </label>
                  @endforeach
                </div>
                <p class="mt-1.5 text-[12px] text-sub">{{ __('business.hints.inactive') }}</p>
              </fieldset>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">{{ __('business.cards.contact') }}</h2>

              <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
                <div>
                  <label for="business_email" class="block text-[13px] font-medium text-ink mb-1.5">
                    {{ __('business.fields.business_email') }} <span class="text-danger">*</span>
                  </label>
                  <input id="business_email" name="business_email" type="email" class="sd-input" required
                         value="{{ old('business_email', $tenant->business_email) }}">
                  @error('business_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="business_phone" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.business_phone') }}</label>
                  <input id="business_phone" name="business_phone" type="tel" class="sd-input"
                         value="{{ old('business_phone', $tenant->business_phone) }}">
                  @error('business_phone')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="support_email" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.support_email') }}</label>
                  <input id="support_email" name="support_email" type="email" class="sd-input"
                         value="{{ old('support_email', $tenant->support_email) }}">
                  @error('support_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="booking_email" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.booking_email') }}</label>
                  <input id="booking_email" name="booking_email" type="email" class="sd-input"
                         value="{{ old('booking_email', $tenant->booking_email) }}">
                  @error('booking_email')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
              </div>

              <div>
                <label for="website" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.website') }}</label>
                <input id="website" name="website" type="url" class="sd-input" placeholder="https://example.com"
                       value="{{ old('website', $tenant->website) }}">
                @error('website')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">{{ __('business.cards.regional') }}</h2>
              <p class="text-[13px] text-sub">
                {{ __('business.hints.regional') }}
              </p>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.date_format') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($dateFormatProps)'></div>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.time_format') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($timeFormatProps)'></div>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.first_day_of_week') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($firstDayProps)'></div>
              </div>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">{{ __('business.cards.defaults') }}</h2>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.default_booking_duration') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($durationProps)'></div>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.default_appointment_interval') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($intervalProps)'></div>
                <p class="mt-1.5 text-[12px] text-sub">{{ __('business.hints.interval') }}</p>
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.default_tax_behavior') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($taxProps)'></div>
              </div>

              <div>
                <label for="default_tax_rate" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('business.fields.default_tax_rate') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <input id="default_tax_rate" name="default_tax_rate" type="text" inputmode="decimal" class="sd-input max-w-[160px]"
                       placeholder="0" value="{{ old('default_tax_rate', $tenant->default_tax_rate) }}">
                <p class="mt-1.5 text-[12px] text-sub">{{ __('business.hints.tax_rate') }}</p>
                @error('default_tax_rate')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.default_staff_assignment') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($assignmentProps)'></div>
              </div>
            </section>

            {{-- Where money is asked for when it is not handed over at the
                 desk. These are read out to a client at the till, so they are
                 kept as the business writes them rather than reformatted. --}}
            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">{{ __('business.cards.payments') }}</h2>
              <p class="text-[13px] text-sub -mt-2">{{ __('business.cards.payments_hint') }}</p>

              @foreach (['paypal_handle', 'zelle_handle', 'cash_app_handle', 'venmo_handle'] as $handle)
                <div>
                  <label for="{{ $handle }}" class="block text-[13px] font-medium text-ink mb-1.5">
                    {{ __('business.fields.'.$handle) }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                  </label>
                  <input id="{{ $handle }}" name="{{ $handle }}" type="text" class="sd-input"
                         placeholder="{{ __('business.placeholders.'.$handle) }}"
                         value="{{ old($handle, $tenant->{$handle}) }}">
                  @error($handle)<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
              @endforeach
            </section>

            {{-- Security. One setting today; it is a card of its own because
                 a session policy is not a booking default and would be looked
                 for under neither. --}}
            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">{{ __('business.cards.security') }}</h2>

              <div>
                <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('business.fields.session_timeout') }}</span>
                <div data-vue-component="MultiSelect" data-props='@json($sessionProps)'></div>
                <p class="mt-1.5 text-[12px] text-sub">{{ __('business.hints.session_timeout') }}</p>
              </div>
            </section>

            <section class="bg-white border border-line rounded-card p-5 space-y-4">
              <h2 class="text-[15px] font-semibold text-head">{{ __('business.cards.presence') }}</h2>

              @foreach ([
                  'instagram_url' => [__('business.fields.instagram'), 'https://instagram.com/yourbusiness'],
                  'facebook_url' => [__('business.fields.facebook'), 'https://facebook.com/yourbusiness'],
                  'tiktok_url' => [__('business.fields.tiktok'), 'https://tiktok.com/@yourbusiness'],
                  'google_business_url' => [__('business.fields.google_business'), 'https://g.page/yourbusiness'],
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
          <button type="submit" id="businessSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('common.save_changes') }}
          </button>
          <a href="{{ route('settings.business.show') }}" data-cancel
             class="styledesk_action">
            {{ __('common.cancel') }}
          </a>
        </div>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    (function () {
      var form = document.getElementById('businessForm');
      if (!form) return;

      var save = document.getElementById('businessSave');

      /* ---- unsaved changes -------------------------------------------------
         Compared against a snapshot rather than tracked with a dirty flag: a
         flag set on the first keystroke still fires after someone types a
         character and deletes it again. */
      function snapshot() {
        return new URLSearchParams(new FormData(form)).toString();
      }

      /* Taken after the Vue islands have mounted their hidden inputs. Before
         that the combos contribute nothing, and every one of them would read
         as a change the moment it appeared. */
      var initial = null;
      window.setTimeout(function () { initial = snapshot(); }, 400);

      var leaving = false;

      function isDirty() {
        return initial !== null && !leaving && snapshot() !== initial;
      }

      window.addEventListener('beforeunload', function (e) {
        if (!isDirty()) return;
        e.preventDefault();
        e.returnValue = '';
      });

      /* Back is a link, so beforeunload would cover it — but only in the
         browser's own wording. Asking here lets the question name what is at
         stake. */
      var back = document.querySelector('[data-back]');
      if (back) {
        back.addEventListener('click', function (e) {
          if (!isDirty()) return;
          if (window.confirm('You have unsaved changes. Leave without saving?')) {
            leaving = true;
            return;
          }
          e.preventDefault();
        });
      }

      /* ---- per-field errors ------------------------------------------------
         Laravel keys errors by field, including nested ones like
         business_type_ids.0. The message belongs under the control it is
         about; a single banner at the top of a long form is a message about
         something the reader cannot see. */
      function clearFieldErrors() {
        form.querySelectorAll('[data-field-error]').forEach(function (el) { el.remove(); });
        form.querySelectorAll('.is-invalid').forEach(function (el) {
          el.classList.remove('is-invalid');
          el.removeAttribute('aria-invalid');
        });
      }

      function fieldElement(name) {
        var base = name.split('.')[0];

        return form.querySelector('[name="' + base + '"]')
            || form.querySelector('[name="' + base + '[]"]')
            /* Combos post through a hidden input, so the visible control is
               its sibling rather than the named element itself. */
            || form.querySelector('[name="' + base + '"] , [name="' + base + '"]');
      }

      function showFieldErrors(errors) {
        clearFieldErrors();

        var firstEl = null;

        Object.keys(errors).forEach(function (name) {
          var el = fieldElement(name);
          if (!el) return;

          /* A hidden input has nothing to outline, so the message and the
             highlight go on the wrapper the user can actually see. */
          var anchor = el.type === 'hidden' ? el.parentElement : el;
          var container = anchor.closest('div') || anchor.parentElement;

          if (anchor.classList) {
            anchor.classList.add('is-invalid');
            anchor.setAttribute('aria-invalid', 'true');
          }

          var p = document.createElement('p');
          p.className = 'mt-1.5 text-[12px] text-danger';
          p.setAttribute('data-field-error', '');
          p.textContent = errors[name][0];
          container.appendChild(p);

          if (!firstEl) firstEl = container;
        });

        if (firstEl) {
          firstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          var focusable = firstEl.querySelector('input:not([type=hidden]), textarea, button');
          if (focusable) focusable.focus({ preventScroll: true });
        }
      }

      function toast(message, type) {
        if (window.styledesk && window.styledesk.toast) {
          window.styledesk.toast(message, type);
        } else {
          window.alert(message);   // last resort; the message must reach them
        }
      }

      /* ---- save ------------------------------------------------------------
         Submitted over fetch so a failure keeps the page and everything typed
         into it. Success follows the address the server returns, landing on
         the read-only view where the confirmation is already in the session —
         so the success message can only appear after the database write
         actually committed. */
      var saving = false;

      form.addEventListener('submit', function (e) {
        if (!window.fetch) return;          // no fetch: plain form post
        e.preventDefault();

        if (saving) return;                 // no duplicate saves in flight
        saving = true;
        save.disabled = true;
        save.textContent = @json(__('common.saving'));
        clearFieldErrors();

        fetch(form.action, {
          method: 'POST',                   // _method=PATCH travels in the body
          body: new FormData(form),
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin'
        }).then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (body) {
            return { response: response, body: body };
          });
        }).then(function (result) {
          if (result.response.ok && result.body.redirect) {
            leaving = true;                 // a saved form is not unsaved work
            window.location.href = result.body.redirect;
            return;                         // stays disabled through the nav
          }

          if (result.response.status === 422) {
            showFieldErrors(result.body.errors || {});
            toast('Please correct the highlighted fields and try again.', 'danger');
          } else if (result.response.status === 500) {
            /* The server's own words are logged, never shown: they can name
               tables, hosts and credentials. */
            toast("We couldn't save your changes right now. Please try again.", 'danger');
          } else {
            toast('Unable to save business settings. Your changes were not saved. Please try again.', 'danger');
          }

          reset();
        }).catch(function () {
          toast('Unable to save business settings. Your changes were not saved. Please try again.', 'danger');
          reset();
        });
      });

      function reset() {
        saving = false;
        save.disabled = false;
        save.textContent = @json(__('common.save_changes'));
      }
    }());
  </script>
@endpush
