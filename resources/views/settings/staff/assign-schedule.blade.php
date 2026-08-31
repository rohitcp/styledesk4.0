@extends('layouts.focused')

@section('title', $staff->displayName().' — '.__('schedule.assign_title'))

@section('content')
  @php
      $dayCount = (int) $from->diffInDays($until) + 1;
      $formProps = [
          'action' => \App\Support\StaffSection::route('schedule.assign', $staff),
          'csrf' => csrf_token(),
          'backUrl' => $returnTo,
          'staffName' => $staff->displayName(),
          'from' => $from->toDateString(),
          'until' => $until->toDateString(),
          'periodLabel' => $period->label(),
          /* The month itself, where the period is exactly one: a screen
             headed "Assign Schedule" tells the reader what they are doing
             and not what they are doing it to, and August is the answer
             they came in with. */
          'monthLabel' => $from->isSameDay($from->startOfMonth()) && $until->isSameDay($from->endOfMonth())
              ? $from->translatedFormat('F Y')
              : null,
          /* One named day when the listing sent the manager here to fix a
             single date, the number of weeks they chose when the period is a
             whole number of them, and a day count when it is not — a period
             cut back to the end of its month is neither. */
          'durationLabel' => match (true) {
              $singleDay => $from->translatedFormat('l, j M Y'),
              $wholeWeeks => trans_choice('schedule.confirm.weeks', $weeks, ['count' => $weeks]),
              default => trans_choice('schedule.confirm.days_long', $dayCount, ['count' => $dayCount]),
          },
          'days' => collect($assignDays)->map(fn (array $day) => $day + [
              'today' => $day['date'] === now()->toDateString(),
          ])->all(),
          /* The month cut into its weeks, worked out here rather than in the
             browser: the labels are dates, and a browser that formatted them
             itself would say them in a different language from the page
             around it. The weeks are the calendar's, which is also what the
             shift rule's weekly ceiling is counted in — so a week that reads
             as over here is the week the guard will refuse. */
          'weeks' => collect($assignDays)
              ->groupBy(fn (array $day) => \Carbon\CarbonImmutable::parse($day['date'])->startOfWeek()->toDateString())
              ->values()
              ->map(function ($days, int $index) {
                  $first = \Carbon\CarbonImmutable::parse($days->first()['date']);
                  $last = \Carbon\CarbonImmutable::parse($days->last()['date']);

                  return [
                      'label' => __('schedule.week_number', ['number' => $index + 1]),
                      'range' => $first->isSameDay($last)
                          ? $first->translatedFormat('j M')
                          : $first->translatedFormat('j M').' – '.$last->translatedFormat('j M'),
                      'dates' => $days->pluck('date')->all(),
                      /* Open on the week the reader is standing in, where the
                         month has one. Otherwise the first: a month opened on
                         nothing is a month whose fields are all one click
                         away. */
                      'current' => $days->contains(fn (array $day) => $day['date'] === now()->toDateString()),
                  ];
              })
              ->all(),
          'rules' => $shiftRulesOn ? $shiftRules->pluck('name', 'id') : [],
          'selectedRule' => old('shift_rule_id', $chosenRule?->id ?? ''),
          'allowSplit' => (bool) $chosenRule?->allow_split_shift,
          'breakDurations' => config('shift_rules.break_durations'),
          /* Keyed by date, so each message sits against its own day rather
             than all of them at the top. */
          /* A period this person has already been sent, whatever state its
             days are in now: editing it is changing something they have been
             told, and the screen says so before the first change rather than
             in the confirmation after the last. */
          'publishedNotice' => $period->publishedAt() === null ? null : [
              'title' => __('schedule.published_notice_title'),
              'body' => __('schedule.published_notice', ['name' => $staff->displayName()]),
          ],
          'errors' => collect($errors->getMessages())
              ->filter(fn ($messages, $key) => str_starts_with($key, 'schedule.'))
              ->mapWithKeys(fn ($messages, $key) => [substr($key, strlen('schedule.')) => $messages])
              ->all(),
          /* A period this person already holds is updated, not published:
             the button says which of the two pressing it does.

             Overwritten rather than added with `+`, which keeps the key the
             left-hand array already has — the schedule file's own
             save_and_publish — and would have left the button saying the
             opposite of what it does. */
          'labels' => array_replace(
              \Illuminate\Support\Arr::except(__('schedule'), ['email', 'validation']) + [
                  'cancel' => __('common.cancel'),
                  'saving' => __('common.saving'),
                  'hours' => __('schedule.month_hours'),
                  'today' => __('staff.schedule_tab.today'),
                  'not_working' => __('staff.schedule_tab.not_working'),
              ],
              $period->publishedAt() === null ? [] : ['save_and_publish' => __('schedule.update_and_publish')],
          ),
      ];
  @endphp

  <div data-vue-component="AssignScheduleForm" data-props='@json($formProps)'></div>

  {{-- The form is a Vue island; without it the screen would be blank rather
       than degraded, so the way back is stated in plain HTML underneath. --}}
  <noscript>
    <div class="w-full px-6 py-8">
      <p class="text-[13px] text-sub">{{ __('schedule.needs_javascript') }}</p>
      <a href="{{ $returnTo }}"
         class="styledesk_action mt-3">← {{ __('schedule.back') }}</a>
    </div>
  </noscript>
@endsection
