{{--
    This client's memberships: what they hold, what they have left, and what
    has happened to it.

    Read as one question a receptionist asks with a client in front of them —
    "what have you got" — so what they hold now comes first and the history
    follows it. What is left of each membership is in its own Plan details
    panel, where it sits beside the membership it belongs to rather than in a
    card of its own that could not say which.

    Nothing here is editable. Ending or holding a membership is its own
    authority and its own act, so those are buttons that post, not fields.
--}}
@php
    $live = $memberships->filter(fn ($membership) => $membership->isLive() || $membership->isPaused());
    $past = $memberships->reject(fn ($membership) => $membership->isLive() || $membership->isPaused());

    $canManage = $canManageMemberships ?? false;
@endphp

<div id="panel-membership" role="tabpanel" aria-labelledby="tab-membership" data-panel="membership" class="pt-4" hidden>

    @if ($memberships->isEmpty())
        <div class="border-t border-line py-16 text-center">
            <p class="text-[15px] font-semibold text-head">{{ __('membership.member.none') }}</p>
            <p class="text-[13px] text-sub mt-1.5 max-w-[420px] mx-auto">{{ __('membership.member.none_hint') }}</p>
        </div>
    @else

    {{-- ------------------------------------------------- what they hold now --}}
    @if ($live->isNotEmpty())
        <h3 class="text-[13px] font-semibold text-head mt-5">{{ __('membership.member.active') }}</h3>

        <div class="mt-2 space-y-3">
            @foreach ($live as $membership)
                <section class="sd-card p-5">
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <a href="{{ route('membership.show', $membership->plan) }}"
                                   class="text-[15px] font-semibold text-head hover:text-brand transition-colors">
                                    {{ $membership->plan->name }}
                                </a>
                                <span class="styledesk_badge {{ $membership->statusClass() }}">{{ $membership->statusLabel() }}</span>
                            </div>

                            <p class="text-[13px] text-sub mt-1">
                                {{ $membership->priceLabel() }}
                                {{-- The number this membership is known by,
                                     beside what it costs: it is what the desk
                                     reads out when the client rings. --}}
                                @if ($membership->reference)
                                    <span class="text-faint">·</span>
                                    <span class="font-mono text-[12px]">{{ $membership->reference }}</span>
                                @endif
                            </p>
                        </div>

                        {{-- One menu rather than a row of buttons: Plan
                             details is the thing a receptionist reaches for
                             with a client in front of them, and the acts that
                             change the membership sit behind the same control
                             where they cannot be pressed by accident. --}}
                        <span class="styledesk_rowmenu shrink-0" data-rowmenu>
                            <button type="button" class="styledesk_action styledesk_action--icon" data-rowmenu-button
                                    aria-haspopup="true" aria-expanded="false"
                                    aria-label="{{ __('membership.member.drawer.actions') }}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>
                                </svg>
                            </button>

                            <span class="styledesk_rowmenu__pop" data-rowmenu-pop hidden role="menu">
                                {{-- Opens the drawer the bookings open in, so
                                     the profile stays where it is. --}}
                                <button type="button" class="styledesk_rowmenu__item w-full" role="menuitem"
                                        data-drawer="{{ route('client-memberships.drawer', $membership) }}">
                                    <x-icon name="id-card" size="14" />
                                    {{ __('membership.member.drawer.plan_details') }}
                                </button>

                                @if ($canManage)
                                    @if ($membership->isPaused())
                                        <button type="button" class="styledesk_rowmenu__item w-full" role="menuitem"
                                                data-membership-act="{{ $membership->id }}-resume">
                                            {{ __('membership.member.resume') }}
                                        </button>
                                    @elseif ($membershipSettings->allow_pause)
                                        <button type="button" class="styledesk_rowmenu__item w-full" role="menuitem"
                                                data-membership-act="{{ $membership->id }}-pause">
                                            {{ __('membership.member.pause') }}
                                        </button>
                                    @endif

                                    @if ($membershipSettings->allow_cancellation && ! $membership->isCancelled() && ! $membership->isPaused())
                                        <span class="styledesk_rowmenu__rule" role="separator"></span>

                                        <button type="button" class="styledesk_rowmenu__item styledesk_rowmenu__item--danger w-full"
                                                role="menuitem" data-membership-act="{{ $membership->id }}-cancel">
                                            {{ __('membership.member.cancel') }}
                                        </button>
                                    @endif
                                @endif
                            </span>
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-x-8 gap-y-2 sm:grid-cols-2 text-[13px]">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-sub">{{ __('membership.member.started') }}</dt>
                            <dd class="font-semibold text-head">{{ $membership->starts_on->translatedFormat('j M Y') }}</dd>
                        </div>

                        {{-- Either the next payment or the day it stops, never
                             both: a membership that is ending has no next
                             billing date, and one that is running has no end. --}}
                        @if ($membership->ends_on)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ __('membership.member.ends') }}</dt>
                                <dd class="font-semibold text-head">{{ $membership->ends_on->translatedFormat('j M Y') }}</dd>
                            </div>
                        @else
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-sub">{{ __('membership.member.next_billing') }}</dt>
                                <dd class="font-semibold text-head">
                                    {{ $membership->next_billing_on?->translatedFormat('j M Y')
                                        ?? __('membership.member.no_billing') }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @if ($membership->isPaused())
                        <p class="mt-3 text-[12.5px] text-sub bg-hover rounded-lg px-3 py-2">
                            {{ __('membership.member.credits_paused') }}
                        </p>
                    @endif

                    @if ($canManage)
                        {{-- The acts themselves, driven from the menu above.
                             Kept as posting forms rather than moved into
                             JavaScript: each still carries its own
                             confirmation, and each still works if the menu
                             never wires itself up. Hidden, because the menu
                             is where a reader chooses one. --}}
                        <div class="hidden" data-membership-forms>
                            @if ($membership->isPaused())
                                <form method="POST" action="{{ route('client-memberships.resume', $membership) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" data-membership-form="{{ $membership->id }}-resume">
                                        {{ __('membership.member.resume') }}
                                    </button>
                                </form>
                            @else
                                {{-- Only where the business allows it. An
                                     action the server refuses is one that
                                     wasted the conversation at the desk. --}}
                                @if ($membershipSettings->allow_pause)
                                    <form method="POST" action="{{ route('client-memberships.pause', $membership) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" data-membership-form="{{ $membership->id }}-pause"
                                                data-confirm-title="{{ __('membership.member.pause') }}"
                                                data-confirm="{{ __('membership.member.pause_confirm') }}"
                                                data-confirm-label="{{ __('membership.member.pause') }}">
                                            {{ __('membership.member.pause') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($membershipSettings->allow_cancellation && ! $membership->isCancelled())
                                    <form method="POST" action="{{ route('client-memberships.cancel', $membership) }}">
                                        @csrf
                                        @method('PATCH')
                                        {{-- What cancelling actually does, in
                                             the question itself: whether it
                                             stops the billing now or at the
                                             end of the cycle is the whole of
                                             what the client is asking. --}}
                                        <button type="submit" data-membership-form="{{ $membership->id }}-cancel"
                                                data-confirm-title="{{ __('membership.member.cancel') }}"
                                                data-confirm="{{ __('membership.member.cancel_confirm') }} {{ __('membership.member.notice_note', [
                                                    'date' => $membership->cancellationTakesEffect($membershipSettings)->translatedFormat('j M Y'),
                                                ]) }}"
                                                data-confirm-label="{{ __('membership.member.cancel') }}"
                                                data-confirm-tone="danger">
                                            {{ __('membership.member.cancel') }}
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>

                        {{-- What cancelling today would actually do. Said
                             before the button is pressed, because "it ends on
                             the 30th" is the whole of what the client is
                             asking and finding out afterwards is too late. --}}
                        @if ($membershipSettings->allow_cancellation && ! $membership->isCancelled() && ! $membership->isPaused())
                            <p class="mt-2 text-[11.5px] text-faint">
                                {{ __('membership.member.notice_note', [
                                    'date' => $membership->cancellationTakesEffect($membershipSettings)->translatedFormat('j M Y'),
                                ]) }}
                            </p>
                        @endif
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    {{-- --------------------------------------------------- and what is over --}}
    @if ($past->isNotEmpty())
        <h3 class="text-[13px] font-semibold text-head mt-5">{{ __('membership.member.past') }}</h3>

        <ul class="mt-2 divide-y divide-line border-t border-line">
            @foreach ($past as $membership)
                <li class="py-3 flex flex-wrap items-baseline justify-between gap-3">
                    <span class="min-w-0">
                        <span class="text-[13.5px] font-semibold text-head">{{ $membership->plan->name }}</span>
                        <span class="block text-[12px] text-sub">
                            {{ $membership->starts_on->translatedFormat('j M Y') }}
                            @if ($membership->ends_on)
                                — {{ $membership->ends_on->translatedFormat('j M Y') }}
                            @endif
                        </span>
                    </span>

                    <span class="styledesk_badge {{ $membership->statusClass() }} shrink-0">{{ $membership->statusLabel() }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- ---------------------------------------------------------- history --}}
    <h3 class="text-[13px] font-semibold text-head mt-5">{{ __('membership.member.history_title') }}</h3>

    @if ($membershipHistory->isEmpty())
        <p class="text-[13px] text-sub mt-2">{{ __('membership.member.history_none') }}</p>
    @else
        <ul class="mt-2 divide-y divide-line border-t border-line">
            @foreach ($membershipHistory as $entry)
                <li class="py-2.5 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="text-[12px] text-sub w-[110px] shrink-0">
                        {{ $entry['at']->translatedFormat('j M Y') }}
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="text-[13px] font-medium text-ink">{{ $entry['title'] }}</span>
                        @if ($entry['detail'])
                            <span class="text-[12.5px] text-sub"> · {{ $entry['detail'] }}</span>
                        @endif
                    </span>

                    @if ($entry['amount'])
                        <span class="text-[13px] font-semibold text-head shrink-0">{{ $entry['amount'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @endif
</div>
