{{--
    The summary of one client account.

    What a support call asks for in the order it asks: how big is this
    business, who runs it, what are they paying for, where are they, and what
    has the platform done to them lately.
--}}
@php
    /* A dash rather than an empty cell: blank reads as "the page failed to
       load this", a dash reads as "there is nothing here". */
    $or = fn (?string $value) => ($value === null || $value === '') ? __('backoffice.clients.none') : $value;

    /* Locations carry their own status vocabulary, which has no enum behind
       it either. A readable word beats a printed lang key. */
    $label = fn (?string $value): string => ($value === null || $value === '')
        ? __('backoffice.clients.none')
        : \Illuminate\Support\Str::headline($value);

    $usageCards = [
        ['label' => __('backoffice.clients.col.locations'), 'value' => $usage['locations']],
        ['label' => __('backoffice.tabs.team'), 'value' => $usage['team']],
        ['label' => __('backoffice.clients.stats.services'), 'value' => $usage['services']],
        ['label' => __('backoffice.clients.col.clients'), 'value' => $usage['clients']],
        ['label' => __('backoffice.clients.stats.users'), 'value' => $usage['users']],
        ['label' => __('backoffice.clients.stats.bookings'), 'value' => $usage['bookings']],
    ];
@endphp

{{-- --------------------------------------------------------- usage summary --}}
<h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.usage') }}</h2>

<div class="mt-3 grid grid-cols-2 lg:grid-cols-6 gap-3">
    @foreach ($usageCards as $card)
        <div class="sd-card px-4 py-3.5">
            <p class="text-[12px] text-sub">{{ $card['label'] }}</p>
            <p class="text-[22px] font-bold text-head mt-1 tabular-nums">{{ number_format($card['value']) }}</p>
        </div>
    @endforeach
</div>

<div class="mt-4 grid lg:grid-cols-2 gap-4">

    {{-- ------------------------------------------------------------ account --}}
    <section class="sd-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.account') }}</h2>

        @php
            $account = [
                __('backoffice.clients.business_name') => $client->name,
                __('backoffice.clients.status') => __('backoffice.clients.statuses.'.$client->displayStatus()),
                __('backoffice.clients.identifier') => $client->getTenantKey(),
                __('backoffice.clients.col.joined') => $client->created_at?->translatedFormat('j M Y')
                    ?? __('backoffice.clients.none'),
                /* The most recent sign-in by anybody there. "Nobody has ever
                   signed in" is a different answer from "we have no record",
                   and the first is the one that explains a quiet account. */
                __('backoffice.clients.last_activity') => \App\Support\TimeFormat::dateTime($lastActivity)
                    ?? __('backoffice.clients.never'),
            ];
        @endphp

        <dl class="mt-3 divide-y divide-line text-[13px]">
            @foreach ($account as $term => $value)
                <div class="py-2.5 flex items-baseline justify-between gap-4">
                    <dt class="text-sub shrink-0">{{ $term }}</dt>
                    <dd class="text-head font-medium text-right break-all">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ----------------------------------------------------- primary contact --}}
    <section class="sd-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.primary_contact') }}</h2>

        @if ($client->owner)
            @php
                $owner = [
                    __('backoffice.clients.owner_name') => $client->owner->name,
                    __('backoffice.clients.owner_email') => $client->owner->email,
                    __('backoffice.clients.owner_phone') => $or($client->owner->phone),
                ];
            @endphp

            <dl class="mt-3 divide-y divide-line text-[13px]">
                @foreach ($owner as $term => $value)
                    <div class="py-2.5 flex items-baseline justify-between gap-4">
                        <dt class="text-sub shrink-0">{{ $term }}</dt>
                        <dd class="text-head font-medium text-right break-all">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="text-[13px] text-sub mt-3">{{ __('backoffice.clients.no_owner') }}</p>
        @endif
    </section>

    {{-- --------------------------------------------------------------- plan --}}
    <section class="sd-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.subscription') }}</h2>

        @php
            $plan = [
                __('backoffice.clients.col.plan') => $client->plan_id ?? __('backoffice.clients.no_plan'),
                __('backoffice.clients.subscription') => __('backoffice.clients.statuses.'.$client->displayStatus()),
                __('backoffice.clients.trial_started') => $client->trial_started_at?->translatedFormat('j M Y')
                    ?? __('backoffice.clients.none'),
                __('backoffice.clients.trial_ends') => $client->trial_ends_at
                    ? $client->trial_ends_at->translatedFormat('j M Y')
                        .($client->trialDaysRemaining() > 0
                            ? ' · '.__('backoffice.clients.trial_days', ['days' => $client->trialDaysRemaining()])
                            : '')
                    : __('backoffice.clients.none'),
            ];
        @endphp

        <dl class="mt-3 divide-y divide-line text-[13px]">
            @foreach ($plan as $term => $value)
                <div class="py-2.5 flex items-baseline justify-between gap-4">
                    <dt class="text-sub shrink-0">{{ $term }}</dt>
                    <dd class="text-head font-medium text-right">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ------------------------------------------------------------ contact --}}
    <section class="sd-card p-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.contact') }}</h2>

        @php
            $contact = [
                __('backoffice.clients.business_email') => $or($client->business_email),
                __('backoffice.clients.business_phone') => $or($client->business_phone),
                __('backoffice.clients.website') => $or($client->website),
                __('backoffice.clients.country') => $or($client->country_code),
                __('backoffice.clients.currency') => $or($client->currency_code),
                __('backoffice.clients.timezone') => $or($client->timezone),
                __('backoffice.clients.language') => $or($client->default_language),
            ];
        @endphp

        <dl class="mt-3 divide-y divide-line text-[13px]">
            @foreach ($contact as $term => $value)
                <div class="py-2.5 flex items-baseline justify-between gap-4">
                    <dt class="text-sub shrink-0">{{ $term }}</dt>
                    <dd class="text-head font-medium text-right break-all">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>

{{-- ------------------------------------------------------------- locations --}}
<section class="sd-card mt-4 overflow-hidden">
    <h2 class="text-[15px] font-semibold text-head px-5 pt-5 pb-3">
        {{ __('backoffice.clients.col.locations') }}
    </h2>

    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-[#fbfbfc]">
                <tr class="border-y border-line text-left text-[11.5px] uppercase tracking-wide text-sub">
                    <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.location_name') }}</th>
                    <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.location_where') }}</th>
                    <th scope="col" class="px-5 py-2.5 font-semibold">{{ __('backoffice.clients.status') }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-line">
                @forelse ($client->locations as $location)
                    <tr class="odd:bg-white even:bg-[#fcfcfd]">
                        <td class="px-5 py-2.5">
                            <span class="font-medium text-head">{{ $location->name }}</span>
                            @if ($location->is_primary)
                                <span class="ml-1.5 text-[11px] font-semibold text-brand">
                                    {{ __('backoffice.clients.primary') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-sub">
                            {{ collect([$location->city, $location->country])->filter()->implode(', ') ?: __('backoffice.clients.none') }}
                        </td>
                        <td class="px-5 py-2.5 text-sub">{{ $label($location->status) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-8 text-center text-[13px] text-sub">
                            {{ __('backoffice.clients.no_locations') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- --------------------------------------------------------------- history --}}
<section class="sd-card mt-4 p-5">
    <h2 class="text-[15px] font-semibold text-head">{{ __('backoffice.clients.activity') }}</h2>

    @if ($activity->isEmpty())
        <p class="text-[13px] text-sub mt-3">{{ __('backoffice.clients.no_activity') }}</p>
    @else
        <ul class="mt-3 divide-y divide-line">
            @foreach ($activity as $entry)
                <li class="py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <span class="text-[13px] font-semibold text-head">{{ $entry->label() }}</span>
                        <span class="text-[12px] text-faint shrink-0">
                            {{ \App\Support\TimeFormat::dateTime($entry->created_at) }}
                        </span>
                    </div>

                    {{-- The reason as its label, not its key: the log is read
                         by people, and 'non_payment' is not a word. --}}
                    @if (data_get($entry->after, 'reason'))
                        <p class="text-[12.5px] text-sub mt-1">
                            {{ __('backoffice.clients.disable_reason') }}:
                            {{ __('backoffice.clients.reasons.'.data_get($entry->after, 'reason')) }}
                        </p>
                    @endif

                    @if (data_get($entry->after, 'note'))
                        <p class="text-[12.5px] text-sub mt-0.5">
                            {{ __('backoffice.clients.note') }}: {{ data_get($entry->after, 'note') }}
                        </p>
                    @endif

                    <p class="text-[12.5px] text-sub mt-0.5">
                        {{ __('backoffice.clients.by') }}:
                        {{ $entry->admin_name ?? $entry->admin_email ?? __('backoffice.audit.unknown_actor') }}
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
