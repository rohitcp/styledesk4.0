@php
    $shift = $data->myShift();
    $remaining = $data->myDay()->filter(fn ($b) => $b->startsAt() > now()->format('H:i')
        && ! in_array($b->status, ['cancelled', 'declined', 'no-show', 'completed'], true))->count();
@endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.my_schedule.title') }}</h2>

  @if ($shift === null)
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.my_schedule.none') }}</p>
  @else
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
      <div>
        <p class="text-[18px] font-bold text-head leading-tight">{{ \App\Support\TimeFormat::time(substr((string) $shift->starts_at, 0, 5)) }}</p>
        <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_schedule.from') }}</p>
      </div>
      <div>
        <p class="text-[18px] font-bold text-head leading-tight">{{ \App\Support\TimeFormat::time(substr((string) $shift->ends_at, 0, 5)) }}</p>
        <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_schedule.to') }}</p>
      </div>
      @if ($shift->break_minutes)
        <div>
          <p class="text-[18px] font-bold text-head leading-tight">{{ __('dashboard.my_performance.minutes', ['count' => $shift->break_minutes]) }}</p>
          <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_schedule.break') }}</p>
        </div>
      @endif
      <div>
        <p class="text-[18px] font-bold text-head leading-tight">{{ $remaining }}</p>
        <p class="text-[11px] text-sub mt-0.5">{{ __('dashboard.my_schedule.remaining') }}</p>
      </div>
    </div>
  @endif
</section>
