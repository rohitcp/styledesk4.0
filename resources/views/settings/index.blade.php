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
                class="styledesk_action mt-4">
          {{ __('settings.clear_search') }}
        </button>
      </div>

      <div class="mt-6">
        @foreach ($groups as $index => $group)
          {{-- Every group renders open, and the script collapses all but the
               first on arrival. The other way round — closed markup opened by
               script — would hand a reader without JavaScript a page of
               headings and nothing else. --}}
          <section class="styledesk_accordion" data-settings-group data-open="true">
            <h2>
              <button type="button"
                      class="styledesk_accordion__header"
                      data-settings-toggle
                      aria-expanded="true"
                      aria-controls="settings-group-{{ $index }}">
                <span class="min-w-0 flex-1">
                  <span class="block text-[15px] font-semibold text-head">{{ $group['name'] }}</span>
                  <span class="block text-[13px] text-sub mt-1 leading-relaxed">{{ $group['description'] }}</span>
                </span>

                <span class="styledesk_accordion__chevron" aria-hidden="true">
                  <x-icon name="chevron-down" size="14" />
                </span>
              </button>
            </h2>

            <div class="styledesk_accordion__panel" id="settings-group-{{ $index }}" role="region"
                 aria-label="{{ $group['name'] }}">
              <div class="styledesk_accordion__clip">
                <div class="styledesk_accordion__body">
                  <div class="gap-3 styledesk_settinggrid">
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
                </div>
              </div>
            </div>
          </section>
        @endforeach
      </div>

    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* Accordion + search filter.
       Progressive enhancement over server-rendered cards: with no JS the full
       list is still there and still usable, which is the right failure mode
       for a page that is only a menu. 36 cards is small enough that matching
       locally beats a round trip per keystroke. */
    (function () {
      var groups = Array.prototype.slice.call(document.querySelectorAll('[data-settings-group]'));
      if (!groups.length) return;

      function setOpen(group, open) {
        group.setAttribute('data-open', open ? 'true' : 'false');
        var toggle = group.querySelector('[data-settings-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      }

      /* One section at a time: the page is a menu, and a menu you have to
         scroll past nine open groups to read is the thing this replaced. */
      function openOnly(group) {
        groups.forEach(function (other) { setOpen(other, other === group); });
      }

      groups.forEach(function (group, index) {
        setOpen(group, index === 0);

        var toggle = group.querySelector('[data-settings-toggle]');
        if (!toggle) return;

        toggle.addEventListener('click', function () {
          if (group.getAttribute('data-open') === 'true') {
            setOpen(group, false);
          } else {
            openOnly(group);
          }
        });
      });

      var box = document.getElementById('settingsSearch');
      if (!box) return;

      var cards = Array.prototype.slice.call(document.querySelectorAll('[data-settings-card]'));
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
        groups.forEach(function (group, index) {
          var any = group.querySelector('[data-settings-card]:not([hidden])');
          group.hidden = !any;

          /* While searching, a match is worth more than a tidy page: a group
             holding a hit opens, whatever was open before. Clearing the box
             puts the page back the way it arrived. */
          if (q) {
            setOpen(group, !!any);
          } else {
            setOpen(group, index === 0);
          }
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
