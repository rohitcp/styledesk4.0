{{--
    Every business subscribed to StyleDesk.

    A server-rendered table rather than the salon application's JS grid: this
    screen is read at a desk, the whole row matters, and a console that cannot
    list its customers when a script fails to load is a console nobody trusts
    during an incident. The filter form works without JavaScript.
--}}
@extends('layouts.backoffice')

@section('title', __('backoffice.nav.clients'))

{{-- The point of this screen is a nine-column table; a reading width would
     put half of it under a horizontal scrollbar on a desk monitor. --}}
@section('container', 'max-w-none')

@section('content')

    <header class="flex flex-wrap items-start gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="text-[22px] font-bold text-head tracking-tight">{{ __('backoffice.clients.title') }}</h1>
            <p class="text-[13px] text-sub mt-1.5">{{ __('backoffice.clients.intro') }}</p>
        </div>
    </header>

    @php
        $cards = [
            ['label' => __('backoffice.clients.stats.total'), 'value' => $stats['total']],
            ['label' => __('backoffice.clients.stats.active'), 'value' => $stats['active']],
            ['label' => __('backoffice.clients.stats.trialing'), 'value' => $stats['trialing']],
            ['label' => __('backoffice.clients.statuses.past_due'), 'value' => $stats['past_due']],
            ['label' => __('backoffice.clients.statuses.disabled'), 'value' => $stats['disabled']],
        ];
    @endphp

    <div class="mt-5 grid grid-cols-2 lg:grid-cols-5 gap-3">
        @foreach ($cards as $card)
            <div class="sd-card px-4 py-3.5">
                <p class="text-[12px] text-sub">{{ $card['label'] }}</p>
                <p class="text-[22px] font-bold text-head mt-1 tabular-nums">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------ filters --}}
    <form method="GET" action="{{ route('backoffice.clients.index') }}"
          class="mt-5 flex flex-wrap items-end gap-3">

        <div class="min-w-0 flex-1 basis-72">
            <label for="search" class="block text-[12px] font-medium text-ink mb-1.5">
                {{ __('backoffice.clients.search_label') }}
            </label>
            <input id="search" name="search" type="search" class="sd-input"
                   placeholder="{{ __('backoffice.clients.search_placeholder') }}"
                   value="{{ $filters['search'] }}">
        </div>

        {{-- One control for the five states the column shows, rather than two
             that each hold half the answer. --}}
        <div>
            <label for="status" class="block text-[12px] font-medium text-ink mb-1.5">
                {{ __('backoffice.clients.status') }}
            </label>
            <select id="status" name="status" class="sd-input" onchange="this.form.submit()">
                <option value="">{{ __('backoffice.clients.any') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>
                        {{ __('backoffice.clients.statuses.'.$status) }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Carried through the form, or changing a filter would silently
             throw away the order the reader chose. --}}
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="direction" value="{{ $filters['direction'] }}">

        <button type="submit"
                class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('backoffice.clients.apply') }}
        </button>

        @if ($filters['search'] !== '' || $filters['status'] !== '')
            <a href="{{ route('backoffice.clients.index') }}"
               class="h-10 inline-flex items-center px-3 text-[13px] font-semibold text-link hover:underline">
                {{ __('backoffice.clients.clear') }}
            </a>
        @endif
    </form>

    {{-- ---------------------------------------------------------- datatable --}}
    @php
        /* A sortable heading flips direction when it is already the sort, and
           otherwise starts on the direction that column is usually read in:
           newest business first, but names from A. */
        $sortLink = function (string $key) use ($filters) {
            $isCurrent = $filters['sort'] === $key;
            $next = $isCurrent
                ? ($filters['direction'] === 'asc' ? 'desc' : 'asc')
                : ($key === 'created' ? 'desc' : 'asc');

            return [
                'url' => request()->fullUrlWithQuery(['sort' => $key, 'direction' => $next, 'page' => null]),
                'current' => $isCurrent,
                'aria' => $isCurrent ? ($filters['direction'] === 'asc' ? 'ascending' : 'descending') : 'none',
                'arrow' => $isCurrent ? ($filters['direction'] === 'asc' ? '↑' : '↓') : '↕',
            ];
        };
    @endphp

    <section class="sd-card mt-4 overflow-hidden">
        {{-- The scroller is on the table, not the page: a console read at 100%
             width should never move its own navigation sideways. --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-[13px] border-collapse">
                <caption class="sr-only">{{ __('backoffice.clients.title') }}</caption>

                {{-- Sticky, so the columns stay named on a long page. --}}
                <thead class="sticky top-0 z-10 bg-[#fbfbfc]">
                    <tr class="border-b border-line text-left text-[11.5px] uppercase tracking-wide text-sub">
                        @php $sort = $sortLink('name'); @endphp
                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap" aria-sort="{{ $sort['aria'] }}">
                            <a href="{{ $sort['url'] }}" class="inline-flex items-center gap-1 hover:text-head">
                                {{ __('backoffice.clients.col.business') }}
                                <span aria-hidden="true" @class(['text-faint' => ! $sort['current']])>{{ $sort['arrow'] }}</span>
                            </a>
                        </th>

                        @php $sort = $sortLink('status'); @endphp
                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap" aria-sort="{{ $sort['aria'] }}">
                            <a href="{{ $sort['url'] }}" class="inline-flex items-center gap-1 hover:text-head">
                                {{ __('backoffice.clients.col.status') }}
                                <span aria-hidden="true" @class(['text-faint' => ! $sort['current']])>{{ $sort['arrow'] }}</span>
                            </a>
                        </th>

                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap">{{ __('backoffice.clients.col.owner') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap">{{ __('backoffice.clients.col.plan') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap text-right">{{ __('backoffice.clients.col.locations') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap text-right">{{ __('backoffice.clients.col.staff') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap text-right">{{ __('backoffice.clients.col.clients') }}</th>

                        @php $sort = $sortLink('created'); @endphp
                        <th scope="col" class="px-4 py-3 font-semibold whitespace-nowrap text-right" aria-sort="{{ $sort['aria'] }}">
                            <a href="{{ $sort['url'] }}" class="inline-flex items-center gap-1 hover:text-head">
                                {{ __('backoffice.clients.col.joined') }}
                                <span aria-hidden="true" @class(['text-faint' => ! $sort['current']])>{{ $sort['arrow'] }}</span>
                            </a>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @forelse ($clients as $client)
                        <tr class="odd:bg-white even:bg-[#fcfcfd] hover:bg-brand/[0.04] transition-colors">
                            <td class="px-4 py-3 align-top">
                                {{-- The whole row is about this business, and the
                                     name is the thing a reader points at. --}}
                                <a href="{{ route('backoffice.clients.show', $client) }}"
                                   class="font-semibold text-head hover:text-brand hover:underline">
                                    {{ $client->name }}
                                </a>
                                <span class="block text-[12px] text-sub">
                                    {{ $client->slug }}@if ($client->country_code) · {{ $client->country_code }}@endif
                                </span>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <x-backoffice.client-status :status="$client->displayStatus()" />
                            </td>

                            <td class="px-4 py-3 align-top">
                                @if ($client->owner)
                                    <span class="block text-head">{{ $client->owner->name }}</span>
                                    <span class="block text-[12px] text-sub">{{ $client->owner->email }}</span>
                                @else
                                    <span class="text-faint">{{ __('backoffice.clients.no_owner') }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top">
                                <span class="block text-head">{{ $client->plan_id ?? __('backoffice.clients.no_plan') }}</span>
                                {{-- Days left, but only while there are any: the Status
                                     column already names the state, and a trial that has
                                     lapsed says so there. --}}
                                @if ($client->trial_ends_at && $client->trialDaysRemaining() > 0)
                                    <span class="block text-[12px] text-sub">
                                        {{ __('backoffice.clients.trial_days', ['days' => $client->trialDaysRemaining()]) }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top text-right tabular-nums text-head">{{ number_format($client->locations_count) }}</td>
                            <td class="px-4 py-3 align-top text-right tabular-nums text-head">{{ number_format($client->staff_count) }}</td>
                            <td class="px-4 py-3 align-top text-right tabular-nums text-head">{{ number_format($client->clients_count) }}</td>

                            <td class="px-4 py-3 align-top text-right whitespace-nowrap text-sub">
                                {{ $client->created_at?->translatedFormat('j M Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <p class="text-[13px] text-sub">
                                    {{ $stats['total'] === 0
                                        ? __('backoffice.clients.empty')
                                        : __('backoffice.clients.no_matches') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- The count and the pager share the footer, so "showing 1–25 of 300"
             is beside the control that changes it. --}}
        @if ($clients->total() > 0)
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3">
                <p class="text-[12.5px] text-sub">
                    {{ __('backoffice.clients.showing', [
                        'first' => number_format($clients->firstItem()),
                        'last' => number_format($clients->lastItem()),
                        'total' => number_format($clients->total()),
                    ]) }}
                </p>

                @if ($clients->hasPages())
                    <div class="[&_nav]:!m-0">{{ $clients->links() }}</div>
                @endif
            </div>
        @endif
    </section>
@endsection
