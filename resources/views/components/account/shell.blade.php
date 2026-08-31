{{--
    The frame every My Account screen sits in.

    One component rather than four copies of the same grid: the left
    navigation, the mobile selector and the heading are identical on all four
    screens, and the only thing that differs is which section is current.

    Below lg the navigation collapses to a selector, which is the pattern the
    rest of the app uses when a sidebar will not fit — a row of tabs would
    either wrap or scroll sideways at four items with these labels.
--}}
@props(['current', 'title', 'intro' => null])

@php
    $sections = App\Support\AccountSection::all();
@endphp

<main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
  <div class="max-w-[1180px]">

    <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('account.title') }}</h1>
    <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('account.intro') }}</p>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-[240px_minmax(0,1fr)] gap-5 items-start">

      {{-- Sticky on desktop so the sections stay reachable from the bottom of
           a long form — the notifications grid is several screens tall. --}}
      <nav class="hidden lg:block sticky top-[76px] bg-white border border-line rounded-card p-2"
           aria-label="{{ __('account.title') }}">
        @foreach ($sections as $section)
          <a href="{{ route($section['route']) }}"
             class="sd-set__nav"
             @if ($section['key'] === $current) aria-current="page" @endif>
            <x-icon :name="$section['icon']" size="16" />
            {{ $section['label'] }}
          </a>
        @endforeach
      </nav>

      {{-- The same list as a selector. A <select> rather than a menu because
           it is a choice of one destination from four, which is what a select
           is, and it comes with the platform's own keyboard and screen-reader
           behaviour on a phone. --}}
      <div class="lg:hidden">
        <label for="accountSection" class="sr-only">{{ __('account.title') }}</label>
        <select id="accountSection" class="sd-input has-value" data-account-section>
          @foreach ($sections as $section)
            <option value="{{ route($section['route']) }}" @selected($section['key'] === $current)>
              {{ $section['label'] }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="min-w-0">
        <div class="mb-5">
          <h2 class="text-[18px] font-semibold text-head">{{ $title }}</h2>
          @if ($intro)
            <p class="text-[13px] text-sub mt-1.5 max-w-[640px] leading-relaxed">{{ $intro }}</p>
          @endif
        </div>

        {{-- The no-JavaScript path still needs somewhere to report a failure;
             with JavaScript the same information arrives as a toast plus a
             message under each offending field. --}}
        @if ($errors->any())
          <div class="sd-alert sd-alert--danger mb-5" role="alert">
            <p class="min-w-0">{{ $errors->first() }}</p>
          </div>
        @endif

        {{ $slot }}
      </div>
    </div>
  </div>
</main>

@once
  @push('scripts')
    <script>
        /* The mobile section selector. Navigation, not a form control: it has
           no submit button beside it, so changing it has to be what moves. */
        document.addEventListener('change', function (event) {
            var picker = event.target.closest('[data-account-section]');
            if (picker) window.location.href = picker.value;
        });
    </script>
  @endpush
@endonce
