{{--
    Save Draft and Publish, for one planning period.

    Included twice — once above the daily table and once below it — because a
    four-week rota is reviewed by scrolling through it, and making the reader
    climb back to the top to act on what they just read is the whole reason
    they stop reading it.

    @param  \App\Support\SchedulePeriod  $period
    @param  bool  $showStatus  The banner is stated once, above the table.
--}}
@php
    $state = $period->state();
    $publishedAt = $period->publishedAt();
@endphp

@if ($period->hasShifts())
  <div class="flex flex-wrap items-center gap-3 {{ $showStatus ? 'mt-4 pt-4 border-t border-line' : 'mt-4' }}"
       data-schedule-state="{{ $state }}">

    @if ($showStatus)
      <div class="min-w-0 flex-1">
        <span @class([
            'styledesk_badge',
            'styledesk_badge--setup' => $state !== \App\Support\SchedulePeriod::PUBLISHED,
            'styledesk_badge--active' => $state === \App\Support\SchedulePeriod::PUBLISHED,
        ])>
          @switch ($state)
            @case (\App\Support\SchedulePeriod::PUBLISHED)
              {{ __('schedule.published_badge') }}
              @break
            @case (\App\Support\SchedulePeriod::CHANGES)
              {{ __('schedule.changes_badge') }}
              @break
            @default
              {{ __('schedule.draft_badge') }}
          @endswitch
        </span>

        {{-- What the badge means for the person whose week it is. A status
             nobody can act on is decoration; this says who has been told. --}}
        <p class="text-[12px] text-sub mt-1.5 leading-relaxed">
          @switch ($state)
            @case (\App\Support\SchedulePeriod::PUBLISHED)
              {{ __('schedule.published_on', ['date' => $publishedAt?->translatedFormat('M j, Y')]) }}
              @if ($period->publishedBy())
                {{ __('schedule.published_by', ['name' => $period->publishedBy()]) }}
              @endif
              · {{ __('schedule.published_hint', ['name' => $staff->displayName()]) }}
              @break
            @case (\App\Support\SchedulePeriod::CHANGES)
              {{ __('schedule.changes_hint', [
                  'date' => $publishedAt?->translatedFormat('M j, Y'),
                  'name' => $staff->displayName(),
              ]) }}
              @break
            @default
              {{ __('schedule.draft_hint', ['name' => $staff->displayName()]) }}
          @endswitch
        </p>
      </div>
    @endif

    @can('update', $staff)
      <div class="shrink-0 flex flex-wrap items-center gap-2 {{ $showStatus ? '' : 'ml-auto' }}">
        {{-- Nothing to keep as a draft once the whole period is published and
             untouched: the button would either lie or un-tell somebody about a
             week they have already been emailed. --}}
        @if ($state !== \App\Support\SchedulePeriod::PUBLISHED)
          <form method="POST" action="{{ \App\Support\StaffSection::route('schedule.draft', $staff) }}">
            @csrf
            <input type="hidden" name="from" value="{{ $from->toDateString() }}">
            <input type="hidden" name="until" value="{{ $until->toDateString() }}">

            {{-- Asked before saving, so the two actions read as a pair: one
                 keeps the week to yourself, the other sends it. Without the
                 question, the secondary button is the one somebody presses by
                 reflex on a page whose other button emails a colleague. --}}
            <button type="submit" class="styledesk_action" data-submit-once
                    data-busy-label="{{ __('common.saving') }}"
                    data-confirm="{{ __('schedule.confirm_draft.intro') }}"
                    data-confirm-title="{{ __('schedule.confirm_draft.title') }}"
                    data-confirm-label="{{ __('schedule.save_draft') }}"
                    data-confirm-tone="brand">
              {{ __('schedule.save_draft') }}
            </button>
          </form>

          <div data-vue-component="PublishSchedule" data-props='@json($publishProps)'></div>
        @endif
      </div>
    @endcan
  </div>
@endif
