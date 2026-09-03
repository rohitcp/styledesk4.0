{{--
    The bill, as figures on the page.

    The read-only half of the payment summary, for the two readers who get no
    island: somebody without permission to take money, and somebody without
    JavaScript. Kept as its own partial so the numbers cannot say one thing
    here and another in the island beside it.
--}}
<div class="bg-white border border-brand rounded-card overflow-hidden">
<dl class="p-4 space-y-2 text-[13px]">
    {{-- The same lines the island states, from the same builder, so the two
         cannot drift apart. Zeros included: a line absent because it is
         nothing looks like a line the page failed to render. --}}
    @foreach ($totals->breakdownFor($booking) as $line)
        @php $strong = $line['strong'] ?? false; @endphp
        <div @class([
            'flex items-baseline justify-between gap-4',
            'pt-2.5 mt-0.5 border-t border-line' => $strong,
        ])>
            <dt class="{{ $strong ? 'font-semibold text-head' : 'text-sub' }}">{{ $line['label'] }}</dt>
            <dd @class([
                'font-bold' => $strong,
                'font-medium' => ! $strong,
                'text-emerald-700' => $line['negative'] ?? false,
                'text-danger' => $line['key'] === 'due' && $booking->dueMinor() > 0,
                'text-head' => ! ($line['negative'] ?? false) && ! ($line['key'] === 'due' && $booking->dueMinor() > 0),
            ])>{{ $line['value'] }}</dd>
        </div>
    @endforeach

    @if ($booking->deposit_minor > 0)
        <div class="flex items-baseline justify-between gap-4">
            <dt class="text-sub">{{ __('bookings.summary.deposit') }}</dt>
            <dd class="font-medium text-head">{{ $totals->money((int) $booking->deposit_minor) }}</dd>
        </div>
    @endif

    <div class="flex items-baseline justify-between gap-4 pt-2.5 mt-0.5 border-t border-line">
        <dt class="text-sub">{{ __('bookings.detail.payment_status') }}</dt>
        <dd><span class="styledesk_badge {{ $booking->paymentStatusClass() }}">{{ $booking->paymentStatusLabel() }}</span></dd>
    </div>
</dl>
</div>
