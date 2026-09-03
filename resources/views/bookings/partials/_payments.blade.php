{{--
    The money: what it comes to, and what has actually changed hands.

    Two sections rather than one, because they answer different questions.
    The summary is what is owed; the transactions are what happened, and a
    bill settled half in cash and half on a card is one of the first and two
    of the second.

    The summary is a Vue island rather than Blade, because it has to change
    the moment a payment lands: a booking is very often paid for somewhere
    other than the screen it was taken on — a deposit on the phone in March,
    the balance at the desk in April — and sending the reader back through a
    page load to see it is a reload in the middle of a conversation.
--}}
<section>
    <h2 class="styledesk_heading">{{ __('bookings.detail.payment_summary') }}</h2>

    <div class="mt-3">
        @if ($canTakePayment)
            @php
                $payProps = [
                    'booking' => $panel,
                    'methods' => $payMethods,
                    'csrf' => csrf_token(),
                    'currencySymbol' => \App\Support\Money::symbol($booking->currency_code),
                    /* Only the copy this island uses. The whole file would
                       put the validation strings and the toasts in the DOM. */
                    'labels' => \Illuminate\Support\Arr::only(__('bookings'), [
                        'summary', 'detail', 'pay', 'methods', 'payment_statuses', 'cancel', 'payment',
                    ]),
                ];
            @endphp

            <div data-vue-component="TakePayment" data-props='@json($payProps)'></div>

            {{-- The island is what takes the money; without it the figures
                 still have to be readable, so they are stated in plain HTML
                 rather than leaving a blank card. --}}
            <noscript>
                @include('bookings.partials._payment-figures')
            </noscript>
        @else
            {{-- A reader who may not take a booking may not take money
                 against one either. They still get the figures. --}}
            @include('bookings.partials._payment-figures')
        @endif
    </div>
</section>

{{-- The island renders the transactions where it is mounted, so this is
     the fallback for the two readers who get no island: somebody without
     permission to take money, and somebody without JavaScript. --}}
@if (! $canTakePayment)
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
                                {{ \App\Support\TimeFormat::dateTime($payment->paid_at) }}
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

                            {{-- The tip beside the bill rather than inside
                                 it: it is owed to whoever did the work, and
                                 a total that has absorbed it can never be
                                 taken apart again. --}}
                            @if ($payment->tip_minor)
                                <p class="text-[11.5px] text-sub">
                                    {{ __('tips.panel.selected') }} {{ $totals->money((int) $payment->tip_minor) }}
                                </p>
                            @endif
                            <span class="styledesk_paystate is-paid mt-1">{{ __('bookings.payment_statuses.'.$payment->status.'.label') }}</span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif
