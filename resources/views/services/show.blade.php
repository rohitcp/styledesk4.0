@extends('layouts.app')

@section('title', $service->name)

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    {{-- Single column, the same shape as the location and staff detail pages:
         a record is read top to bottom, and a second column is a second place
         to start reading. --}}
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('services.index') }}" class="hover:text-ink transition-colors">{{ __('services.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $service->name }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2.5">
            @if ($service->color)
              <span class="styledesk_servicedot shrink-0" style="--service-color: {{ $service->color }}" aria-hidden="true"></span>
            @endif
            <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight min-w-0">{{ $service->name }}</h1>
          </div>

          <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="styledesk_badge {{ $service->is_active ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
              {{ $service->is_active ? __('services.status.active') : __('services.status.inactive') }}
            </span>

            <span class="styledesk_badge {{ $service->online_booking_enabled ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
              {{ $service->online_booking_enabled ? __('services.status.online_enabled') : __('services.status.online_disabled') }}
            </span>

            <span class="text-[12px] text-sub">{{ $service->category?->name ?? __('services.uncategorised') }}</span>
          </div>
        </div>

        {{-- Back beside Edit as one action group, secondary first — the same
             pair, in the same order, as every other detail page. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('services.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          @if ($canEdit)
            <a href="{{ route('services.edit', $service) }}"
               class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('services.edit') }}
            </a>
          @endif
        </div>
      </div>

      @unless ($service->is_active)
        {{-- Said once at the top rather than repeated beside every field it
             affects. What "retired" means is the part people do not know: it
             stops new bookings and changes nothing that already happened. --}}
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <div class="flex items-start gap-2.5">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
            <p class="min-w-0">{{ __('services.retired_notice') }}</p>
          </div>
        </div>
      @endunless

      <div class="mt-6 space-y-5">

        <x-settings.card title="{{ __('services.section.about') }}">
          <x-settings.field label="{{ __('services.name') }}" :value="$service->name" />
          <x-settings.field label="{{ __('services.category') }}" :value="$service->category?->name" />
          <x-settings.field label="{{ __('services.description') }}" :value="$service->description" />
          <x-settings.field label="{{ __('services.columns.status') }}">
            <span class="styledesk_badge {{ $service->is_active ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
              {{ $service->is_active ? __('services.status.active') : __('services.status.inactive') }}
            </span>
          </x-settings.field>
        </x-settings.card>

        {{-- The gallery, read-only. The default leads, marked, because which
             picture a client meets first is a decision this page has to be
             able to answer without opening the form. Rendered only when there
             is something to show: an empty card is a question about whether
             the feature is broken. --}}
        @php($serviceImages = $service->orderedImages())
        @if ($serviceImages->isNotEmpty())
          @php($storage = app(\App\Contracts\TenantStorageContract::class))
          <x-settings.card title="{{ __('services.images.label') }}">
            <ul class="grid grid-cols-3 sm:grid-cols-4 gap-2.5">
              @foreach ($serviceImages as $image)
                <li class="relative">
                  <div class="aspect-square rounded-lg overflow-hidden border bg-hover
                              {{ $image->id === $service->image_file_id ? 'border-brand ring-1 ring-brand' : 'border-line' }}">
                    <img src="{{ $storage->url($image) }}" alt="{{ $image->original_filename }}"
                         class="w-full h-full object-cover" loading="lazy">
                  </div>
                  @if ($image->id === $service->image_file_id)
                    <span class="absolute top-1 left-1 px-1.5 h-5 inline-flex items-center rounded bg-brand text-white text-[10px] font-semibold">
                      {{ __('services.images.default') }}
                    </span>
                  @endif
                </li>
              @endforeach
            </ul>
          </x-settings.card>
        @endif

        <x-settings.card title="{{ __('services.section.price') }}">
          {{-- A row per currency the business prices in, rather than one
               number and a guess at which money it is. --}}
          @foreach ($currencies as $code)
            <x-settings.field label="{{ __('services.price') }} — {{ $code }}"
                              :value="$service->priceLabel($code) ?: null" />
          @endforeach

          {{-- What was actually configured, not a yes: "20% required" and
               "yes" are different answers, and the page is being asked which
               deposit rather than whether. Read from the price row, which is
               where the form wrote it. --}}
          @foreach ($currencies as $code)
            <x-settings.field label="{{ __('services.deposit_required') }} — {{ $code }}"
                              :value="$service->depositLabelIn($code) ?: __('common.no')" />
          @endforeach
        </x-settings.card>

        <x-settings.card title="{{ __('services.section.booking') }}"
                         description="{{ __('services.total_time', ['duration' => $service->durationLabel($service->bookedMinutes())]) }}">
          <x-settings.field label="{{ __('services.duration') }}" :value="$service->durationLabel()" />
          <x-settings.field label="{{ __('services.preparation') }}" :value="$service->durationLabel($service->preparation_minutes)" />
          <x-settings.field label="{{ __('services.processing') }}" :value="$service->durationLabel($service->processing_minutes)" />
          <x-settings.field label="{{ __('services.cleanup') }}" :value="$service->durationLabel($service->cleanup_minutes)" />
          <x-settings.field label="{{ __('services.buffer') }}" :value="$service->durationLabel($service->buffer_minutes)" />

          <x-settings.field label="{{ __('services.color') }}">
            @if ($service->color)
              <span class="inline-flex items-center gap-2">
                <span class="styledesk_swatchpick__dot" style="--service-color: {{ $service->color }}" aria-hidden="true"></span>
                <span class="font-mono text-[12px] text-sub">{{ $service->color }}</span>
              </span>
            @endif
          </x-settings.field>

          <x-settings.field label="{{ __('services.online_booking') }}">
            <span class="styledesk_badge {{ $service->online_booking_enabled ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
              {{ $service->online_booking_enabled ? __('services.status.online_enabled') : __('services.status.online_disabled') }}
            </span>
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="{{ __('services.staff') }}">
          <x-settings.field label="{{ __('services.columns.staff') }}">
            {{-- Named in full here, where there is room: the listing shows a
                 count because a table row has none. --}}
            @if ($service->staff->isEmpty())
              {{ __('services.anyone') }}
            @else
              <span class="flex flex-wrap gap-1.5">
                @foreach ($service->staff as $member)
                  <span class="styledesk_metachip">{{ $member->first_name }} {{ $member->last_name }}</span>
                @endforeach
              </span>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="{{ __('services.locations') }}">
          <x-settings.field label="{{ __('services.columns.location') }}">
            @if ($service->locations->isNotEmpty())
              <span class="flex flex-wrap gap-1.5">
                @foreach ($service->locations as $location)
                  <span class="styledesk_metachip">{{ $location->name }}</span>
                @endforeach
              </span>
            @endif
          </x-settings.field>
        </x-settings.card>

        <x-settings.card title="{{ __('services.columns.resource') }}"
                         description="{{ __('services.requires_resource_hint') }}">
          <x-settings.field label="{{ __('services.requires_resource') }}"
                            :value="$service->requires_resource ? __('services.resource_required') : __('services.resource_not_required')" />

          {{-- The rooms themselves, named. Only while the switch is on: a
               list of resources under "does not need one" reads as a
               contradiction, and the mapping is kept precisely so it can be
               left alone. Chips, as the locations above are. --}}
          @if ($service->requires_resource)
            <x-settings.field label="{{ __('services.resources') }}">
              @if ($service->resources->isEmpty())
                {{ __('services.resources_none') }}
              @else
                <span class="flex flex-wrap gap-1.5">
                  @foreach ($service->resources as $resource)
                    <span class="styledesk_metachip">{{ $resource->name }}</span>
                  @endforeach
                </span>
              @endif
            </x-settings.field>
          @endif
        </x-settings.card>
      </div>
    </div>
  </main>
@endsection
