@extends('layouts.app')

@section('title', 'Business')

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
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Business</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Business</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            Business name, type, contact details and operating configuration.
          </p>
        </div>

        {{-- Back and Edit sit together as one action group. Secondary first,
             so the eye lands on the primary action last and closest to the
             edge it will click. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}"
             class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Back
          </a>

          <a href="{{ route('settings.business.edit') }}"
             class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            Edit business
          </a>
        </div>
      </div>

      {{-- Two columns on desktop, stacked below. Identity and how to reach the
           business on the left; the settings that are summarised from other
           modules on the right. --}}
      <div class="mt-6 grid gap-5 lg:grid-cols-2 items-start">

        <div class="space-y-5">

          <x-settings.card title="Business information">
            <x-settings.field label="Business name" :value="$tenant->name" />
            <x-settings.field label="Legal business name" :value="$tenant->legal_name" />

            <x-settings.field label="Business type">
              @if ($tenant->businessTypes->isNotEmpty())
                <span class="flex flex-wrap gap-1.5">
                  @foreach ($tenant->businessTypes as $type)
                    <span class="styledesk_badge styledesk_badge--soon">{{ $type->name }}</span>
                  @endforeach
                </span>
              @endif
            </x-settings.field>

            <x-settings.field label="Category / specialisation" :value="$tenant->business_category" />
            <x-settings.field label="Description" :value="$tenant->description" />

            <x-settings.field label="Business logo" :manage="route('settings.index')" manage-label="Branding →">
              @if ($tenant->logo_path)
                <img src="{{ Storage::disk('brand')->url($tenant->logo_path) }}" alt="{{ $tenant->name }} logo"
                     class="h-10 w-10 rounded-lg object-cover border border-line">
              @endif
            </x-settings.field>

            <x-settings.field label="Status">
              <span class="styledesk_badge {{ $tenant->status === 'active' ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ ucfirst($tenant->status) }}
              </span>
            </x-settings.field>
          </x-settings.card>

          <x-settings.card title="Contact information">
            <x-settings.field label="Primary email" :value="$tenant->business_email" />
            <x-settings.field label="Primary phone" :value="$tenant->business_phone" />
            <x-settings.field label="Support email" :value="$tenant->support_email" />
            <x-settings.field label="Booking contact email" :value="$tenant->booking_email" />

            <x-settings.field label="Website">
              @if ($tenant->website)
                <a href="{{ $tenant->website }}" target="_blank" rel="noopener noreferrer"
                   class="text-link hover:underline break-all">{{ $tenant->website }}</a>
              @endif
            </x-settings.field>
          </x-settings.card>

          <x-settings.card title="Business address"
                           description="{{ $locationCount > 1 ? 'Your primary address. '.$locationCount.' locations in total.' : null }}">
            @if ($primaryLocation)
              <x-settings.field label="Address line 1" :value="$primaryLocation->address_line1" />
              <x-settings.field label="Address line 2" :value="$primaryLocation->address_line2" />
              <x-settings.field label="City" :value="$primaryLocation->city" />
              <x-settings.field label="State / province" :value="$primaryLocation->state" />
              <x-settings.field label="ZIP / postal code" :value="$primaryLocation->postal_code" />
              <x-settings.field label="Country" :value="config('locations.countries.'.$primaryLocation->country, $primaryLocation->country)" />
            @else
              <x-settings.field label="Address" value="" />
            @endif

            <div class="pt-3">
              <a href="{{ route('settings.index') }}" class="text-[13px] font-medium text-link hover:underline">
                Manage locations →
              </a>
            </div>
          </x-settings.card>

        </div>

        <div class="space-y-5">

          <x-settings.card title="Regional settings"
                           description="Configured in their own modules; shown here for context.">
            <x-settings.field label="Primary language"
                              :value="$languageNames[$tenant->default_language] ?? $tenant->default_language"
                              :manage="route('settings.index')" manage-label="Manage" />

            <x-settings.field label="Secondary languages">
              @php $secondaryLanguages = array_slice($languages, 1); @endphp
              @if ($secondaryLanguages)
                {{ collect($secondaryLanguages)->map(fn ($c) => $languageNames[$c] ?? $c)->join(', ') }}
              @endif
            </x-settings.field>

            <x-settings.field label="Primary currency"
                              :value="$tenant->currency_code ? ($currencyNames[$tenant->currency_code]['name'] ?? $tenant->currency_code).' ('.$tenant->currency_code.')' : null"
                              :manage="route('settings.index')" manage-label="Manage" />

            <x-settings.field label="Secondary currencies">
              @php $secondaryCurrencies = array_slice($currencies, 1); @endphp
              @if ($secondaryCurrencies)
                {{ implode(', ', $secondaryCurrencies) }}
              @endif
            </x-settings.field>

            <x-settings.field label="Time zone">
              @if ($tz)
                {{-- Both halves: the offset name is what people recognise, the
                     IANA identifier is what is actually stored. --}}
                {{ config('locations.timezones.'.$tz, $tz) }} <span class="text-sub">— {{ $tz }}</span>
              @endif
            </x-settings.field>

            <x-settings.field label="Date format"
                              :value="$tenant->date_format ? $formats['date_formats'][$tenant->date_format].' — '.now()->format($tenant->date_format) : null" />
            <x-settings.field label="Time format" :value="$formats['time_formats'][$tenant->time_format] ?? null" />
            <x-settings.field label="First day of week"
                              :value="$tenant->first_day_of_week !== null ? ($formats['first_day_of_week'][$tenant->first_day_of_week] ?? null) : null" />
          </x-settings.card>

          <x-settings.card title="Business defaults"
                           description="Starting points for new bookings and services.">
            <x-settings.field label="Default location" :value="optional($primaryLocation)->name"
                              :manage="route('settings.index')" manage-label="Manage" />
            <x-settings.field label="Default booking duration"
                              :value="$formats['booking_durations'][$tenant->default_booking_duration] ?? null" />
            <x-settings.field label="Default appointment interval"
                              :value="$formats['appointment_intervals'][$tenant->default_appointment_interval] ?? null" />
            <x-settings.field label="Default tax behaviour"
                              :value="$formats['tax_behaviors'][$tenant->default_tax_behavior] ?? null"
                              :manage="route('settings.index')" manage-label="Manage" />
            <x-settings.field label="Default staff assignment"
                              :value="$formats['staff_assignment'][$tenant->default_staff_assignment] ?? null" />

            <x-settings.field label="Allow online booking" :manage="route('settings.index')" manage-label="Manage">
              <span class="styledesk_badge {{ optional($bookingSettings)->is_enabled ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ optional($bookingSettings)->is_enabled ? 'Enabled' : 'Disabled' }}
              </span>
            </x-settings.field>

            <x-settings.field label="Guest booking enabled" :manage="route('settings.index')" manage-label="Manage">
              <span class="styledesk_badge {{ optional($bookingSettings)->allow_new_clients ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ optional($bookingSettings)->allow_new_clients ? 'Enabled' : 'Disabled' }}
              </span>
            </x-settings.field>
          </x-settings.card>

          <x-settings.card title="Business presence" description="Where clients find you outside StyleDesk.">
            @foreach ([
                'Instagram' => $tenant->instagram_url,
                'Facebook' => $tenant->facebook_url,
                'TikTok' => $tenant->tiktok_url,
                'Google Business Profile' => $tenant->google_business_url,
            ] as $label => $url)
              <x-settings.field :label="$label">
                @if ($url)
                  <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                     class="text-link hover:underline break-all">{{ $url }}</a>
                @endif
              </x-settings.field>
            @endforeach
          </x-settings.card>

          <x-settings.card title="Advanced information">
            {{-- The tenant id identifies this business in support tickets and
                 API calls. Read-only everywhere: changing it would orphan
                 every row that points at it. --}}
            <x-settings.field label="Business ID">
              <code class="text-[13px] text-sub break-all">{{ $tenant->getTenantKey() }}</code>
            </x-settings.field>
            <x-settings.field label="Booking address" :value="$tenant->slug.'.'.config('tenancy.tenant_domain_suffix')" />
          </x-settings.card>

        </div>
      </div>
    </div>
  </main>
@endsection
