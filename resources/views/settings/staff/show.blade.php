@extends('layouts.app')

@section('title', $staff->displayName())

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[860px]">

      @php
          $opts = config('staff');
          $avatarUrl = $staff->avatar_path ? Storage::disk('brand')->url($staff->avatar_path) : null;

          $specialities = collect($staff->specialities ?? [])
              ->map(fn ($s) => $opts['specialities'][$s] ?? $s);
      @endphp

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.staff.index') }}" class="hover:text-ink transition-colors">Staff members</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $staff->displayName() }}</span>
      </nav>

      {{-- ===================== Header =====================
           Who this is and how to reach them, together. The first version put
           the email and phone in a card below the fold, so the two questions
           this page is opened to answer were the two it answered last. --}}
      <div class="styledesk_profile mt-3">
        <span class="styledesk_profile__avatar" aria-hidden="true">
          @if ($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="">
          @else
            {{ $staff->initials() }}
          @endif
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
            <div class="min-w-0 flex-1">
              <h1 class="text-[22px] sm:text-[26px] font-bold text-head tracking-tight leading-tight">
                {{ $staff->displayName() }}
              </h1>

              <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-sub">
                @if ($staff->job_title)<span>{{ $staff->job_title }}</span>@endif
                @if ($staff->job_title && $staff->roleRecord)<span class="text-faint">&middot;</span>@endif
                @if ($staff->roleRecord)<span>{{ $staff->roleRecord->name }}</span>@endif
                {{-- Only when it actually differs. Someone whose preferred
                     name matches their first name would otherwise see their
                     own name twice on one line. --}}
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
                  <span class="styledesk_badge styledesk_badge--soon">Bookable</span>
                @endif
                @if (! $staff->login_enabled)
                  <span class="styledesk_badge styledesk_badge--soon">No login</span>
                @endif
              </p>
            </div>

            <div class="shrink-0 flex items-center gap-2">
              <a href="{{ route('settings.staff.index') }}"
                 class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Back
              </a>

              @can('update', $staff)
                <a href="{{ route('settings.staff.edit', $staff) }}"
                   class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                  Edit
                </a>
              @endcan
            </div>
          </div>

          @php
              $headline = [
                  ['icon' => 'envelope', 'label' => 'Email', 'value' => $staff->email, 'href' => $staff->email ? 'mailto:'.$staff->email : null],
                  ['icon' => 'address-book', 'label' => 'Phone', 'value' => $staff->phone, 'href' => $staff->phone ? 'tel:'.$staff->phone : null],
                  ['icon' => 'location-dot', 'label' => 'Location', 'value' => $staff->location?->name ?? 'All locations'],
                  ['icon' => 'clock', 'label' => 'Last login', 'value' => $staff->user?->last_login_at?->diffForHumans() ?? 'Never'],
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
      </div>

      {{-- ===================== Detail =====================
           One column. These are label/value facts read top to bottom, not
           two independent tracks — side by side the eye has to choose a
           column, then jump back up for the other, for content that has a
           natural order. --}}
      <div class="mt-5 space-y-5">

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">About</h2>
          <x-settings.facts :facts="[
              'Legal name' => trim($staff->first_name.' '.$staff->middle_name.' '.$staff->last_name),
              'Preferred name' => $staff->preferred_name,
              'Pronouns' => $opts['pronouns'][$staff->pronouns] ?? $staff->pronouns,
              'Staff ID' => $staff->employee_ref,
              'Bio' => $staff->bio,
          ]" />
        </section>

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">Contact</h2>
          <x-settings.facts :facts="[
              'Primary email' => $staff->email,
              'Work email' => $staff->work_email,
              'Primary phone' => $staff->phone ? $staff->phone.($staff->phone_type ? ' ('.($opts['phone_types'][$staff->phone_type] ?? $staff->phone_type).')' : '') : null,
              'Secondary phone' => $staff->secondary_phone,
              'Address' => $staff->address,
              'Emergency contact' => $staff->emergency_contact_name
                  ? trim($staff->emergency_contact_name.' — '.$staff->emergency_contact_phone.' '.($staff->emergency_contact_relationship ? '('.$staff->emergency_contact_relationship.')' : ''))
                  : null,
          ]" />
        </section>

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">Role &amp; access</h2>
          <x-settings.facts :facts="[
              'Role' => $staff->roleRecord?->name,
              'Primary location' => $staff->location?->name ?? 'All locations',
              'Staff login' => $staff->login_enabled ? 'Enabled' : 'Disabled',
              'Account' => $staff->user ? $staff->user->email : null,
              'Last login' => $staff->user?->last_login_at?->diffForHumans() ?? 'Never',
          ]" />
        </section>

        <section class="bg-white border border-line rounded-card p-5">
          <div class="flex items-center gap-3">
            <h2 class="text-[15px] font-semibold text-head">Services</h2>
            <span class="ml-auto text-[12px] text-sub">{{ $staff->services->count() }} assigned</span>
          </div>

          @if ($staff->services->isEmpty())
            <p class="mt-3 text-[13px] text-sub">
              No services assigned, so {{ $staff->displayName() }} cannot be booked by name.
              @can('update', $staff)
                <a href="{{ route('settings.staff.edit', $staff) }}" class="text-link font-medium hover:underline">Assign services</a>.
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
          <h2 class="text-[15px] font-semibold text-head">Employment</h2>
          <x-settings.facts :facts="[
              'Employment type' => $opts['employment_types'][$staff->employment_type] ?? null,
              'Provider type' => $opts['provider_types'][$staff->provider_type] ?? null,
              'Specialities' => $specialities->isNotEmpty() ? $specialities->join(', ') : null,
              'Added' => $staff->created_at?->format('j F Y'),
          ]" />
        </section>

        @if ($invitation)
          <section class="bg-white border border-line rounded-card p-5">
            <h2 class="text-[15px] font-semibold text-head">Invitation</h2>
              <x-settings.facts :facts="[
                  'Sent to' => $invitation->email,
                  {{-- outcomeLabel, not statusLabel: the latter answers the
                       team list's question about the person and says 'Active'
                       for an accepted invitation, which on this card reads as
                       the invitation still being open. --}}
                  'Status' => $invitation->outcomeLabel(),
                  'Sent' => $invitation->sent_at?->diffForHumans(),
                  'Accepted' => $invitation->accepted_at?->diffForHumans(),
                  // An expiry that has already been overtaken by acceptance is
                  // a date that no longer means anything.
                  'Expires' => $invitation->accepted_at ? null : $invitation->expires_at?->format('j F Y'),
            ]" />
          </section>
        @endif

        <section class="bg-white border border-line rounded-card p-5">
          <h2 class="text-[15px] font-semibold text-head">Activity</h2>
            <p class="text-[13px] text-sub mt-1">Administrative changes to this record.</p>

            @if ($history->isEmpty())
              <p class="mt-3 text-[13px] text-faint">Nothing recorded yet.</p>
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
