@extends('layouts.app')

@section('title', $location->name)

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Single column, per §13: the same shape as the staff profile and the
         business screen, so a settings page always reads top to bottom rather
         than asking which column to start in. --}}
    <div class="max-w-[760px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.locations.index') }}" class="hover:text-ink transition-colors">Locations</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $location->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ $location->name }}</h1>

          <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($location->code)
              <span class="text-[12px] text-sub font-mono">{{ $location->code }}</span>
            @endif
            @if ($location->is_primary)
              <span class="styledesk_badge styledesk_badge--active">Primary location</span>
            @endif
            <span class="styledesk_badge {{ $location->statusClass() }}">{{ $location->statusLabel() }}</span>
            @if ($location->typeLabel())
              <span class="text-[12px] text-sub">{{ $location->typeLabel() }}</span>
            @endif
          </div>
        </div>

        {{-- Back beside Edit as one action group, secondary first. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.locations.index') }}"
             class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Back
          </a>

          @can('update', $location)
            <a href="{{ route('settings.locations.edit', $location) }}"
               class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              Edit location
            </a>
          @endcan
        </div>
      </div>

      @unless ($location->isActive())
        {{-- Said once at the top rather than repeated beside every field it
             affects. What "inactive" means is the part people do not know. --}}
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <div class="flex items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p class="min-w-0">
              This location is inactive. It takes no new bookings and is hidden from online booking.
              Its past appointments, transactions and staff history are unchanged.
            </p>
          </div>
        </div>
      @endunless

      <div class="mt-6 space-y-5">

        <x-settings.card title="Location information">
          <x-settings.field label="Location name" :value="$location->name" />
          <x-settings.field label="Location code" :value="$location->code" />
          <x-settings.field label="Location type" :value="$location->typeLabel()" />
          <x-settings.field label="Primary location" :value="$location->is_primary ? 'Yes' : 'No'" />
          <x-settings.field label="Status">
            <span class="styledesk_badge {{ $location->statusClass() }}">{{ $location->statusLabel() }}</span>
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="Address">
          <x-settings.field label="Address line 1" :value="$location->address_line1" />
          <x-settings.field label="Address line 2" :value="$location->address_line2" />
          <x-settings.field label="Suite / unit" :value="$location->suite" />
          <x-settings.field label="City" :value="$location->city" />
          <x-settings.field label="State / province" :value="$location->state" />
          <x-settings.field label="ZIP / postal code" :value="$location->postal_code" />
          <x-settings.field label="Country" :value="$location->countryName()" />
          <x-settings.field label="Time zone">
            @if ($location->timezone)
              {{-- Both halves: the offset name is what people recognise, the
                   IANA identifier is what is actually stored. --}}
              {{ config('locations.timezones.'.$location->timezone, $location->timezone) }}
              <span class="text-sub">— {{ $location->timezone }}</span>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="Contact details"
                         description="Used instead of the main business contact details wherever this branch is named.">
          <x-settings.field label="Main phone" :value="$location->phone" />
          <x-settings.field label="Secondary phone" :value="$location->phone_secondary" />
          <x-settings.field label="Internal extension" :value="$location->extension" />
          <x-settings.field label="Location email" :value="$location->email" />
          <x-settings.field label="Booking contact email" :value="$location->booking_email" />
          <x-settings.field label="Customer service email" :value="$location->support_email" />
          <x-settings.field label="Contact person" :value="$location->contact_person" />
          <x-settings.field label="Website">
            @if ($location->website)
              <a href="{{ $location->website }}" target="_blank" rel="noopener noreferrer"
                 class="text-link hover:underline break-all">{{ $location->website }}</a>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="Location manager"
                         description="Who is responsible for this branch. This does not change what they can do in StyleDesk.">
          <x-settings.field label="Location manager"
                            :manage="route('settings.roles.index')" manage-label="Permissions →">
            @if ($location->manager)
              <a href="{{ route('settings.staff.show', $location->manager) }}" class="text-link hover:underline">
                {{ $location->manager->displayName() }}
              </a>
              @if ($location->manager->job_title)
                <span class="text-sub">— {{ $location->manager->job_title }}</span>
              @endif
            @else
              <span class="text-faint">Not assigned</span>
            @endif
          </x-settings.field>

          <x-settings.field label="Assistant managers">
            @if ($location->assistantManagers->isNotEmpty())
              <span class="flex flex-col gap-1">
                @foreach ($location->assistantManagers as $assistant)
                  <a href="{{ route('settings.staff.show', $assistant) }}" class="text-link hover:underline">
                    {{ $assistant->displayName() }}
                  </a>
                @endforeach
              </span>
            @else
              <span class="text-faint">None</span>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="Business hours"
                         description="Times are in this location's own time zone, {{ $location->timezone }}.">
          <dl class="mt-1">
            @foreach ($hoursByDay as $dayNumber => $day)
              @php
                  // The day the location is actually having right now, which
                  // is not necessarily the reader's day.
                  $isToday = $dayNumber === now($location->timezone ?: config('app.timezone'))->dayOfWeek;
              @endphp
              <div class="flex items-start gap-4 py-2.5 border-b border-line last:border-0">
                <dt class="w-[110px] shrink-0 text-[13px] {{ $isToday ? 'font-semibold text-head' : 'text-sub' }}">
                  {{ $day['label'] }}
                  @if ($isToday)
                    <span class="block text-[11px] font-normal text-sub">Today</span>
                  @endif
                </dt>
                <dd class="min-w-0 flex-1 text-[14px] text-head">
                  @if ($day['periods']->isEmpty())
                    <span class="text-faint">Closed</span>
                  @else
                    {{-- Each period on its own line. A split day joined by a
                         comma reads as one long opening with a typo in it. --}}
                    @foreach ($day['periods'] as $period)
                      <span class="block">{{ $period->rangeLabel() }}</span>
                    @endforeach
                  @endif
                </dd>
              </div>
            @endforeach
          </dl>
        </x-settings.card>

        {{-- What this branch does not configure yet.
             One card saying where these settings currently come from, rather
             than five empty cards implying they were configured here and left
             blank. Each names the module that owns it today. --}}
        <x-settings.card title="Configured elsewhere"
                         description="These follow the business-wide settings until per-location overrides arrive.">
          <x-settings.field label="Holidays & special hours"
                            value="Follows business hours"
                            :manage="route('settings.index')" manage-label="Business hours →" />
          <x-settings.field label="Staff assigned"
                            :value="$location->staff->count().' '.Str::plural('staff member', $location->staff->count()).' have this as their primary location'"
                            :manage="route('settings.staff.index', ['location' => $location->id])" manage-label="Staff →" />
          <x-settings.field label="Services offered"
                            value="All services the business offers"
                            :manage="route('settings.index')" manage-label="Services →" />
          <x-settings.field label="Resources & rooms" value="Not configured yet" />
          <x-settings.field label="Booking settings"
                            value="Uses business settings"
                            :manage="route('settings.business.show')" manage-label="Business →" />
          <x-settings.field label="Currency & language"
                            value="Uses business settings"
                            :manage="route('settings.business.show')" manage-label="Business →" />
        </x-settings.card>

      </div>
    </div>
  </main>
@endsection
