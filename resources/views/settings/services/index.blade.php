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
        {{-- One form holding the whole order. The order is the record, so it
             is stated in full rather than as a pair of swapped ids — two
             people dragging at once would otherwise produce a list neither of
             them arranged. --}}
        <form method="POST" action="{{ route('settings.services.reorder') }}" data-reorder-form class="mt-5">
          @csrf

          <div class="bg-white border border-line rounded-card overflow-hidden">
            <div class="styledesk_catrow styledesk_catrow--slim styledesk_catrow--head">
              <span></span>
              <span>{{ __('services.categories_ui.columns.name') }}</span>
              <span class="hidden md:block">{{ __('services.categories_ui.columns.type') }}</span>
              <span>{{ __('services.categories_ui.columns.status') }}</span>
              <span class="sr-only">{{ __('services.categories_ui.columns.action') }}</span>
            </div>

            <div data-category-list>
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

                <div class="styledesk_catrow styledesk_catrow--slim" draggable="true" data-category-row data-id="{{ $category->id }}">
                  <span class="styledesk_catrow__grip" data-drag-handle aria-hidden="true" title="{{ __('services.categories_ui.reorder_hint') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
                  </span>

                  <span class="min-w-0">
                    <span class="block text-[14px] text-head truncate">{{ $category->name }}</span>
                    @if ($category->description)
                      <span class="block text-[12px] text-sub truncate">{{ $category->description }}</span>
                    @endif
                  </span>

                  <span class="hidden md:block">
                    <span class="styledesk_metachip">
                      {{ $category->isSystem() ? __('services.categories_ui.system') : __('services.categories_ui.custom') }}
                    </span>
                  </span>

                  <span>
                    <span class="styledesk_badge {{ $category->isActive() ? 'styledesk_badge--active' : 'styledesk_badge--soon' }}">
                      {{ $category->isActive() ? __('services.categories_ui.active') : __('services.categories_ui.inactive') }}
                    </span>
                  </span>

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

                  <input type="hidden" name="order[]" value="{{ $category->id }}">
                </div>
              @endforeach
            </div>
          </div>

          {{-- Only offered once something has moved: a Save that does nothing
               is a Save the reader learns to ignore. --}}
          <div class="mt-3 flex items-center gap-3" data-reorder-bar hidden>
            <button type="submit" class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
              {{ __('services.categories_ui.save_order') }}
            </button>
            <p class="text-[12px] text-sub">{{ __('services.categories_ui.order_changed') }}</p>
          </div>
        </form>

        {{-- The per-row forms, outside the reorder form: a form inside a form
             is not a thing HTML has, and the browser silently drops it. --}}
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
