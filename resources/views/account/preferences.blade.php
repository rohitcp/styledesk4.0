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
  @endphp

  <form method="POST" action="{{ route('account.preferences.update') }}" class="space-y-5 max-w-[820px]">
    @csrf
    @method('PATCH')

    <section class="bg-white border border-line rounded-card p-5 sm:p-6">
      <h3 class="text-[15px] font-semibold text-head">{{ __('account.preferences.language_card') }}</h3>

      <div class="mt-5 max-w-[420px]">
        <label for="locale" class="block text-[13px] font-medium text-ink mb-1.5">{{ __('account.preferences.language') }}</label>
        <div data-vue-component="MultiSelect" data-props='@json($languageProps)'></div>
        <p class="mt-1.5 text-[12px] text-sub">{{ __('account.preferences.language_hint') }}</p>
      </div>
    </section>

    <section class="bg-white border border-line rounded-card p-5 sm:p-6">
      <h3 class="text-[15px] font-semibold text-head">{{ __('account.preferences.format_card') }}</h3>

      <div class="mt-5 grid sm:grid-cols-2 gap-x-5 gap-y-5">
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

    <section class="bg-white border border-line rounded-card p-5 sm:p-6">
      <h3 class="text-[15px] font-semibold text-head">{{ __('account.preferences.calendar_card') }}</h3>
      <p class="text-[13px] text-sub mt-1 max-w-[620px]">{{ __('account.preferences.calendar_intro') }}</p>

      <div class="mt-5 max-w-[420px]">
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
      <div class="mt-5 space-y-2.5">
        <x-toggle name="show_weekends" :label="__('account.preferences.show_weekends')" :checked="$toggle('show_weekends')" />
        <x-toggle name="show_cancelled" :label="__('account.preferences.show_cancelled')" :checked="$toggle('show_cancelled')" />
        <x-toggle name="show_resource_color" :label="__('account.preferences.show_resource_color')" :checked="$toggle('show_resource_color')" />
        <x-toggle name="show_staff_color" :label="__('account.preferences.show_staff_color')" :checked="$toggle('show_staff_color')" />
      </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" data-submit-once
              class="inline-flex items-center h-11 px-6 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
        {{ __('account.preferences.save') }}
      </button>
    </div>
  </form>

  {{-- Its own form so it cannot be reached by pressing Enter in a field, and
       confirmed because it discards choices without asking a question the
       reader can answer field by field. --}}
  <form method="POST" action="{{ route('account.preferences.reset') }}" class="mt-5 max-w-[820px]">
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
</x-account.shell>
@endsection
