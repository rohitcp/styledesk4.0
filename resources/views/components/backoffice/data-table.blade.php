{{--
    The console's one table.

    Services, the email log and the team directory are the same screen three
    times over — search, filter, sort, page, choose how many rows — and three
    hand-rolled copies is three chances for the sort arrow to point the wrong
    way on one of them. The columns and the filters are described; the rows
    are the caller's, because only the caller knows what a row means.

    Server-rendered, like the rest of the console. The salon application has a
    JavaScript grid; a platform console that cannot list a customer's services
    when a script fails to load is one nobody trusts during an incident. The
    toolbar is a plain GET form and works with scripting switched off.
--}}
@props([
    'paginator',
    'columns',
    'sort' => '',
    'direction' => 'asc',
    'search' => '',
    'searchPlaceholder' => '',
    'filters' => [],
    'perPage' => 25,
    'perPageOptions' => [10, 25, 50, 100],
    'caption' => '',
    'empty' => '',
    'noMatches' => '',
    'minWidth' => '900px',
])

@php
    /* Whether the reader has narrowed anything. Decides which dead end the
       empty state offers: "nothing here yet" and "nothing matches" are
       different sentences and only one of them has a way out. */
    $narrowed = $search !== '' || collect($filters)->contains(fn (array $filter) => ($filter['value'] ?? '') !== '');

    /**
     * A sortable heading flips direction when it is already the sort, and
     * otherwise starts on the direction that column is usually read in:
     * newest first for a date, A first for a name.
     */
    $sortLink = function (array $column) use ($sort, $direction) {
        $isCurrent = $sort === $column['sort'];
        $next = $isCurrent
            ? ($direction === 'asc' ? 'desc' : 'asc')
            : ($column['desc_first'] ?? false ? 'desc' : 'asc');

        return [
            'url' => request()->fullUrlWithQuery(['sort' => $column['sort'], 'direction' => $next, 'page' => null]),
            'current' => $isCurrent,
            'aria' => $isCurrent ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none',
            'arrow' => $isCurrent ? ($direction === 'asc' ? '↑' : '↓') : '↕',
        ];
    };

    /* The page the reader is on is deliberately dropped: a narrower search
       has fewer pages, and carrying page 4 into it lands on an empty one. */
    $action = url()->current();
@endphp

<div {{ $attributes->merge(['class' => 'sd-card overflow-hidden']) }}>

    {{-- ------------------------------------------------------------ toolbar --}}
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3 px-4 py-3.5 border-b border-line">

        <div class="min-w-0 flex-1 basis-64">
            <label for="{{ $id = 'dt-search-'.\Illuminate\Support\Str::slug($caption ?: 'table') }}"
                   class="block text-[12px] font-medium text-ink mb-1.5">
                {{ __('backoffice.table.search') }}
            </label>
            <input id="{{ $id }}" name="search" type="search" class="sd-input"
                   value="{{ $search }}"
                   placeholder="{{ $searchPlaceholder ?: __('backoffice.table.search') }}">
        </div>

        @foreach ($filters as $filter)
            <div>
                <label for="dt-{{ $filter['name'] }}" class="block text-[12px] font-medium text-ink mb-1.5">
                    {{ $filter['label'] }}
                </label>
                <select id="dt-{{ $filter['name'] }}" name="{{ $filter['name'] }}" class="sd-input"
                        onchange="this.form.submit()">
                    <option value="">{{ $filter['any'] ?? __('backoffice.clients.any') }}</option>
                    @foreach ($filter['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) ($filter['value'] ?? '') === (string) $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endforeach

        <div>
            <label for="dt-per-page-{{ $id }}" class="block text-[12px] font-medium text-ink mb-1.5">
                {{ __('backoffice.table.per_page') }}
            </label>
            <select id="dt-per-page-{{ $id }}" name="per_page" class="sd-input" onchange="this.form.submit()">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        {{-- Carried through, or changing a filter would silently throw away
             the order the reader chose. --}}
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">

        <button type="submit"
                class="h-10 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
            {{ __('backoffice.clients.apply') }}
        </button>

        @if ($narrowed)
            <a href="{{ $action }}"
               class="h-10 inline-flex items-center px-3 text-[13px] font-semibold text-link hover:underline">
                {{ __('backoffice.clients.clear') }}
            </a>
        @endif
    </form>

    {{-- -------------------------------------------------------------- table --}}
    {{-- The scroller is on the table, not the page: a console read at full
         width should never move its own navigation sideways. --}}
    <div class="overflow-x-auto">
        <table class="w-full text-[13px] border-collapse" style="min-width: {{ $minWidth }}">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif

            <thead class="bg-[#fbfbfc]">
                <tr class="border-b border-line text-left text-[11.5px] uppercase tracking-wide text-sub">
                    @foreach ($columns as $column)
                        @php $sortable = ($column['sort'] ?? null) !== null; @endphp
                        @php $link = $sortable ? $sortLink($column) : null; @endphp

                        <th scope="col"
                            @if ($sortable) aria-sort="{{ $link['aria'] }}" @endif
                            @class([
                                'px-4 py-3 font-semibold whitespace-nowrap',
                                'text-right' => ($column['align'] ?? 'left') === 'right',
                            ])>
                            @if ($sortable)
                                <a href="{{ $link['url'] }}" class="inline-flex items-center gap-1 hover:text-head">
                                    {{ $column['label'] }}
                                    <span aria-hidden="true" @class(['text-faint' => ! $link['current']])>{{ $link['arrow'] }}</span>
                                </a>
                            @else
                                {{ $column['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody class="divide-y divide-line">
                @if ($paginator->total() > 0)
                    {{ $slot }}
                @else
                    <tr>
                        <td colspan="{{ count($columns) }}" class="px-4 py-12 text-center">
                            <p class="text-[13px] text-sub">
                                {{ $narrowed ? ($noMatches ?: __('backoffice.table.no_matches')) : $empty }}
                            </p>
                            @if ($narrowed)
                                <a href="{{ $action }}" class="inline-block mt-2 text-[13px] font-semibold text-link hover:underline">
                                    {{ __('backoffice.clients.clear') }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- The count and the pager share the footer, so "showing 1–25 of 300" is
         beside the control that changes it. --}}
    @if ($paginator->total() > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3">
            <p class="text-[12.5px] text-sub">
                {{ __('backoffice.clients.showing', [
                    'first' => number_format($paginator->firstItem()),
                    'last' => number_format($paginator->lastItem()),
                    'total' => number_format($paginator->total()),
                ]) }}
            </p>

            @if ($paginator->hasPages())
                {{-- The pager carries its own "Showing 1 to 25 of 44" on wide
                     screens, which is the line to its left said twice. The
                     line stays and the pager's copy goes, because the line is
                     the one that is still there on a single page. --}}
                <div class="[&_nav]:!m-0 [&_p]:hidden">{{ $paginator->links() }}</div>
            @endif
        </div>
    @endif
</div>
