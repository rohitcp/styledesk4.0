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
    <div>

      <!-- ---------- Summary card ---------- -->
      <section class="min-w-0 bg-white border border-line rounded-card p-5 sm:p-7">

        @php
            // Greeting follows the viewer's own clock, not the server's, once
            // per-user timezones exist. Until then it is the app timezone.
            $hour = now()->hour;
            $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        @endphp
        <p class="text-[13px] text-sub">{{ now()->format('l, F j') }}</p>
        <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight mt-1">{{ $greeting }}, {{ auth()->user()->first_name }}</h1>

        <!-- Underline tabs — same pattern as the design system -->
        <div class="mt-6 flex items-end gap-5 border-b border-line">
          <a href="#" class="h-10 flex items-center text-[14px] font-medium text-ink border-b-2 border-brand">Your Summary</a>
          <a href="#" class="h-10 flex items-center text-[14px] font-medium text-sub hover:text-ink border-b-2 border-transparent transition-colors">Recent Comments</a>
        </div>

        <!-- Explore Features -->
        <div class="mt-8">
          <div class="flex items-start gap-2.5">
            <span class="sd-tip text-faint mt-1.5 shrink-0 cursor-grab" data-tip="Drag to reorder" aria-hidden="true">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.8"/><circle cx="15" cy="5" r="1.8"/><circle cx="9" cy="12" r="1.8"/><circle cx="15" cy="12" r="1.8"/><circle cx="9" cy="19" r="1.8"/><circle cx="15" cy="19" r="1.8"/></svg>
            </span>
            <div class="min-w-0 flex-1">
              <h2 class="text-[19px] sm:text-[20px] font-bold text-head tracking-tight">Explore Features</h2>
              <p class="text-[14px] text-sub mt-1.5 max-w-[640px]">Take a minute to view the panels below to guide your next actions and discover what StyleDesk can do for you.</p>
            </div>
            <button class="h-8 w-8 grid place-items-center rounded-md text-faint hover:bg-hover hover:text-sub shrink-0 transition-colors" data-tip="Dismiss" aria-label="Dismiss">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            </button>
          </div>

          {{-- A wrapping grid, not a horizontal rail.
               The rail kept two of the five cards off-screen behind arrows, so
               the ones most worth watching were the ones nobody saw. Every card
               is on the page now and the row count follows the width. --}}
          <div class="mt-5 styledesk_cardgrid">

              <article class="border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Manage Contacts</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">StyleDesk separates Contacts into two types, Person and Organization.</p>
                  <button class="mt-4 h-8 px-3.5 rounded-md border border-stroke bg-hover text-ink text-[13px] font-semibold hover:bg-sel transition-colors">Watch Clip</button>
                </div>
              </article>

              <article class="border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Manage Sales Opportunities</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Stay in control of your sales pipeline and track deals from start to finish.</p>
                  <button class="styledesk_action mt-4">Watch Clip</button>
                </div>
              </article>

              <article class="border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Log a Note or Activity</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Keep your team informed with detailed logs of interactions.</p>
                  <button class="styledesk_action mt-4">Watch Clip</button>
                </div>
              </article>

              <article class="border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Comments</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Leave threaded comments for colleagues, and reply from the app or email.</p>
                  <button class="styledesk_action mt-4">Watch Clip</button>
                </div>
              </article>

              <article class="border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Build Workflows</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Automate the repetitive steps so your team can focus on the work itself.</p>
                  <button class="styledesk_action mt-4">Watch Clip</button>
                </div>
              </article>

          </div>
        </div>
      </section>

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
