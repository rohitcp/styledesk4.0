@extends('layouts.app')

@section('title', __('hours.title').' — '.$location->name)

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- 760px, matching the Location edit form.
         The hours card is the same island on both screens, so a wider column
         here would stretch its rows and push the pickers further from their
         day label — the same component looking like two. --}}
    <div class="max-w-[760px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.hours.index') }}" class="hover:text-ink transition-colors">{{ __('hours.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $location->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ $location->name }}</h1>
          <p class="text-[14px] text-sub mt-2 leading-relaxed">
            {{ __('hours.timezone_note', [
                'name' => config('locations.timezones.'.$location->timezone, $location->timezone),
                'identifier' => $location->timezone,
            ]) }}
          </p>
        </div>

        <a href="{{ route('settings.hours.index') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      @if ($errors->any())
        <div class="sd-alert sd-alert--danger mt-5" role="alert">
          <p class="min-w-0">{{ __('hours.correct_fields') }}</p>
        </div>
      @endif

      {{-- Which week is on screen. Said before the form, because everything
           below it means something different depending on the answer. --}}
      @if ($isFutureSchedule)
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <div class="flex flex-wrap items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p class="min-w-0 flex-1">
              {{ __('hours.future.editing', ['date' => \Illuminate\Support\Carbon::parse($schedule)->isoFormat('D MMMM Y')]) }}
              <a href="{{ route('settings.hours.edit', $location) }}" class="text-link hover:underline font-medium">{{ __('hours.future.edit_today') }}</a>
            </p>

            <form method="POST" action="{{ route('settings.hours.schedule.destroy', $location) }}"
                  onsubmit="return confirm(@js(__('hours.future.discard_confirm')));">
              @csrf
              @method('DELETE')
              <input type="hidden" name="schedule" value="{{ $schedule }}">
              <button type="submit" class="text-[13px] font-semibold text-danger hover:underline">{{ __('hours.future.discard') }}</button>
            </form>
          </div>
        </div>
      @elseif ($futureSchedules->isNotEmpty())
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <div class="flex items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p class="min-w-0">
              {{ __('hours.future.pending', ['date' => \Illuminate\Support\Carbon::parse($futureSchedules->first())->isoFormat('D MMMM Y')]) }}
              <a href="{{ route('settings.hours.edit', ['location' => $location, 'schedule' => $futureSchedules->first()]) }}"
                 class="text-link hover:underline font-medium">{{ __('hours.future.edit_those') }}</a>
            </p>
          </div>
        </div>
      @endif

      <form id="hoursForm" method="POST" action="{{ route('settings.hours.update', $location) }}" class="mt-6 space-y-5">
        @csrf
        @method('PATCH')

        @php
            /**
             * The same island onboarding and Edit Location mount.
             *
             * Split-period mode, so a day can close for lunch and the rows
             * post hours[day][index][field] — which is what SaveOpeningHours
             * reads from both screens.
             */
            $submittedHours = old('hours');

            $hoursInitial = collect(config('locations.weekdays'))->map(function ($label, $day) use ($submittedHours, $hoursByDay) {
                if (is_array($submittedHours)) {
                    $submittedDay = $submittedHours[$day] ?? [];

                    return [
                        'is_open' => (bool) ($submittedDay['is_open'] ?? false),
                        'periods' => collect($submittedDay)->except('is_open')->map(fn ($period) => [
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

            // Validation keys are concrete — hours.1 from the overlap rule —
            // so a day's messages are collected by prefix rather than looked
            // up by a wildcard, which matches nothing.
            $hoursErrors = [];

            foreach ($errors->messages() as $key => $messages) {
                if (str_starts_with($key, 'hours.')) {
                    $hoursErrors[(int) explode('.', $key)[1]] ??= $messages[0];
                }
            }

            $hoursProps = [
                'initial' => $hoursInitial,
                'splitPeriods' => true,
                'use12Hours' => App\Support\TimeFormat::use12Hours(),
                'days' => array_values(App\Support\LocationOptions::weekdays()),
                // Word for word what the Location edit form says, so the
                // card reads the same wherever it is opened from.
                'title' => __('locations.hours_card'),
                'description' => __('locations.hours_card_hint'),
                'errors' => (object) $hoursErrors,
                'labels' => __('locations.hours_editor'),
            ];
        @endphp

        <div data-vue-component="BusinessHours" data-props='@json($hoursProps)'></div>

        {{-- ------------------------------------------ effective date --}}
        <section class="bg-white border border-line rounded-card p-5 space-y-4">
          <div>
            <h2 class="text-[15px] font-semibold text-head">{{ __('hours.effective.title') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">
              {{ __('hours.effective.hint') }}
            </p>
          </div>

          <div class="sm:max-w-[280px]">
            <label for="effective_from" class="block text-[13px] font-medium text-ink mb-1.5">
              {{ __('hours.effective.label') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
            </label>
            <input id="effective_from" name="effective_from" type="date" class="sd-input"
                   min="{{ now()->addDay()->toDateString() }}"
                   value="{{ old('effective_from', $isFutureSchedule ? $schedule : '') }}">
            @error('effective_from')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
          </div>
        </section>

        {{-- ------------------------------------- apply to other branches --}}
        @if ($otherLocations->isNotEmpty())
          <section class="bg-white border border-line rounded-card p-5 space-y-4">
            <div>
              <h2 class="text-[15px] font-semibold text-head">{{ __('hours.apply.title') }}</h2>
              <p class="text-[13px] text-sub mt-0.5">
                {{ __('hours.apply.hint') }}
              </p>
            </div>

            @php $chosenApply = array_map('strval', old('apply_to', [])); @endphp

            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
              @foreach ($otherLocations as $other)
                <label class="flex items-center gap-2.5 cursor-pointer">
                  <input type="checkbox" name="apply_to[]" value="{{ $other->id }}" class="sd-check"
                         @checked(in_array((string) $other->id, $chosenApply, true))>
                  <span class="text-[13px] text-ink">{{ $other->name }}</span>
                </label>
              @endforeach
            </div>

            {{-- Named plainly rather than left implied. Copying hours is
                 destructive to whatever those branches held, and the person
                 ticking the box should know that before they save. --}}
            <p class="text-[12px] text-sub">
              {{ __('hours.apply.warning') }}
            </p>
          </section>
        @endif

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" id="hoursSave"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors disabled:opacity-60 disabled:pointer-events-none">
            {{ __('hours.save') }}
          </button>
          <a href="{{ route('settings.hours.index') }}"
             class="h-9 px-3.5 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            {{ __('common.cancel') }}
          </a>
        </div>
      </form>

      {{-- ---------------------------------- holidays, closures, special --}}
      <section class="mt-8 bg-white border border-line rounded-card overflow-hidden">
        <div class="px-5 py-4 border-b border-line flex flex-wrap items-start gap-3">
          <div class="min-w-0 flex-1">
            <h2 class="text-[15px] font-semibold text-head">{{ __('hours.exceptions.title') }}</h2>
            <p class="text-[13px] text-sub mt-0.5">
              {{ __('hours.exceptions.hint') }}
            </p>
          </div>

          <button type="button" data-closure-add
                  class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="13" />
            {{ __('hours.exceptions.add') }}
          </button>
        </div>

        @if ($closures->isEmpty())
          <p class="px-5 py-8 text-center text-[13px] text-sub">
            {{ __('hours.exceptions.empty') }}
          </p>
        @else
          <ul class="divide-y divide-line">
            @foreach ($closures as $closure)
              <li class="px-5 py-3.5 flex flex-wrap items-start gap-x-4 gap-y-2">
                <span class="w-[130px] shrink-0">
                  <span class="block text-[13px] font-medium text-head">{{ $closure->dateLabel() }}</span>
                  @if ($closure->isInProgress())
                    <span class="styledesk_badge styledesk_badge--setup mt-1">{{ __('hours.upcoming.in_progress') }}</span>
                  @endif
                </span>

                <span class="min-w-0 flex-1">
                  <span class="block text-[13px] text-ink">{{ $closure->name }}</span>
                  <span class="block text-[12px] text-sub">{{ $closure->typeLabel() }} · {{ $closure->hoursLabel() }}</span>
                  @if ($closure->notes)
                    {{-- Internal. Never shown to clients: "Priya covering
                         reception" is a note to the team. --}}
                    <span class="block text-[12px] text-faint mt-1">{{ $closure->notes }}</span>
                  @endif
                </span>

                <span class="shrink-0 flex items-center gap-2">
                  <button type="button" class="text-[13px] font-medium text-link hover:underline"
                          data-closure-edit
                          data-closure="{{ json_encode([
                              'id' => $closure->id,
                              'type' => $closure->type,
                              'name' => $closure->name,
                              'starts_on' => $closure->starts_on->toDateString(),
                              'ends_on' => $closure->ends_on->toDateString(),
                              'is_closed_all_day' => $closure->is_closed_all_day,
                              'opens_at' => $closure->timeValue('opens_at'),
                              'closes_at' => $closure->timeValue('closes_at'),
                              'notes' => $closure->notes,
                              'action' => route('settings.hours.closures.update', [$location, $closure]),
                          ]) }}">
                    {{ __('common.edit') }}
                  </button>

                  <button type="button" class="text-[13px] font-medium text-danger hover:underline"
                          data-closure-delete
                          data-name="{{ $closure->name }}"
                          data-action="{{ route('settings.hours.closures.destroy', [$location, $closure]) }}">
                    {{ __('common.delete') }}
                  </button>
                </span>
              </li>
            @endforeach
          </ul>
        @endif
      </section>

      {{-- The add/edit form. One form for the whole list rather than one per
           row: the fields are identical and only the action differs. --}}
      <div id="closureDialog" class="styledesk_modal" hidden>
        <div class="styledesk_modal__scrim" data-closure-close></div>

        <div class="styledesk_modal__panel" role="dialog" aria-modal="true" aria-labelledby="closureTitle">
          <div class="styledesk_modal__head">
            <h2 id="closureTitle" class="text-[15px] font-semibold text-head">{{ __('hours.exceptions.add_title') }}</h2>
            <button type="button" class="styledesk_modal__close" data-closure-close aria-label="{{ __('common.close') }}">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
          </div>

          <form id="closureForm" method="POST" action="{{ route('settings.hours.closures.store', $location) }}"
                class="styledesk_modal__body space-y-4">
            @csrf
            <input type="hidden" name="_method" value="POST" data-closure-method>

            <div>
              <label for="closure_type" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('hours.exceptions.type') }} <span class="text-danger">*</span>
              </label>
              <select id="closure_type" name="type" class="sd-input" data-closure-type>
                @foreach (App\Support\ClosureTypes::all() as $value => $meta)
                  <option value="{{ $value }}" data-closes="{{ $meta['closes'] ? '1' : '0' }}"
                          @selected(old('type') === $value)>{{ $meta['label'] }}</option>
                @endforeach
              </select>
              @error('type')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="closure_name" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('hours.exceptions.name') }} <span class="text-danger">*</span>
              </label>
              <input id="closure_name" name="name" type="text" class="sd-input" data-capitalize
                     placeholder="{{ __('hours.exceptions.name_placeholder') }}" value="{{ old('name') }}">
              @error('name')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4">
              <div>
                <label for="closure_starts_on" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('hours.exceptions.from') }} <span class="text-danger">*</span>
                </label>
                <input id="closure_starts_on" name="starts_on" type="date" class="sd-input"
                       value="{{ old('starts_on') }}" data-closure-start>
                @error('starts_on')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>

              <div>
                <label for="closure_ends_on" class="block text-[13px] font-medium text-ink mb-1.5">
                  {{ __('hours.exceptions.to') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
                </label>
                <input id="closure_ends_on" name="ends_on" type="date" class="sd-input"
                       value="{{ old('ends_on') }}" data-closure-end>
                <p class="mt-1.5 text-[12px] text-sub">{{ __('hours.exceptions.to_hint') }}</p>
                @error('ends_on')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
              </div>
            </div>

            <div class="pt-4 border-t border-line space-y-3">
              <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="is_closed_all_day" value="1" class="sd-check mt-0.5"
                       data-closure-closed @checked(old('is_closed_all_day', true))>
                <span class="min-w-0">
                  <span class="block text-[13px] font-medium text-ink">{{ __('hours.exceptions.closed_all_day') }}</span>
                  <span class="block text-[12px] text-sub">{{ __('hours.exceptions.closed_all_day_hint') }}</span>
                </span>
              </label>

              <div class="grid sm:grid-cols-2 gap-x-4 gap-y-4 pl-[26px]" data-closure-times hidden>
                <div>
                  <label for="closure_opens_at" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('hours.exceptions.opens') }}</label>
                  <input id="closure_opens_at" name="opens_at" type="time" class="sd-input" value="{{ old('opens_at') }}">
                  @error('opens_at')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label for="closure_closes_at" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('hours.exceptions.closes') }}</label>
                  <input id="closure_closes_at" name="closes_at" type="time" class="sd-input" value="{{ old('closes_at') }}">
                  @error('closes_at')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
                </div>
              </div>
            </div>

            <div>
              <label for="closure_notes" class="block text-[13px] font-medium text-ink mb-1.5">
                {{ __('hours.exceptions.notes') }} <span class="text-faint font-normal">{{ __('common.optional') }}</span>
              </label>
              <textarea id="closure_notes" name="notes" rows="2" class="sd-input"
                        placeholder="{{ __('hours.exceptions.notes_placeholder') }}">{{ old('notes') }}</textarea>
              @error('notes')<p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
              <button type="submit"
                      class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                {{ __('hours.exceptions.save') }}
              </button>
              <button type="button" data-closure-close
                      class="h-9 px-3.5 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                {{ __('common.cancel') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <form id="closureDeleteForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    (function () {
      var dialog = document.getElementById('closureDialog');
      if (!dialog) return;

      var form = document.getElementById('closureForm');
      var title = document.getElementById('closureTitle');
      var method = form.querySelector('[data-closure-method]');
      var typeField = form.querySelector('[data-closure-type]');
      var closedField = form.querySelector('[data-closure-closed]');
      var times = form.querySelector('[data-closure-times]');
      var storeAction = form.action;

      /* Times are hidden on a full-day closure rather than disabled. A row of
         greyed inputs invites the reader to work out whether they apply. */
      function syncTimes() {
        times.hidden = closedField.checked;
      }

      closedField.addEventListener('change', syncTimes);

      /* Each kind of exception has a usual answer — a public holiday normally
         closes, "special opening hours" by definition does not. Set as a
         starting point, not a rule: a salon open late on Christmas Eve is
         ordinary, and the toggle stays theirs to change. */
      typeField.addEventListener('change', function () {
        var option = typeField.options[typeField.selectedIndex];
        closedField.checked = option.getAttribute('data-closes') === '1';
        syncTimes();
      });

      function open(values) {
        if (values) {
          title.textContent = @json(__('hours.exceptions.edit_title'));
          form.action = values.action;
          method.value = 'PATCH';

          typeField.value = values.type;
          form.querySelector('#closure_name').value = values.name || '';
          form.querySelector('#closure_starts_on').value = values.starts_on || '';
          /* Blanked when it equals the start, so a single day reads as a
             single day rather than as a range that happens to be one long. */
          form.querySelector('#closure_ends_on').value =
            values.ends_on && values.ends_on !== values.starts_on ? values.ends_on : '';
          closedField.checked = !!values.is_closed_all_day;
          form.querySelector('#closure_opens_at').value = values.opens_at || '';
          form.querySelector('#closure_closes_at').value = values.closes_at || '';
          form.querySelector('#closure_notes').value = values.notes || '';
        } else {
          title.textContent = @json(__('hours.exceptions.add_title'));
          form.action = storeAction;
          method.value = 'POST';
          form.reset();
        }

        syncTimes();
        dialog.hidden = false;
        document.body.classList.add('is-locked');
        form.querySelector('#closure_name').focus();
      }

      function close() {
        dialog.hidden = true;
        document.body.classList.remove('is-locked');
      }

      document.querySelector('[data-closure-add]')?.addEventListener('click', function () { open(null); });

      document.querySelectorAll('[data-closure-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
          open(JSON.parse(button.getAttribute('data-closure')));
        });
      });

      document.querySelectorAll('[data-closure-close]').forEach(function (button) {
        button.addEventListener('click', close);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !dialog.hidden) close();
      });

      /* Reopened when the server rejected it, so the messages are read beside
         the fields they are about rather than behind a closed panel. */
      @if ($errors->hasAny(['type', 'name', 'starts_on', 'ends_on', 'opens_at', 'closes_at', 'notes']))
        open(null);
        syncTimes();
      @endif

      /* Delete, behind a confirmation that names the entry. */
      var deleteForm = document.getElementById('closureDeleteForm');

      document.querySelectorAll('[data-closure-delete]').forEach(function (button) {
        button.addEventListener('click', function () {
          /* Built server-side per language: Spanish does not put the name
             where English does, and string addition cannot express that. */
          var confirmText = @json(__('hours.exceptions.delete_confirm', ['name' => '__NAME__']))
            .replace('__NAME__', button.getAttribute('data-name'));

          if (!window.confirm(confirmText)) return;

          deleteForm.action = button.getAttribute('data-action');
          deleteForm.submit();
        });
      });
    }());

    /* One submission. A second POST would write the week twice, and with
       "apply to other locations" ticked that is a second pass over every
       branch. */
    (function () {
      var form = document.getElementById('hoursForm');
      if (!form) return;

      var save = document.getElementById('hoursSave');
      var saving = false;

      form.addEventListener('submit', function (e) {
        if (saving) { e.preventDefault(); return; }
        saving = true;
        save.disabled = true;
        save.textContent = @json(__('common.saving'));
      });
    }());
  </script>
@endpush
