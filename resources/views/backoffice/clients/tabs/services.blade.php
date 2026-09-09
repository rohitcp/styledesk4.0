{{--
    The services this business sells.

    Listing only for this phase. The console reads a customer's configuration
    to answer a support question; editing a salon's price list from here would
    be changing it without the salon, so there is nothing on this tab that can
    be pressed.
--}}
@php
    $columns = [
        ['label' => __('backoffice.services.col.name'), 'sort' => 'name'],
        ['label' => __('backoffice.services.col.category')],
        ['label' => __('backoffice.services.col.duration'), 'sort' => 'duration', 'align' => 'right'],
        ['label' => __('backoffice.services.col.price'), 'align' => 'right'],
        ['label' => __('backoffice.services.col.location')],
        ['label' => __('backoffice.services.col.status'), 'sort' => 'status'],
        ['label' => __('backoffice.services.col.created'), 'sort' => 'created', 'desc_first' => true, 'align' => 'right'],
    ];

    /* The currency the business prices in, not the reader's: the figure on
       this screen has to be the figure on the salon's own screen. */
    $currency = (string) $client->currency_code;
@endphp

<x-backoffice.data-table
    :paginator="$services"
    :columns="$columns"
    :sort="$serviceFilters['sort']"
    :direction="$serviceFilters['direction']"
    :search="$serviceFilters['search']"
    :search-placeholder="__('backoffice.services.search_placeholder')"
    :per-page="$serviceFilters['per_page']"
    :caption="__('backoffice.tabs.services')"
    :empty="__('backoffice.services.empty')"
    :filters="[[
        'name' => 'status',
        'label' => __('backoffice.services.col.status'),
        'value' => $serviceFilters['status'],
        'options' => [
            'active' => __('backoffice.services.statuses.active'),
            'inactive' => __('backoffice.services.statuses.inactive'),
        ],
    ]]">

    @foreach ($services as $service)
        <tr class="odd:bg-white even:bg-[#fcfcfd] hover:bg-brand/[0.04] transition-colors">
            <td class="px-4 py-3 align-top">
                <span class="font-semibold text-head">{{ $service->name }}</span>

                @if ($service->description)
                    <span class="block text-[12px] text-sub line-clamp-1">{{ $service->description }}</span>
                @endif
            </td>

            <td class="px-4 py-3 align-top text-sub">
                {{ $service->category?->name ?? __('backoffice.clients.none') }}
            </td>

            <td class="px-4 py-3 align-top text-right whitespace-nowrap text-sub tabular-nums">
                {{ $service->durationLabel() }}
            </td>

            <td class="px-4 py-3 align-top text-right whitespace-nowrap text-head tabular-nums">
                {{-- Empty, not "0.00": a service nobody has priced yet is not a
                     free one, and a table that says it is will be believed. --}}
                {{ $service->priceLabel($currency) ?: __('backoffice.services.no_price') }}
            </td>

            <td class="px-4 py-3 align-top text-sub">
                {{-- No rows on the pivot means everywhere, which is what the
                     booking engine reads it as. A dash here would say the
                     opposite of what the service actually does. --}}
                {{ $service->locations->isEmpty()
                    ? __('backoffice.services.all_locations')
                    : $service->locations->pluck('name')->implode(', ') }}
            </td>

            <td class="px-4 py-3 align-top">
                <span @class([
                    'inline-flex items-center rounded-full px-2 py-0.5 text-[11.5px] font-semibold whitespace-nowrap',
                    'bg-emerald-50 text-emerald-700' => $service->is_active,
                    'bg-slate-100 text-slate-600' => ! $service->is_active,
                ])>
                    {{ __('backoffice.services.statuses.'.($service->is_active ? 'active' : 'inactive')) }}
                </span>
            </td>

            <td class="px-4 py-3 align-top text-right whitespace-nowrap text-sub">
                {{ $service->created_at?->translatedFormat('j M Y') }}
            </td>
        </tr>
    @endforeach
</x-backoffice.data-table>
