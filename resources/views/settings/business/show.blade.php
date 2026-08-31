@extends('layouts.app')

@section('title', __('business.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[1180px]">

      @php
          $formats = config('business_profile');
          $tz = $tenant->timezone ?? optional($primaryLocation)->timezone;
          $currencyNames = config('currencies.currencies');
          $languageNames = config('currencies.languages');
      @endphp

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('business.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('business.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            {{ __('business.intro') }}
          </p>
        </div>

        {{-- Back and Edit sit together as one action group. Secondary first,
             so the eye lands on the primary action last and closest to the
             edge it will click. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}"
             class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          <a href="{{ route('settings.business.edit') }}"
             class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('business.edit') }}
          </a>
        </div>
      </div>

      {{-- Two columns on desktop, stacked below. Identity and how to reach the
           business on the left; the settings that are summarised from other
           modules on the right. --}}
      <div class="mt-6 grid gap-5 lg:grid-cols-2 items-start">

        <div class="space-y-5">

          <x-settings.card title="{{ __('business.cards.information') }}">
            <x-settings.field label="{{ __('business.fields.name') }}" :value="$tenant->name" />
            <x-settings.field label="{{ __('business.fields.legal_name') }}" :value="$tenant->legal_name" />

            <x-settings.field label="{{ __('business.fields.business_type') }}">
              @if ($tenant->businessTypes->isNotEmpty())
                <span class="flex flex-wrap gap-1.5">
                  @foreach ($tenant->businessTypes as $type)
                    <span class="styledesk_badge styledesk_badge--soon">{{ $type->label() }}</span>
                  @endforeach
                </span>
              @endif
            </x-settings.field>

            <x-settings.field label="{{ __('business.fields.category') }}" :value="$tenant->business_category" />
            <x-settings.field label="{{ __('business.fields.description') }}" :value="$tenant->description" />

            <x-settings.field label="{{ __('business.fields.logo') }}" :manage="route('settings.index')" manage-label="{{ __('business.manage_branding') }}">
              @if ($tenant->logo_path)
                <img src="{{ Storage::disk('brand')->url($tenant->logo_path) }}" alt="{{ $tenant->name }} logo"
                     class="h-10 w-10 rounded-lg object-cover border border-line">
              @endif
            </x-settings.field>

            <x-settings.field label="{{ __('business.fields.status') }}">
              <span class="styledesk_badge {{ $tenant->status === 'active' ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ $tenant->status === 'active' ? __('common.active') : __('common.inactive') }}
              </span>
            </x-settings.field>
          </x-settings.card>

          <x-settings.card title="{{ __('business.cards.contact') }}">
            <x-settings.field label="{{ __('business.fields.business_email') }}" :value="$tenant->business_email" />
            <x-settings.field label="{{ __('business.fields.business_phone') }}" :value="$tenant->business_phone" />
            <x-settings.field label="{{ __('business.fields.support_email') }}" :value="$tenant->support_email" />
            <x-settings.field label="{{ __('business.fields.booking_email') }}" :value="$tenant->booking_email" />

            <x-settings.field label="{{ __('business.fields.website') }}">
              @if ($tenant->website)
                <a href="{{ $tenant->website }}" target="_blank" rel="noopener noreferrer"
                   class="text-link hover:underline break-all">{{ $tenant->website }}</a>
              @endif
            </x-settings.field>
          </x-settings.card>

          <x-settings.card title="{{ __('business.cards.address') }}"
                           description="{{ $locationCount > 1 ? __('business.cards.address_hint', ['count' => $locationCount]) : null }}">
            @if ($primaryLocation)
              <x-settings.field label="{{ __('business.fields.address_line1') }}" :value="$primaryLocation->address_line1" />
              <x-settings.field label="{{ __('business.fields.address_line2') }}" :value="$primaryLocation->address_line2" />
              <x-settings.field label="{{ __('business.fields.city') }}" :value="$primaryLocation->city" />
              <x-settings.field label="{{ __('business.fields.state') }}" :value="$primaryLocation->state" />
              <x-settings.field label="{{ __('business.fields.postal_code') }}" :value="$primaryLocation->postal_code" />
              <x-settings.field label="{{ __('business.fields.country') }}" :value="config('locations.countries.'.$primaryLocation->country, $primaryLocation->country)" />
            @else
              <x-settings.field label="{{ __('business.fields.address') }}" value="" />
            @endif

            <div class="pt-3">
              <a href="{{ route('settings.index') }}" class="text-[13px] font-medium text-link hover:underline">
                {{ __('business.manage_locations') }}
              </a>
            </div>
          </x-settings.card>

        </div>

        <div class="space-y-5">

          <x-settings.card title="{{ __('business.cards.regional') }}"
                           description="{{ __('business.cards.regional_hint') }}">
            <x-settings.field label="{{ __('business.fields.primary_language') }}"
                              :value="$languageNames[$tenant->default_language] ?? $tenant->default_language"
                              :manage="route('settings.index')" manage-label="{{ __('business.manage') }}" />

            <x-settings.field label="{{ __('business.fields.secondary_languages') }}">
              @php $secondaryLanguages = array_slice($languages, 1); @endphp
              @if ($secondaryLanguages)
                {{ collect($secondaryLanguages)->map(fn ($c) => $languageNames[$c] ?? $c)->join(', ') }}
              @endif
            </x-settings.field>

            <x-settings.field label="{{ __('business.fields.primary_currency') }}"
                              :value="$tenant->currency_code ? ($currencyNames[$tenant->currency_code]['name'] ?? $tenant->currency_code).' ('.$tenant->currency_code.')' : null"
                              :manage="route('settings.index')" manage-label="{{ __('business.manage') }}" />

            <x-settings.field label="{{ __('business.fields.secondary_currencies') }}">
              @php $secondaryCurrencies = array_slice($currencies, 1); @endphp
              @if ($secondaryCurrencies)
                {{ implode(', ', $secondaryCurrencies) }}
              @endif
            </x-settings.field>

            <x-settings.field label="{{ __('business.fields.timezone') }}">
              @if ($tz)
                {{-- Both halves: the offset name is what people recognise, the
                     IANA identifier is what is actually stored. --}}
                {{ config('locations.timezones.'.$tz, $tz) }} <span class="text-sub">— {{ $tz }}</span>
              @endif
            </x-settings.field>

            <x-settings.field label="{{ __('business.fields.date_format') }}"
                              :value="$tenant->date_format ? App\Support\BusinessProfile::dateFormats()[$tenant->date_format].' — '.now()->format($tenant->date_format) : null" />
            {{-- Falls back to the default the app is really using, so this
                 reads the same as every clock on every other screen. --}}
            <x-settings.field label="{{ __('business.fields.time_format') }}"
                              :value="App\Support\BusinessProfile::label('timeFormats', (string) ($tenant->time_format ?: App\Support\TimeFormat::DEFAULT))" />
            <x-settings.field label="{{ __('business.fields.first_day_of_week') }}"
                              :value="$tenant->first_day_of_week !== null ? App\Support\BusinessProfile::label('firstDayOfWeek', (string) $tenant->first_day_of_week) : null" />
          </x-settings.card>

          <x-settings.card title="{{ __('business.cards.payments') }}"
                           description="{{ __('business.cards.payments_hint') }}">
            @foreach (['paypal_handle', 'zelle_handle', 'cash_app_handle', 'venmo_handle'] as $handle)
              <x-settings.field label="{{ __('business.fields.'.$handle) }}" :value="$tenant->{$handle}" />
            @endforeach
          </x-settings.card>

          <x-settings.card title="{{ __('business.cards.defaults') }}"
                           description="{{ __('business.cards.defaults_hint') }}">
            <x-settings.field label="{{ __('business.fields.default_location') }}" :value="optional($primaryLocation)->name"
                              :manage="route('settings.index')" manage-label="{{ __('business.manage') }}" />
            <x-settings.field label="{{ __('business.fields.default_booking_duration') }}"
                              :value="App\Support\BusinessProfile::label('bookingDurations', (string) $tenant->default_booking_duration)" />
            <x-settings.field label="{{ __('business.fields.default_appointment_interval') }}"
                              :value="App\Support\BusinessProfile::label('appointmentIntervals', (string) $tenant->default_appointment_interval)" />
            <x-settings.field label="{{ __('business.fields.default_tax_behavior') }}"
                              :value="App\Support\BusinessProfile::label('taxBehaviors', (string) $tenant->default_tax_behavior)"
                              :manage="route('settings.index')" manage-label="{{ __('business.manage') }}" />
            <x-settings.field label="{{ __('business.fields.default_tax_rate') }}"
                              :value="$tenant->default_tax_rate ? rtrim(rtrim(number_format((float) $tenant->default_tax_rate, 2), '0'), '.').'%' : null" />
            <x-settings.field label="{{ __('business.fields.default_staff_assignment') }}"
                              :value="App\Support\BusinessProfile::label('staffAssignment', (string) $tenant->default_staff_assignment)" />

            <x-settings.field label="{{ __('business.fields.allow_online_booking') }}" :manage="route('settings.index')" manage-label="{{ __('business.manage') }}">
              <span class="styledesk_badge {{ optional($bookingSettings)->is_enabled ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ optional($bookingSettings)->is_enabled ? __('business.enabled') : __('business.disabled') }}
              </span>
            </x-settings.field>

            <x-settings.field label="{{ __('business.fields.guest_booking') }}" :manage="route('settings.index')" manage-label="{{ __('business.manage') }}">
              <span class="styledesk_badge {{ optional($bookingSettings)->allow_new_clients ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ optional($bookingSettings)->allow_new_clients ? __('business.enabled') : __('business.disabled') }}
              </span>
            </x-settings.field>
          </x-settings.card>

          <x-settings.card title="{{ __('business.cards.presence') }}" description="{{ __('business.cards.presence_hint') }}">
            @foreach ([
                __('business.fields.instagram') => $tenant->instagram_url,
                __('business.fields.facebook') => $tenant->facebook_url,
                __('business.fields.tiktok') => $tenant->tiktok_url,
                __('business.fields.google_business') => $tenant->google_business_url,
            ] as $label => $url)
              <x-settings.field :label="$label">
                @if ($url)
                  <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                     class="text-link hover:underline break-all">{{ $url }}</a>
                @endif
              </x-settings.field>
            @endforeach
          </x-settings.card>

          <x-settings.card title="{{ __('business.cards.advanced') }}">
            {{-- The tenant id identifies this business in support tickets and
                 API calls. Read-only everywhere: changing it would orphan
                 every row that points at it. --}}
            <x-settings.field label="{{ __('business.fields.business_id') }}">
              <code class="text-[13px] text-sub break-all">{{ $tenant->getTenantKey() }}</code>
            </x-settings.field>
            <x-settings.field label="{{ __('business.fields.booking_address') }}" :value="$tenant->slug.'.'.config('tenancy.tenant_domain_suffix')" />
          </x-settings.card>

        </div>
      </div>
    </div>
  </main>
@endsection
