@extends('layouts.app')

@section('title', __('account.preferences.title'))

@section('content')
<x-account.shell current="preferences" :title="__('account.preferences.title')" :intro="__('account.preferences.intro')">

  @php
      $prefs = $user->preferences;
      $profile = App\Support\BusinessProfile::class;
      $blank = __('account.preferences.use_business');

      /**
       * Every combo here carries an empty option, and that option is the
       * feature: "" means "follow the business", which is different from any
       * value the business happens to have chosen today. Picking the
       * business's current value would freeze it, so the blank stays.
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

      /*
       * Language, date and time open on what is actually in force rather than
       * on the blank.
       *
       * These three are what somebody comes to this page to CHECK, and a
       * field reading "Use the business setting" answers a different question
       * from the one they asked — it says where the answer comes from, not
       * what it is. The remaining combos keep the blank, because "follow the
       * business" is a real answer for a timezone in a way it is not for the
       * language the screen is already in.
       */
      $languageProps = $combo(
          'locale',
          $languages,
          old('locale', $user->locale ?? App\Support\Locale::forUser($user)),
          __('account.preferences.language_default'),
      );
      $dateProps = $combo(
          'date_format',
          $profile::dateFormats(),
          old('date_format', $prefs?->date_format ?? App\Support\AccountPreferences::dateFormat($user)),
          $blank,
      );
      $timeProps = $combo(
          'time_format',
          $profile::timeFormats(),
          old('time_format', $prefs?->time_format ?? App\Support\AccountPreferences::timeFormat($user)),
          $blank,
      );
      $zoneProps = $combo('timezone', $timezones, old('timezone', $prefs?->timezone), $blank);
      $dayProps = $combo('first_day_of_week', $profile::firstDayOfWeek(), old('first_day_of_week', $prefs?->first_day_of_week), $blank);
      $viewProps = $combo('calendar_view', __('account.preferences.calendar_views'), old('calendar_view', $prefs?->calendar_view), $blank);

      $toggle = fn (string $key) => (bool) old($key, App\Support\AccountPreferences::calendarToggle($user, $key));

      /**
       * What a combo currently says, in words.
       *
       * Read off the same props the control is built from rather than from
       * the model a second time, so the summary and the field can never
       * disagree — including about the blank, which reads as "use the
       * business setting" in both places.
       */
      $chosen = function (array $props) use ($blank) {
          $value = $props['modelValue'][0] ?? null;

          return $value === null ? $blank : ($props['options'][$value] ?? $blank);
      };

      /* On and Off rather than Yes and No: the field these summarise is a
         switch, and a summary that renames the states makes the reader
         translate between the two views. */
      $switch = fn (bool $on) => $on ? __('common.on') : __('common.off');
  @endphp

  {{-- Read first, edit on request; the partial at the foot of the page does
       the toggling and explains the contract.

       Three cards, one Save. Each opens on its own so a reader changing their
       time zone is not handed the calendar switches as well, but the button
       at the foot belongs to the form as a whole — every field is still in
       the page while it is closed, and a closed card posts what it already
       held. That is what makes one Save honest here: the save writes all of
       them, and the ones nobody opened are written back unchanged. --}}

  <form method="POST" action="{{ route('account.preferences.update') }}" class="space-y-5 max-w-[820px]">
    @csrf
    @method('PATCH')

    <section class="bg-white border border-line rounded-card p-5 sm:p-6"
             data-editable-card data-editing="{{ $errors->any() ? 'true' : 'false' }}">
      <div class="flex items-start justify-between gap-4">
        <h3 class="text-[15px] font-semibold text-head">{{ __('account.preferences.language_card') }}</h3>

        <button type="button" class="styledesk_action shrink-0" data-editable-edit hidden
                aria-expanded="false" aria-label="{{ __('account.preferences.language_card') }}">
          <x-icon name="pen-to-square" size="14" />
          {{ __('common.edit') }}
        </button>
      </div>

      <dl class="mt-5 sd-dl" data-editable-view hidden>
        <dt class="sd-dl__t">{{ __('account.preferences.language') }}</dt>
        <dd class="sd-dl__d">{{ $chosen($languageProps) }}</dd>
      </dl>

      <div class="mt-5 max-w-[420px]" data-editable-fields>
        <label for="locale" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.preferences.language') }}</label>
        <div data-vue-component="MultiSelect" data-props='@json($languageProps)'></div>
        <p class="mt-1.5 text-[12px] text-sub">{{ __('account.preferences.language_hint') }}</p>
      </div>
    </section>

    <section class="bg-white border border-line rounded-card p-5 sm:p-6"
             data-editable-card data-editing="{{ $errors->any() ? 'true' : 'false' }}">
      <div class="flex items-start justify-between gap-4">
        <h3 class="text-[15px] font-semibold text-head">{{ __('account.preferences.format_card') }}</h3>

        <button type="button" class="styledesk_action shrink-0" data-editable-edit hidden
                aria-expanded="false" aria-label="{{ __('account.preferences.format_card') }}">
          <x-icon name="pen-to-square" size="14" />
          {{ __('common.edit') }}
        </button>
      </div>

      <dl class="mt-5 sd-dl" data-editable-view hidden>
        <dt class="sd-dl__t">{{ __('account.preferences.date_format') }}</dt>
        <dd class="sd-dl__d">{{ $chosen($dateProps) }}</dd>

        <dt class="sd-dl__t">{{ __('account.preferences.time_format') }}</dt>
        <dd class="sd-dl__d">{{ $chosen($timeProps) }}</dd>

        <dt class="sd-dl__t">{{ __('account.preferences.timezone') }}</dt>
        <dd class="sd-dl__d">{{ $chosen($zoneProps) }}</dd>

        <dt class="sd-dl__t">{{ __('account.preferences.first_day_of_week') }}</dt>
        <dd class="sd-dl__d">{{ $chosen($dayProps) }}</dd>
      </dl>

      <div class="mt-5 grid sm:grid-cols-2 gap-x-5 gap-y-5" data-editable-fields>
        <div>
          <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.preferences.date_format') }}</span>
          <div data-vue-component="MultiSelect" data-props='@json($dateProps)'></div>
        </div>

        <div>
          <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.preferences.time_format') }}</span>
          <div data-vue-component="MultiSelect" data-props='@json($timeProps)'></div>
        </div>

        <div>
          <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.preferences.timezone') }}</span>
          <div data-vue-component="MultiSelect" data-props='@json($zoneProps)'></div>
          <p class="mt-1.5 text-[12px] text-sub">{{ __('account.preferences.timezone_hint') }}</p>
        </div>

        <div>
          <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.preferences.first_day_of_week') }}</span>
          <div data-vue-component="MultiSelect" data-props='@json($dayProps)'></div>
        </div>
      </div>
    </section>

    <section class="bg-white border border-line rounded-card p-5 sm:p-6"
             data-editable-card data-editing="{{ $errors->any() ? 'true' : 'false' }}">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <h3 class="text-[15px] font-semibold text-head">{{ __('account.preferences.calendar_card') }}</h3>
          <p class="text-[13px] text-sub mt-1 max-w-[620px]">{{ __('account.preferences.calendar_intro') }}</p>
        </div>

        <button type="button" class="styledesk_action shrink-0" data-editable-edit hidden
                aria-expanded="false" aria-label="{{ __('account.preferences.calendar_card') }}">
          <x-icon name="pen-to-square" size="14" />
          {{ __('common.edit') }}
        </button>
      </div>

      <dl class="mt-5 sd-dl" data-editable-view hidden>
        <dt class="sd-dl__t">{{ __('account.preferences.calendar_view') }}</dt>
        <dd class="sd-dl__d">{{ $chosen($viewProps) }}</dd>

        <dt class="sd-dl__t">{{ __('account.preferences.show_weekends') }}</dt>
        <dd class="sd-dl__d">{{ $switch($toggle('show_weekends')) }}</dd>

        <dt class="sd-dl__t">{{ __('account.preferences.show_cancelled') }}</dt>
        <dd class="sd-dl__d">{{ $switch($toggle('show_cancelled')) }}</dd>

        <dt class="sd-dl__t">{{ __('account.preferences.show_resource_color') }}</dt>
        <dd class="sd-dl__d">{{ $switch($toggle('show_resource_color')) }}</dd>

        <dt class="sd-dl__t">{{ __('account.preferences.show_staff_color') }}</dt>
        <dd class="sd-dl__d">{{ $switch($toggle('show_staff_color')) }}</dd>
      </dl>

      <div class="mt-5 max-w-[420px]" data-editable-fields>
        <span class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.preferences.calendar_view') }}</span>
        <div data-vue-component="MultiSelect" data-props='@json($viewProps)'></div>
      </div>

      {{-- Boxed switches with a gap between them, which is how every other
           screen in the app stacks them.

           They used to carry `sd-optrow` as well, and the two fought: the
           switch is already a bordered card with its own padding, and the row
           class laid a second set of padding and a divider over it — then
           took the top padding off the first one, so the list read as three
           even rows under a squashed one. --}}
      <div class="mt-5 space-y-2.5" data-editable-fields>
        <x-toggle name="show_weekends" :label="__('account.preferences.show_weekends')" :checked="$toggle('show_weekends')" />
        <x-toggle name="show_cancelled" :label="__('account.preferences.show_cancelled')" :checked="$toggle('show_cancelled')" />
        <x-toggle name="show_resource_color" :label="__('account.preferences.show_resource_color')" :checked="$toggle('show_resource_color')" />
        <x-toggle name="show_staff_color" :label="__('account.preferences.show_staff_color')" :checked="$toggle('show_staff_color')" />
      </div>
    </section>

    {{-- One row for three cards, shown as soon as any of them is open. --}}
    <div class="flex flex-wrap items-center gap-3" data-editable-actions>
      <button type="submit" data-submit-once
              class="inline-flex items-center h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
        {{ __('account.preferences.save') }}
      </button>

      {{-- A plain link here rather than the close-and-reset the other screens
           use: these fields are Vue components, and a form reset would put
           the posted values back without the controls noticing. Fetching the
           page again is the one cancel that cannot leave the two disagreeing. --}}
      <a href="{{ route('account.preferences') }}"
         class="h-11 px-4 inline-flex items-center rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
        {{ __('account.cancel') }}
      </a>
    </div>
  </form>

  {{-- Its own form so it cannot be reached by pressing Enter in a field, and
       confirmed because it discards choices without asking a question the
       reader can answer field by field. --}}
  <form method="POST" action="{{ route('account.preferences.reset') }}" class="mt-5 max-w-[820px]" data-editable-actions>
    @csrf
    <button type="submit"
            data-confirm="{{ __('account.reset_confirm') }}"
            data-confirm-label="{{ __('account.preferences.reset_action') }}"
            data-confirm-tone="brand"
            class="h-11 px-4 inline-flex items-center rounded-lg text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
      {{ __('account.preferences.reset_action') }}
    </button>
    <p class="text-[12px] text-sub mt-1.5">{{ __('account.preferences.reset_hint') }}</p>
  </form>

  @include('account.partials._editable')
</x-account.shell>
@endsection
