@php $issues = $data->scheduleIssues(); @endphp

<section class="sd-card p-5">
  <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.schedule_issues.title') }}</h2>

  @if ($issues === [])
    <p class="text-[13px] text-sub mt-3">{{ __('dashboard.schedule_issues.none') }}</p>
  @else
    <ul class="mt-3 space-y-2">
      @foreach ($issues as $issue)
        <li class="flex items-start gap-2.5">
          <span class="styledesk_badge styledesk_badge--attention shrink-0">{{ $issue['count'] }}</span>
          <span class="min-w-0">
            <span class="block text-[13px] text-ink">{{ __('dashboard.schedule_issues.'.$issue['key']) }}</span>
            @if (! empty($issue['names']))
              <span class="block text-[12px] text-faint">{{ $issue['names'] }}</span>
            @endif
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</section>
