@extends('layouts.app')

@section('title', __('resources.title'))

@section('content')
  <main class="w-full px-6 lg:px-8 pt-5 pb-[100px]">

    <header class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('resources.title') }}</h1>
        <p class="text-[14px] text-sub mt-2 max-w-[640px] leading-relaxed">{{ __('resources.subtitle') }}</p>
      </div>

      @if ($canCreate)
        <button type="button" class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors"
                data-resource-add>
          <x-icon name="plus" size="14" />
          {{ __('resources.add') }}
        </button>
      @endif
    </header>

    @if ($total === 0)
      {{-- Nothing yet, and nothing pretending otherwise: no search over an
           empty list, no filters that can only return nothing. --}}
      <div class="mt-8 max-w-[520px]">
        <p class="text-[15px] font-semibold text-head">{{ __('resources.none_yet') }}</p>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed">{{ __('resources.none_yet_hint') }}</p>
      </div>
    @else
      <form method="GET" class="flex flex-wrap items-center gap-2 mt-6">
        <div class="relative flex-1 min-w-[220px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="14" />
          </span>
          <input type="search" name="search" value="{{ $filters['search'] }}"
                 class="sd-input styledesk_input--prefixed !h-9"
                 placeholder="{{ __('resources.search') }}" aria-label="{{ __('resources.search') }}">
        </div>

        <x-combo name="category" :options="$categories->pluck('name', 'id')" :selected="$filters['category']"
                 :placeholder="__('resources.all_categories')"
                 class="w-full lg:w-[180px] shrink-0" />

        <x-combo name="location" :options="$locations->pluck('name', 'id')" :selected="$filters['location']"
                 :placeholder="__('resources.all_locations')"
                 class="w-full lg:w-[180px] shrink-0" />

        <x-combo name="status" :selected="$filters['status']"
                 :placeholder="__('resources.all_statuses')"
                 :options="[
                     'available' => __('resources.availability.available'),
                     'blocked' => __('resources.availability.blocked'),
                     'inactive' => __('resources.availability.inactive'),
                 ]"
                 class="w-full lg:w-[170px] shrink-0" />

        <button type="submit" class="styledesk_action styledesk_action--sm">{{ __('common.search') }}</button>
      </form>

      <p class="text-[13px] text-sub mt-4">{{ trans_choice('resources.count', $resources->count()) }}</p>

      @if ($resources->isEmpty())
        <p class="text-[13px] text-sub mt-6">{{ __('resources.no_matches') }}</p>
      @else
        {{-- Grouped by category, because that is how a business counts them:
             four styling chairs, two treatment rooms. --}}
        @foreach ($grouped as $categoryName => $items)
          <section class="mt-6">
            <h2 class="styledesk_label">{{ $categoryName }}</h2>

            <div class="grid gap-2.5 mt-2.5 sm:grid-cols-2 xl:grid-cols-3">
              @foreach ($items as $resource)
                @include('resources.partials._card', ['resource' => $resource])
              @endforeach
            </div>
          </section>
        @endforeach
      @endif
    @endif

    @if ($canCreate || $canEdit)
      @include('resources.partials._form-modal')
    @endif

    @if ($canBlock)
      @include('resources.partials._block-modal')
    @endif
  </main>
@endsection

@push('scripts')
  @include('resources.partials._scripts')
@endpush
