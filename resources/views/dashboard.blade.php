@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    {{-- Section 17: getting started. Sits above the page so it is the first
         thing a new owner sees, and disappears on its own once everything is
         done — a checklist that stays after completion is just clutter. --}}
    @if ($showChecklist)
        <div class="w-full px-4 sm:px-5 lg:px-6 pt-5">
            <section class="bg-white border border-line rounded-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="min-w-0">
                        <h2 class="text-[15px] font-semibold text-head">Getting started</h2>
                        <p class="text-[13px] text-sub mt-1">A few things left to set up. You can come back to these any time.</p>
                    </div>

                    @if ($canDismissChecklist)
                        <form method="POST" action="{{ route('getting-started.dismiss') }}" class="ml-auto">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="h-8 px-3 rounded-md text-[13px] font-semibold text-sub hover:text-ink hover:bg-hover transition-colors">
                                Dismiss
                            </button>
                        </form>
                    @endif
                </div>

                <ul class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2">
                    @foreach ($checklist as $item)
                        <li class="flex items-start gap-2.5">
                            @if ($item['done'])
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-success mt-0.5 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span class="text-[13px] text-sub line-through">{{ $item['label'] }}</span>
                            @else
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-faint mt-0.5 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/></svg>
                                <span class="text-[13px] text-ink">{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    @endif
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    {{-- Single column. Tasks and Goals used to occupy a 352px side rail; with
         those gone there is nothing to put beside the summary, and keeping the
         two-column grid would only narrow it for an empty neighbour. --}}
    <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 pb-[120px]">

      @php
          /* The greeting follows the viewer's own clock once per-user
             timezones exist. Until then it is the business's. */
          $hour = now()->hour;
          $greeting = $hour < 12 ? __('dashboard.greeting.morning')
              : ($hour < 18 ? __('dashboard.greeting.afternoon') : __('dashboard.greeting.evening'));

          /* Handed to every panel so none of them formats money its own
             way — a dashboard where two cards round differently is a
             dashboard somebody reconciles by hand. */
          $currency = \App\Support\Currencies::resolve();
          $money = fn (int $minor) => \App\Support\Money::format($minor / 100, $currency);

          $user = auth()->user();
          $shows = fn (string $widget) => $widgets->contains($widget);
          $canCheckIn = $user->hasPermission('appointments.check_in', 'own');
          $showsTips = $user->hasPermission('dashboard.view_tips', 'own')
              && \App\Models\TipSettings::forTenant($tenant)->is_enabled;
      @endphp

      <header class="flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <p class="text-[13px] text-sub">{{ now()->translatedFormat('l, j F') }}</p>
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight mt-1">
            {{ $greeting }}, {{ $user->first_name }}
          </h1>
        </div>

        <div class="shrink-0 flex flex-wrap items-center justify-end gap-2">
          {{-- Only where there is a choice to make. One branch is not a
               filter, it is the business, and a dropdown with a single
               entry is furniture. --}}
          @if ($locations->isNotEmpty())
            <form method="GET" action="{{ route('dashboard') }}">
              <select name="location" class="sd-input w-[200px]" onchange="this.form.submit()"
                      aria-label="{{ __('dashboard.location') }}">
                <option value="">{{ __('dashboard.all_locations') }}</option>
                @foreach ($locations as $location)
                  <option value="{{ $location->id }}" @selected($chosenLocation === $location->id)>{{ $location->name }}</option>
                @endforeach
              </select>
            </form>
          @endif

          {{-- The things somebody came here to do, beside the greeting
               rather than under everything they came to read. --}}
          @if ($widgets->contains('quick_actions'))
            @include('dashboard.widgets._quick_actions', ['inline' => true])
          @endif
        </div>
      </header>

      @php
          /* Split into the page and the column beside it.

             The side panels are the ones somebody glances at repeatedly
             while working on something else. Which of them this reader
             actually gets is still decided by their permissions — the split
             only says where a panel goes, never whether. */
          $side = collect(config('dashboard.side'))->filter(fn (string $w) => $widgets->contains($w))->values();
          /* Quick actions have moved up into the greeting row, so they are
             not one of the panels any more. */
          $main = $widgets->reject(fn (string $w) => $side->contains($w) || $w === 'quick_actions')->values();
      @endphp

      <div class="mt-6 grid gap-4 {{ $side->isEmpty() ? '' : 'xl:grid-cols-[minmax(0,1fr)_minmax(320px,380px)]' }}">

        {{-- The page itself, in this role's own order. --}}
        <div class="min-w-0 space-y-4">
          @foreach ($main as $widget)
            @include('dashboard.widgets._'.$widget)
          @endforeach
        </div>

        {{-- Beside it, tabbed: only one of the three is urgent at a time,
             and stacking them would push the third below the fold on the
             one screen nobody scrolls. --}}
        @if ($side->isNotEmpty())
          <aside class="min-w-0">
            {{-- No padding of its own, and clipped to the radius: the tab
                 bar is this card's top edge, so anything between it and the
                 border reads as a control sitting on a panel rather than as
                 the panel itself. --}}
            <div class="sd-card !p-0 overflow-hidden xl:sticky xl:top-4" data-dashboard-side>
              {{-- Flush to the card. A tab bar inset from the edge reads as
                   a control sitting on a panel rather than as the panel's
                   own top. --}}
              <div class="flex items-end border-b border-line" role="tablist">
                @foreach ($side as $index => $widget)
                  <button type="button" role="tab" id="side-tab-{{ $widget }}"
                          aria-controls="side-panel-{{ $widget }}"
                          aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                          data-side-tab="{{ $widget }}"
                          @class([
                              /* Never wrapped: a two-line tab label in a
                                 three-tab bar reads as three paragraphs. */
                              'h-10 flex-1 px-2 text-[12.5px] font-semibold whitespace-nowrap border-b-2 -mb-px transition-colors',
                              'border-brand text-brand' => $index === 0,
                              'border-transparent text-sub hover:text-head' => $index !== 0,
                          ])>
                    {{ __('dashboard.'.$widget.'.title') }}
                  </button>
                @endforeach
              </div>

              @foreach ($side as $index => $widget)
                <div role="tabpanel" id="side-panel-{{ $widget }}"
                     aria-labelledby="side-tab-{{ $widget }}"
                     data-side-panel="{{ $widget }}" @unless ($index === 0) hidden @endunless>
                  {{-- Rendered without its own card: a card inside a card is
                       a border around a border. --}}
                  @include('dashboard.widgets._'.$widget, ['bare' => true])
                </div>
              @endforeach
            </div>
          </aside>
        @endif
      </div>
    </main>

@endsection

@push('scripts')
  <script>
    /* Nav sub-menu. Opens on hover for pointers and on focus for keyboards;
       the parent stays a plain link so a click (or a tap, where there is no
       hover at all) goes straight to All Clients. */
    document.querySelectorAll('[data-menu]').forEach(function (menu) {
      var trigger = menu.querySelector('[aria-haspopup]');
      var pop = menu.querySelector('[data-menu-pop]');
      var closeTimer = null;

      function open() {
        window.clearTimeout(closeTimer);
        pop.hidden = false;
        menu.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
      }
      function close() {
        pop.hidden = true;
        menu.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
      }
      // Small delay on leave so the diagonal trip from icon to menu is forgiving.
      function scheduleClose() {
        window.clearTimeout(closeTimer);
        closeTimer = window.setTimeout(close, 160);
      }

      menu.addEventListener('mouseenter', open);
      menu.addEventListener('mouseleave', scheduleClose);
      menu.addEventListener('focusin', open);
      menu.addEventListener('focusout', function (e) {
        if (!menu.contains(e.relatedTarget)) close();
      });

      trigger.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          open();
          var first = pop.querySelector('.sd-menu__item');
          if (first) first.focus();
        }
      });

      menu.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        // Focus first, then close: focusing the trigger fires focusin, which
        // would re-open the menu if close() had already run.
        trigger.focus();
        close();
      });
    });

  </script>
@endpush
