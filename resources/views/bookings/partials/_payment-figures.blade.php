{{--
    The bill, as figures on the page.

    The read-only half of the payment summary, for the two readers who get no
    island: somebody without permission to take money, and somebody without
    JavaScript. Kept as its own partial so the numbers cannot say one thing
    here and another in the island beside it.
--}}
<dl class="bg-white border border-line rounded-card p-4 space-y-2.5 text-[13px]">
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

    {{-- The line anybody opening this page is usually looking for, so it is
         stated even when it is nothing. --}}
    <div class="flex items-baseline justify-between gap-4">
        <dt class="font-semibold text-head">{{ __('bookings.summary.due') }}</dt>
        <dd class="font-bold {{ $booking->dueMinor() > 0 ? 'text-danger' : 'text-head' }}">
            {{ $totals->money($booking->dueMinor()) }}
        </dd>
    </div>

    <div class="flex items-baseline justify-between gap-4 pt-2.5 border-t border-line">
        <dt class="text-sub">{{ __('bookings.detail.payment_status') }}</dt>
        <dd><span class="styledesk_badge {{ $booking->paymentStatusClass() }}">{{ $booking->paymentStatusLabel() }}</span></dd>
    </div>
</dl>
