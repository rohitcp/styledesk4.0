@extends('layouts.app')

@section('title', 'Staff members')

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[1180px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Staff members</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Staff members</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            {{ $activeCount }} active {{ Str::plural('member', $activeCount) }}@if ($pendingCount), {{ $pendingCount }} pending {{ Str::plural('invite', $pendingCount) }}@endif.
          </p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}"
             class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Back
          </a>

          @can('create', App\Models\Staff::class)
            <a href="{{ route('settings.staff.create') }}"
               class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              Add staff member
            </a>
          @endcan
        </div>
      </div>

      {{-- Filters post as a GET form, so a filtered directory is a URL that
           can be bookmarked, shared and reloaded. --}}
      <form method="GET" action="{{ route('settings.staff.index') }}" class="mt-6 rounded-card border border-line bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div class="sm:col-span-2">
            <label for="staff-search" class="block text-[12px] font-medium text-sub mb-1.5">Search</label>
            <input id="staff-search" name="search" type="search" class="sd-input"
                   value="{{ $filters['search'] }}" placeholder="Name, email, phone or job title">
          </div>

          @php
              $selects = [
                  ['name' => 'role', 'label' => 'Role', 'options' => $roles->pluck('name', 'key')],
                  ['name' => 'location', 'label' => 'Location', 'options' => $locations->pluck('name', 'id')],
                  ['name' => 'service', 'label' => 'Service', 'options' => $services->pluck('name', 'id')],
                  ['name' => 'provider_type', 'label' => 'Provider type', 'options' => collect(config('staff.provider_types'))],
                  ['name' => 'employment_type', 'label' => 'Employment', 'options' => collect(config('staff.employment_types'))],
                  ['name' => 'status', 'label' => 'Status', 'options' => collect(config('staff.statuses'))->map(fn ($s) => $s['label'])],
                  ['name' => 'sort', 'label' => 'Sort by', 'options' => collect(config('staff.sorts'))],
              ];
          @endphp

          @foreach ($selects as $select)
            <div>
              <label for="staff-{{ $select['name'] }}" class="block text-[12px] font-medium text-sub mb-1.5">{{ $select['label'] }}</label>
              <select id="staff-{{ $select['name'] }}" name="{{ $select['name'] }}" class="sd-input">
                <option value="">{{ $select['name'] === 'sort' ? 'Name' : 'All' }}</option>
                @foreach ($select['options'] as $value => $label)
                  <option value="{{ $value }}" @selected((string) $filters[$select['name']] === (string) $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
          @endforeach
        </div>

        <div class="mt-3 flex items-center gap-2">
          <button type="submit" class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            Apply
          </button>
          <a href="{{ route('settings.staff.index') }}" class="h-9 px-3.5 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            Clear
          </a>
        </div>
      </form>

      @if ($staff->isEmpty())
        <div class="mt-6 rounded-card border border-line bg-white p-10 text-center">
          <p class="text-[15px] font-semibold text-head">No staff match these filters.</p>
          <p class="text-[13px] text-sub mt-1.5">Clear the filters, or invite someone from the team step.</p>
        </div>
      @else
        {{-- A table, scrolling inside its own container: the row carries nine
             facts and squeezing them into a phone-width card would drop the
             ones the directory exists to compare. --}}
        <div class="mt-6 rounded-card border border-line bg-white overflow-x-auto">
          <table class="w-full text-[13px]" style="min-width: 860px">
            <thead>
              <tr class="text-left text-[12px] text-sub border-b border-line">
                <th class="font-medium px-4 py-3">Name</th>
                <th class="font-medium px-4 py-3">Role</th>
                <th class="font-medium px-4 py-3">Location</th>
                <th class="font-medium px-4 py-3">Contact</th>
                <th class="font-medium px-4 py-3 text-right">Services</th>
                <th class="font-medium px-4 py-3">Status</th>
                <th class="font-medium px-4 py-3">Last login</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              @foreach ($staff as $member)
                <tr class="hover:bg-hover/50 transition-colors">
                  <td class="px-4 py-3">
                    <span class="flex items-center gap-2.5">
                      <span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">{{ $member->initials() }}</span>
                      <span class="min-w-0">
                        <span class="block font-semibold text-head truncate">{{ $member->displayName() }}</span>
                        @if ($member->job_title)
                          <span class="block text-[12px] text-sub truncate">{{ $member->job_title }}</span>
                        @endif
                      </span>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-ink">{{ $member->roleRecord?->name ?? '—' }}</td>
                  <td class="px-4 py-3 text-ink">{{ $member->location?->name ?? 'All locations' }}</td>
                  <td class="px-4 py-3">
                    <span class="block text-ink truncate">{{ $member->email ?? '—' }}</span>
                    @if ($member->phone)
                      <span class="block text-[12px] text-sub">{{ $member->phone }}</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-right text-ink">{{ $member->services_count }}</td>
                  <td class="px-4 py-3">
                    <span class="styledesk_badge {{ $member->statusClass() }}">{{ $member->statusLabel() }}</span>
                  </td>
                  <td class="px-4 py-3 text-sub">
                    {{ $member->user?->last_login_at?->diffForHumans() ?? 'Never' }}
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </main>
@endsection
