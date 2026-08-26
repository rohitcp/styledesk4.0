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
    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[minmax(0,1fr)_352px] xl:grid-cols-[minmax(0,1fr)_384px] items-start">

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
            <button class="h-8 w-8 grid place-items-center rounded-md text-faint hover:bg-hover hover:text-sub shrink-0 transition-colors" data-tip="Dismiss">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            </button>
          </div>

          <!-- Carousel -->
          <div class="relative mt-5">
            <div class="sd-scroll-x gap-4 pb-1" id="featureRail">

              <article class="w-[248px] shrink-0 border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
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

              <article class="w-[248px] shrink-0 border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Manage Sales Opportunities</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Stay in control of your sales pipeline and track deals from start to finish.</p>
                  <button class="mt-4 h-8 px-3.5 rounded-md border border-stroke bg-white text-ink text-[13px] font-semibold hover:bg-hover transition-colors">Watch Clip</button>
                </div>
              </article>

              <article class="w-[248px] shrink-0 border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Log a Note or Activity</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Keep your team informed with detailed logs of interactions.</p>
                  <button class="mt-4 h-8 px-3.5 rounded-md border border-stroke bg-white text-ink text-[13px] font-semibold hover:bg-hover transition-colors">Watch Clip</button>
                </div>
              </article>

              <article class="w-[248px] shrink-0 border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Comments</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Leave threaded comments for colleagues, and reply from the app or email.</p>
                  <button class="mt-4 h-8 px-3.5 rounded-md border border-stroke bg-white text-ink text-[13px] font-semibold hover:bg-hover transition-colors">Watch Clip</button>
                </div>
              </article>

              <article class="w-[248px] shrink-0 border border-line rounded-card overflow-hidden bg-white hover:shadow-md transition-shadow">
                <div class="sd-thumb grid place-items-center">
                  <span class="h-11 w-11 rounded-full bg-white/85 grid place-items-center text-head shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5l11 6.5-11 6.5v-13z"/></svg>
                  </span>
                </div>
                <div class="p-4">
                  <h3 class="text-[15px] font-semibold text-head">Build Workflows</h3>
                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">Automate the repetitive steps so your team can focus on the work itself.</p>
                  <button class="mt-4 h-8 px-3.5 rounded-md border border-stroke bg-white text-ink text-[13px] font-semibold hover:bg-hover transition-colors">Watch Clip</button>
                </div>
              </article>

            </div>

            <button data-rail-prev class="hidden sm:grid absolute left-0 top-[70px] -translate-x-1/2 h-9 w-9 rounded-full bg-white border border-line shadow-md place-items-center text-sub hover:bg-hover disabled:opacity-0 disabled:pointer-events-none transition-all" data-tip="Previous">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M14.5 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button data-rail-next class="hidden sm:grid absolute right-0 top-[70px] translate-x-1/2 h-9 w-9 rounded-full bg-white border border-line shadow-md place-items-center text-sub hover:bg-hover disabled:opacity-0 disabled:pointer-events-none transition-all" data-tip="Next">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9.5 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
        </div>
      </section>

      <!-- ---------- Side column ---------- -->
      <div class="min-w-0 space-y-5">

        <!-- Tasks -->
        <section class="bg-white border border-line rounded-card">
          <div class="flex items-center gap-3 px-5 pt-5">
            <h2 class="text-[18px] font-semibold text-head">Tasks</h2>
            <div class="ml-auto inline-flex rounded-lg border border-stroke overflow-hidden shrink-0">
              <button class="h-8 px-3 bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">Add Task</button>
              <button class="h-8 w-7 grid place-items-center border-l border-stroke bg-white hover:bg-hover text-sub transition-colors" data-tip="More">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            </div>
          </div>

          <div class="mt-4 px-5 flex items-end gap-5 border-b border-line">
            <a href="#" class="h-9 flex items-center gap-1.5 text-[13px] font-medium text-ink border-b-2 border-brand">
              Today <span class="inline-flex items-center h-5 px-1.5 rounded-md bg-hover text-faint text-[11px] font-medium">1</span>
            </a>
            <a href="#" class="h-9 flex items-center gap-1.5 text-[13px] font-medium text-sub hover:text-ink border-b-2 border-transparent transition-colors">
              Next 7 days <span class="inline-flex items-center h-5 px-1.5 rounded-md bg-hover text-faint text-[11px] font-medium">5</span>
            </a>
            <a href="#" class="h-9 flex items-center gap-1.5 text-[13px] font-medium text-sub hover:text-ink border-b-2 border-transparent transition-colors">
              Overdue <span class="inline-flex items-center h-5 px-1.5 rounded-md bg-hover text-faint text-[11px] font-medium">0</span>
            </a>
          </div>

          <div class="divide-y divide-line">
            <label class="flex gap-3 px-5 py-4 hover:bg-hover/50 cursor-pointer transition-colors">
              <input type="checkbox" class="sd-check mt-0.5" />
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span class="inline-flex items-center h-5 px-2 rounded-md text-[11px] font-semibold" style="color:#16a34a;background:#dcfce7">Onboarding</span>
                  <span class="text-[14px] font-semibold text-head">Add a Contact</span>
                </div>
                <p class="text-[13px] text-sub mt-1 line-clamp-2">Welcome to StyleDesk! It’s time to get started and add your first contact.</p>
                <p class="text-[13px] text-sub mt-1">for <a href="#" class="text-link font-medium hover:underline">StyleDesk</a></p>
              </div>
            </label>
          </div>
        </section>

        <!-- Goals -->
        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[18px] font-semibold text-head">Goals</h2>

          <!-- Preview built from the system's card + progress-bar patterns
               rather than a bitmap illustration. -->
          <div class="mt-6 mx-auto max-w-[292px] rounded-card border border-line bg-white shadow-sm p-3.5">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center gap-1 text-[12px] font-medium text-ink">
                Yearly Sales Goal
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="text-faint"><path d="M9.5 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </span>
              <span class="ml-auto text-[11px] text-faint">January</span>
            </div>
            <div class="flex items-baseline gap-2 mt-2">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="text-success self-center shrink-0"><path d="M8 4h8v4a4 4 0 01-8 0V4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M16 5h3v1.5a3 3 0 01-3 3M8 5H5v1.5a3 3 0 003 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10 20h4M12 13v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
              <span class="text-[17px] font-bold text-head">£102,600</span>
              <span class="text-[12px] text-faint">/ £80,000</span>
            </div>
            <div class="flex items-center gap-2.5 mt-2.5">
              <div class="h-2 flex-1 rounded-full bg-line overflow-hidden">
                <span class="block h-full rounded-full bg-success" style="width:100%"></span>
              </div>
              <span class="text-[11px] font-medium text-sub shrink-0">128%</span>
            </div>
          </div>

          <h3 class="text-[16px] font-semibold text-head text-center mt-7">Achieve More: Define Goals &amp; Track Success</h3>
          <p class="text-[13px] text-sub text-center mt-2 max-w-[310px] mx-auto leading-relaxed">Use Goals to set clear targets, monitor progress, and celebrate success.</p>

          <button class="mt-5 mx-auto flex items-center gap-2 h-10 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[14px] font-semibold transition-colors">
            Add Goal
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
          </button>
        </section>

      </div>
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

    // Feature carousel — scrolls by one card, arrows disable at the ends.
    // In the Laravel + Vue build this becomes a small <FeatureRail> component.
    (function () {
      var rail = document.getElementById('featureRail');
      if (!rail) return;
      var prev = document.querySelector('[data-rail-prev]');
      var next = document.querySelector('[data-rail-next]');
      var card = rail.firstElementChild;

      function step() {
        return card ? card.offsetWidth + 16 : 264;   /* card + gap-4 */
      }
      function sync() {
        var max = rail.scrollWidth - rail.clientWidth - 1;
        prev.disabled = rail.scrollLeft <= 0;
        next.disabled = rail.scrollLeft >= max;
      }
      prev.addEventListener('click', function () { rail.scrollBy({ left: -step(), behavior: 'smooth' }); });
      next.addEventListener('click', function () { rail.scrollBy({ left: step(), behavior: 'smooth' }); });
      rail.addEventListener('scroll', sync, { passive: true });
      window.addEventListener('resize', sync);
      sync();
    }());
  </script>
@endpush
