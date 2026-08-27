@extends('layouts.app')

@section('title', 'Locations')

@section('content')
  {{-- pb-[200px]: the grid ends on a card, and a card flush against the footer
       reads as the page having been cut off rather than finished. --}}
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="max-w-[1180px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Locations</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Locations</h1>
          @php
              /**
               * Built here rather than with an inline directive.
               *
               * Blade only recognises a directive when the character before
               * the @ is not a word character, so "inactive" followed by the
               * closing directive left it uncompiled and printed on the page.
               */
              $summary = $activeCount.' active '.Str::plural('location', $activeCount);

              if ($totalCount > $activeCount) {
                  $summary .= ', '.($totalCount - $activeCount).' inactive';
              }
          @endphp

          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            {{ $summary }}. Open a location for its manager, hours, services, staff and booking settings.
          </p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}"
             class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Back
          </a>

          @can('create', App\Models\Location::class)
            <a href="{{ route('settings.locations.create') }}"
               class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              <x-icon name="plus" size="13" />
              Add location
            </a>
          @endcan
        </div>
      </div>

      {{-- The search row is only worth its space once there is something to
           search. On a business with one branch it is a control that can only
           ever hide the single card below it. --}}
      @if ($totalCount > 1)
        <form method="GET" action="{{ route('settings.locations.index') }}" class="mt-6">
          <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[240px]">
              <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                <x-icon name="magnifying-glass" size="15" />
              </span>
              <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                     value="{{ $filters['search'] }}" aria-label="Search locations"
                     placeholder="Search by name, code, city or address">
            </div>

            <div class="min-w-[190px]">
              @php
                  $statusOptions = collect(config('locations.statuses'))->map(fn ($status) => $status['label']);
              @endphp
              <x-combo name="status" :options="$statusOptions" :selected="$filters['status']"
                       placeholder="All statuses" />
            </div>

            <button type="submit"
                    class="h-11 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              Search
            </button>

            @if ($filters['search'] !== '' || $filters['status'])
              <a href="{{ route('settings.locations.index') }}"
                 class="h-11 px-4 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                Clear
              </a>
            @endif
          </div>
        </form>
      @endif

      @if ($locations->isEmpty())
        {{-- Two different empty states. "No locations yet" is a business that
             has not started; "nothing matches" is a filter that is too narrow.
             Offering "Add your first location" to someone who has six of them
             behind a search box would be answering a question they did not
             ask. --}}
        <div class="mt-6 rounded-card border border-line bg-white p-10 text-center">
          @if ($totalCount === 0)
            <span class="styledesk_settingcard__icon mx-auto" aria-hidden="true">
              <x-icon name="location-dot" size="18" />
            </span>

            <p class="text-[15px] font-semibold text-head mt-3">No locations yet.</p>
            <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto leading-relaxed">
              Add the branch your business operates from, so staff, services and bookings have somewhere
              to belong.
            </p>

            @can('create', App\Models\Location::class)
              <a href="{{ route('settings.locations.create') }}"
                 class="mt-4 inline-flex items-center gap-1.5 h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                <x-icon name="plus" size="13" />
                Add your first location
              </a>
            @endcan
          @else
            <p class="text-[15px] font-semibold text-head">No locations match your search.</p>
            <p class="text-[13px] text-sub mt-1.5">Try a different word, or clear the filters.</p>

            <a href="{{ route('settings.locations.index') }}"
               class="mt-4 inline-flex items-center h-9 px-4 rounded-md border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
              Clear search
            </a>
          @endif
        </div>
      @else
        {{-- A summary card, not a settings screen. Services, staff, resources,
             booking rules and holiday hours are deliberately absent: the card
             answers "which branch is this and how do I reach it", and the
             page behind it answers everything else. --}}
        <div class="mt-6 styledesk_locationgrid">
          @foreach ($locations as $location)
            <div class="styledesk_locationcard">

              <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                  <h2 class="text-[15px] font-semibold text-head truncate">
                    <a href="{{ route('settings.locations.show', $location) }}"
                       class="styledesk_locationcard__link text-head hover:text-link transition-colors">
                      {{ $location->name }}
                    </a>
                  </h2>

                  <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    @if ($location->is_primary)
                      <span class="styledesk_badge styledesk_badge--active">Primary</span>
                    @endif

                    <span class="styledesk_badge {{ $location->statusClass() }}">{{ $location->statusLabel() }}</span>

                    @if ($location->code)
                      <span class="text-[12px] text-sub font-mono">{{ $location->code }}</span>
                    @endif
                  </div>
                </div>

                <span class="styledesk_rowmenu styledesk_locationcard__actions shrink-0" data-rowmenu>
                  <button type="button" class="styledesk_rowmenu__button" data-rowmenu-button
                          aria-haspopup="true" aria-expanded="false"
                          aria-label="Actions for {{ $location->name }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                    </svg>
                  </button>

                  <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
                    <a href="{{ route('settings.locations.show', $location) }}" class="styledesk_rowmenu__item" role="menuitem">
                      <x-icon name="location-dot" size="14" /> View location
                    </a>

                    @can('update', $location)
                      <a href="{{ route('settings.locations.edit', $location) }}" class="styledesk_rowmenu__item" role="menuitem">
                        <x-icon name="pen-to-square" size="14" /> Edit location
                      </a>
                    @endcan

                    {{-- Only rendered when it is actually permitted. The policy
                         refuses deactivating the primary branch, so that card
                         simply does not offer it rather than offering a control
                         that will be refused. --}}
                    @can('deactivate', $location)
                      <button type="button" class="styledesk_rowmenu__item styledesk_rowmenu__item--danger"
                              role="menuitem"
                              data-set-status
                              data-name="{{ $location->name }}"
                              data-status="inactive"
                              data-action="{{ route('settings.locations.status', $location) }}">
                        <x-icon name="calendar-xmark" size="14" /> Deactivate location
                      </button>
                    @endcan

                    @can('activate', $location)
                      <button type="button" class="styledesk_rowmenu__item" role="menuitem"
                              data-set-status
                              data-name="{{ $location->name }}"
                              data-status="active"
                              data-action="{{ route('settings.locations.status', $location) }}">
                        <x-icon name="calendar-check" size="14" /> Activate location
                      </button>
                    @endcan
                  </span>
                </span>
              </div>

              <dl class="mt-3.5 space-y-3 text-[13px]">
                <div>
                  <dt class="sr-only">Address</dt>
                  <dd class="text-sub leading-relaxed">
                    {{-- Two lines, the way an address is written on an
                         envelope. One long comma-joined string is what the
                         table row needed; a card has the height to do it
                         properly. --}}
                    <span class="block">{{ collect([$location->address_line1, $location->address_line2, $location->suite])->filter()->join(', ') }}</span>
                    <span class="block">{{ collect([$location->city, $location->state, $location->postal_code])->filter()->join(', ') }}</span>
                  </dd>
                </div>

                <div>
                  <dt class="text-[12px] text-sub">Manager</dt>
                  {{-- "Not assigned", not a dash. A branch with no manager is
                       a decision someone has yet to make, and an em dash reads
                       as a field that failed to load. --}}
                  <dd class="text-ink truncate">{{ $location->manager?->displayName() ?? 'Not assigned' }}</dd>
                </div>

                <div>
                  <dt class="text-[12px] text-sub">Contact</dt>
                  <dd class="text-ink truncate">{{ $location->phone ?: 'No phone number' }}</dd>
                  @if ($location->email)
                    <dd class="text-sub truncate">{{ $location->email }}</dd>
                  @endif
                </div>

                <div>
                  <dt class="text-[12px] text-sub">Today</dt>
                  <dd class="text-ink">{{ $location->todayLabel() }}</dd>
                </div>
              </dl>

              {{-- Holds the foot of a stretched card down, so every card in a
                   row ends on the same line whatever its address ran to. --}}
              <div class="styledesk_locationcard__spacer"></div>

              <p class="mt-3.5 pt-3 border-t border-line text-[12px] text-sub truncate">
                {{ config('locations.timezones.'.$location->timezone, $location->timezone) }}
              </p>
            </div>
          @endforeach
        </div>
      @endif

      {{-- One status form for the grid, not one per card: a form per card is
           a dozen identical elements whose only difference is an action, and
           the confirmation has to name the branch anyway. --}}
      <form id="locationStatusForm" method="POST" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" data-status-field>
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* Card action menus. Identical behaviour to the staff directory's: one
       open at a time, positioned against its button because the panel is
       fixed, and closed by a scroll it cannot follow. */
    (function () {
      var menus = Array.prototype.slice.call(document.querySelectorAll('[data-rowmenu]'));
      if (!menus.length) return;

      function closeAll(except) {
        menus.forEach(function (menu) {
          if (menu === except) return;
          menu.querySelector('[data-rowmenu-pop]').hidden = true;
          menu.querySelector('[data-rowmenu-button]').setAttribute('aria-expanded', 'false');
          menu.classList.remove('is-open');
        });
      }

      menus.forEach(function (menu) {
        var button = menu.querySelector('[data-rowmenu-button]');
        var pop = menu.querySelector('[data-rowmenu-pop]');

        button.addEventListener('click', function (e) {
          /* The whole card is a link. Without this the menu button would
             open the location it belongs to instead of its own menu. */
          e.preventDefault();
          e.stopPropagation();

          var opening = pop.hidden;
          closeAll(menu);
          pop.hidden = !opening;
          button.setAttribute('aria-expanded', opening ? 'true' : 'false');
          menu.classList.toggle('is-open', opening);

          if (opening) place(button, pop);
        });

        /* Same reason: a click anywhere inside the open panel must not fall
           through to the card underneath it. */
        pop.addEventListener('click', function (e) { e.stopPropagation(); });
      });

      function place(button, pop) {
        var rect = button.getBoundingClientRect();

        pop.style.visibility = 'hidden';
        var height = pop.offsetHeight;
        var width = pop.offsetWidth;
        pop.style.visibility = '';

        /* Flip above when there is not room below, so a card in the last row
           does not open its menu into the fold. */
        var below = window.innerHeight - rect.bottom;
        var top = below < height + 12 ? rect.top - height - 4 : rect.bottom + 4;

        pop.style.top = Math.max(8, top) + 'px';
        pop.style.left = Math.max(8, rect.right - width) + 'px';
      }

      document.addEventListener('click', function () { closeAll(null); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll(null);
      });

      window.addEventListener('scroll', function () { closeAll(null); }, true);
      window.addEventListener('resize', function () { closeAll(null); });
    }());

    /* Deactivate and activate, behind a confirmation that names the branch
       and says what changes. */
    (function () {
      var form = document.getElementById('locationStatusForm');
      if (!form) return;

      var field = form.querySelector('[data-status-field]');

      document.querySelectorAll('[data-set-status]').forEach(function (button) {
        button.addEventListener('click', function () {
          var name = button.getAttribute('data-name');
          var status = button.getAttribute('data-status');

          var message = status === 'inactive'
            ? 'Make ' + name + ' inactive? It stops taking new bookings and is hidden from online booking. Its past appointments, transactions and staff history are kept.'
            : 'Make ' + name + ' active again? It can take bookings and will appear in online booking.';

          if (!window.confirm(message)) {
            return;
          }

          field.value = status;
          form.action = button.getAttribute('data-action');
          form.submit();
        });
      });
    }());
  </script>
@endpush
