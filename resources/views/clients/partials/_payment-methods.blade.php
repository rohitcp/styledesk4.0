{{--
    This client's cards.

    What is shown here is everything StyleDesk holds: a brand, four digits and
    an expiry. The card itself lives at the gateway, whose business that is —
    so there is no "view card number", because there is nothing to view.

    A card that renews a membership cannot simply be removed. The alternative
    is a subscription whose next payment silently fails and a client who finds
    out when their credits stop.
--}}
@php
    $usable = $paymentMethods->reject(fn ($card) => $card->isRemoved());
    $removed = $paymentMethods->filter(fn ($card) => $card->isRemoved());
@endphp

<div id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" data-panel="payments" class="pt-4" hidden>

    @if ($usable->isEmpty())
        <div class="border-t border-line py-16 text-center">
            <p class="text-[15px] font-semibold text-head">{{ __('payments.methods_list.none') }}</p>
            <p class="text-[13px] text-sub mt-1.5 max-w-[440px] mx-auto">{{ __('payments.methods_list.none_hint') }}</p>
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($usable as $card)
                @php
                    /* Which memberships would stop renewing if this went. Read
                       here rather than in the controller so the warning names
                       them rather than saying "some". */
                    $renewing = $card->memberships
                        ->filter(fn ($membership) => $membership->auto_renew && $membership->isLive());
                @endphp

                <li class="sd-card p-5">
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="text-[15px] font-semibold text-head">{{ $card->label() }}</span>

                                @if ($card->is_default)
                                    <span class="styledesk_badge styledesk_badge--active">{{ __('payments.methods_list.default') }}</span>
                                @endif

                                @if ($card->isExpired())
                                    <span class="styledesk_badge styledesk_badge--danger">{{ __('payments.methods_list.expired') }}</span>
                                @elseif ($card->isExpiringSoon())
                                    <span class="styledesk_badge styledesk_badge--setup">{{ __('payments.methods_list.expiring') }}</span>
                                @endif
                            </div>

                            <p class="text-[13px] text-sub mt-1">
                                {{ __('payments.methods_list.expires', ['date' => $card->expiryLabel() ?? '—']) }}
                                · {{ __('payments.methods_list.gateway', ['name' => ucfirst($card->gateway)]) }}
                            </p>

                            @if ($renewing->isNotEmpty())
                                <p class="text-[12.5px] text-sub mt-1.5">
                                    {{ __('payments.methods_list.used_by', [
                                        'name' => $renewing->map(fn ($m) => $m->plan?->name)->filter()->join(', '),
                                    ]) }}
                                </p>
                            @endif
                        </div>

                        @if ($canManageCards)
                            <div class="flex flex-wrap gap-2 shrink-0">
                                {{-- Only where it would change something, and
                                     only on a card that could actually be
                                     charged: making an expired card the
                                     default is queuing up a failed renewal. --}}
                                @if (! $card->is_default && $card->isChargeable())
                                    <form method="POST" action="{{ route('client-cards.default', ['client' => $client, 'method' => $card]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="styledesk_action styledesk_action--sm">
                                            {{ __('payments.methods_list.make_default') }}
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('client-cards.destroy', ['client' => $client, 'method' => $card]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="styledesk_action styledesk_action--sm styledesk_action--danger"
                                            data-confirm-title="{{ __('payments.methods_list.remove') }}"
                                            data-confirm="{{ $renewing->isNotEmpty()
                                                ? __('payments.methods_list.in_use', ['name' => $renewing->map(fn ($m) => $m->plan?->name)->filter()->join(', ')])
                                                : __('payments.methods_list.remove_confirm') }}"
                                            data-confirm-label="{{ __('payments.methods_list.remove') }}">
                                        {{ __('payments.methods_list.remove') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Cards that were taken out of use. Kept rather than erased: a
         membership renewed on one of these last month still points at it, and
         a row that vanished would leave that payment unexplained. --}}
    @if ($removed->isNotEmpty())
        <h3 class="text-[13px] font-semibold text-head mt-5">{{ __('payments.methods_list.statuses.removed') }}</h3>

        <ul class="mt-2 divide-y divide-line border-t border-line">
            @foreach ($removed as $card)
                <li class="py-2.5 flex items-baseline justify-between gap-3">
                    <span class="text-[13px] text-sub">{{ $card->label() }}</span>
                    <span class="text-[12px] text-faint">
                        {{ $card->removed_at?->translatedFormat('j M Y') }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
