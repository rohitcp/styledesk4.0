{{--
    The mobile navigation drawer.

    Below lg the icon rail is hidden and, until this existed, nothing replaced
    it — the whole product was unreachable on a tablet or a phone. Rendered
    from the same config as the rail so the two cannot drift.

    Labels are shown rather than tooltips: a tooltip needs a hover, and the
    devices this drawer exists for do not have one.
--}}
<div id="sd-drawer" class="styledesk_drawer" hidden>
  <div class="styledesk_drawer__scrim" data-drawer-close aria-hidden="true"></div>

  <div class="styledesk_drawer__panel" role="dialog" aria-modal="true" aria-label="{{ __('navigation.main_menu') }}">
    <div class="styledesk_drawer__head">
      <span class="flex items-center gap-2.5 text-head">
        <svg width="22" height="22" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
          <path d="M6.5 21.5 L14 6 L18.5 6 L11 21.5 Z"/>
          <path d="M14.5 21.5 L22 6 L26.5 6 L19 21.5 Z"/>
          <rect x="4" y="24.6" width="24" height="3.6" rx="1.8"/>
        </svg>
        <span class="text-[15px] font-bold tracking-tight">StyleDesk</span>
      </span>

      <button type="button" class="styledesk_drawer__close" data-drawer-close aria-label="{{ __('common.close') }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <nav class="styledesk_drawer__body" aria-label="{{ __('navigation.drawer_main') }}">
      @php $navCounts = $navCounts ?? []; @endphp

      @foreach (config('navigation.primary') as $item)
        @php
            $active = \App\Support\Nav::isActive($item);
            $count = $navCounts[$item['count'] ?? ''] ?? null;
        @endphp

        <a href="{{ \App\Support\Nav::href($item) }}" {!! \App\Support\Nav::pending($item) !!}
           class="styledesk_drawerlink @if ($active) is-active @endif"
           @if ($active) aria-current="page" @endif>
          <span class="styledesk_drawerlink__icon"><x-icon :name="$item['icon']" size="17" /></span>
          {{ \App\Support\Nav::label($item) }}

          {{-- "Staff · 12". The drawer draws labels, so the number can simply
               follow the word it is about. --}}
          @if ($count !== null)
            <span class="styledesk_drawerlink__count">{{ $count }}</span>
          @endif
        </a>

        {{-- Children are listed inline rather than behind another tap. The
             drawer scrolls, so depth costs more than length here. --}}
        @if (! empty($item['children']))
          <div class="styledesk_drawer__sub">
            @foreach ($item['children'] as $child)
              @continue (! empty($child['separator']))

              @if (! empty($child['section']))
                {{-- The same headings the desktop menu uses: a group of six
                     links reads as a list without them. --}}
                <span class="styledesk_drawersub__section">{{ $child['section'] }}</span>
              @else
                @if (\App\Support\Nav::isPending($child))
                  <span class="styledesk_drawersub styledesk_drawersub--soon" aria-disabled="true"
                        {!! \App\Support\Nav::pending($child) !!}>
                    {{ \App\Support\Nav::label($child) }}
                    <span class="sd-menu__soon">{{ __('navigation.coming_soon') }}</span>
                  </span>
                @else
                  <a href="{{ \App\Support\Nav::href($child) }}"
                     class="styledesk_drawersub @if (\App\Support\Nav::isCurrent($child)) is-active @endif">{{ \App\Support\Nav::label($child) }}</a>
                @endif
              @endif
            @endforeach
          </div>
        @endif
      @endforeach

      <div class="styledesk_drawer__rule" role="separator"></div>

      @foreach (config('navigation.utility') as $item)
        <a href="{{ \App\Support\Nav::href($item) }}" {!! \App\Support\Nav::pending($item) !!} class="styledesk_drawerlink">
          <span class="styledesk_drawerlink__icon"><x-icon :name="$item['icon']" size="17" /></span>
          {{ \App\Support\Nav::label($item) }}
        </a>
      @endforeach

      {{-- Same permission as the app bar's gear. Hidden here too, so shrinking
           the window never becomes a way around the check. --}}
      @if (auth()->user()?->canManageSettings())
        <a href="{{ route('settings.index') }}"
           class="styledesk_drawerlink @if (request()->routeIs('settings.*')) is-active @endif">
          <span class="styledesk_drawerlink__icon"><x-icon name="gear" size="17" /></span>
          App settings
        </a>
      @endif
    </nav>
  </div>
</div>

@push('scripts')
  <script>
    /* Navigation drawer. */
    (function () {
      var drawer = document.getElementById('sd-drawer');
      var toggle = document.querySelector('[data-drawer-toggle]');
      if (!drawer || !toggle) return;

      var panel = drawer.querySelector('.styledesk_drawer__panel');
      var lastFocused = null;

      function open() {
        lastFocused = document.activeElement;
        drawer.hidden = false;
        /* Read after the element is displayed so the transition has a frame
           to start from; setting the class in the same tick would apply it
           to a still-hidden element and skip the animation. */
        window.requestAnimationFrame(function () { drawer.classList.add('is-open'); });
        document.body.style.overflow = 'hidden';
        toggle.setAttribute('aria-expanded', 'true');
        panel.querySelector('a, button').focus();
      }

      function close() {
        drawer.classList.remove('is-open');
        document.body.style.overflow = '';
        toggle.setAttribute('aria-expanded', 'false');
        window.setTimeout(function () { drawer.hidden = true; }, 200);
        /* Focus goes back where it came from, otherwise it lands on <body>
           and a keyboard user has to tab from the top of the page again. */
        if (lastFocused) lastFocused.focus();
      }

      toggle.addEventListener('click', function () {
        drawer.hidden ? open() : close();
      });

      drawer.addEventListener('click', function (e) {
        if (e.target.closest('[data-drawer-close]')) close();
        /* Any navigation closes it: following a link inside an open drawer
           otherwise leaves it open over the page that loads. */
        if (e.target.closest('a')) close();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !drawer.hidden) close();
      });

      /* Keep focus inside while it is open — it is a modal, and tabbing out
         to the page behind it is how a screen reader user gets lost. */
      panel.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;

        var items = panel.querySelectorAll('a[href], button:not([disabled])');
        if (!items.length) return;

        var first = items[0];
        var last = items[items.length - 1];

        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      });

      /* Growing past the breakpoint brings the icon rail back, so an open
         drawer becomes a second menu over a working one. */
      window.matchMedia('(min-width: 1024px)').addEventListener('change', function (e) {
        if (e.matches && !drawer.hidden) close();
      });
    }());
  </script>
@endpush
