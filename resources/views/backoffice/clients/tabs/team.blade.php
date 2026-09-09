{{--
    The people who work at this business.

    Listing only for this phase: no adding, no removing, no resetting anybody's
    password and nothing touching permissions. Those are the salon's own to
    manage, and a console that could do them quietly would be a console that
    could do them by accident.

    Filtered by location rather than by status. Staff::status() is derived from
    four columns and a precedence — archived beats suspended beats an
    unaccepted invitation — and a WHERE that re-stated the rule would be a
    second definition of who counts as active, free to disagree with the first.
--}}
@php
    $columns = [
        ['label' => __('backoffice.team.col.name'), 'sort' => 'name'],
        ['label' => __('backoffice.team.col.email'), 'sort' => 'email'],
        ['label' => __('backoffice.team.col.role')],
        ['label' => __('backoffice.team.col.location')],
        ['label' => __('backoffice.team.col.status')],
        ['label' => __('backoffice.team.col.last_login'), 'align' => 'right'],
        ['label' => __('backoffice.team.col.created'), 'sort' => 'created', 'desc_first' => true, 'align' => 'right'],
    ];

    /* The console's palette, not the salon application's badge classes: two
       screens showing the same word in two colours is how a reader stops
       trusting either. */
    $tones = [
        'active' => 'bg-emerald-50 text-emerald-700',
        'inactive' => 'bg-slate-100 text-slate-600',
        'archived' => 'bg-slate-100 text-slate-600',
        'suspended' => 'bg-red-50 text-red-700',
        'on-leave' => 'bg-amber-50 text-amber-700',
        'invite-expired' => 'bg-red-50 text-red-700',
        'invite-failed' => 'bg-red-50 text-red-700',
        'invite-queued' => 'bg-sky-50 text-sky-700',
        'pending-invite' => 'bg-sky-50 text-sky-700',
    ];
@endphp

<x-backoffice.data-table
    :paginator="$team"
    :columns="$columns"
    :sort="$teamFilters['sort']"
    :direction="$teamFilters['direction']"
    :search="$teamFilters['search']"
    :search-placeholder="__('backoffice.team.search_placeholder')"
    :per-page="$teamFilters['per_page']"
    :caption="__('backoffice.tabs.team')"
    :empty="__('backoffice.team.empty')"
    :filters="$teamLocations->isEmpty() ? [] : [[
        'name' => 'location',
        'label' => __('backoffice.team.col.location'),
        'value' => $teamFilters['location'],
        'options' => $teamLocations->all(),
    ]]">

    @foreach ($team as $member)
        @php $status = $member->status(); @endphp

        <tr class="odd:bg-white even:bg-[#fcfcfd] hover:bg-brand/[0.04] transition-colors">
            <td class="px-4 py-3 align-top">
                <span class="font-semibold text-head">{{ $member->displayName() }}</span>

                @if ($member->job_title)
                    <span class="block text-[12px] text-sub">{{ $member->job_title }}</span>
                @endif
            </td>

            <td class="px-4 py-3 align-top text-sub break-all">
                {{ $member->work_email ?: ($member->email ?: __('backoffice.clients.none')) }}
            </td>

            <td class="px-4 py-3 align-top text-sub">{{ $member->roleName() }}</td>

            <td class="px-4 py-3 align-top text-sub">
                {{ $member->location?->name ?? __('backoffice.clients.none') }}
            </td>

            <td class="px-4 py-3 align-top">
                <span @class([
                    'inline-flex items-center rounded-full px-2 py-0.5 text-[11.5px] font-semibold whitespace-nowrap',
                    $tones[$status] ?? 'bg-slate-100 text-slate-600',
                ])>
                    {{ $member->statusLabel() }}
                </span>
            </td>

            <td class="px-4 py-3 align-top text-right whitespace-nowrap text-sub">
                {{-- Read from the account behind the row. Somebody invited but
                     not yet signed up has no account to have signed in with,
                     which is a different answer from "never". --}}
                @if ($member->user === null)
                    <span class="text-faint">{{ __('backoffice.team.no_account') }}</span>
                @else
                    {{ \App\Support\TimeFormat::dateTime($member->user->last_login_at) ?? __('backoffice.clients.never') }}
                @endif
            </td>

            <td class="px-4 py-3 align-top text-right whitespace-nowrap text-sub">
                {{ $member->created_at?->translatedFormat('j M Y') }}
            </td>
        </tr>
    @endforeach
</x-backoffice.data-table>
