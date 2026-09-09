{{-- Who is working, and how their day is going.

     There is no "checked in" column: StyleDesk records a client arriving,
     not a member of staff clocking on, and a column that guessed would be a
     column somebody rotas by. --}}
@php $team = $data->staffToday(); @endphp

@php $bare = $bare ?? false; @endphp

<section @class(['sd-card p-5' => ! $bare, 'px-4 pb-3' => $bare])>
  @unless ($bare)
    <h2 class="text-[15px] font-semibold text-head">{{ __('dashboard.staff_today.title') }}</h2>
  @endunless

  @if ($team->isEmpty())
    <p class="text-[13px] text-sub {{ $bare ? '' : 'mt-3' }}">{{ __('dashboard.staff_today.none') }}</p>
  @else
    <ul class="mt-3 divide-y divide-line">
      @foreach ($team as $row)
        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5">
          <span class="min-w-0 flex-1">
            <span class="block text-[13px] font-semibold text-head truncate">{{ $row['staff']->displayName() }}</span>
            <span class="block text-[12px] text-sub">
              {{ \App\Support\TimeFormat::time($row['starts_at']) }} – {{ \App\Support\TimeFormat::time($row['ends_at']) }}
              <span class="text-faint">· {{ __('dashboard.staff_today.remaining', ['count' => $row['remaining']]) }}</span>
            </span>
          </span>

          <span class="text-[12px] text-sub shrink-0">
            @if ($row['current'])
              <span class="styledesk_badge styledesk_badge--active">{{ __('dashboard.staff_today.now') }}</span>
            @elseif ($row['next'])
              {{ __('dashboard.staff_today.next') }} {{ \App\Support\TimeFormat::time($row['next']->startsAt()) }}
            @else
              <span class="text-faint">{{ __('dashboard.staff_today.free') }}</span>
            @endif
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</section>
