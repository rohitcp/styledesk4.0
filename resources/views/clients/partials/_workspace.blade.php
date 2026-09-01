{{--
    The middle column: what has happened to this client, and what to do next.

    Every panel is in the page and switched by class rather than fetched, so a
    tab costs nothing and the Back button still means "the page before" — a
    tab is a view of one record, not a place of its own.
--}}
<div class="mt-5 pt-4 border-t border-line">
    <div class="flex flex-wrap items-center gap-1" role="tablist" aria-label="{{ $name }}">
        {{-- Bookings first: the question a profile is usually opened to
             answer is "when are they next in", and Activity is the record of
             everything else, which is what you read after the specifics. --}}
        {{-- Leads sits beside bookings rather than inside it: they are two
             different things counted two different ways, and a segment one
             level down is a thing nobody finds. Only where there are leads —
             a tab for nothing teaches the reader the wrong thing about this
             client. --}}
        {{-- Services sits next to bookings because it is the same diary read
             the other way round: bookings answer "when", services answer
             "what, and how often". --}}
        @foreach (array_filter(['bookings', $hasLeads ? 'leads' : null, 'services', 'notes', 'files', 'activity']) as $tab)
            <button type="button" role="tab" data-tab="{{ $tab }}"
                    id="tab-{{ $tab }}" aria-controls="panel-{{ $tab }}"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    tabindex="{{ $loop->first ? '0' : '-1' }}"
                    class="styledesk_tab {{ $loop->first ? 'is-active' : '' }}">
                {{ __('clients.module.workspace.tabs.'.$tab) }}
            </button>
        @endforeach
    </div>
</div>

{{-- ----------------------------------------------------------- activity --}}
{{--
    What has happened to this client, newest first.

    Read from the audit table rather than reconstructed from whatever still
    exists: these rows were written when the things happened, by whoever did
    them, and they outlive what they describe — a note added and later deleted
    leaves both entries behind, which is the question the tab exists to answer.

    Nothing here can be edited or removed. There is no route to do it and the
    model refuses it, because an audit trail somebody can tidy is not one
    anybody can rely on.
--}}
<div id="panel-activity" role="tabpanel" aria-labelledby="tab-activity" data-panel="activity" class="pt-4" hidden>
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[220px]">
            <span class="styledesk_input__prefix pointer-events-none" aria-hidden="true">
                <x-icon name="magnifying-glass" size="14" />
            </span>
            <input id="activitySearch" type="search" class="sd-input styledesk_input--prefixed !h-9"
                   placeholder="{{ __('clients.module.workspace.activity.search') }}"
                   aria-label="{{ __('clients.module.workspace.activity.search') }}">
        </div>

        <div class="flex flex-wrap gap-1.5">
            @foreach (['all', 'bookings', 'notes', 'client', 'tags', 'payments'] as $filter)
                <button type="button" data-activity-filter="{{ $filter }}"
                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                        class="styledesk_action styledesk_action--sm {{ $loop->first ? 'is-active' : '' }}">
                    {{ __('clients.module.workspace.activity.filters.'.$filter) }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($activity->isEmpty())
        <p class="text-[13px] text-sub mt-4">{{ __('clients.module.workspace.activity.none') }}</p>
    @else
        <ol class="mt-4">
            @foreach ($activity as $event)
                @php
                    /* One icon per kind of thing, not per event: a reader
                       scanning the column is looking for "the booking ones",
                       and sixteen different glyphs is a legend to learn. */
                    $icon = [
                        'bookings' => 'calendar-check',
                        'notes' => 'clipboard-list',
                        'client' => 'user',
                        'tags' => 'tag',
                        'payments' => 'credit-card',
                    ][$event->category] ?? 'user';

                    $body = $event->readableDescription($canViewNotes);
                    $changes = $event->changes ?? [];
                    $meta = $event->meta ?? [];
                @endphp

                <li data-event="{{ $event->category }}" class="flex items-start gap-3 py-3 border-b border-line">
                    <span class="styledesk_settingcard__icon shrink-0 !h-8 !w-8" aria-hidden="true">
                        <x-icon :name="$icon" size="14" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <p class="text-[13px] font-semibold text-head">{{ $event->title() }}</p>
                            <time datetime="{{ $event->created_at?->toIso8601String() }}" class="text-[12px] text-sub">
                                {{ $event->created_at?->isoFormat('D MMM Y · h:mm A') }}
                            </time>
                        </div>

                        {{-- Who did it — and StyleDesk itself where nobody
                             did, because an automated confirmation and a
                             receptionist pressing a button are different
                             facts and a blank would look like missing data. --}}
                        <p class="text-[12px] mt-0.5 {{ $event->isSystem() ? 'text-faint italic' : 'text-sub' }}">
                            {{ __('clients.module.workspace.activity.by', ['name' => $event->actor()]) }}
                        </p>

                        @if ($event->category === 'notes' && $event->is_private)
                            <p class="text-[12px] text-faint mt-1">
                                <span class="styledesk_badge styledesk_badge--soon">{{ __('clients.module.workspace.activity.private_note') }}</span>
                            </p>
                        @endif

                        @if (filled($body))
                            <p class="text-[13px] text-ink mt-1 leading-relaxed whitespace-pre-line">{{ \Illuminate\Support\Str::limit(strip_tags($body), 240) }}</p>
                        @elseif ($event->category === 'notes' && filled($event->description))
                            {{-- That a note was written is not the secret;
                                 what it says is. --}}
                            <p class="text-[12px] text-faint mt-1">{{ __('clients.module.workspace.activity.private_hidden') }}</p>
                        @endif

                        @if (! empty($meta['when']) || ! empty($meta['reference']))
                            <p class="text-[12px] text-sub mt-1">
                                {{ collect([$meta['when'] ?? null, $meta['reference'] ?? null])->filter()->join(' · ') }}
                            </p>
                        @endif

                        {{-- How it was paid, and — where it was only part of
                             the bill — what is still owed, which is the half
                             somebody has to act on. --}}
                        @if (! empty($meta['method']))
                            <p class="text-[12px] text-sub mt-1">
                                {{ collect([
                                    $meta['method'] ?? null,
                                    $event->type === 'payment.partial' && ! empty($meta['due'])
                                        ? __('bookings.summary.due').' '.$meta['due']
                                        : null,
                                ])->filter()->join(' · ') }}
                            </p>
                        @endif

                        @if (! empty($meta['reason']))
                            <p class="text-[12px] text-sub mt-1">{{ $meta['reason'] }}</p>
                        @endif

                        {{-- Before and after, folded away.

                             Somebody who changed three fields did one thing,
                             so it is one entry — and the detail is behind a
                             disclosure because the timeline is read for what
                             happened far more often than for what it was. --}}
                        @if (count($changes))
                            <details class="mt-1.5 group">
                                <summary class="text-[12px] font-semibold text-link cursor-pointer list-none">
                                    <span class="group-open:hidden">
                                        {{ count($changes) === 1
                                            ? __('clients.module.workspace.activity.one_field_changed')
                                            : __('clients.module.workspace.activity.fields_changed', ['count' => count($changes)]) }}
                                        · {{ __('clients.module.workspace.activity.view_changes') }}
                                    </span>
                                    <span class="hidden group-open:inline">{{ __('clients.module.workspace.activity.hide_changes') }}</span>
                                </summary>

                                <dl class="mt-2 space-y-2 border-l-2 border-line pl-3">
                                    @foreach ($changes as $change)
                                        <div>
                                            <dt class="text-[12px] font-semibold text-ink">{{ $change['field'] ?? '' }}</dt>
                                            <dd class="text-[12px] text-sub">
                                                <span class="line-through">{{ $change['from'] ?? __('clients.module.workspace.activity.empty_value') }}</span>
                                                <span class="mx-1" aria-hidden="true">→</span>
                                                <span class="text-head font-medium">{{ $change['to'] ?? __('clients.module.workspace.activity.empty_value') }}</span>
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </details>
                        @endif

                        @if ($event->booking)
                            <a href="{{ route('bookings.show', $event->booking) }}"
                               class="inline-block text-[12px] font-semibold text-link mt-1.5">
                                {{ __('clients.module.workspace.activity.view_booking') }}
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        <p id="activityEmpty" class="text-[13px] text-sub mt-4" hidden>
            {{ __('clients.module.workspace.activity.no_matches') }}
        </p>
    @endif

    {{-- Refunds are the one thing on this list that cannot happen yet, and
         a reader filtering to Payments deserves to know that rather than
         conclude nobody has ever been refunded. --}}
    <p class="text-[12px] text-faint mt-4 leading-relaxed">
        <span class="styledesk_badge styledesk_badge--soon">{{ __('clients.module.workspace.activity.refund_soon') }}</span>
        <span class="ml-1.5">{{ __('clients.module.workspace.activity.refund_hint') }}</span>
    </p>
</div>

{{-- ----------------------------------------------------------- bookings --}}
<div id="panel-bookings" role="tabpanel" aria-labelledby="tab-bookings" data-panel="bookings" class="pt-4">
    @unless ($canViewHistory)
        <p class="text-[13px] text-sub">{{ __('clients.module.workspace.bookings.hidden') }}</p>
    @else
        {{-- What they have booked, and what they started and did not finish.
             Two different things counted two different ways by everybody who
             reads them, so they are two segments rather than one list. --}}
        @php
            $bookingLabels = [
                'bookings' => __('clients.module.workspace.tabs.bookings'),
                'leads' => __('leads.title'),
                'all_dates' => __('clients.module.workspace.bookings.all_dates'),
                'all_services' => __('clients.module.workspace.bookings.all_services'),
                'search_services' => __('bookings.service.search_categories'),
                'none' => __('clients.module.workspace.bookings.none_match'),
                'no_leads' => __('leads.none_yet'),
                'stopped_at' => __('leads.drawer.stopped_at'),
                'last_activity' => __('leads.drawer.last_activity'),
                'view_full' => __('clients.module.workspace.bookings.view_full'),
                'cancel' => __('bookings.detail.cancel_booking'),
                'soon' => __('leads.drawer.soon'),
                'transactions' => __('bookings.detail.transactions'),
                'close' => __('leads.drawer.close'),
                'complete' => __('leads.actions.complete'),
                'not_selected' => __('leads.drawer.not_selected'),
                'summary' => __('leads.drawer.summary'),
                'client' => __('leads.drawer.client'),
                'booking' => __('leads.drawer.booking'),
                'payment' => __('leads.drawer.payment'),
                'journey' => __('leads.drawer.journey'),
                'activity' => __('leads.drawer.activity'),
                'no_activity' => __('leads.drawer.no_activity'),
            ];
        @endphp

        <div data-client-bookings
             data-url="{{ route('bookings.for-client', $client) }}"
             data-labels='@json($bookingLabels)'>

            {{-- Only what this client actually has: a filter that can offer a
                 month with nothing in it is a filter that finds nothing. --}}
            {{-- Year and month apart rather than one list of every month
                 this client has ever booked in: two short lists are quicker
                 to read than one long one, and the year is the half people
                 change least. The app's own combo, like every other
                 dropdown. --}}
            @php
                $months = collect(range(1, 12))->mapWithKeys(fn (int $month) => [
                    str_pad((string) $month, 2, '0', STR_PAD_LEFT) => \Carbon\CarbonImmutable::create(null, $month)->translatedFormat('F'),
                ]);
            @endphp

            <div class="flex flex-wrap items-end gap-2 mt-3" data-booking-filters>
                <div class="w-full sm:w-[150px]">
                    <x-combo name="booking_year" :options="$bookingYears"
                             :selected="now()->format('Y')"
                             :placeholder="__('clients.module.workspace.bookings.all_years')" />
                </div>

                <div class="w-full sm:w-[170px]">
                    <x-combo name="booking_month" :options="$months"
                             :selected="now()->format('m')"
                             :placeholder="__('clients.module.workspace.bookings.all_months')" />
                </div>

                <div class="w-full sm:w-[220px]">
                    <x-combo name="booking_service" :options="$bookingServices"
                             :placeholder="$bookingLabels['all_services']" />
                </div>
            </div>

            <div class="mt-4" data-booking-list></div>
        </div>
    @endunless
</div>

{{-- -------------------------------------------------------------- leads --}}
@if ($hasLeads)
    <div id="panel-leads" role="tabpanel" aria-labelledby="tab-leads" data-panel="leads" class="pt-4" hidden>
        {{-- Filled by the same fetch the bookings panel makes: one answer
             holds both, and asking twice for two halves of it would show one
             half a beat before the other. --}}
        <div data-lead-list></div>
    </div>
@endif

{{-- -------------------------------------------------------------- notes --}}
{{-- ----------------------------------------------------------- services --}}
<div id="panel-services" role="tabpanel" aria-labelledby="tab-services" data-panel="services" class="pt-4" hidden>
    @php
        $serviceTabProps = [
            'urls' => [
                'add' => route('clients.favorite-services.store', $client),
                'remove' => route('clients.favorite-services.destroy', ['client' => $client, 'service' => ':service']),
            ],
            'favorites' => $clientServices['favorites'],
            'history' => $clientServices['history'],
            'services' => $bookableServices,
            'csrf' => csrf_token(),
            'canEdit' => $canEdit,
            'labels' => __('clients.module.workspace.services') + ['save' => __('common.save'), 'cancel' => __('common.cancel')],
        ];
    @endphp

    <div data-vue-component="ClientServices" data-props='@json($serviceTabProps)'></div>
</div>

<div id="panel-notes" role="tabpanel" aria-labelledby="tab-notes" data-panel="notes" class="pt-4" hidden>
    @include('clients.partials._notes')
</div>

{{-- -------------------------------------------------------------- files --}}
<div id="panel-files" role="tabpanel" aria-labelledby="tab-files" data-panel="files" class="pt-4" hidden>
    <p class="text-[13px] text-sub leading-relaxed">{{ __('clients.module.workspace.files.coming') }}</p>
</div>
