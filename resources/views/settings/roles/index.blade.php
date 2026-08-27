@extends('layouts.app')

@section('title', 'Roles & permissions')

@section('content')
  {{-- pb-[200px]: the role list ends on a card, and a card flush against
       the footer reads as the page having been cut off rather than finished.
       The gap is what says the list is complete. --}}
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="max-w-[900px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">App settings</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">Roles &amp; permissions</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">Roles &amp; permissions</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            What Owners, Admins, Managers, Receptionists, Service Providers and custom roles can access.
          </p>
        </div>

        <a href="{{ route('settings.index') }}"
           class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Back
        </a>
      </div>

      {{-- Said once, plainly, instead of disabling controls all over the
           screen. A greyed-out Add Role implies it might become available if
           you were someone else; saying editing comes later is the truth. --}}
      <div class="sd-alert sd-alert--info mt-5" role="status">
        <div class="flex items-start gap-2.5">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" class="shrink-0 mt-px" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M12 11v5.5M12 8v.4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>
          <p class="min-w-0">
            These roles are view-only for now. Creating and editing roles arrives in a later release —
            until then, change what someone can do by changing their role on their staff record.
          </p>
        </div>
      </div>

      <div class="mt-5 space-y-3">
        @foreach ($roles as $role)
          <a href="{{ route('settings.roles.show', $role) }}"
             class="styledesk_settingcard block hover:border-brand">
            <span class="styledesk_settingcard__icon" aria-hidden="true">
              <x-icon name="user-shield" size="18" />
            </span>

            <span class="min-w-0 flex-1">
              <span class="flex flex-wrap items-center gap-2">
                <span class="text-[14px] font-semibold text-head">{{ $role->name }}</span>

                <span class="styledesk_badge {{ $role->isSystem() ? 'styledesk_badge--soon' : 'styledesk_badge--active' }}">
                  {{ $role->isSystem() ? 'System role' : 'Custom role' }}
                </span>
              </span>

              <span class="block text-[13px] text-sub mt-1 leading-relaxed">{{ $role->description }}</span>

              <span class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-[12px] text-sub">
                <span>
                  {{-- Owner is answered without the table, so its count is
                       the catalogue rather than its rows. --}}
                  <span class="font-semibold text-ink">{{ $role->key === 'owner' ? $permissionTotal : $role->permissions_count }}</span>
                  of {{ $permissionTotal }} permissions
                </span>
                <span>
                  <span class="font-semibold text-ink">{{ $role->staff_count }}</span>
                  {{ Str::plural('staff member', $role->staff_count) }}
                </span>
              </span>
            </span>

            <span class="shrink-0 self-center flex items-center gap-2 text-[13px] font-medium text-link">
              View permissions
              <x-icon name="chevron-right" size="12" />
            </span>
          </a>
        @endforeach
      </div>
    </div>
  </main>
@endsection
