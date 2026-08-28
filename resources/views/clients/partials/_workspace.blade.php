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
        @foreach (['bookings', 'notes', 'files', 'activity'] as $tab)
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
            @foreach (['all', 'bookings', 'notes', 'messages', 'payments', 'changes'] as $filter)
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
                <li data-event="{{ $event['type'] }}" class="flex items-start gap-3 py-3 border-b border-line">
                    <span class="styledesk_settingcard__icon shrink-0 !h-8 !w-8" aria-hidden="true">
                        <x-icon :name="$event['icon']" size="14" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <p class="text-[13px] font-semibold text-head">{{ $event['title'] }}</p>
                            <time datetime="{{ $event['at']->toIso8601String() }}" class="text-[12px] text-sub">
                                {{ $event['at']->isoFormat('D MMM Y · h:mm A') }}
                            </time>
                        </div>

                        @if (! empty($event['meta']))
                            <p class="text-[12px] text-sub mt-0.5">{{ $event['meta'] }}</p>
                        @endif

                        @if (! empty($event['body']))
                            <p class="text-[13px] text-ink mt-1 leading-relaxed whitespace-pre-line">{{ $event['body'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        <p id="activityEmpty" class="text-[13px] text-sub mt-4" hidden>
            {{ __('clients.module.workspace.activity.no_matches') }}
        </p>
    @endif

    {{-- Said once, plainly: the filters name things that cannot appear yet,
         and a reader filtering to Payments deserves to know why the list is
         empty rather than assume the client never paid. --}}
    <p class="text-[12px] text-faint mt-4 leading-relaxed">
        {{ __('clients.module.workspace.activity.pending_modules') }}
    </p>
</div>

{{-- ----------------------------------------------------------- bookings --}}
<div id="panel-bookings" role="tabpanel" aria-labelledby="tab-bookings" data-panel="bookings" class="pt-4">
    @unless ($canViewHistory)
        <p class="text-[13px] text-sub">{{ __('clients.module.workspace.bookings.hidden') }}</p>
    @else
        <h3 class="styledesk_heading">{{ __('clients.module.workspace.bookings.upcoming') }}</h3>
        <p class="text-[13px] text-sub mt-1.5">
            {{ $client->next_booking_at
                ? $client->next_booking_at->isoFormat('D MMM Y · h:mm A')
                : __('clients.module.workspace.bookings.none_upcoming') }}
        </p>

        <h3 class="styledesk_heading mt-5 pt-4 border-t border-line">{{ __('clients.module.workspace.bookings.previous') }}</h3>
        <p class="text-[13px] text-sub mt-1.5">
            {{ $client->last_visit_at
                ? $client->last_visit_at->isoFormat('D MMM Y')
                : __('clients.module.workspace.bookings.none_previous') }}
        </p>

        <p class="text-[12px] text-faint mt-4 leading-relaxed">{{ __('clients.module.workspace.bookings.coming') }}</p>
    @endunless
</div>

{{-- -------------------------------------------------------------- notes --}}
<div id="panel-notes" role="tabpanel" aria-labelledby="tab-notes" data-panel="notes" class="pt-4" hidden>
    @include('clients.partials._notes')
</div>

{{-- -------------------------------------------------------------- files --}}
<div id="panel-files" role="tabpanel" aria-labelledby="tab-files" data-panel="files" class="pt-4" hidden>
    <p class="text-[13px] text-sub leading-relaxed">{{ __('clients.module.workspace.files.coming') }}</p>
</div>
