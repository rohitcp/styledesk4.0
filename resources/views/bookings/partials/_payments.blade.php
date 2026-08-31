{{--
    The money: what it comes to, and what has actually changed hands.

    Two sections rather than one, because they answer different questions.
    The summary is what is owed; the transactions are what happened, and a
    bill settled half in cash and half on a card is one of the first and two
    of the second.

    Read-only. Money is taken on the booking screen, and a page that could
    also take it would be a second place for the same mistake.
--}}
<section>
    <h2 class="styledesk_heading">{{ __('bookings.detail.payment_summary') }}</h2>

    <dl class="mt-3 bg-white border border-line rounded-card p-4 space-y-2.5 text-[13px]">
        @foreach ($totals->lines() as $line)
            <div class="flex items-baseline justify-between gap-4">
                <dt class="{{ ($line['strong'] ?? false) ? 'font-semibold text-head' : 'text-sub' }}">{{ $line['label'] }}</dt>
                <dd class="{{ ($line['strong'] ?? false) ? 'font-bold text-head' : 'font-medium text-head' }}">{{ $line['value'] }}</dd>
            </div>
        @endforeach

        @if ($booking->deposit_minor > 0)
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-sub">{{ __('bookings.summary.deposit') }}</dt>
                <dd class="font-medium text-head">{{ $totals->money((int) $booking->deposit_minor) }}</dd>
            </div>
        @endif

        <div class="flex items-baseline justify-between gap-4 pt-2.5 border-t border-line">
            <dt class="text-sub">{{ __('bookings.summary.paid') }}</dt>
            <dd class="font-medium text-head">{{ $totals->money($booking->paidMinor()) }}</dd>
        </div>

        {{-- The line anybody opening this page is usually looking for, so it
             is stated even when it is nothing. --}}
        <div class="flex items-baseline justify-between gap-4">
            <dt class="font-semibold text-head">{{ __('bookings.summary.due') }}</dt>
            <dd class="font-bold {{ $booking->dueMinor() > 0 ? 'text-danger' : 'text-head' }}">
                {{ $totals->money($booking->dueMinor()) }}
            </dd>
        </div>
    </dl>
</section>

<section>
    <h2 class="styledesk_heading">{{ __('bookings.detail.transactions') }}</h2>

    @if ($booking->payments->isEmpty())
        <p class="mt-2 text-[13px] text-sub">{{ __('bookings.detail.no_transactions') }}</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach ($booking->payments as $payment)
                <li class="bg-white border border-line rounded-card p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[13.5px] font-semibold text-head">{{ $payment->methodLabel() }}</p>
                            <p class="text-[12px] text-sub mt-0.5">
                                {{ $payment->paid_at?->translatedFormat('j M Y · H:i') }}
                                @if ($payment->recordedBy)
                                    · {{ __('bookings.detail.recorded_by', ['name' => $payment->recordedBy->name]) }}
                                @endif
                            </p>

                            @if ($payment->reference)
                                <p class="text-[12px] text-faint mt-1 font-mono">{{ $payment->reference }}</p>
                            @endif

                            @if ($payment->change_minor)
                                <p class="text-[12px] text-sub mt-1">
                                    {{ __('bookings.pay.change') }}: {{ $totals->money((int) $payment->change_minor) }}
                                </p>
                            @endif
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-[13.5px] font-semibold text-head">{{ $payment->amountLabel() }}</p>
                            <span class="styledesk_paystate is-paid mt-1">{{ __('bookings.payment_statuses.'.$payment->status.'.label') }}</span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
