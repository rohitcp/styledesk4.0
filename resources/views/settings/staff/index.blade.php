@extends('layouts.app')

@section('title', __('staff.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 py-5 sm:py-6">
    <div class="max-w-[1180px]">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('staff.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('staff.title') }}</h1>
          <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">
            @php
                /**
                 * Built here rather than inline.
                 *
                 * Each half is a whole phrase in its own language: Str::plural()
                 * only knows English and would have produced "2 invitación
                 * pendientes".
                 */
                $summary = trans_choice('staff.summary', $activeCount, ['count' => $activeCount]);

                if ($pendingCount) {
                    $summary .= ', '.trans_choice('staff.summary_pending', $pendingCount, ['count' => $pendingCount]);
                }
            @endphp
            {{ $summary }}.
          </p>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}"
             class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          @can('create', App\Models\Staff::class)
            <a href="{{ route('settings.staff.create') }}"
               class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('staff.add') }}
            </a>
          @endcan
        </div>
      </div>

      @php
          // Everything except the search box, so the button can say how many
          // are narrowing the list without counting the search twice.
          $activeFilters = collect($filters)
              ->except(['search', 'sort'])
              ->filter(fn ($value) => $value !== null)
              ->count();
      @endphp

      {{-- Search stays in the open; the rest lives behind a button.
           Seven controls permanently on screen made the filters bigger than
           the directory they filter, and most visits use none of them. --}}
      <form method="GET" action="{{ route('settings.staff.index') }}" class="mt-6">
        <div class="flex flex-wrap items-center gap-2">
          <div class="relative flex-1 min-w-[240px]">
            <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
              <x-icon name="magnifying-glass" size="15" />
            </span>
            <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                   value="{{ $filters['search'] }}" aria-label="{{ __('staff.search_label') }}"
                   placeholder="{{ __('staff.search_placeholder') }}">
          </div>

          <button type="submit"
                  class="h-11 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('common.search') }}
          </button>

          <button type="button" data-filter-toggle aria-expanded="{{ $activeFilters ? 'true' : 'false' }}"
                  aria-controls="staff-filters"
                  class="h-11 px-4 inline-flex items-center gap-2 rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
            </svg>
            {{ __('common.filter') }}
            @if ($activeFilters)
              <span class="inline-flex items-center justify-center h-5 min-w-[1.25rem] px-1 rounded-full bg-brand text-white text-[11px] font-semibold">{{ $activeFilters }}</span>
            @endif
          </button>
        </div>

        {{-- Open on load when something is filtering, so a shared or
             bookmarked URL does not hide the reason the list is short. --}}
        <div id="staff-filters" class="mt-3 rounded-card border border-line bg-white p-4" @if (! $activeFilters) hidden @endif>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-combo name="role" label="{{ __('staff.filters.role') }}" :options="$roles->mapWithKeys(fn ($r) => [$r->key => $r->label()])"
                     :selected="$filters['role']" placeholder="{{ __('staff.filters.all_roles') }}" />

            <x-combo name="location" label="{{ __('staff.filters.location') }}" :options="$locations->pluck('name', 'id')"
                     :selected="$filters['location']" placeholder="{{ __('staff.filters.all_locations') }}" />

            <x-combo name="service" label="{{ __('staff.filters.service') }}" :options="$services->pluck('name', 'id')"
                     :selected="$filters['service']" placeholder="{{ __('staff.filters.all_services') }}" />

            <x-combo name="provider_type" label="{{ __('staff.filters.provider_type') }}" :options="App\Support\StaffOptions::providerTypes()"
                     :selected="$filters['provider_type']" placeholder="{{ __('staff.filters.all_provider_types') }}" />

            <x-combo name="employment_type" label="{{ __('staff.filters.employment') }}" :options="App\Support\StaffOptions::employmentTypes()"
                     :selected="$filters['employment_type']" placeholder="{{ __('staff.filters.all_employment') }}" />

            @php
                $statusOptions = App\Support\StaffOptions::statuses();
            @endphp
            <x-combo name="status" label="{{ __('staff.filters.status') }}" :options="$statusOptions"
                     :selected="$filters['status']" placeholder="{{ __('staff.filters.all_statuses') }}" />

            <x-combo name="sort" label="{{ __('staff.filters.sort') }}" :options="App\Support\StaffOptions::sorts()"
                     :selected="$filters['sort']" placeholder="{{ __('staff.columns.name') }}" />
          </div>

          <div class="mt-3 flex items-center gap-2">
            <button type="submit" class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('staff.apply_filters') }}
            </button>
            <a href="{{ route('settings.staff.index') }}" class="h-9 px-3.5 inline-flex items-center rounded-lg border border-stroke bg-white hover:bg-hover text-ink text-[13px] font-semibold transition-colors">
              {{ __('common.clear') }}
            </a>
          </div>
        </div>
      </form>

      @if ($staff->isEmpty())
        <div class="mt-6 rounded-card border border-line bg-white p-10 text-center">
          <p class="text-[15px] font-semibold text-head">{{ __('staff.empty_title') }}</p>
          <p class="text-[13px] text-sub mt-1.5">{{ __('staff.empty_hint') }}</p>
        </div>
      @else
        {{-- A table, scrolling inside its own container: the row carries nine
             facts and squeezing them into a phone-width card would drop the
             ones the directory exists to compare. --}}
        <div class="mt-6 rounded-card border border-line bg-white overflow-x-auto">
          <table class="w-full text-[13px]" style="min-width: 860px">
            <thead>
              <tr class="text-left text-[12px] text-sub border-b border-line">
                <th class="font-medium px-4 py-3">{{ __('staff.columns.name') }}</th>
                <th class="font-medium px-4 py-3">{{ __('staff.columns.role') }}</th>
                <th class="font-medium px-4 py-3">{{ __('staff.columns.location') }}</th>
                <th class="font-medium px-4 py-3">{{ __('staff.columns.contact') }}</th>
                <th class="font-medium px-4 py-3 text-right">{{ __('staff.columns.services') }}</th>
                <th class="font-medium px-4 py-3">{{ __('staff.columns.status') }}</th>
                <th class="font-medium px-4 py-3">{{ __('staff.columns.last_login') }}</th>
                <th class="font-medium px-4 py-3 text-right"><span class="sr-only">{{ __('staff.columns.actions') }}</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              @foreach ($staff as $member)
                <tr class="hover:bg-hover/50 transition-colors">
                  <td class="px-4 py-3">
                    <span class="flex items-center gap-2.5">
                      <span class="sd-avatar sd-avatar--sm shrink-0" aria-hidden="true">{{ $member->initials() }}</span>
                      <span class="min-w-0">
                        <span class="block font-semibold text-head truncate">{{ $member->directoryName() }}</span>
                        @if ($member->job_title)
                          <span class="block text-[12px] text-sub truncate">{{ $member->job_title }}</span>
                        @endif
                      </span>
                    </span>
                  </td>
                  {{-- Falls back to the role the row names when no Role
                       record is linked. A dash there would say "this person
                       has no role", when what happened is that a link was
                       never made. --}}
                  <td class="px-4 py-3 text-ink">{{ $member->roleName() }}</td>
                  <td class="px-4 py-3 text-ink">{{ $member->location?->name ?? __('staff.all_locations') }}</td>
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
                    {{ $member->user?->last_login_at?->diffForHumans() ?? __('staff.never') }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="styledesk_rowmenu" data-rowmenu>
                      <button type="button" class="styledesk_rowmenu__button" data-rowmenu-button
                              aria-haspopup="true" aria-expanded="false"
                              aria-label="{{ __('staff.actions_for', ['name' => $member->displayName()]) }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                          <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                        </svg>
                      </button>

                      <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
                        <a href="{{ route('settings.staff.show', $member) }}" class="styledesk_rowmenu__item" role="menuitem">
                          <x-icon name="user" size="14" /> {{ __('staff.view_profile') }}
                        </a>

                        @can('update', $member)
                          <a href="{{ route('settings.staff.edit', $member) }}" class="styledesk_rowmenu__item" role="menuitem">
                            <x-icon name="pen-to-square" size="14" /> {{ __('common.edit') }}
                          </a>
                        @endcan

                        {{-- Only rendered when it is actually permitted. The
                             policy refuses deleting yourself and deleting the
                             last owner, so those rows simply do not offer it
                             rather than offering a button that will be
                             refused. --}}
                        @can('delete', $member)
                          {{-- No separator before Delete. Three items is a
                               short enough list to read at a glance, and a
                               rule through the middle of it implies a grouping
                               that is not there. Delete stays distinguishable
                               by being the only red one. --}}
                          <button type="button" class="styledesk_rowmenu__item styledesk_rowmenu__item--danger"
                                  role="menuitem"
                                  data-delete-staff
                                  data-name="{{ $member->displayName() }}"
                                  data-action="{{ route('settings.staff.destroy', $member) }}">
                            <x-icon name="trash-can" size="14" /> {{ __('common.delete') }}
                          </button>
                        @endcan
                      </span>
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Only once there is more than one page. A pager under a list that
             fits on one screen is furniture describing nothing. --}}
        @if ($staff->hasPages())
          {{-- Laravel's own pager, which already prints "Showing 1 to 25 of
               28 results" and drops to prev/next on a phone. A hand-written
               count beside it said the same thing twice. --}}
          <div class="mt-4">
            {{ $staff->onEachSide(1)->links() }}
          </div>
        @endif
      @endif

      {{-- One delete form for the table, not one per row: a form per row is
           twelve identical elements whose only difference is an action, and
           the confirmation has to name the person anyway. --}}
      <form id="staffDeleteForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
      </form>
    </div>
  </main>
@endsection

@push('scripts')
  <script>
    /* The filter panel. A plain disclosure: the filters are a real GET form
       and work with no JavaScript at all, so this only decides whether they
       are on screen. */
    (function () {
      var toggle = document.querySelector('[data-filter-toggle]');
      var panel = document.getElementById('staff-filters');
      if (!toggle || !panel) return;

      toggle.addEventListener('click', function () {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
      });
    }());

    /* Row action menus. */
    (function () {
      var menus = Array.prototype.slice.call(document.querySelectorAll('[data-rowmenu]'));
      if (!menus.length) return;

      function closeAll(except) {
        menus.forEach(function (menu) {
          if (menu === except) return;
          menu.querySelector('[data-rowmenu-pop]').hidden = true;
          menu.querySelector('[data-rowmenu-button]').setAttribute('aria-expanded', 'false');
          menu.classList.remove('is-open');
        });
      }

      menus.forEach(function (menu) {
        var button = menu.querySelector('[data-rowmenu-button]');
        var pop = menu.querySelector('[data-rowmenu-pop]');

        button.addEventListener('click', function (e) {
          e.stopPropagation();
          var opening = pop.hidden;
          /* One open menu at a time: two panels over the same table is a
             guess about which row the next click belongs to. */
          closeAll(menu);
          pop.hidden = !opening;
          button.setAttribute('aria-expanded', opening ? 'true' : 'false');
          menu.classList.toggle('is-open', opening);

          if (opening) place(button, pop);
        });
      });

      /* The panel is fixed, so it is positioned against the button rather
         than by the layout. Right edges aligned, because the menu sits at the
         end of a row and a left-aligned panel would hang off the page. */
      function place(button, pop) {
        var rect = button.getBoundingClientRect();

        pop.style.visibility = 'hidden';
        var height = pop.offsetHeight;
        var width = pop.offsetWidth;
        pop.style.visibility = '';

        /* Flip above when there is not room below, so the last rows of a
           long table do not open a menu into the fold. */
        var below = window.innerHeight - rect.bottom;
        var top = below < height + 12 ? rect.top - height - 4 : rect.bottom + 4;

        pop.style.top = Math.max(8, top) + 'px';
        pop.style.left = Math.max(8, rect.right - width) + 'px';
      }

      document.addEventListener('click', function () { closeAll(null); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll(null);
      });

      /* A fixed panel cannot follow the thing it is anchored to, so any
         scroll closes it rather than leaving it floating over the page. */
      window.addEventListener('scroll', function () { closeAll(null); }, true);
      window.addEventListener('resize', function () { closeAll(null); });
    }());

    /* Delete, behind a confirmation that names who is being removed. */
    (function () {
      var form = document.getElementById('staffDeleteForm');
      if (!form) return;

      document.querySelectorAll('[data-delete-staff]').forEach(function (button) {
        button.addEventListener('click', function () {
          var name = button.getAttribute('data-name');

          /* Built server-side per language: Spanish does not put the name
             where English does, and string addition cannot express that. */
          var message = @json(__('staff.delete_confirm', ['name' => '__NAME__'])).replace('__NAME__', name);

          if (!window.confirm(message)) {
            return;
          }

          form.action = button.getAttribute('data-action');
          form.submit();
        });
      });
    }());
  </script>
@endpush
