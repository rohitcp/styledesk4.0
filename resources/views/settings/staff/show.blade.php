@extends('layouts.app')

@section('title', $staff->displayName())

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[1180px]">

      @php
          $opts = config('staff');
          $avatarUrl = $staff->avatar_path ? Storage::disk('brand')->url($staff->avatar_path) : null;
      @endphp

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.staff.index') }}" class="hover:text-ink transition-colors">Staff members</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $staff->displayName() }}</span>
      </nav>

      {{-- §14's header: who this is, at a glance, with the actions beside it. --}}
      <div class="mt-3 flex flex-wrap items-start gap-4">
        <span class="shrink-0 h-14 w-14 rounded-xl overflow-hidden border border-line bg-hover grid place-items-center">
          @if ($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="" class="h-full w-full object-cover">
          @else
            <span class="text-[17px] font-semibold text-sub">{{ $staff->initials() }}</span>
          @endif
        </span>

        <div class="min-w-0 flex-1">
          <h1 class="text-[22px] sm:text-[26px] font-bold text-head tracking-tight">{{ $staff->displayName() }}</h1>
          <p class="text-[14px] text-sub mt-1">
            {{ $staff->job_title ?: 'No job title' }}
            @if ($staff->roleRecord) &middot; {{ $staff->roleRecord->name }} @endif
          </p>
          <p class="mt-2">
            <span class="styledesk_badge {{ $staff->statusClass() }}">{{ $staff->statusLabel() }}</span>
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

      <div class="mt-6 grid gap-5 lg:grid-cols-2 items-start">

        <div class="space-y-5">
          <x-settings.card title="Overview">
            <x-settings.field label="Full name" :value="trim($staff->first_name.' '.$staff->middle_name.' '.$staff->last_name)" />
            <x-settings.field label="Preferred name" :value="$staff->preferred_name" />
            <x-settings.field label="Pronouns" :value="$opts['pronouns'][$staff->pronouns] ?? $staff->pronouns" />
            <x-settings.field label="Job title" :value="$staff->job_title" />
            <x-settings.field label="Staff ID" :value="$staff->employee_ref" />
            <x-settings.field label="Bio" :value="$staff->bio" />
          </x-settings.card>

          <x-settings.card title="Contact information">
            <x-settings.field label="Primary email" :value="$staff->email" />
            <x-settings.field label="Work email" :value="$staff->work_email" />
            <x-settings.field label="Primary phone"
                              :value="$staff->phone ? $staff->phone.($staff->phone_type ? ' ('.($opts['phone_types'][$staff->phone_type] ?? $staff->phone_type).')' : '') : null" />
            <x-settings.field label="Secondary phone" :value="$staff->secondary_phone" />
            <x-settings.field label="Address" :value="$staff->address" />
            <x-settings.field label="Emergency contact"
                              :value="$staff->emergency_contact_name ? trim($staff->emergency_contact_name.' — '.$staff->emergency_contact_phone.' '.($staff->emergency_contact_relationship ? '('.$staff->emergency_contact_relationship.')' : '')) : null" />
          </x-settings.card>

          <x-settings.card title="Services" description="What this person can be booked for.">
            @if ($staff->services->isEmpty())
              <x-settings.field label="Assigned services" value="" />
            @else
              <x-settings.field label="Assigned services">
                <span class="flex flex-wrap gap-1.5">
                  @foreach ($staff->services as $service)
                    <span class="styledesk_badge styledesk_badge--soon">{{ $service->name }}</span>
                  @endforeach
                </span>
              </x-settings.field>
            @endif
            <x-settings.field label="Bookable">
              <span class="styledesk_badge {{ $staff->provides_services ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ $staff->provides_services ? 'Yes' : 'No' }}
              </span>
            </x-settings.field>
          </x-settings.card>
        </div>

        <div class="space-y-5">
          <x-settings.card title="Role & access">
            <x-settings.field label="Role" :value="$staff->roleRecord?->name" />
            <x-settings.field label="Primary location" :value="$staff->location?->name ?? 'All locations'" />
            <x-settings.field label="Staff login">
              <span class="styledesk_badge {{ $staff->login_enabled ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                {{ $staff->login_enabled ? 'Enabled' : 'Disabled' }}
              </span>
            </x-settings.field>
            <x-settings.field label="Account" :value="$staff->user ? 'Linked to '.$staff->user->email : 'No account yet'" />
            <x-settings.field label="Last login" :value="$staff->user?->last_login_at?->diffForHumans() ?? 'Never'" />
          </x-settings.card>

          <x-settings.card title="Employment">
            <x-settings.field label="Employment type" :value="$opts['employment_types'][$staff->employment_type] ?? null" />
            <x-settings.field label="Provider type" :value="$opts['provider_types'][$staff->provider_type] ?? null" />
            <x-settings.field label="Specialities">
              @if ($staff->specialities)
                {{ collect($staff->specialities)->map(fn ($s) => $opts['specialities'][$s] ?? $s)->join(', ') }}
              @endif
            </x-settings.field>
            <x-settings.field label="Added" :value="$staff->created_at?->format('j F Y')" />
          </x-settings.card>

          @if ($invitation)
            <x-settings.card title="Invitation">
              <x-settings.field label="Sent to" :value="$invitation->email" />
              <x-settings.field label="Status">
                <span class="styledesk_badge {{ $staff->statusClass() }}">{{ $invitation->statusLabel() }}</span>
              </x-settings.field>
              <x-settings.field label="Sent" :value="$invitation->sent_at?->diffForHumans()" />
              <x-settings.field label="Expires" :value="$invitation->expires_at?->format('j F Y')" />
            </x-settings.card>
          @endif

          <x-settings.card title="Activity" description="Administrative changes to this record.">
            @forelse ($history as $entry)
              <x-settings.field :label="$entry->created_at->format('j M Y, H:i')">
                {{ str_replace(['staff.', '_'], ['', ' '], $entry->action) }}
                <span class="text-sub">by {{ $entry->actor_name ?? 'someone since removed' }}</span>
              </x-settings.field>
            @empty
              <x-settings.field label="History" value="" />
            @endforelse
          </x-settings.card>
        </div>
      </div>
    </div>
  </main>
@endsection
