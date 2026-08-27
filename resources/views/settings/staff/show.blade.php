@extends('layouts.app')

@section('title', $staff->displayName())

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[860px]">

      @php
          $opts = config('staff');
          $avatarUrl = $staff->avatar_path ? Storage::disk('brand')->url($staff->avatar_path) : null;

          $specialities = collect($staff->specialities ?? [])
              ->map(fn ($s) => App\Support\StaffOptions::label('specialities', $s) ?? $s);
      @endphp

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.staff.index') }}" class="hover:text-ink transition-colors">{{ __('staff.title') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $staff->displayName() }}</span>
      </nav>

      {{-- ===================== Header =====================
           Two bands stacked: who this is, then how to reach them. The strip
           is a sibling of the identity row rather than a child of its text
           column, which is what lets its divider run the full width of the
           card instead of starting where the avatar ends. --}}
      <div class="styledesk_profile mt-3">

        <div class="styledesk_profile__top">
          <span class="styledesk_profile__avatar" aria-hidden="true">
            @if ($avatarUrl)
              <img src="{{ $avatarUrl }}" alt="">
            @else
              {{ $staff->initials() }}
            @endif
          </span>

          <div class="min-w-0 flex-1">
            <h1 class="text-[22px] sm:text-[26px] font-bold text-head tracking-tight leading-tight">
              {{ $staff->displayName() }}
            </h1>

            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-sub">
              @if ($staff->job_title)<span>{{ $staff->job_title }}</span>@endif
              @if ($staff->job_title && $staff->roleRecord)<span class="text-faint">&middot;</span>@endif
              @if ($staff->roleRecord)<span>{{ $staff->roleRecord->name }}</span>@endif

              {{-- Only when it differs, or someone whose preferred name is
                   their first name reads their own name twice. --}}
              @php $legalName = trim($staff->first_name.' '.$staff->last_name); @endphp
              @if ($legalName !== $staff->displayName())
                <span class="text-faint">&middot;</span>
                <span class="text-faint">{{ $legalName }}</span>
              @endif

              @if ($staff->pronouns)
                <span class="text-faint">&middot;</span>
                <span>{{ $opts['pronouns'][$staff->pronouns] ?? $staff->pronouns }}</span>
              @endif
            </p>

            <p class="mt-2.5 flex flex-wrap items-center gap-1.5">
              <span class="styledesk_badge {{ $staff->statusClass() }}">{{ $staff->statusLabel() }}</span>
              @if ($staff->provides_services)
                <span class="styledesk_badge styledesk_badge--soon">{{ __('staff.profile.bookable') }}</span>
              @endif
              @if (! $staff->login_enabled)
                <span class="styledesk_badge styledesk_badge--soon">{{ __('staff.profile.no_login') }}</span>
              @endif
            </p>
          </div>

          <div class="shrink-0 flex items-center gap-2">
            <a href="{{ route('settings.staff.index') }}"
               class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              {{ __('common.back') }}
            </a>

            @can('update', $staff)
              <a href="{{ route('settings.staff.edit', $staff) }}"
                 class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                {{ __('common.edit') }}
              </a>
            @endcan
          </div>
        </div>

        @php
            $headline = [
                ['icon' => 'envelope', 'label' => __('staff.profile.summary_email'), 'value' => $staff->email, 'href' => $staff->email ? 'mailto:'.$staff->email : null],
                ['icon' => 'address-book', 'label' => __('staff.profile.summary_phone'), 'value' => $staff->phone, 'href' => $staff->phone ? 'tel:'.$staff->phone : null],
                ['icon' => 'location-dot', 'label' => __('staff.profile.summary_location'), 'value' => $staff->location?->name ?? 'All locations'],
                ['icon' => 'clock', 'label' => __('staff.profile.last_login'), 'value' => $staff->user?->last_login_at?->diffForHumans() ?? 'Never'],
            ];
        @endphp

        <dl class="styledesk_profile__facts">
          @foreach ($headline as $fact)
            @continue (! filled($fact['value']))
            <div class="styledesk_profile__fact">
              <x-icon :name="$fact['icon']" size="14" />
              <div class="min-w-0">
                <dt>{{ $fact['label'] }}</dt>
                <dd class="truncate">
                  @if (! empty($fact['href']))
                    <a href="{{ $fact['href'] }}" class="text-link hover:underline">{{ $fact['value'] }}</a>
                  @else
                    {{ $fact['value'] }}
                  @endif
                </dd>
              </div>
            </div>
          @endforeach
        </dl>
      </div>

      {{-- ===================== Detail =====================
           One column. These are label/value facts read top to bottom, not
           two independent tracks — side by side the eye has to choose a
           column, then jump back up for the other, for content that has a
           natural order. --}}
      <div class="mt-5 space-y-5">

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.about') }}</h2>
          <x-settings.facts :facts="[
              __('staff.profile.legal_name') => trim($staff->first_name.' '.$staff->middle_name.' '.$staff->last_name),
              __('staff.profile.preferred_name') => $staff->preferred_name,
              __('staff.profile.pronouns') => App\Support\StaffOptions::label('pronouns', $staff->pronouns) ?? $staff->pronouns,
              __('staff.profile.employee_ref') => $staff->employee_ref,
              'Bio' => $staff->bio,
          ]" />
        </section>

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.contact') }}</h2>
          <x-settings.facts :facts="[
              __('staff.profile.email') => $staff->email,
              __('staff.profile.work_email') => $staff->work_email,
              __('staff.profile.phone') => $staff->phone
                  ? $staff->phone.($staff->phone_type ? ' ('.(App\Support\StaffOptions::label('phoneTypes', $staff->phone_type) ?? $staff->phone_type).')' : '')
                  : null,
              __('staff.profile.secondary_phone') => $staff->secondary_phone,
              __('staff.profile.address') => $staff->address,
              __('staff.profile.emergency_contact') => $staff->emergency_contact_name
                  ? trim($staff->emergency_contact_name.' — '.$staff->emergency_contact_phone.' '.($staff->emergency_contact_relationship ? '('.$staff->emergency_contact_relationship.')' : ''))
                  : null,
          ]" />
        </section>

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.access') }}</h2>
          <x-settings.facts :facts="[
              __('staff.profile.role') => $staff->roleRecord?->label(),
              __('staff.profile.location') => $staff->location?->name ?? __('staff.all_locations'),
              __('staff.profile.login') => $staff->login_enabled ? __('business.enabled') : __('business.disabled'),
              __('staff.profile.account') => $staff->user ? $staff->user->email : null,
              __('staff.profile.last_login') => $staff->user?->last_login_at?->diffForHumans() ?? __('staff.never'),
          ]" />
        </section>

        <section class="bg-white border border-line rounded-card p-5">
          <div class="flex items-center gap-3">
            <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.services') }}</h2>
            <span class="ml-auto text-[12px] text-sub">{{ $staff->services->count() }} assigned</span>
          </div>

          @if ($staff->services->isEmpty())
            <p class="mt-3 text-[13px] text-sub">
              No services assigned, so {{ $staff->displayName() }} cannot be booked by name.
              @can('update', $staff)
                <a href="{{ route('settings.staff.edit', $staff) }}" class="text-link font-medium hover:underline">{{ __('staff.profile.assign_services') }}</a>.
              @endcan
            </p>
          @else
            <div class="mt-3 flex flex-wrap gap-1.5">
              @foreach ($staff->services as $service)
                <span class="styledesk_badge styledesk_badge--soon">{{ $service->name }}</span>
              @endforeach
            </div>
          @endif
        </section>

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.employment') }}</h2>
          <x-settings.facts :facts="[
              __('staff.profile.employment_type') => App\Support\StaffOptions::label('employmentTypes', $staff->employment_type),
              __('staff.profile.provider_type') => App\Support\StaffOptions::label('providerTypes', $staff->provider_type),
              __('staff.profile.specialities') => $specialities->isNotEmpty() ? $specialities->join(', ') : null,
              __('staff.profile.added') => $staff->created_at?->isoFormat('D MMMM Y'),
          ]" />
        </section>

        @if ($invitation)
          <section class="bg-white border border-line rounded-card p-5">
            <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.invitation') }}</h2>
              @php
                $invitationFacts = [__('staff.profile.sent_to') => $invitation->email];

                // The outcome and when it happened are one fact, so they
                // share a line rather than a status row and a date row
                // saying the same thing twice.
                $invitationFacts['Status'] = trim(
                    $invitation->outcomeLabel().' '.($invitation->accepted_at?->diffForHumans() ?? '')
                );

                $invitationFacts['Sent'] = $invitation->sent_at?->diffForHumans();

                /**
                 * Omitted entirely once accepted, rather than passed as null.
                 *
                 * A null value is reported as "Not set", which claims someone
                 * failed to fill in an expiry — when in fact acceptance has
                 * made the date meaningless and it is deliberately withheld.
                 */
                if ($invitation->accepted_at === null) {
                    $invitationFacts['Expires'] = $invitation->expires_at?->format('j F Y');
                }
            @endphp

            <x-settings.facts :facts="$invitationFacts" />
          </section>
        @endif

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.activity') }}</h2>
            <p class="text-[13px] text-sub mt-1">{{ __('staff.profile.activity_hint') }}</p>

            @if ($history->isEmpty())
              <p class="mt-3 text-[13px] text-faint">{{ __('staff.profile.activity_empty') }}</p>
            @else
              {{-- A history is a sequence, so it is drawn as one rather than
                   as label/value pairs where the date is the label. --}}
              <ol class="styledesk_timeline mt-4">
                @foreach ($history as $entry)
                  <li class="styledesk_timeline__item">
                    <p class="styledesk_timeline__action">
                      {{ str_replace(['staff.', '_'], ['', ' '], $entry->action) }}
                    </p>
                    <p class="styledesk_timeline__meta">
                      {{ $entry->created_at->format('j M Y, H:i') }}
                      &middot; {{ $entry->actor_name ?? 'someone since removed' }}
                    </p>
                  </li>
                @endforeach
              </ol>
            @endif
        </section>

      </div>

    </div>
  </main>
@endsection
