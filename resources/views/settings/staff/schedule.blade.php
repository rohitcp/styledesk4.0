@extends('layouts.app')

@section('title', $staff->displayName().' — '.__('staff.tabs.schedule'))

@section('content')
  {{-- The full-width container the clients screens use: the same padding,
       the same breakpoints, no narrow column of its own. A workspace that
       sat in 1080px while every other screen filled the window would read
       as a different application. --}}
  <main class="w-full px-6 lg:px-8 pt-4 pb-[100px]">

      @include('settings.staff._header', ['tab' => 'schedule'])

      {{-- The year, and what each of its months came to.

           A rota is planned a period at a time and reviewed a year at a time,
           which is why both are on this page: the cards answer "how much did
           they work" and the grid below answers "what are they doing". --}}
      <section class="bg-white border border-line rounded-card p-5 mt-5">
        <h2 class="text-[15px] font-semibold text-head">{{ __('schedule.monthly_summary') }}</h2>

        {{-- Twelve cards: a year reads as one run of months. Each is a link
             into that month, so the summary is also a way to navigate. --}}
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-2.5">
          @foreach ($months as $card)
            @php $isSelected = $filters['period'] === 'month' && $filters['month'] === $card['month']; @endphp

            <a href="{{ \App\Support\StaffSection::route('schedule', $staff).'?'.http_build_query([
                   'period' => 'month', 'year' => $year, 'month' => $card['month'],
               ]) }}"
               @class([
                   'rounded-lg border p-3 transition-colors',
                   'border-brand bg-brand/5' => $isSelected,
                   'border-line hover:bg-hover/50' => ! $isSelected,
               ])
               @if ($isSelected) aria-current="true" @endif>
              <span class="block text-[12px] font-semibold text-head">{{ $card['label'] }}</span>
              <span class="block text-[18px] font-bold text-head leading-tight mt-1">
                {{ $card['hours'] }}<span class="text-[12px] font-medium text-sub"> {{ __('schedule.month_hours') }}</span>
              </span>
              <span class="block text-[12px] text-sub mt-0.5">
                {{ $card['shifts'] }} {{ __('schedule.month_shifts') }}
              </span>
            </a>
          @endforeach
        </div>
      </section>

      @php
          /* The Shift Rule action is offered only when there is something to
             choose from — the feature on and an active rule to pick — or when
             this person is already on one, so an assignment made before a rule
             was retired can still be changed or taken off. */
          $canManageShiftRule = $shiftRulesOn && ($shiftRules->isNotEmpty() || $staff->shiftRule);

          $state = $period->state();
          $publishedAt = $period->publishedAt();

          /* Publishing an edited period is a different sentence from
             publishing it the first time: the staff member has already been
             told once, and the button should say which of the two this is. */
          $isRepublish = $state === \App\Support\SchedulePeriod::CHANGES;

          $publishProps = [
              'action' => \App\Support\StaffSection::route('schedule.publish', $staff),
              'csrf' => csrf_token(),
              'staffName' => $staff->displayName(),
              'from' => $from->toDateString(),
              'until' => $until->toDateString(),
              'periodLabel' => $period->label(),
              'durationLabel' => trans_choice('schedule.confirm.weeks', $period->weeks(), ['count' => $period->weeks()]),
              'workingDays' => $period->workingDays(),
              'totalHoursLabel' => trans_choice('schedule.confirm.hours', (int) ceil($period->totalHours()), ['count' => $period->totalHours()]),
              'isRepublish' => $isRepublish,
              'labels' => \Illuminate\Support\Arr::only(__('schedule'), [
                  'publish', 'publish_changes', 'publishing', 'confirm',
              ]) + ['cancel' => __('common.cancel')],
          ];
      @endphp

      <section class="bg-white border border-line rounded-card p-5 mt-5">
        <div class="flex flex-wrap items-start gap-4">
          <div class="min-w-0 flex-1">
            <h2 class="text-[15px] font-semibold text-head">{{ __('schedule.working_schedule') }}</h2>

            {{-- The period is stated, not offered: a page opened without one
                 is already showing this month, and the month somebody is
                 looking at is worth more room than the controls for changing
                 it. Those live behind the pencil, one click away. --}}
            <p class="flex items-center gap-2 mt-1">
              <span class="text-[15px] font-semibold text-head">{{ $period->label() }}</span>
              <button type="button" class="sd-iconbtn grid place-items-center" data-period-toggle
                      aria-expanded="false" aria-controls="schedulePeriodPanel"
                      aria-label="{{ __('schedule.filters.edit_period') }}"
                      title="{{ __('schedule.filters.edit_period') }}">
                <x-icon name="pen-to-square" size="13" />
              </button>
            </p>

            {{-- A GET form, so Apply is a normal navigation: the address bar
                 ends up holding the month being read, which is what makes the
                 page bookmarkable and the back button mean what it says. The
                 period length rides along hidden — it was chosen on the way
                 in and this panel is only about which month. --}}
            <form method="GET" id="schedulePeriodPanel" hidden
                  class="mt-3 rounded-lg border border-line p-4 max-w-[520px]">
              <input type="hidden" name="period" value="{{ $filters['period'] }}">

              <div class="grid gap-3 sm:grid-cols-2">
                <x-combo name="month" :label="__('schedule.filters.month')"
                         :options="collect(range(1, 12))->mapWithKeys(fn (int $m) => [
                             $m => \Carbon\CarbonImmutable::create($filters['year'], $m, 1)->translatedFormat('F'),
                         ])"
                         :selected="$filters['month']"
                         :placeholder="__('schedule.filters.month')" />

                <x-combo name="year" :label="__('schedule.filters.year')"
                         :options="collect($years)->mapWithKeys(fn (int $y) => [$y => (string) $y])"
                         :selected="$filters['year']"
                         :placeholder="(string) $filters['year']" />
              </div>

              <div class="flex flex-wrap items-center gap-2 mt-4">
                <button type="submit"
                        class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                  {{ __('schedule.filters.apply') }}
                </button>
                <button type="button" class="styledesk_action" data-period-cancel>{{ __('common.cancel') }}</button>
              </div>
            </form>

            {{-- What the period comes to, above the days themselves: the
                 question asked of a rota is usually "is that enough hours",
                 and counting them by eye down a list is the reader doing the
                 arithmetic. --}}
            <p class="text-[13px] text-sub mt-1">
              {{ trans_choice('schedule.summary', $period->workingDays(), [
                  'hours' => $period->totalHours(),
                  'count' => $period->workingDays(),
              ]) }}
            </p>
          </div>

          @can('update', $staff)
            {{-- The pattern somebody is on, the period being planned, and the
                 one-off day that belongs to neither. Assign Schedule is the
                 primary of the three because it is the one this page exists
                 for. --}}
            <div class="shrink-0 flex flex-wrap items-center gap-2">
              @if ($canManageShiftRule)
                <button type="button" class="styledesk_action"
                        data-rule-toggle aria-expanded="false" aria-controls="shiftRulePanel">
                  {{ __('staff.schedule_tab.shift_rule') }}
                </button>
              @endif

              @php
                  /* Every month the dialog can offer, worked out here rather
                     than in the browser.

                     The labels are ready-made strings: a browser that
                     formatted its own dates would say them in a different
                     language from the page around them. Changing the month
                     inside the dialog is a choice between these, so it needs
                     no round trip.

                     A month is planned whole, from its first to its last —
                     including the one already under way, so the schedule a
                     manager opens covers the month they are looking at rather
                     than the part of it that happens to be left. */
                  $assignPeriods = collect($years)->crossJoin(range(1, 12))
                      ->mapWithKeys(function (array $pair) {
                          [$optionYear, $optionMonth] = $pair;

                          $monthFrom = \Carbon\CarbonImmutable::create($optionYear, $optionMonth, 1)->startOfDay();
                          $monthUntil = $monthFrom->endOfMonth()->startOfDay();

                          return [$optionYear.'-'.$optionMonth => [
                              'label' => $monthFrom->translatedFormat('F Y'),
                              'range' => $monthFrom->translatedFormat('j M Y').' – '
                                  .$monthUntil->translatedFormat('j M Y'),
                              'from' => $monthFrom->toDateString(),
                              /* An exact day count, so the screen the dialog
                                 opens covers the month it promised however
                                 many days that month has. */
                              'days' => (int) $monthFrom->diffInDays($monthUntil) + 1,
                          ]];
                      });

                  $startProps = [
                      'formUrl' => \App\Support\StaffSection::route('schedule.assign.form', $staff),
                      'periods' => $assignPeriods,
                      'months' => collect(range(1, 12))->mapWithKeys(fn (int $m) => [
                          $m => \Carbon\CarbonImmutable::create($filters['year'], $m, 1)->translatedFormat('F'),
                      ]),
                      'years' => collect($years)->mapWithKeys(fn (int $y) => [$y => (string) $y]),
                      'month' => $filters['month'],
                      'year' => $filters['year'],
                      'labels' => \Illuminate\Support\Arr::only(__('schedule'), [
                          'assign', 'assign_title', 'duration_intro', 'period', 'continue_label',
                      ]) + [
                          'cancel' => __('common.cancel'),
                          'month' => __('schedule.filters.month'),
                          'year' => __('schedule.filters.year'),
                          'edit_period' => __('schedule.filters.edit_period'),
                      ],
                  ];
              @endphp

              <div data-vue-component="AssignScheduleStart" data-props='@json($startProps)'></div>
            </div>
          @endcan
        </div>

        @can('update', $staff)
          @if ($canManageShiftRule)
            @php
                /* An inactive rule already on somebody stays on them — it is
                   new assignments it is kept out of — so the current one is
                   added back to the list, or the combo would show a bare id
                   and saving would silently drop it. */
                $ruleOptions = $shiftRules->pluck('name', 'id');

                if ($staff->shiftRule && ! $ruleOptions->has($staff->shift_rule_id)) {
                    $ruleOptions = $ruleOptions->put($staff->shift_rule_id, $staff->shiftRule->name);
                }
            @endphp

            {{-- The rule is the template; the dated rows below are the
                 reality. It is asked for in a dialog rather than in a card of
                 its own, because the page is read for the working schedule
                 and only consulted for the pattern behind it — and a panel
                 opening in the middle of the page pushed the schedule down
                 the screen to make room for a question about something else. --}}
            <div id="shiftRulePanel" class="styledesk_modal" hidden data-shift-rule-panel>
              <div class="styledesk_modal__scrim" data-rule-cancel></div>

              <div class="styledesk_modal__panel" role="dialog" aria-modal="true"
                   aria-labelledby="shiftRuleTitle">
                <div class="styledesk_modal__head">
                  <h2 id="shiftRuleTitle" class="text-[15px] font-semibold text-head">
                    {{ __('staff.schedule_tab.shift_rule') }}
                  </h2>

                  <button type="button" class="styledesk_modal__close" data-rule-cancel
                          aria-label="{{ __('common.cancel') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round"/>
                    </svg>
                  </button>
                </div>

                <form method="POST" action="{{ \App\Support\StaffSection::route('shift-rule', $staff) }}">
                  @csrf
                  @method('PATCH')

                  <div class="styledesk_modal__body">
                    @if ($staff->shiftRule)
                      <p class="text-[12px] text-faint">{{ __('staff.schedule_tab.current_rule') }}</p>
                      <p class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="text-[14px] font-semibold text-head">{{ $staff->shiftRule->name }}</span>
                        <span class="styledesk_badge {{ $staff->shiftRule->statusClass() }}">
                          {{ $staff->shiftRule->statusLabel() }}
                        </span>
                      </p>
                    @else
                      <p class="text-[13px] font-medium text-head">{{ __('staff.schedule_tab.no_rule') }}</p>
                    @endif

                    <div class="mt-3">
                      <x-combo name="shift_rule_id"
                               :label="$staff->shiftRule ? __('staff.schedule_tab.change_rule') : __('staff.schedule_tab.assign_rule')"
                               :options="$ruleOptions"
                               :selected="old('shift_rule_id', $staff->shift_rule_id)"
                               :placeholder="__('staff.fields.no_shift_rule')" />
                    </div>
                  </div>

                  <div class="styledesk_modalfoot">
                    <button type="submit" data-submit-once data-busy-label="{{ __('common.saving') }}"
                            class="h-9 px-4 rounded-lg bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold transition-colors">
                      {{ __('common.save') }}
                    </button>
                    <button type="button" class="styledesk_action" data-rule-cancel>{{ __('common.cancel') }}</button>

                    {{-- Posted by the form below rather than by this one:
                         taking the rule off posts an empty value, and a
                         button that had to out-rank the combo to do it would
                         be one DOM reorder away from silently saving instead.
                         The form attribute is what lets it stand in the
                         footer next to Save while belonging to the other. --}}
                    @if ($staff->shiftRule)
                      <button type="submit" form="removeShiftRule"
                              class="styledesk_action styledesk_action--danger ml-auto"
                              data-confirm="{{ __('staff.schedule_tab.remove_rule_confirm', ['rule' => $staff->shiftRule->name, 'name' => $staff->displayName()]) }}"
                              data-confirm-title="{{ __('staff.schedule_tab.remove_rule') }}"
                              data-confirm-label="{{ __('staff.schedule_tab.remove_rule') }}"
                              data-confirm-tone="danger">
                        {{ __('staff.schedule_tab.remove_rule') }}
                      </button>
                    @endif
                  </div>
                </form>
              </div>
            </div>

            @if ($staff->shiftRule)
              <form id="removeShiftRule" method="POST" class="hidden"
                    action="{{ \App\Support\StaffSection::route('shift-rule', $staff) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="shift_rule_id" value="">
              </form>
            @endif
          @endif
        @endcan

        @include('settings.staff._schedule-actions', ['showStatus' => true])

        @php
            $gridLabels = [
                'columns' => __('schedule.columns'),
                'locked' => __('schedule.locked'),
                'showing' => __('schedule.showing'),
                'actions_for' => __('schedule.actions_for'),
            ];

            $gridConfig = [
                'labels' => $gridLabels,
                'name_field' => 'name',
                'page_size' => 50,
                'columns' => [
                    ['field' => 'date', 'title' => $gridLabels['columns']['date'], 'type' => 'lockable', 'grow' => 1.4, 'min' => 130, 'responsive' => 0],
                    ['field' => 'day', 'title' => $gridLabels['columns']['day'], 'width' => 70, 'responsive' => 4],
                    ['field' => 'working', 'title' => $gridLabels['columns']['working'], 'type' => 'badge', 'width' => 140, 'responsive' => 1],
                    ['field' => 'time', 'title' => $gridLabels['columns']['time'], 'grow' => 2, 'min' => 160, 'responsive' => 1],
                    ['field' => 'status', 'title' => $gridLabels['columns']['status'], 'type' => 'badge', 'width' => 110, 'responsive' => 1],
                    ['field' => 'published_on', 'title' => $gridLabels['columns']['published_on'], 'grow' => 1.6, 'min' => 150, 'responsive' => 5, 'muted' => true],
                    ['field' => 'published_by', 'title' => $gridLabels['columns']['published_by'], 'grow' => 1.3, 'min' => 130, 'responsive' => 5, 'muted' => true],
                    ['field' => 'hours', 'title' => $gridLabels['columns']['hours'], 'width' => 110, 'responsive' => 2],
                    ['field' => 'bookings', 'title' => $gridLabels['columns']['bookings'], 'width' => 90, 'responsive' => 6, 'muted' => true],
                    ['field' => 'actions', 'type' => 'actions'],
                ],
            ];
        @endphp

        <div class="mt-4 styledesk_gridframe">
          <div data-grid
               data-url="{{ \App\Support\StaffSection::route('schedule.data', $staff).'?'.http_build_query($filters) }}"
               data-config='@json($gridConfig)'></div>
        </div>

        {{-- Said once, underneath: booking counts need the booking module, and
             a column of zeroes with no explanation reads as a broken count. --}}
        <p class="mt-3 text-[12px] text-faint">{{ __('schedule.bookings_pending') }}</p>

        @include('settings.staff._schedule-actions', ['showStatus' => false])
      </section>
  </main>
@endsection
