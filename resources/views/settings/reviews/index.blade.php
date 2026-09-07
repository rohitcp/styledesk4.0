@extends('layouts.app')

@section('title', __('reviews.settings.title'))

{{--
    App Settings → Reviews & Feedback.

    The same shape Tips uses: the switch is its own form at the top and saves
    itself, and nothing below it exists until it is on. None of those
    questions mean anything to a business that is not asking for reviews, and
    a screenful of settings that do nothing is a screen that has to be read
    before it can be dismissed.

    Switching off hides them; it erases nothing. The switch form carries the
    current timing and channel as hidden fields, so turning reviews off in
    November and on again in March finds them exactly as they were.
--}}

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('reviews.settings.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('reviews.settings.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('reviews.settings.intro') }}</p>
        </div>

        <a href="{{ route('settings.index') }}" class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {{ __('common.back') }}
        </a>
      </div>

      {{-- The switch, on its own and posting on its own.

           It is not one of the settings below it — it decides whether they
           are asked about at all — so it sits above them rather than inside
           their form. The rest travel with it as hidden fields, so switching
           reviews on cannot quietly reset the timing somebody chose. --}}
      <form method="POST" action="{{ route('settings.reviews.update') }}" class="mt-6" data-reviews-switch>
        @csrf
        @method('PATCH')

        <input type="hidden" name="delay" value="{{ $settings->delay }}">
        <input type="hidden" name="channel" value="{{ $settings->channel }}">
        <input type="hidden" name="google_enabled" value="{{ $settings->google_enabled ? 1 : 0 }}">

        <div class="sd-card p-5">
          <x-toggle name="is_enabled" :label="__('reviews.settings.enable')"
                    :hint="__('reviews.settings.enable_hint')" :checked="$settings->is_enabled"
                    data-reviews-enabled />

          @unless ($settings->is_enabled)
            <p class="mt-3 text-[12px] text-sub bg-hover rounded-lg px-3 py-2">{{ __('reviews.settings.disabled_note') }}</p>
          @endunless

          {{-- Only ever seen with the script blocked, which hides it and
               saves on the toggle instead. Without it the switch would be the
               one control on the screen that cannot be operated at all. --}}
          <button type="submit" class="styledesk_action mt-3" data-reviews-fallback>
            {{ __('common.save') }}
          </button>
        </div>
      </form>

      {{-- Nothing below means anything until reviews are on, so until they
           are, there is nothing below. --}}
      @if ($settings->is_enabled)
      <form method="POST" action="{{ route('settings.reviews.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PATCH')

        {{-- Saving the timing must not switch the asking off, and must not
             lose the Google preference either: neither control is in this
             form, so both answers travel as hidden fields. --}}
        <input type="hidden" name="is_enabled" value="1">
        <input type="hidden" name="google_enabled" value="{{ $settings->google_enabled ? 1 : 0 }}">

        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('reviews.settings.timing') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('reviews.settings.timing_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($delays as $delay)
              <x-choice type="radio" name="delay" :value="$delay"
                        :label="__('reviews.settings.delays.'.$delay)"
                        :checked="$settings->delay === $delay" />
            @endforeach
          </div>
        </div>

        <div class="sd-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('reviews.settings.channel') }}</h2>
          <p class="text-[12.5px] text-sub mt-1 leading-relaxed">{{ __('reviews.settings.channel_hint') }}</p>

          <div class="mt-3 grid gap-2 sm:grid-cols-3">
            @foreach ($channels as $key => $channel)
              {{-- A channel nothing can deliver is shown and disabled rather
                   than hidden: the screen should say what is planned, and a
                   business must not be able to switch on a message that never
                   arrives. --}}
              <x-choice type="radio" name="channel" :value="$key"
                        :label="__('reviews.settings.channels.'.$key)"
                        :hint="$channel['available'] ? null : __('reviews.settings.coming_soon')"
                        :checked="$settings->channel === $key"
                        :disabled="! $channel['available']" />
            @endforeach
          </div>
        </div>

        {{-- The Google card is held back.

             Everything behind it is built and tested — google_enabled on the
             settings row, google_review_url per location, the branch button
             on the review page and the redirect that records the click — and
             the update endpoint still accepts both fields. Only the controls
             are absent, so nothing here has to be rebuilt to bring it back:
             restore this block from git history.

             While it is out, no branch has a review URL, so no client is ever
             offered the button. That is the intended state, not a gap. --}}

        <div>
          <button type="submit"
                  class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('common.save') }}
          </button>
        </div>
      </form>
      @endif

    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* The switch saves itself.

       It is the only control a reader touches in its form, and a toggle that
       needed a Save button beside it would be one that looks like it has
       already taken effect and has not. Without this script the form still
       posts — the button below it is the fallback, and the page reloads
       either way, which is what makes the settings underneath appear. */
    (function () {
      var form = document.querySelector('[data-reviews-switch]');
      if (!form) return;

      var toggle = form.querySelector('[data-reviews-enabled] input[type="checkbox"]');
      if (!toggle) return;

      var fallback = form.querySelector('[data-reviews-fallback]');
      if (fallback) fallback.hidden = true;

      toggle.addEventListener('change', function () {
        form.submit();
      });
    }());
  </script>
@endpush
