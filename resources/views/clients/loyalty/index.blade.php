@extends('layouts.app')

@section('title', __('loyalty.members.title'))

{{--
    Clients → Loyalty.

    Everybody who has joined the scheme, newest first. A list of members
    rather than a filter on the client list: the columns that make it worth
    opening — balance, lifetime earned, redeemed — say nothing about somebody
    who never joined, and a list where most rows are blank teaches the reader
    to stop reading it.

    The shared listing grid, the same one the clients, the plans and the
    members use, so this table behaves like every other table in the app.
--}}

@section('content')
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

    <div class="flex flex-wrap items-start gap-4">
      <div class="min-w-0 flex-1">
        <h1 class="text-[24px] sm:text-[28px] font-bold text-head tracking-tight">{{ __('loyalty.members.title') }}</h1>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[640px]">{{ __('loyalty.members.intro') }}</p>
      </div>
    </div>

    @unless ($settings->is_enabled)
      {{-- Not an empty list: the scheme has never been switched on, so there
           is nothing to be empty. Saying "no members yet" would send the
           reader looking for members instead of for the switch. --}}
      <div class="sd-card p-8 mt-6 text-center">
        <span class="styledesk_badge styledesk_badge--soon">{{ __('common.coming_soon') }}</span>

        <h2 class="text-[16px] font-semibold text-head mt-3">{{ __('loyalty.members.disabled') }}</h2>
        <p class="text-[13px] text-sub mt-1.5 leading-relaxed max-w-[460px] mx-auto">
          {{ __('loyalty.members.disabled_hint') }}
        </p>

        @if ($canConfigure)
          <a href="{{ route('settings.loyalty.index') }}"
             class="inline-flex items-center h-9 px-4 mt-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('loyalty.members.enable') }}
          </a>
        @else
          {{-- Somebody who cannot reach App Settings is not helped by a link
               that would turn them away at the door. --}}
          <p class="text-[12.5px] text-faint mt-4">{{ __('loyalty.members.ask_an_owner') }}</p>
        @endif
      </div>
    @else
      <form method="GET" action="{{ route('clients.loyalty') }}" class="mt-4">
        <div class="relative w-full lg:w-[320px]">
          <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
            <x-icon name="magnifying-glass" size="15" />
          </span>
          <input name="search" type="search" class="sd-input styledesk_input--prefixed"
                 value="{{ $filters['search'] }}" data-live-search
                 aria-label="{{ __('loyalty.members.search') }}"
                 placeholder="{{ __('loyalty.members.search') }}">

          @if ($filters['search'] !== '')
            <button type="button" class="styledesk_input__clear" data-search-clear
                    aria-label="{{ __('common.clear') }}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
          @endif
        </div>
      </form>

      <p class="mt-3 text-[12px] font-semibold text-sub" data-result-count></p>

      @php
          $gridConfig = [
              'labels' => [
                  'actions_for' => __('loyalty.members.actions_for', ['name' => ':name']),
                  'showing' => __('loyalty.members.showing'),
                  'results' => [
                      'zero' => __('loyalty.members.results.zero'),
                      'one' => __('loyalty.members.results.one'),
                      'many' => __('loyalty.members.results.many'),
                  ],
                  'clear_filters' => __('loyalty.members.results.clear'),
                  'empty' => __('loyalty.members.none'),
              ],
              'name_field' => 'name',
              'page_size' => 25,
              'columns' => [
                  ['field' => 'name', 'title' => __('loyalty.members.columns.client'), 'type' => 'primary', 'grow' => 1.6, 'min' => 170, 'responsive' => 0],
                  ['field' => 'member_id', 'title' => __('loyalty.members.columns.member_id'), 'width' => 130, 'responsive' => 4, 'muted' => true],
                  ['field' => 'mobile', 'title' => __('loyalty.members.columns.mobile'), 'width' => 150, 'responsive' => 2],
                  ['field' => 'email', 'title' => __('loyalty.members.columns.email'), 'grow' => 1.4, 'min' => 170, 'responsive' => 5],
                  ['field' => 'status', 'title' => __('loyalty.members.columns.status'), 'type' => 'badge', 'width' => 120, 'responsive' => 1],
                  ['field' => 'enrolled', 'title' => __('loyalty.members.columns.enrolled'), 'width' => 120, 'responsive' => 6, 'muted' => true],
                  ['field' => 'balance', 'title' => __('loyalty.members.columns.balance'), 'width' => 110, 'responsive' => 0],
                  ['field' => 'earned', 'title' => __('loyalty.members.columns.earned'), 'width' => 120, 'responsive' => 7, 'muted' => true],
                  ['field' => 'redeemed', 'title' => __('loyalty.members.columns.redeemed'), 'width' => 120, 'responsive' => 8, 'muted' => true],
                  ['field' => 'tier', 'title' => __('loyalty.members.columns.tier'), 'width' => 110, 'responsive' => 9, 'muted' => true],
                  ['field' => 'last_activity', 'title' => __('loyalty.members.columns.last_activity'), 'width' => 140, 'responsive' => 3, 'muted' => true],
                  ['type' => 'actions'],
              ],
          ];
      @endphp

      <div class="mt-2 styledesk_gridframe">
        <div data-grid
             data-url="{{ route('clients.loyalty.data', array_filter($filters)) }}"
             data-config='@json($gridConfig)'></div>
      </div>
    @endunless

  </main>
@endsection
