@extends('layouts.app')

@section('title', 'Locations')

@section('content')
  {{-- pb-[200px]: the list ends on a card, and a card flush against the footer
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
            {{ $summary }}. Branches, addresses, managers, operating hours and contact details.
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
               class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              Add location
            </a>
          @endcan
        </div>
      </div>

      {{-- Search and a status filter, and nothing else. A business has a
           handful of branches, not a directory of them: the seven-control
           filter panel the staff list needs would be larger than the list. --}}
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

      @if ($locations->isEmpty())
        <div class="mt-6 rounded-card border border-line bg-white p-10 text-center">
          <p class="text-[15px] font-semibold text-head">
            {{ $totalCount === 0 ? 'No locations yet.' : 'No locations match your search.' }}
          </p>
          <p class="text-[13px] text-sub mt-1.5">
            {{ $totalCount === 0
                ? 'Add the branch your business operates from so staff, services and bookings have somewhere to belong.'
                : 'Try a different word, or clear the filters.' }}
          </p>
        </div>
      @else
        {{-- A single-column list of cards rather than a table. Each branch
             carries an address, a manager and today's hours — three things
             that read as sentences and would be squeezed into unreadable
             columns by a table wide enough to hold them all. --}}
        <div class="mt-6 space-y-3">
          @foreach ($locations as $location)
            <div class="rounded-card border border-line bg-white p-5">
              <div class="flex flex-wrap items-start gap-4">
                <span class="styledesk_settingcard__icon shrink-0" aria-hidden="true">
                  <x-icon name="location-dot" size="18" />
                </span>

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('settings.locations.show', $location) }}"
                       class="text-[15px] font-semibold text-head hover:text-link transition-colors">
                      {{ $location->name }}
                    </a>

                    @if ($location->code)
                      <span class="text-[12px] text-sub font-mono">{{ $location->code }}</span>
                    @endif

                    @if ($location->is_primary)
                      <span class="styledesk_badge styledesk_badge--active">Primary</span>
                    @endif

                    <span class="styledesk_badge {{ $location->statusClass() }}">{{ $location->statusLabel() }}</span>

                    @if ($location->typeLabel())
                      <span class="text-[12px] text-sub">{{ $location->typeLabel() }}</span>
                    @endif
                  </div>

                  <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ $location->addressLine() }}</p>

                  <dl class="mt-3 grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3 text-[13px]">
                    <div>
                      <dt class="text-[12px] text-sub">Contact</dt>
                      <dd class="text-ink truncate">{{ $location->phone ?: '—' }}</dd>
                      @if ($location->email)
                        <dd class="text-[12px] text-sub truncate">{{ $location->email }}</dd>
                      @endif
                    </div>

                    <div>
                      <dt class="text-[12px] text-sub">Location manager</dt>
                      {{-- "Not assigned", not a dash. A branch with no manager
                           is a decision someone has yet to make, and an em
                           dash reads as a field that failed to load. --}}
                      <dd class="text-ink truncate">{{ $location->manager?->displayName() ?? 'Not assigned' }}</dd>
                    </div>

                    <div>
                      <dt class="text-[12px] text-sub">Today</dt>
                      <dd class="text-ink">{{ $location->todayLabel() }}</dd>
                      <dd class="text-[12px] text-sub truncate">{{ $location->timezone }}</dd>
                    </div>
                  </dl>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                  <a href="{{ route('settings.locations.show', $location) }}"
                     class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                    View
                  </a>

                  @can('update', $location)
                    <a href="{{ route('settings.locations.edit', $location) }}"
                       class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                      <x-icon name="pen-to-square" size="14" /> Edit
                    </a>
                  @endcan
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </main>
@endsection
