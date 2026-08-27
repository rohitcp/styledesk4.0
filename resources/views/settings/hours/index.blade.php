@extends('layouts.app')

@section('title', 'Business hours')

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="max-w-[1180px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Business hours</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Business hours</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            Opening hours for each location, plus the holidays, closures and special hours that override them.
            Times are shown in each location's own time zone.
          </p>
        </div>

        <a href="{{ route('settings.index') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back
        </a>
      </div>

      @if ($locations->isEmpty())
        <div class="mt-6 rounded-card border border-line bg-white p-10 text-center">
          <p class="text-[15px] font-semibold text-head">No locations yet.</p>
          <p class="text-[13px] text-sub mt-1.5">Opening hours belong to a location, so add one first.</p>
          <a href="{{ route('settings.locations.create') }}"
             class="mt-4 inline-flex items-center h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            Add a location
          </a>
        </div>
      @else

        {{-- What is coming up, before the weekly pattern.
             The regular week is the thing people already know; the exceptions
             are what they came to check. Putting the calendar first is the
             difference between a page that answers a question and one that
             makes you scroll past the answer you already knew. --}}
        @if ($upcoming->isNotEmpty())
          <section class="mt-6 bg-white border border-line rounded-card overflow-hidden">
            <div class="px-5 py-4 border-b border-line">
              <h2 class="text-[15px] font-semibold text-head">Coming up</h2>
              <p class="text-[13px] text-sub mt-0.5">
                Holidays, closures and special hours in the next 12 months.
              </p>
            </div>

            <ul class="divide-y divide-line">
              @foreach ($upcoming as $closure)
                <li class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                  <span class="w-[130px] shrink-0 text-[13px] font-medium text-head">{{ $closure->dateLabel() }}</span>

                  <span class="min-w-0 flex-1">
                    <span class="block text-[13px] text-ink truncate">{{ $closure->name }}</span>
                    <span class="block text-[12px] text-sub truncate">
                      {{ $closure->typeLabel() }} · {{ $closure->location->name }}
                    </span>
                  </span>

                  @if ($closure->isInProgress())
                    {{-- Distinguished from the merely scheduled: something
                         happening right now is the one line on this list a
                         person may need to act on today. --}}
                    <span class="styledesk_badge styledesk_badge--setup">In progress</span>
                  @endif

                  <span class="text-[13px] text-sub">{{ $closure->hoursLabel() }}</span>
                </li>
              @endforeach
            </ul>
          </section>
        @endif

        <div class="mt-5 space-y-5">
          @foreach ($locations as $location)
            @php
                $locationClosures = $closuresByLocation->get($location->id, collect());
                $byDay = $location->hours->groupBy('day_of_week');
                $futureSchedules = $location->futureScheduleDates();
            @endphp

            <section class="bg-white border border-line rounded-card overflow-hidden">
              <div class="px-5 py-4 border-b border-line flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                  <h2 class="text-[15px] font-semibold text-head">
                    {{ $location->name }}
                    @if ($location->is_primary)
                      <span class="styledesk_badge styledesk_badge--active ml-1.5">Primary</span>
                    @endif
                    @unless ($location->isActive())
                      <span class="styledesk_badge styledesk_badge--soon ml-1.5">Inactive</span>
                    @endunless
                  </h2>
                  <p class="text-[13px] text-sub mt-0.5">
                    {{ config('locations.timezones.'.$location->timezone, $location->timezone) }}
                    <span class="text-faint">— {{ $location->timezone }}</span>
                  </p>
                </div>

                @can('manageHours', $location)
                  <a href="{{ route('settings.hours.edit', $location) }}"
                     class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                    <x-icon name="pen-to-square" size="14" />
                    Edit hours
                  </a>
                @endcan
              </div>

              @if ($futureSchedules->isNotEmpty())
                {{-- Announced, not left to be discovered on the day it takes
                     effect. A business that entered next month's hours and
                     forgot should be reminded before the first of the month,
                     not surprised by it. --}}
                <div class="px-5 py-2.5 bg-hover/60 border-b border-line text-[13px] text-sub">
                  New hours start
                  {{ \Illuminate\Support\Carbon::parse($futureSchedules->first())->format('j F Y') }}.
                  <a href="{{ route('settings.hours.edit', ['location' => $location, 'schedule' => $futureSchedules->first()]) }}"
                     class="text-link hover:underline font-medium">Review them</a>
                </div>
              @endif

              <dl class="px-5 py-1">
                @foreach (App\Support\LocationOptions::weekdays() as $day => $label)
                  @php
                      $periods = $byDay->get($day, collect());
                      $isToday = $day === now($location->timezone ?: config('app.timezone'))->dayOfWeek;
                  @endphp
                  <div class="flex items-start gap-4 py-2.5 border-b border-line last:border-0">
                    <dt class="w-[110px] shrink-0 text-[13px] {{ $isToday ? 'font-semibold text-head' : 'text-sub' }}">
                      {{ $label }}
                      @if ($isToday)
                        <span class="block text-[11px] font-normal text-sub">Today</span>
                      @endif
                    </dt>
                    <dd class="min-w-0 flex-1 text-[14px] text-head">
                      @if ($periods->isEmpty())
                        <span class="text-faint">Closed</span>
                      @else
                        {{-- Each period on its own line. A split day joined by
                             a comma reads as one long opening with a typo in
                             it. --}}
                        @foreach ($periods as $period)
                          <span class="block">{{ $period->rangeLabel() }}</span>
                        @endforeach
                      @endif
                    </dd>
                  </div>
                @endforeach
              </dl>

              @if ($locationClosures->isNotEmpty())
                @php
                    /**
                     * Built here rather than with an inline directive.
                     *
                     * Blade only recognises a directive when the character
                     * before the @ is not a word character, so "more" followed
                     * by the closing directive leaves it uncompiled and prints
                     * it on the page.
                     */
                    $shown = $locationClosures->take(3)
                        ->map(fn ($c) => $c->name.' ('.$c->dateLabel().')')
                        ->join(', ');

                    $remaining = $locationClosures->count() - 3;

                    if ($remaining > 0) {
                        $shown .= ', and '.$remaining.' more';
                    }
                @endphp

                <div class="px-5 py-3 border-t border-line">
                  <p class="text-[12px] text-sub">
                    <span class="font-semibold text-ink">{{ $locationClosures->count() }}</span>
                    upcoming {{ Str::plural('exception', $locationClosures->count()) }} — {{ $shown }}.
                  </p>
                </div>
              @endif
            </section>
          @endforeach
        </div>
      @endif
    </div>
  </main>
@endsection
