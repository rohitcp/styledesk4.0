@extends('layouts.app')

@section('title', $location->name)

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Single column, per §13: the same shape as the staff profile and the
         business screen, so a settings page always reads top to bottom rather
         than asking which column to start in. --}}
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.locations.index') }}" class="hover:text-ink transition-colors">{{ __('locations.title') }}</a>
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
              <span class="styledesk_badge styledesk_badge--active">{{ __('locations.fields.primary') }}</span>
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
             class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          @can('update', $location)
            <a href="{{ route('settings.locations.edit', $location) }}"
               class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('locations.edit') }}
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
              {{ __('locations.inactive_notice') }}
            </p>
          </div>
        </div>
      @endunless

      <div class="mt-6 space-y-5">

        <x-settings.card title="{{ __('locations.cards.information') }}">
          <x-settings.field label="{{ __('locations.fields.name') }}" :value="$location->name" />
          <x-settings.field label="{{ __('locations.fields.code') }}" :value="$location->code" />
          <x-settings.field label="{{ __('locations.fields.type') }}" :value="$location->typeLabel()" />
          <x-settings.field label="{{ __('locations.fields.primary') }}" :value="$location->is_primary ? __('common.yes') : __('common.no')" />
          <x-settings.field label="{{ __('locations.fields.status') }}">
            <span class="styledesk_badge {{ $location->statusClass() }}">{{ $location->statusLabel() }}</span>
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="{{ __('locations.cards.address') }}">
          <x-settings.field label="{{ __('locations.fields.address_line1') }}" :value="$location->address_line1" />
          <x-settings.field label="{{ __('locations.fields.address_line2') }}" :value="$location->address_line2" />
          <x-settings.field label="{{ __('locations.fields.suite') }}" :value="$location->suite" />
          <x-settings.field label="{{ __('locations.fields.city') }}" :value="$location->city" />
          <x-settings.field label="{{ __('locations.fields.state') }}" :value="$location->state" />
          <x-settings.field label="{{ __('locations.fields.postal_code') }}" :value="$location->postal_code" />
          <x-settings.field label="{{ __('locations.fields.country') }}" :value="$location->countryName()" />
          <x-settings.field label="{{ __('locations.fields.timezone') }}">
            @if ($location->timezone)
              {{-- Both halves: the offset name is what people recognise, the
                   IANA identifier is what is actually stored. --}}
              {{ config('locations.timezones.'.$location->timezone, $location->timezone) }}
              <span class="text-sub">— {{ $location->timezone }}</span>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="{{ __('locations.cards.contact') }}"
                         description="{{ __('locations.cards.contact_hint') }}">
          <x-settings.field label="{{ __('locations.fields.phone_short') }}" :value="$location->phone" />
          <x-settings.field label="{{ __('locations.fields.phone_secondary') }}" :value="$location->phone_secondary" />
          <x-settings.field label="{{ __('locations.fields.extension') }}" :value="$location->extension" />
          <x-settings.field label="{{ __('locations.fields.email') }}" :value="$location->email" />
          <x-settings.field label="{{ __('locations.fields.booking_email') }}" :value="$location->booking_email" />
          <x-settings.field label="{{ __('locations.fields.support_email') }}" :value="$location->support_email" />
          <x-settings.field label="{{ __('locations.fields.contact_person') }}" :value="$location->contact_person" />
          <x-settings.field label="{{ __('locations.fields.website') }}">
            @if ($location->website)
              <a href="{{ $location->website }}" target="_blank" rel="noopener noreferrer"
                 class="text-link hover:underline break-all">{{ $location->website }}</a>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="{{ __('locations.cards.manager') }}"
                         description="{{ __('locations.cards.manager_hint_short') }}">
          <x-settings.field label="{{ __('locations.fields.manager') }}"
                            :manage="route('settings.roles.index')" manage-label="{{ __('locations.elsewhere.link_permissions') }}">
            @if ($location->manager)
              <a href="{{ route('settings.staff.show', $location->manager) }}" class="text-link hover:underline">
                {{ $location->manager->displayName() }}
              </a>
              @if ($location->manager->job_title)
                <span class="text-sub">— {{ $location->manager->job_title }}</span>
              @endif
            @else
              <span class="text-faint">{{ __('locations.not_assigned') }}</span>
            @endif
          </x-settings.field>

          <x-settings.field label="{{ __('locations.fields.assistants') }}">
            @if ($location->assistantManagers->isNotEmpty())
              <span class="flex flex-col gap-1">
                @foreach ($location->assistantManagers as $assistant)
                  <a href="{{ route('settings.staff.show', $assistant) }}" class="text-link hover:underline">
                    {{ $assistant->displayName() }}
                  </a>
                @endforeach
              </span>
            @else
              <span class="text-faint">{{ __('common.none') }}</span>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="{{ __('locations.cards.hours') }}"
                         description="{{ __('locations.cards.hours_hint', ['timezone' => $location->timezone]) }}">
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
                    <span class="block text-[11px] font-normal text-sub">{{ __('locations.fields.today') }}</span>
                  @endif
                </dt>
                <dd class="min-w-0 flex-1 text-[14px] text-head">
                  @if ($day['periods']->isEmpty())
                    <span class="text-faint">{{ __('locations.closed') }}</span>
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
        <x-settings.card title="{{ __('locations.cards.elsewhere') }}"
                         description="{{ __('locations.cards.elsewhere_hint') }}">
          <x-settings.field label="{{ __('locations.elsewhere.holidays') }}"
                            value="{{ __('locations.elsewhere.holidays_value') }}"
                            :manage="route('settings.index')" manage-label="{{ __('locations.elsewhere.link_hours') }}" />
          <x-settings.field label="{{ __('locations.elsewhere.staff') }}"
                            :value="trans_choice('locations.elsewhere.staff_value', $location->staff->count(), ['count' => $location->staff->count()])"
                            :manage="route('settings.staff.index', ['location' => $location->id])" manage-label="{{ __('locations.elsewhere.link_staff') }}" />
          <x-settings.field label="{{ __('locations.elsewhere.services') }}"
                            value="{{ __('locations.elsewhere.services_value') }}"
                            :manage="route('settings.index')" manage-label="{{ __('locations.elsewhere.link_services') }}" />
          <x-settings.field label="{{ __('locations.elsewhere.resources') }}" value="{{ __('locations.elsewhere.resources_value') }}" />
          <x-settings.field label="{{ __('locations.elsewhere.booking') }}"
                            value="{{ __('locations.elsewhere.uses_business') }}"
                            :manage="route('settings.business.show')" manage-label="{{ __('locations.elsewhere.link_business') }}" />
          <x-settings.field label="{{ __('locations.elsewhere.currency') }}"
                            value="{{ __('locations.elsewhere.uses_business') }}"
                            :manage="route('settings.business.show')" manage-label="{{ __('locations.elsewhere.link_business') }}" />
        </x-settings.card>

      </div>
    </div>
  </main>
@endsection
