@extends('layouts.app')

@section('title', __('services.categories_ui.title'))

@section('content')
  <main class="w-full px-4 sm:px-5 lg:px-6 pt-5 sm:pt-6 pb-[200px]">
    <div class="styledesk_form">

      <nav class="text-[13px] text-sub" aria-label="Breadcrumb">
        <a href="{{ route('settings.index') }}" class="hover:text-ink transition-colors">{{ __('navigation.app_settings') }}</a>
        <span class="mx-1.5 text-faint">/</span>
        <span class="text-ink">{{ __('services.categories_ui.title') }}</span>
      </nav>

      <div class="mt-3 flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('services.categories_ui.title') }}</h1>
          <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('services.categories_ui.intro') }}</p>
        </div>

        {{-- Back beside Add as one group, secondary first: the same pair, in
             the same order, as every other settings page. --}}
        <div class="shrink-0 flex items-center gap-2">
          <a href="{{ route('settings.index') }}" class="styledesk_action">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ __('common.back') }}
          </a>

          <button type="button" data-category-add
                  class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            <x-icon name="plus" size="14" />
            {{ __('services.categories_ui.add') }}
          </button>
        </div>
      </div>

      <form method="GET" class="mt-5 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed" value="{{ $search }}"
                 aria-label="{{ __('services.categories_ui.search') }}"
                 placeholder="{{ __('services.categories_ui.search') }}">
        </div>

        <button type="submit" class="styledesk_search shrink-0">{{ __('common.search') }}</button>
      </form>

      @if ($categories->isEmpty())
        <p class="text-[13px] text-sub mt-6">{{ __('services.categories_ui.no_matches') }}</p>
      @else
        {{-- A table, in A–Z order.

             It was a hand-arranged list you dragged rows around in. A table
             is what this actually is — five facts per category, read down a
             column — and alphabetical is how somebody finds "Hair Colour"
             among thirty without reading all thirty.

             Dragging is gone with it: a list that re-sorts itself by name
             cannot also be one you arrange by hand, and a grip that appears
             to work and does nothing is worse than no grip. The order
             categories appear in when a service is being filed is still the
             hand-set one — see the note on the controller. --}}
        <div class="sd-tablewrap bg-white border border-line rounded-card mt-5">
          <table class="sd-table sd-table--cards" style="min-width: 640px">
            <thead>
              <tr>
                <th scope="col">{{ __('services.categories_ui.columns.name') }}</th>
                <th scope="col">{{ __('services.categories_ui.columns.type') }}</th>
                <th scope="col" class="sd-table__num">{{ __('services.categories_ui.columns.services') }}</th>
                <th scope="col">{{ __('services.categories_ui.columns.status') }}</th>
                <th scope="col"><span class="sr-only">{{ __('services.categories_ui.columns.action') }}</span></th>
              </tr>
            </thead>

            <tbody>
              @foreach ($categories as $category)
                @php
                    /* Assembled here rather than inline in the attribute:
                       Blade's json directive counts brackets instead of
                       reading PHP, so an array literal written inside the tag
                       ends at its first closing bracket. */
                    $editable = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'system' => $category->isSystem(),
                    ];
                @endphp

                <tr>
                  <td>
                    <span class="sd-cell__label">{{ __('services.categories_ui.columns.name') }}</span>
                    <span class="min-w-0">
                      <span class="block text-[14px] font-medium text-head">{{ $category->name }}</span>
                      @if ($category->description)
                        <span class="block text-[12px] text-sub">{{ $category->description }}</span>
                      @endif
                    </span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('services.categories_ui.columns.type') }}</span>
                    <span class="styledesk_metachip">
                      {{ $category->isSystem() ? __('services.categories_ui.system') : __('services.categories_ui.custom') }}
                    </span>
                  </td>

                  {{-- What is actually filed under it, which is the number
                       somebody wants before they deactivate anything. --}}
                  <td class="sd-table__num">
                    <span class="sd-cell__label">{{ __('services.categories_ui.columns.services') }}</span>
                    <span class="text-head">{{ $category->services_count }}</span>
                  </td>

                  <td>
                    <span class="sd-cell__label">{{ __('services.categories_ui.columns.status') }}</span>
                    <span class="styledesk_badge {{ $category->isActive() ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                      {{ $category->isActive() ? __('services.categories_ui.active') : __('services.categories_ui.inactive') }}
                    </span>
                  </td>

                  <td class="text-right">
                    <span class="styledesk_rowmenu" data-rowmenu>
                      {{-- The same control the listing grids use, so a row of
                           categories and a row of clients are acted on through
                           one recognisable button rather than two. --}}
                      <button type="button" class="styledesk_rowmenu__button" data-rowmenu-button
                              aria-haspopup="true" aria-expanded="false"
                              aria-label="{{ __('services.categories_ui.actions_for', ['name' => $category->name]) }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                          <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                        </svg>
                      </button>

                      <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
                        <button type="button" class="styledesk_rowmenu__item w-full" role="menuitem"
                                data-category-edit data-category='@json($editable)'>
                          {{ __('common.edit') }}
                        </button>

                        <button type="submit" form="categoryToggle{{ $category->id }}" class="styledesk_rowmenu__item w-full" role="menuitem">
                          {{ $category->isActive() ? __('services.categories_ui.deactivate') : __('services.categories_ui.activate') }}
                        </button>

                        {{-- Absent for a system category rather than shown
                             disabled: it is not a thing that will become
                             possible, and a greyed row invites the reader to
                             work out why. --}}
                        @unless ($category->isSystem())
                          <span class="styledesk_rowmenu__rule" role="separator"></span>

                          <button type="submit" form="categoryDelete{{ $category->id }}" role="menuitem"
                                  class="styledesk_rowmenu__item styledesk_rowmenu__item--danger w-full"
                                  data-confirm-title="{{ __('services.categories_ui.delete') }}"
                                  data-confirm="{{ __('services.categories_ui.delete_confirm', ['name' => $category->name]) }}"
                                  data-confirm-label="{{ __('services.categories_ui.delete') }}"
                                  data-confirm-tone="danger">
                            {{ __('services.categories_ui.delete') }}
                          </button>
                        @endunless
                      </span>
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- The per-row forms, kept out of the table: a form is not
             something a <tr> may contain, and the browser silently drops
             one that is. Each row's menu reaches its form by id. --}}
        @foreach ($categories as $category)
          <form id="categoryToggle{{ $category->id }}" method="POST" action="{{ route('settings.services.toggle', $category) }}" class="hidden">
            @csrf @method('PATCH')
          </form>

          @unless ($category->isSystem())
            <form id="categoryDelete{{ $category->id }}" method="POST" action="{{ route('settings.services.destroy', $category) }}" class="hidden">
              @csrf @method('DELETE')
            </form>
          @endunless
        @endforeach
      @endif
    </div>

    @include('settings.services._modal')
  </main>
@endsection

@push('scripts')
  @include('settings.services._scripts')
@endpush
