@extends('layouts.app')

@section('title', $staff->displayName())

@section('content')
  {{-- The full-width container the clients screens use: the same padding,
       the same breakpoints, no narrow column of its own. A workspace that
       sat in 1080px while every other screen filled the window would read
       as a different application. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

      @php
          $specialities = collect($staff->specialities ?? [])
              ->map(fn ($s) => App\Support\StaffOptions::label('specialities', $s) ?? $s);
      @endphp

      @include('settings.staff._header', ['tab' => 'show'])

      {{-- A small report, from the data that exists.

           The appointment figures the brief also asks for need a booking
           module; a card reading "—" is a card that teaches the reader to
           ignore the row, so they are left out until there is something to
           put in them. The row is a list rather than four hand-placed cards,
           which is what lets the rest slot in beside these later. --}}
      <div class="mt-5 styledesk_statrow styledesk_scroll">
        @foreach ($staff->scheduleSummary() as $metric)
          <span class="styledesk_statcard styledesk_statcard--{{ ['blue', 'violet', 'green', 'amber'][$loop->index % 4] }}">
            <span class="min-w-0">
              <span class="styledesk_label block">{{ __('staff.report.'.$metric['key']) }}</span>
              <span class="block text-[20px] font-bold text-head leading-tight mt-0.5">{{ $metric['value'] }}</span>
            </span>
          </span>
        @endforeach
      </div>

      {{-- ===================== Detail =====================
           One column up to xl, two beyond it.

           These are label/value facts read top to bottom, and side by side the
           eye has to choose a column then jump back up for the other. That
           argument held while the page sat in an 860px column; it does not
           hold at full width, where a single column of short facts stretched
           across a wide monitor is a line of three words and a hand's width of
           nothing. Columns, not a masonry grid: the cards keep their order
           down each column, so the reading order is still the order they are
           written in. --}}
      <div class="mt-5 xl:columns-2 xl:gap-5 [&>section]:break-inside-avoid [&>section]:mb-5 space-y-5 xl:space-y-0">

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">{{ __('staff.profile.about') }}</h2>
          <x-settings.facts :facts="[
              __('staff.profile.legal_name') => trim($staff->first_name.' '.$staff->middle_name.' '.$staff->last_name),
              __('staff.profile.preferred_name') => $staff->preferred_name,
              __('staff.profile.pronouns') => App\Support\StaffOptions::label('pronouns', $staff->pronouns) ?? $staff->pronouns,
              __('staff.profile.employee_ref') => $staff->employee_ref,
              __('staff.fields.date_of_birth') => $staff->date_of_birth?->translatedFormat('j F Y'),
              __('staff.fields.started_on') => $staff->started_on?->translatedFormat('j F Y'),
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
                <a href="{{ \App\Support\StaffSection::route('edit', $staff) }}" class="text-link font-medium hover:underline">{{ __('staff.profile.assign_services') }}</a>.
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

        {{-- The chairs and rooms this person works at. Beside the services
             rather than inside them: a service says which rooms will do, this
             says which of them this person uses, and the booking engine will
             need both. --}}
        @if ($staff->resources->isNotEmpty())
          <section class="bg-white border border-line rounded-card p-5">
            <div class="flex items-center gap-3">
              <h2 class="text-[15px] font-semibold text-head">{{ __('staff.fields.resources') }}</h2>
              <span class="ml-auto text-[12px] text-sub">{{ $staff->resources->count() }}</span>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
              @foreach ($staff->resources as $resource)
                <span class="styledesk_badge styledesk_badge--soon">{{ $resource->name }}</span>
              @endforeach
            </div>
          </section>
        @endif

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
                      {{ \App\Support\TimeFormat::dateTime($entry->created_at, ', ') }}
                      &middot; {{ $entry->actor_name ?? 'someone since removed' }}
                    </p>
                  </li>
                @endforeach
              </ol>
            @endif
        </section>

      </div>

  </main>
@endsection
