@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[1180px]">

      <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('settings.title') }}</h1>
      <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
        {{ __('settings.intro') }}
      </p>

      {{-- Server-rendered, filtered client-side.
           The cards are real markup rather than a Vue island fed a JSON blob:
           the list is the page's entire content, so rendering it in the
           browser would mean an empty page whenever the bundle is slow, stale
           or blocked — and nothing here needs to react to anything except a
           search box. --}}
      <div class="mt-6 relative max-w-[420px]">
        <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
          <x-icon name="magnifying-glass" size="15" />
        </span>
        <input id="settingsSearch" type="search" class="sd-input styledesk_input--prefixed"
               placeholder="{{ __('settings.search_placeholder') }}" aria-label="{{ __('settings.search_placeholder') }}" autocomplete="off">
      </div>

      <p id="settingsCount" class="mt-2.5 text-[13px] text-sub" role="status" aria-live="polite" hidden></p>

      <div id="settingsEmpty" class="mt-8 rounded-card border border-line bg-white p-8 text-center" hidden>
        <p class="text-[15px] font-semibold text-head">{{ __('settings.no_matches') }}</p>
        <p class="text-[13px] text-sub mt-1.5">{{ __('settings.no_matches_hint') }}</p>
        <button type="button" id="settingsClear"
                class="mt-4 h-9 px-4 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
          {{ __('settings.clear_search') }}
        </button>
      </div>

      @foreach ($groups as $group)
        <section class="mt-8" data-settings-group>
          <h2 class="text-[15px] font-semibold text-head">{{ $group['name'] }}</h2>
          <p class="text-[13px] text-sub mt-1">{{ $group['description'] }}</p>

          <div class="mt-4 gap-3 styledesk_settinggrid">
            @foreach ($group['modules'] as $module)
              {{-- A link when the module exists, a plain div when it does not.
                   A card that looks clickable and leads nowhere is worse than
                   one that says it is not ready yet. --}}
              @php
                  $tag = $module['url'] ? 'a' : 'div';
              @endphp
              <{{ $tag }}
                @if ($module['url']) href="{{ $module['url'] }}" @endif
                class="styledesk_settingcard @if (! $module['url']) styledesk_settingcard--soon @endif"
                data-settings-card
                data-haystack="{{ $module['haystack'] }}">

                <span class="styledesk_settingcard__icon" aria-hidden="true">
                  <x-icon :name="$module['icon']" size="18" />
                </span>

                <span class="min-w-0 flex-1">
                  <span class="flex flex-wrap items-center gap-2">
                    <span class="text-[14px] font-semibold text-head">{{ $module['name'] }}</span>
                    <span class="styledesk_badge {{ $module['status_class'] }}">{{ $module['status_label'] }}</span>
                  </span>
                  <span class="block text-[13px] text-sub mt-1 leading-relaxed">{{ $module['description'] }}</span>

                  @if (! empty($module['counts']))
                    <span class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2">
                      @foreach ($module['counts'] as $count)
                        <span class="text-[12px] text-sub">
                          <span class="font-semibold text-ink">{{ $count['value'] }}</span> {{ $count['label'] }}
                        </span>
                      @endforeach
                    </span>
                  @endif
                </span>

                @if ($module['url'])
                  <span class="shrink-0 text-faint self-center"><x-icon name="chevron-right" size="14" /></span>
                @endif
              </{{ $tag }}>
            @endforeach
          </div>
        </section>
      @endforeach

    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* Search filter.
       Progressive enhancement over server-rendered cards: with no JS the full
       list is still there and still usable, which is the right failure mode
       for a page that is only a menu. 36 cards is small enough that matching
       locally beats a round trip per keystroke. */
    (function () {
      var box = document.getElementById('settingsSearch');
      if (!box) return;

      var cards = Array.prototype.slice.call(document.querySelectorAll('[data-settings-card]'));
      var groups = Array.prototype.slice.call(document.querySelectorAll('[data-settings-group]'));
      var count = document.getElementById('settingsCount');
      var empty = document.getElementById('settingsEmpty');
      var clear = document.getElementById('settingsClear');

      function apply() {
        var q = box.value.trim().toLowerCase();
        /* Every word must match, so "email template" narrows rather than
           widening the way an OR would. */
        var words = q ? q.split(/\s+/) : [];
        var shown = 0;

        cards.forEach(function (card) {
          var hay = card.getAttribute('data-haystack');
          var hit = words.every(function (w) { return hay.indexOf(w) !== -1; });
          card.hidden = !hit;
          if (hit) shown++;
        });

        /* A heading with no cards under it reads as a section that failed to
           load, so a group hides with its last card. */
        groups.forEach(function (group) {
          var any = group.querySelector('[data-settings-card]:not([hidden])');
          group.hidden = !any;
        });

        empty.hidden = shown !== 0;
        count.hidden = !q;
        count.textContent = shown + (shown === 1 ? ' setting matches ' : ' settings match ') + '“' + box.value.trim() + '”.';
      }

      box.addEventListener('input', apply);
      clear.addEventListener('click', function () { box.value = ''; apply(); box.focus(); });
    }());
  </script>
@endpush
