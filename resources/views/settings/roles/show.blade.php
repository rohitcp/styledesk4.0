@extends('layouts.app')

@section('title', $role->label())

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <a href="{{ route('settings.roles.index') }}" class="hover:text-ink transition-colors">Roles &amp; permissions</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ $role->label() }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ $role->label() }}</h1>

          <p class="mt-2 flex flex-wrap items-center gap-1.5">
            <span class="styledesk_badge {{ $role->isSystem() ? 'styledesk_badge--soon' : 'styledesk_badge--active' }}">
              {{ $role->isSystem() ? 'System role' : 'Custom role' }}
            </span>
            @if ($role->key === 'owner')
              {{-- Named on the screen because it is a rule of the product,
                   not a setting somebody chose. --}}
              <span class="styledesk_badge styledesk_badge--setup">Protected</span>
            @endif
          </p>

          <p class="text-[14px] text-sub mt-2.5 max-w-[640px] leading-relaxed">{{ $role->describe() }}</p>

          <p class="text-[13px] text-sub mt-2">
            {{ __('roles.permissions_summary', ['granted' => $grantedTotal, 'total' => $permissionTotal]) }}
            &middot;
            {{ trans_choice('roles.staff_summary', $role->staff_count, ['count' => $role->staff_count]) }}
          </p>
        </div>

        <a href="{{ route('settings.roles.index') }}"
           class="styledesk_action shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back
        </a>
      </div>

      @if ($role->key === 'owner')
        <div class="sd-alert sd-alert--info mt-5" role="status">
          <div class="flex items-start gap-2.5">
            <x-icon name="shield-halved" size="17" class="shrink-0 mt-px" />
            <p class="min-w-0">
              The Owner role is protected: it cannot be renamed or deleted, its core permissions cannot be
              removed, and a business can never be left without an owner. Ownership moves through
              Transfer&nbsp;ownership rather than by changing a role.
            </p>
          </div>
        </div>
      @endif

      <div class="mt-5 space-y-4">
        @foreach ($groups as $group)
          <section class="bg-white border border-line rounded-card p-5">
            <div class="flex items-center gap-3">
              <span class="styledesk_settingcard__icon" aria-hidden="true">
                <x-icon :name="$group['icon']" size="17" />
              </span>
              <h2 class="text-[15px] font-semibold text-head">{{ $group['label'] }}</h2>
              <span class="ml-auto text-[12px] text-sub">
                {{ $group['grantedCount'] }} of {{ count($group['permissions']) }}
              </span>
            </div>

            {{-- Text and a mark, never a checkbox. A disabled checkbox says
                 "you may not change this"; a tick and a dash say what is and
                 is not true, which is what a read-only screen is for. --}}
            <ul class="mt-3 grid sm:grid-cols-2 gap-x-6">
              @foreach ($group['permissions'] as $permission)
                <li class="flex items-start gap-2.5 py-1.5">
                  @if ($permission['granted'])
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-0.5 text-green-600" aria-hidden="true">
                      <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  @else
                    <span class="shrink-0 mt-0.5 w-[15px] text-center text-faint leading-[15px]" aria-hidden="true">&mdash;</span>
                  @endif

                  <span class="min-w-0 flex-1 text-[13px] {{ $permission['granted'] ? 'text-ink' : 'text-faint' }}">
                    {{ $permission['label'] }}

                    {{-- Scope only when it is narrower than everything:
                         "View clients" and "View clients (Own)" are different
                         permissions to the person reading. --}}
                    @if ($permission['scope'])
                      <span class="styledesk_badge styledesk_badge--soon ml-1">{{ $permission['scope'] }}</span>
                    @endif

                    @if ($permission['owner_only'] && $role->key !== 'owner')
                      <span class="styledesk_badge styledesk_badge--setup ml-1">Owner only</span>
                    @endif
                  </span>

                  <span class="sr-only">{{ $permission['granted'] ? 'Granted' : 'Not granted' }}</span>
                </li>
              @endforeach
            </ul>
          </section>
        @endforeach
      </div>
    </div>
  </main>
@endsection
