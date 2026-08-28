@extends('layouts.app')

@section('title', $resource->name)

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Single column, the same shape as the service and location detail
         pages: a record is read top to bottom. --}}
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('resources.index') }}" class="hover:text-ink transition-colors">{{ __('resources.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $resource->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2.5">
            @if ($resource->color)
              <span class="styledesk_servicedot shrink-0" style="--service-color: {{ $resource->color }}" aria-hidden="true"></span>
            @endif
            <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight min-w-0">{{ $resource->name }}</h1>
          </div>

          <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($resource->code)
              <span class="text-[12px] text-sub font-mono">{{ $resource->code }}</span>
            @endif

            <span class="styledesk_badge {{ $resource->availabilityStatus() === 'available' ? 'styledesk_badge--active' : ($resource->availabilityStatus() === 'blocked' ? 'styledesk_badge--setup' : 'styledesk_badge--soon') }}">
              {{ $resource->availabilityLabel() }}
            </span>

            <span class="text-[12px] text-sub">{{ $resource->category?->name ?? __('resources.uncategorised') }}</span>
          </div>
        </div>

        {{-- Back beside Edit as one action group, secondary first. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('resources.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          @if ($canEdit)
            <a href="{{ route('resources.edit', $resource) }}"
               class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('resources.edit') }}
            </a>
          @endif
        </div>
      </div>

      @if ($block)
        {{-- Why it is out and when it is back, once at the top rather than
             repeated beside every field the block affects. --}}
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <div class="flex items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p class="min-w-0">
              {{ $block->reasonLabel() }} ·
              {{ $block->ends_at
                  ? __('resources.blocked_until', ['date' => $block->ends_at->isoFormat('D MMM Y')])
                  : __('resources.blocked_indefinitely') }}
            </p>
          </div>
        </div>
      @endif

      <div class="mt-6 space-y-5">

        <x-settings.card title="{{ __('resources.section.about') }}">
          <x-settings.field label="{{ __('resources.name') }}" :value="$resource->name" />
          <x-settings.field label="{{ __('resources.form.code') }}" :value="$resource->code" />
          <x-settings.field label="{{ __('resources.category') }}" :value="$resource->category?->name" />
          <x-settings.field label="{{ __('resources.form.color') }}">
            @if ($resource->color)
              <span class="inline-flex items-center gap-2">
                <span class="styledesk_swatchpick__dot" style="--service-color: {{ $resource->color }}" aria-hidden="true"></span>
                <span class="font-mono text-[12px] text-sub">{{ $resource->color }}</span>
              </span>
            @endif
          </x-settings.field>

          <x-settings.field label="{{ __('resources.description') }}" :value="$resource->description" />
        </x-settings.card>

        <x-settings.card title="{{ __('resources.form.place') }}">
          <x-settings.field label="{{ __('resources.location') }}" :value="$resource->location?->name" />
          <x-settings.field label="{{ __('resources.capacity') }}"
                            :value="$resource->capacity === 1
                                ? __('resources.holds_one')
                                : __('resources.holds_many', ['count' => $resource->capacity])" />
        </x-settings.card>

        <x-settings.card title="{{ __('resources.form.status') }}">
          <x-settings.field label="{{ __('resources.form.resource_status') }}">
            <span class="styledesk_badge {{ $resource->is_active ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
              {{ $resource->is_active ? __('resources.form.active') : __('resources.form.inactive') }}
            </span>
          </x-settings.field>

          <x-settings.field label="{{ __('resources.form.availability_status') }}"
                            :value="__('resources.form.availability.'.$resource->availability_status)" />
        </x-settings.card>

        <x-settings.card title="{{ __('resources.form.availability_schedule') }}">
          <x-settings.field label="{{ __('resources.form.availability_schedule') }}"
                            :value="__('resources.form.type.'.$resource->availability_type)" />

          @if ($resource->hasCustomHours())
            @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
              @php $hours = $resource->hours->firstWhere('day', $day); @endphp

              <x-settings.field label="{{ __('locations.weekdays.'.$day) }}">
                @if ($hours && $hours->is_available && $hours->starts_at && $hours->ends_at)
                  {{ substr((string) $hours->starts_at, 0, 5) }} – {{ substr((string) $hours->ends_at, 0, 5) }}
                @else
                  <span class="text-faint">{{ __('resources.form.closed') }}</span>
                @endif
              </x-settings.field>
            @endforeach
          @endif
        </x-settings.card>

        <x-settings.card title="{{ __('resources.form.booking') }}">
          <x-settings.field label="{{ __('resources.form.interval') }}"
                            :value="$resource->booking_interval_minutes
                                ? __('resources.form.minutes', ['count' => $resource->booking_interval_minutes])
                                : __('resources.form.inherit')" />
          <x-settings.field label="{{ __('resources.form.preparation') }}" :value="__('resources.form.minutes', ['count' => $resource->preparation_minutes])" />
          <x-settings.field label="{{ __('resources.form.cleanup') }}" :value="__('resources.form.minutes', ['count' => $resource->cleanup_minutes])" />
          <x-settings.field label="{{ __('resources.form.buffer') }}" :value="__('resources.form.minutes', ['count' => $resource->buffer_minutes])" />
        </x-settings.card>

        <x-settings.card title="{{ __('resources.form.services') }}">
          <x-settings.field label="{{ __('resources.form.assigned_services') }}">
            @if ($resource->services->isEmpty())
              {{ __('resources.form.assigned_services_hint') }}
            @else
              <span class="flex flex-wrap gap-1.5">
                @foreach ($resource->services as $service)
                  <span class="styledesk_metachip">{{ $service->name }}</span>
                @endforeach
              </span>
            @endif
          </x-settings.field>
        </x-settings.card>

        @if ($resource->internal_notes)
          <x-settings.card title="{{ __('resources.form.notes') }}"
                           description="{{ __('resources.form.internal_notes_hint') }}">
            <x-settings.field label="{{ __('resources.form.internal_notes') }}" :value="$resource->internal_notes" />
          </x-settings.card>
        @endif
      </div>
    </div>
  </main>
@endsection
