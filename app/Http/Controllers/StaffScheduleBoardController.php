<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffShift;
use App\Support\SchedulePeriod;
use App\Support\StaffSection;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The whole team's rota, a month at a time.
 *
 * The per-person schedule screen answers "what is this one person working";
 * this one answers the question a manager actually opens the app with —
 * "whose month is still empty". A row per person, a column per month, and the
 * cell says which of the three states that month is in.
 *
 * Read-only: every action on it leads somewhere that already exists. A cell
 * with a schedule opens that person's month, a cell without one starts the
 * assign flow for it, and nothing is written here.
 */
class StaffScheduleBoardController extends Controller
{
    /**
     * How many months the board shows at once.
     *
     * A year, so a manager scrolling sideways covers the whole planning
     * horizon without changing a filter. Three of them behind the month in
     * focus, because "who did we forget last month" is asked as often as
     * "who is not covered next month".
     */
    private const MONTHS = 12;

    private const MONTHS_BEHIND = 3;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $filters = $this->filters($request);

        return view('settings.staff.schedules', [
            'filters' => $filters,
            'columns' => $this->columns($filters),
            'years' => $this->years(),
            /* The Assign Schedule dialog picks from everybody, whatever the
               board is currently filtered down to: it is a way in, not a
               statement about what is on screen. */
            'staffOptions' => Staff::query()
                ->orderBy('first_name')->orderBy('last_name')
                ->get()
                ->mapWithKeys(fn (Staff $member) => [$member->id => $member->displayName()]),
        ]);
    }

    /**
     * The rows the board's grid asks for, as JSON.
     *
     * The same shape every other listing in the app returns, because it is
     * the same grid: a page of rows, the total, and each row carrying its own
     * actions. What differs is that most of a row here is one cell per month.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);

        $filters = $this->filters($request);
        $columns = $this->columns($filters);

        $staff = Staff::query()
            ->with('location')
            /* Name, email, phone or job title — the same four the staff
               directory searches, so one search box does not mean two things
               in one module. Grouped, or the or-chain would swallow every
               condition beside it. */
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $q) use ($filters) {
                $like = '%'.$filters['search'].'%';

                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('preferred_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('work_email', 'like', $like)
                    ->orWhere('job_title', 'like', $like);
            }))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $states = $this->statesByStaffAndMonth($staff->pluck('id')->all(), $columns);

        $rows = $staff->map(fn (Staff $member) => $this->row($member, $columns, $states, $filters));

        /* Filtered on the built cells rather than in the query: "has a draft
           somewhere in this window" is a statement about the cells, and the
           cells are the thing that was just worked out. */
        if ($filters['status'] !== '') {
            $rows = $rows->filter(fn (array $row) => collect($row['cells'])
                ->contains(fn (array $cell) => $this->matchesStatus($cell, $filters['status'])));
        }

        $rows = $rows->values();

        $size = min(100, max(1, (int) $request->query('size', 25)));
        $page = max(1, (int) $request->query('page', 1));

        return response()->json([
            'last_page' => (int) max(1, ceil($rows->count() / $size)),
            /* last_row is what the counter reads. Without it the grid works
               the total out as pages x page size and says "of 40" when there
               are 33 — a rounded-up number presented as a count. */
            'last_row' => $rows->count(),
            'total' => $rows->count(),
            'data' => $rows->forPage($page, $size)->map(function (array $row) {
                /* The cells are keyed by month on the way out: the grid reads
                   a field per column, and the list they were built as is only
                   convenient in here. */
                $fields = [];

                foreach ($row['cells'] as $cell) {
                    $fields[self::field($cell['key'])] = Arr::except($cell, ['key', 'state']);
                }

                return Arr::except($row, ['cells']) + $fields;
            })->values(),
        ]);
    }

    /** The grid field one month's cells live in. */
    public static function field(string $monthKey): string
    {
        return 'm'.str_replace('-', '_', $monthKey);
    }

    /**
     * One staff member, as the grid reads them.
     *
     * @param  Collection<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, int>>  $states
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function row(Staff $staff, Collection $columns, array $states, array $filters): array
    {
        $focus = CarbonImmutable::create($filters['year'], $filters['month'], 1);

        return [
            'id' => $staff->id,
            'name' => $staff->displayName(),
            'initials' => $staff->initials(),
            /* The job title rides beside the name, the way the client grid
               puts a reference beside theirs. */
            'primary_badge' => $staff->job_title,
            /* Clicking the row rather than a month opens the person: the
               months are the cells' own business. */
            'url' => StaffSection::route('schedule', $staff).'?'.http_build_query([
                'period' => 'month', 'year' => $filters['year'], 'month' => $filters['month'],
            ]),
            'menu' => [
                [
                    'label' => __('staff_schedules.menu.view', ['month' => $focus->translatedFormat('F Y')]),
                    'url' => StaffSection::route('schedule', $staff).'?'.http_build_query([
                        'period' => 'month', 'year' => $filters['year'], 'month' => $filters['month'],
                    ]),
                ],
                [
                    'label' => __('staff_schedules.menu.assign', ['month' => $focus->translatedFormat('F Y')]),
                    'url' => $this->assignUrl($staff, $filters['year'], $filters['month']),
                ],
            ],
            'cells' => $columns->map(fn (array $column) => $this->cell($staff, $column, $states))->all(),
        ];
    }

    /**
     * One person's month, as the board's modal reads it.
     *
     * A published month is opened to be checked rather than changed, and the
     * schedule page is a whole screen away from the board a manager is
     * working down. This answers the same question in place: every day of the
     * month, worked or not, with the times behind each one.
     *
     * Days off are rows too. A list of only the working days hides the gaps,
     * and the gaps are half of what a rota is read for.
     */
    public function month(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('view', $staff);

        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $from = CarbonImmutable::create($data['year'], $data['month'], 1)->startOfMonth();
        $until = $from->endOfMonth();

        $period = SchedulePeriod::for($staff->loadMissing('location'), $from, $until);
        $byDate = $period->byDate();

        $days = [];

        for ($day = $from; $day->lte($until); $day = $day->addDay()) {
            $onDay = ($byDate[$day->toDateString()] ?? collect())
                ->reject(fn (StaffShift $shift) => $shift->isCancelled());

            $days[] = [
                'date' => $day->translatedFormat('j M Y'),
                'day' => $day->translatedFormat('D'),
                'working' => $onDay->isNotEmpty(),
                'label' => $onDay->isNotEmpty() ? __('schedule.working') : __('schedule.not_working'),
                'hours' => __('schedule.hours_short', [
                    'count' => round($onDay->sum(fn (StaffShift $shift) => $shift->workedMinutes()) / 60, 1),
                ]),
                'shifts' => $onDay->values()->map(fn (StaffShift $shift, int $index) => [
                    /* Numbered rather than named: a split day is two blocks of
                       the same shift, and "1 of 2" is what tells them apart on
                       a row that has already said the date. */
                    'name' => $onDay->count() > 1 ? __('staff_schedules.month.split', ['index' => $index + 1]) : null,
                    'start' => $shift->startsAt()->translatedFormat('g:i A'),
                    'end' => $shift->endsAt()->translatedFormat('g:i A'),
                    'break' => $shift->break_minutes
                        ? __('staff_schedules.month.minutes', ['count' => $shift->break_minutes])
                        : null,
                    'hours' => __('schedule.hours_short', ['count' => round($shift->workedMinutes() / 60, 1)]),
                ])->all(),
            ];
        }

        return response()->json([
            'staff' => $staff->displayName(),
            'month' => $from->translatedFormat('F Y'),
            'state' => $period->state(),
            'state_label' => __('staff_schedules.states.'.match ($period->state()) {
                SchedulePeriod::PUBLISHED => 'published',
                SchedulePeriod::CHANGES => 'changes',
                SchedulePeriod::EMPTY => 'not-scheduled',
                default => 'draft',
            }),
            'published' => $period->state() === SchedulePeriod::PUBLISHED,
            'shifts' => trans_choice('staff_schedules.shifts_count', $period->shifts->count(), [
                'count' => $period->shifts->count(),
            ]),
            'assign_url' => $this->assignUrl($staff, (int) $data['year'], (int) $data['month']),
            'days' => $days,
        ]);
    }

    /**
     * The Assign Schedule action, which needs a person and a month first.
     *
     * A redirect rather than a screen of its own: the assign flow already
     * exists and already knows how to build a month, so this only answers
     * whose month it is about.
     */
    public function start(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Staff::class);

        $data = $request->validate([
            'staff' => ['required', 'integer'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $staff = Staff::query()->findOrFail($data['staff']);

        $this->authorize('update', $staff);

        return redirect($this->assignUrl($staff, (int) $data['year'], (int) $data['month']));
    }

    /**
     * What the toolbar is asking for.
     *
     * Month and year default to today's, so the board opens on the month the
     * manager is standing in rather than on a range they have to choose
     * before they can read anything.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        $coverage = (string) $request->query('coverage', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            /* Clamped rather than refused: a hand-edited query string should
               show a sensible month, not an error page. */
            'month' => max(1, min(12, (int) $request->query('month', now()->month))),
            'year' => max(2000, min(2100, (int) $request->query('year', now()->year))),
            'status' => in_array($status, self::statuses(), true) ? $status : '',
            'coverage' => in_array($coverage, self::coverages(), true) ? $coverage : '',
        ];
    }

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return ['scheduled', 'not-scheduled', 'draft', 'published'];
    }

    /** @return array<int, string> */
    public static function coverages(): array
    {
        return ['completed', 'current', 'future'];
    }

    /**
     * The months across the top.
     *
     * Where each one sits relative to today travels with it — the board is
     * read for coverage, and "this month" has to be findable at a glance in a
     * row of twelve that otherwise look alike.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function columns(array $filters): Collection
    {
        $thisMonth = CarbonImmutable::now()->startOfMonth();
        $start = CarbonImmutable::create($filters['year'], $filters['month'], 1)
            ->startOfMonth()
            ->subMonths(self::MONTHS_BEHIND);

        return collect(range(0, self::MONTHS - 1))
            ->map(function (int $offset) use ($start, $thisMonth) {
                $month = $start->addMonths($offset);

                return [
                    'year' => $month->year,
                    'month' => $month->month,
                    'key' => $month->format('Y-n'),
                    'label' => $month->translatedFormat('M Y'),
                    'from' => $month->toDateString(),
                    'until' => $month->endOfMonth()->toDateString(),
                    'days' => (int) $month->diffInDays($month->endOfMonth()) + 1,
                    'coverage' => match (true) {
                        $month->lessThan($thisMonth) => 'completed',
                        $month->equalTo($thisMonth) => 'current',
                        default => 'future',
                    },
                ];
            })
            ->when($filters['coverage'] !== '', fn (Collection $months) => $months
                ->filter(fn (array $month) => $month['coverage'] === $filters['coverage']))
            ->values();
    }

    /**
     * One state per person per month, in one query.
     *
     * Counted in the database rather than by loading the shifts: a year of
     * twelve months across a salon's worth of staff is tens of thousands of
     * rows to answer a question about three numbers.
     *
     * @param  array<int, int>  $staffIds
     * @param  Collection<int, array<string, mixed>>  $columns
     * @return array<string, array<string, int>> "staffId:Y-n" => counts
     */
    private function statesByStaffAndMonth(array $staffIds, Collection $columns): array
    {
        if ($staffIds === [] || $columns->isEmpty()) {
            return [];
        }

        $rows = StaffShift::query()
            ->whereIn('staff_id', $staffIds)
            ->inRange($columns->first()['from'], $columns->last()['until'])
            /* A cancelled shift is a fact about the week, not cover for it:
               a month whose only shift was called off is not scheduled. */
            ->where('status', '!=', 'cancelled')
            ->selectRaw('staff_id, year(date) as y, month(date) as m')
            ->selectRaw('count(*) as shifts')
            ->selectRaw("sum(case when publish_status = 'published' then 1 else 0 end) as published")
            ->selectRaw('sum(case when published_at is not null then 1 else 0 end) as ever_published')
            ->groupByRaw('staff_id, year(date), month(date)')
            ->get();

        $states = [];

        foreach ($rows as $row) {
            $states[$row->staff_id.':'.$row->y.'-'.$row->m] = [
                'shifts' => (int) $row->shifts,
                'published' => (int) $row->published,
                'ever_published' => (int) $row->ever_published,
            ];
        }

        return $states;
    }

    /**
     * One month of one person's rota, as the board draws it.
     *
     * The same three states the per-person screen uses, worked out the same
     * way — published means every shift in the month has been sent, and a
     * month sent once and edited since is neither published nor a fresh
     * draft.
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, array<string, int>>  $states
     * @return array<string, mixed>
     */
    private function cell(Staff $staff, array $column, array $states): array
    {
        $counts = $states[$staff->id.':'.$column['key']] ?? null;

        $state = match (true) {
            $counts === null || $counts['shifts'] === 0 => 'not-scheduled',
            $counts['published'] === $counts['shifts'] => 'published',
            $counts['ever_published'] > 0 => 'changes',
            default => 'draft',
        };

        $label = __('staff_schedules.states.'.$state);

        return [
            'key' => $column['key'],
            'state' => $state,
            'label' => $label,
            /* Three colours, four states: a month sent once and edited since
               is a draft as far as the person holding it is concerned. */
            'tone' => match ($state) {
                'published' => 'published',
                'not-scheduled' => 'none',
                default => 'draft',
            },
            'shifts' => $counts['shifts'] ?? 0,
            'describe' => __(
                $state === 'not-scheduled' ? 'staff_schedules.start_schedule' : 'staff_schedules.open_schedule',
                ['name' => $staff->displayName(), 'month' => $column['label']],
            ).' · '.$label,
            /* Where the cell leads: the month itself where there is one to
               read, and the flow that would create it where there is not. */
            'url' => $state === 'not-scheduled'
                ? $this->assignUrl($staff, $column['year'], $column['month'])
                : StaffSection::route('schedule', $staff).'?'.http_build_query([
                    'period' => 'month',
                    'year' => $column['year'],
                    'month' => $column['month'],
                ]),
            /* A month that has been sent is read before it is changed, so the
               cell opens it in place. The url above stays as it is: it is
               where the cell goes with no script, and where the modal's own
               Edit action starts from. */
            'modal' => $state === 'published'
                ? route('staff.schedules.month', $staff).'?'.http_build_query([
                    'year' => $column['year'], 'month' => $column['month'],
                ])
                : null,
        ];
    }

    /** Whether a cell answers the status the toolbar is filtering on. */
    private function matchesStatus(array $cell, string $status): bool
    {
        $state = $cell['state'];

        return match ($status) {
            'scheduled' => $state !== 'not-scheduled',
            'not-scheduled' => $state === 'not-scheduled',
            /* A month edited since it was sent is a draft again in every
               sense that matters here: somebody has to press Publish. */
            'draft' => in_array($state, ['draft', 'changes'], true),
            'published' => $state === 'published',
            default => true,
        };
    }

    /**
     * The assign flow for one person and one whole month.
     *
     * The board is where a manager comes back to, so it is what the flow
     * returns to — with the same filters they left it on.
     */
    private function assignUrl(Staff $staff, int $year, int $month): string
    {
        $from = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return StaffSection::route('schedule.assign.form', $staff).'?'.http_build_query([
            'days' => (int) $from->diffInDays($from->endOfMonth()) + 1,
            'from' => $from->toDateString(),
            'return' => route('staff.schedules').'?'.http_build_query([
                'year' => $year, 'month' => $month,
            ]),
        ]);
    }

    /**
     * The years worth offering.
     *
     * Last year, this year and the next two: a rota is planned forwards, and
     * a board that could not reach next January would be useless every
     * December.
     *
     * @return array<int, int>
     */
    private function years(): array
    {
        $year = (int) now()->year;

        return [$year - 1, $year, $year + 1, $year + 2];
    }
}
