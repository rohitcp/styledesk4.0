{{--
    This client's memberships: what they hold, what they have left, and what
    has happened to it.

    Read as one question a receptionist asks with a client in front of them —
    "what have you got, and can you use it today" — which is why the credits
    sit above the history rather than after it. The history is the record;
    the credits are the answer.

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

    {{-- ------------------------------------------------ what they can spend --}}
    <section class="sd-card p-5">
        <h3 class="text-[15px] font-semibold text-head">{{ __('membership.member.credits') }}</h3>

        @if ($membershipCredits->isEmpty())
            <p class="text-[13px] text-sub mt-2">{{ __('membership.member.credits_none') }}</p>
        @else
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($membershipCredits as $credit)
                    <li class="rounded-lg border border-brand/30 bg-brand/5 px-3.5 py-3">
                        <p class="text-[13.5px] font-semibold text-head">{{ $credit['service'] }}</p>
                        <p class="text-[13px] text-brand font-semibold mt-0.5">
                            {{ __('membership.member.credit_count', ['count' => $credit['remaining']]) }}
                        </p>
                        {{-- Only where there is a deadline. "Expires never" is
                             a line nobody needs to read. --}}
                        @if ($credit['expires_on'])
                            <p class="text-[11.5px] text-sub mt-0.5">
                                {{ __('membership.member.credit_expires', [
                                    'date' => \Illuminate\Support\Carbon::parse($credit['expires_on'])->translatedFormat('j M Y'),
                                ]) }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

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

                            <p class="text-[13px] text-sub mt-1">{{ $membership->priceLabel() }}</p>
                        </div>
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
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($membership->isPaused())
                                <form method="POST" action="{{ route('client-memberships.resume', $membership) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="styledesk_action">{{ __('membership.member.resume') }}</button>
                                </form>
                            @else
                                {{-- Only where the business allows it. A button
                                     the server refuses is a button that wasted
                                     the conversation at the desk. --}}
                                @if ($membershipSettings->allow_pause)
                                    <form method="POST" action="{{ route('client-memberships.pause', $membership) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="styledesk_action"
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
                                        <button type="submit" class="styledesk_action styledesk_action--danger"
                                                data-confirm-title="{{ __('membership.member.cancel') }}"
                                                data-confirm="{{ __('membership.member.cancel_confirm') }}"
                                                data-confirm-label="{{ __('membership.member.cancel') }}">
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
