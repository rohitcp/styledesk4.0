{{-- What needs somebody's attention.

     Built from the same figures the panels above show rather than from a
     table of its own: an alert that can disagree with the list it is about
     is an alert nobody trusts twice.

     Role-aware by construction — it is assembled from the panels this
     reader has, so a provider is never told about the branch's unpaid
     bills. --}}
@php
    $late = $shows('checkin') ? $data->lateArrivals()->count() : 0;
    $waitingLong = $shows('waiting') ? $data->waiting()->where('too_long', true)->count() : 0;
    $unpaid = $shows('payments') ? $data->payments()['due_today'] : 0;
    $unstaffed = $shows('schedule_issues')
        ? collect($data->scheduleIssues())->firstWhere('key', 'bookings_without_staff')['count'] ?? 0
        : 0;

    $alerts = array_values(array_filter([
        $late > 0 ? ['count' => $late, 'text' => trans_choice('dashboard.alerts.late', $late, ['count' => $late]),
                     'url' => route('bookings.index', ['tab' => 'check-in'])] : null,
        $waitingLong > 0 ? ['count' => $waitingLong, 'text' => trans_choice('dashboard.alerts.waiting_too_long', $waitingLong, ['count' => $waitingLong]),
                            'url' => null] : null,
        $unpaid > 0 ? ['count' => $unpaid, 'text' => trans_choice('dashboard.alerts.unpaid', $unpaid, ['count' => $unpaid]),
                       'url' => route('bookings.index', ['tab' => 'today', 'payment' => 'unpaid'])] : null,
        $unstaffed > 0 ? ['count' => $unstaffed, 'text' => trans_choice('dashboard.alerts.unstaffed', $unstaffed, ['count' => $unstaffed]),
                          'url' => route('bookings.index', ['tab' => 'today'])] : null,
    ]));
@endphp

@php $bare = $bare ?? false; @endphp

<section @class(['sd-card p-5' => ! $bare, 'px-4 pb-3' => $bare])>
  @unless ($bare)
    <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.alerts.title') }}</h2>
  @endunless

  @if ($alerts === [])
    <p class="text-[13px] text-sub {{ $bare ? '' : 'mt-3' }}">{{ __('dashboard.alerts.none') }}</p>
  @else
    <ul class="mt-3 space-y-2">
      @foreach ($alerts as $alert)
        <li class="flex items-start gap-2.5">
          <span class="styledesk_badge styledesk_badge--attention shrink-0">{{ $alert['count'] }}</span>
          @if ($alert['url'])
            <a href="{{ $alert['url'] }}" class="text-[13px] text-link hover:underline">{{ $alert['text'] }}</a>
          @else
            <span class="text-[13px] text-ink">{{ $alert['text'] }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</section>
