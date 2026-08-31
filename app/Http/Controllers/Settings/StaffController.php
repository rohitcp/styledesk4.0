<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Staff\CreateStaffMember;
use App\Http\Controllers\Controller;
use App\Jobs\SendSchedulePublishedEmail;
use App\Mail\SchedulePublishedMail;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Service;
use App\Models\ShiftRule;
use App\Models\Staff;
use App\Models\StaffNote;
use App\Models\StaffShift;
use App\Models\TeamInvitation;
use App\Support\Currencies;
use App\Support\InputCase;
use App\Support\RoleGuard;
use App\Support\ScheduleGuard;
use App\Support\SchedulePeriod;
use App\Support\StaffSection;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The staff directory, §3.
 *
 * Read-only for now: adding, editing and the per-staff screens are the next
 * stage. What is here is the list, its filters and the state of each person,
 * which is the part every other staff screen is reached from.
 */
class StaffController extends Controller
{
    /** Rows before the directory splits into pages. */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $filters = $this->filters($request);
        $staff = $this->directory($filters);

        return view('settings.staff.index', [
            'filters' => $filters,
            'roles' => Role::query()->orderBy('display_order')->get(),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            // Counted over everything that matched, not over the page being
            // looked at: "6 active members" must not become "3" on page two.
            'activeCount' => $staff->filter(fn (Staff $m) => $m->status() === 'active')->count(),
            'pendingCount' => $staff->filter(fn (Staff $m) => $m->status() === 'pending-invite')->count(),
            /* Whether this business has anybody at all, which is a different
               question from whether anybody matched — and gets a different
               empty state. */
            'hasStaff' => Staff::query()->exists(),
        ]);
    }

    /**
     * The rows the listing grid asks for, as JSON.
     *
     * The same shape every other listing serves — see ClientController and
     * ServiceController. Search, filters and paging stay on the server
     * because the query is what knows which rows this business may see.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);

        $filters = $this->filters($request);
        $staff = $this->directory($filters);

        /* The grid asks for its own page size and the server decides what it
           is allowed to be: an unchecked parameter would let anyone ask for
           the whole directory in one request. */
        $size = min(200, max(1, (int) $request->query('size', self::PER_PAGE)));
        $page = max(1, (int) $request->query('page', 1));

        return response()->json([
            'last_page' => max(1, (int) ceil($staff->count() / $size)),
            /* What the counter reads. Without it the grid works the total out
               as pages x page size and says "of 50" when there are 43. */
            'last_row' => $staff->count(),
            'total' => $staff->count(),
            'data' => $staff->forPage($page, $size)
                ->map(fn (Staff $member) => $this->row($request, $member))
                ->values()
                ->all(),
        ]);
    }

    /**
     * One row, already worded.
     *
     * Rendered here rather than in the browser for the reason every other
     * listing does it: the phrases are the ones the rest of the app uses, in
     * the reader's language, and which actions a row offers is a permission
     * question that must not have a second home in JavaScript.
     *
     * @return array<string, mixed>
     */
    private function row(Request $request, Staff $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->directoryName(),
            'initials' => $member->initials(),
            /* The staff ID beside the name rather than in a column of its
               own: it is what tells two people with the same name apart, and
               it has to survive the table narrowing. */
            'primary_badge' => $member->employee_ref,
            'role' => $member->job_title ?: $member->roleName(),
            'location' => $member->location?->name ?? __('staff.all_locations'),
            'services' => (string) $member->services_count,
            'phone' => $member->phone,
            'email' => $member->email,
            'status' => $member->statusLabel(),
            'status_class' => $member->statusClass(),

            'url' => StaffSection::route('show', $member),
            'menu' => $this->rowMenu($request, $member),
        ];
    }

    /**
     * One row's actions.
     *
     * View is always there; the rest depend on what this reader may do.
     * Scheduling is shown disabled rather than hidden — the screens are not
     * built yet, and an entry that quietly disappears reads as a permission
     * the reader lacks rather than as work still to come.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowMenu(Request $request, Staff $member): array
    {
        $menu = [
            ['label' => __('staff.view'), 'url' => StaffSection::route('show', $member)],
        ];

        if ($request->user()->can('update', $member)) {
            $menu[] = ['label' => __('staff.edit'), 'url' => StaffSection::route('edit', $member)];
        }

        $menu[] = ['separator' => true];
        $menu[] = ['label' => __('staff.manage_schedule'), 'disabled' => true];
        $menu[] = ['label' => __('staff.set_time_off'), 'disabled' => true];

        if ($request->user()->can('update', $member)) {
            $label = $member->is_active ? __('staff.deactivate') : __('staff.activate');

            $menu[] = ['separator' => true];
            $menu[] = [
                'label' => $label,
                'url' => StaffSection::route('status', $member),
                'method' => 'PATCH',
                'danger' => $member->is_active,
                'confirm' => $member->is_active
                    ? __('staff.deactivate_confirm', ['name' => $member->displayName()])
                    : null,
                'confirm_title' => $label,
                'confirm_label' => $label,
                'tone' => $member->is_active ? 'danger' : 'brand',
            ];
        }

        if ($request->user()->can('delete', $member)) {
            $menu[] = [
                'label' => __('common.delete'),
                'url' => StaffSection::route('destroy', $member),
                'method' => 'DELETE',
                'danger' => true,
                'confirm' => __('staff.delete_confirm', ['name' => $member->displayName()]),
                'confirm_title' => __('common.delete'),
                'confirm_label' => __('common.delete'),
                'tone' => 'danger',
            ];
        }

        return $menu;
    }

    /**
     * Available now, or not.
     *
     * Deliberately narrower than the form's three-way status: this is the one
     * switch a directory needs in a hurry, and someone going on leave is a
     * decision with a reason behind it that belongs on their record.
     */
    public function toggleStatus(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        /**
         * Three destinations, one address.
         *
         * The menu offers "Set on leave" as well as the plain toggle, because
         * away and gone are different facts about a rota — and both are the
         * same act to whoever is doing it: deciding whether this person is
         * available. `to` names the destination when there is one; without it
         * the switch simply flips.
         */
        $to = $request->input('to');

        $nowActive = $to === null ? ! $staff->is_active : $to === 'active';
        $membership = $to === 'on-leave' ? 'on-leave' : ($nowActive ? 'active' : 'inactive');

        $staff->forceFill([
            /* Somebody on leave is not available to be booked, so they are not
               active — membership_status keeps the reason. Coming back lands
               on active, not back on leave. */
            'is_active' => $membership === 'active',
            'membership_status' => $membership,
        ])->save();

        $nowActive = $membership === 'active';

        AuditLog::record(
            $nowActive ? 'staff.activated' : 'staff.deactivated',
            $request->user(), $staff, [], ['membership_status' => $membership], $staff->displayName(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => match ($membership) {
                'active' => __('staff.activated_person', ['name' => $staff->displayName()]),
                'on-leave' => __('staff.on_leave_person', ['name' => $staff->displayName()]),
                default => __('staff.deactivated_person', ['name' => $staff->displayName()]),
            },
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Staff::class);

        return view('settings.staff.create', $this->formData($request));
    }

    public function store(Request $request, CreateStaffMember $creator): RedirectResponse
    {
        $this->authorize('create', Staff::class);

        $tenant = $request->user()->tenant;
        $data = $this->validated($request);

        /**
         * §32: nobody hands out authority they do not hold.
         *
         * Checked here as well as in the form, because the form only decides
         * which options are drawn and this is a POST body.
         */
        $role = Role::query()->find($data['role_id']);

        if ($role === null || ! RoleGuard::canAssignRole($request->user(), $role)) {
            throw ValidationException::withMessages([
                'role_id' => __('staff.validation.role_not_yours'),
            ]);
        }

        /**
         * The async upload wins.
         *
         * With JavaScript the bytes have already been stored and the form
         * carries only the path; without it the file arrives here instead.
         * Checking the path first means a successful upload is never redone.
         */
        if ($request->filled('avatar_path')) {
            $data['avatar_path'] = $request->string('avatar_path')->toString();
        } elseif ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('staff', 'brand');
        }

        try {
            $staff = $creator->create($tenant, $request->user(), $data);
        } catch (Throwable $e) {
            Log::error('Staff member could not be created.', [
                'tenant_id' => $tenant->getTenantKey(),
                'user_id' => $request->user()->id,
                'exception' => $e->getMessage(),
            ]);

            return back()->withInput()->with('toast', [
                'type' => 'danger',
                'message' => __('staff.add_failed'),
            ]);
        }

        /**
         * Whole sentences per language, not a name with English glued to it.
         *
         * Spanish does not put the person's name or the address where English
         * does, and string addition cannot express that.
         */
        $message = $staff->invite_status === 'sent'
            ? __('staff.created_invited_to', ['name' => $staff->displayName(), 'email' => $staff->email])
            : __('staff.created', ['name' => $staff->displayName()]);

        /**
         * Back to the section the reader came from — §6.
         *
         * Someone who opened the form from Staff → Add Staff is running the
         * day and wants the directory; someone who opened it from App
         * Settings is configuring the business and wants Settings → Staff.
         * The record is the same either way; only where they are put down
         * afterwards differs.
         */
        if ($request->input('after_save') === 'add_another') {
            /* Straight back to an empty form, still in the same section. The
               name of the person just added is carried in the toast, because
               a blank form is otherwise indistinguishable from a save that
               did not happen. */
            return redirect()
                ->route(StaffSection::name('create'))
                ->with('toast', ['type' => 'success', 'message' => $message]);
        }

        return redirect()
            ->route(StaffSection::name('index'))
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function show(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);

        return view('settings.staff.show', [
            'staff' => $staff->load(['roleRecord', 'location', 'user', 'services', 'resources']),
            'invitation' => TeamInvitation::query()->where('staff_id', $staff->id)->latest('id')->first(),
            'history' => AuditLog::query()
                ->where('subject_type', Staff::class)
                ->where('subject_id', $staff->id)
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * The Schedule tab — the rule this person is on, and the shifts they
     * actually work.
     *
     * The rule is the template; the shifts are the dated reality. Changing one
     * of those shifts must never reach back and edit the pattern everybody
     * else is on, which is why they are separate records and separate screens.
     */
    public function schedule(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);

        [$filters, $from, $until] = $this->scheduleFilters($request);

        $tenant = $request->user()->tenant;
        $shiftRulesOn = (bool) $tenant?->shift_rules_enabled;

        $year = $filters['year'];
        $location = $staff->location ?? Location::query()->orderByDesc('is_primary')->orderBy('name')->first();

        $period = SchedulePeriod::for($staff, $from, $until);

        return view('settings.staff.schedule', [
            'staff' => $staff->load(['location', 'shiftRule']),
            'tab' => 'schedule',
            'filters' => $filters,
            'from' => $from,
            'until' => $until,
            'year' => $year,
            'years' => $this->scheduleYears($staff, $filters['year']),
            'months' => $this->monthlySummary($staff, $year),
            'location' => $location,
            /* One object for the whole range: the banner, the publish
               dialog's summary and the email all read their numbers from it,
               rather than each recomputing them and drifting apart. */
            'period' => $period,
            /* Grouped by day, so the view draws a week without asking the
               database once per date. */
            'shiftsByDate' => $period->byDate(),
            'shiftRulesOn' => $shiftRulesOn,
            'shiftRules' => $shiftRulesOn
                ? ShiftRule::availableAt($staff->location_id)
                : collect(),
        ]);
    }

    /**
     * The period the listing is showing.
     *
     * Three controls rather than one: a period length, and the month and year
     * it starts from. Kept in the query string so a schedule can be
     * bookmarked and the back button means what it says.
     *
     * @return array{0: array<string, mixed>, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function scheduleFilters(Request $request): array
    {
        $period = (string) $request->query('period', 'month');

        if (! array_key_exists($period, SchedulePeriod::PERIODS)) {
            $period = 'month';
        }

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        /* Clamped rather than refused: a hand-edited query string should show
           a sensible month, not an error page. */
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        [$from, $until] = SchedulePeriod::range($period, $year, $month);

        return [compact('period', 'year', 'month'), $from, $until];
    }

    /**
     * The listing grid's rows: every date in the period, worked or not.
     *
     * Days off are rows too. A list of only the working days hides the gaps,
     * and the gaps are half of what a rota is read for — and a grid that
     * skipped them could not be paged by date at all.
     */
    public function scheduleData(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('view', $staff);

        [$filters, $from, $until] = $this->scheduleFilters($request);

        $period = SchedulePeriod::for($staff, $from, $until);
        $byDate = $period->byDate();

        $size = min(200, max(1, (int) $request->query('size', 50)));
        $page = max(1, (int) $request->query('page', 1));

        /* Paged over the dates themselves rather than over the shifts: the
           row is a day, and a day with three shifts is still one row. */
        $dates = [];

        for ($day = $from; $day->lte($until); $day = $day->addDay()) {
            $dates[] = $day;
        }

        $total = count($dates);
        $canEdit = $request->user()->can('update', $staff);

        $rows = collect(array_slice($dates, ($page - 1) * $size, $size))
            ->map(fn (CarbonImmutable $day) => $this->scheduleRow($staff, $day, $byDate, $canEdit, $filters));

        return response()->json([
            'last_page' => (int) max(1, ceil($total / $size)),
            /* last_row is what the counter reads. Without it the grid works
               the total out as pages x page size and says "of 400" when there
               are 365 — a rounded-up number presented as a count. */
            'last_row' => $total,
            'total' => $total,
            'data' => $rows,
        ]);
    }

    /**
     * One date, as the grid reads it.
     *
     * @param  Collection<string, Collection<int, StaffShift>>  $byDate
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function scheduleRow(
        Staff $staff,
        CarbonImmutable $day,
        Collection $byDate,
        bool $canEdit,
        array $filters,
    ): array {
        $date = $day->toDateString();
        $shifts = ($byDate[$date] ?? collect())->reject(fn (StaffShift $shift) => $shift->isCancelled());
        $working = $shifts->isNotEmpty();

        $published = $working && $shifts->every(fn (StaffShift $shift) => $shift->isPublished());
        $publishedAt = $shifts->filter(fn (StaffShift $shift) => $shift->published_at !== null)
            ->max(fn (StaffShift $shift) => $shift->published_at->getTimestamp());

        $hours = round($shifts->sum(fn (StaffShift $shift) => $shift->workedMinutes()) / 60, 1);

        return [
            'id' => $date,
            'date' => $day->translatedFormat('j M Y'),
            /* Published days carry a padlock beside the date: the staff
               member has been emailed this one, so changing it is telling
               them something different rather than filling a blank in. */
            'date_locked' => $published,
            'locked_label' => __('schedule.locked'),
            'day' => $day->translatedFormat('D'),
            'name' => $day->translatedFormat('j M Y'),
            'working' => $working ? __('schedule.working') : __('schedule.not_working'),
            'working_class' => $working ? 'styledesk_badge--active' : 'styledesk_badge--soon',
            /* Each period on its own line: a split day is two blocks, and
               running them together reads as one long shift. */
            'time' => $working
                ? $shifts->map(fn (StaffShift $shift) => $shift->hoursLabel())->implode(' / ')
                : null,
            'status' => $working ? __('schedule.publish_statuses.'.($published ? 'published' : 'draft')) : null,
            'status_class' => $published ? 'styledesk_badge--active' : 'styledesk_badge--setup',
            'published_on' => $publishedAt === null
                ? null
                : CarbonImmutable::createFromTimestamp($publishedAt)->translatedFormat('j M Y, g:i A'),
            'published_by' => $shifts->first(fn (StaffShift $shift) => $shift->publisher !== null)?->publisher?->name,
            /* Nought hours rather than a dash: a day off is a known
               quantity, and an em dash in a numeric column reads as a figure
               nobody could work out. */
            'hours' => __('schedule.hours_short', ['count' => $working ? $hours : 0]),
            /* Zero until the booking module exists, and stated as a number
               for the same reason — a blank reads as "unknown", and this is
               known: nobody is booked with anybody yet. */
            'bookings' => '0',
            'menu' => $this->scheduleRowMenu($staff, $day, $working, $canEdit, $filters),
        ];
    }

    /**
     * What a date row offers.
     *
     * Built here rather than in the browser: which actions a row has and what
     * they warn about are permission decisions, and re-deciding them in
     * JavaScript would be a second place for a rule to live.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function scheduleRowMenu(
        Staff $staff,
        CarbonImmutable $day,
        bool $working,
        bool $canEdit,
        array $filters,
    ): array {
        if (! $canEdit) {
            return [];
        }

        $date = $day->toDateString();
        $back = StaffSection::route('schedule', $staff).'?'.http_build_query($filters);

        $menu = [[
            'label' => $working ? __('common.edit') : __('schedule.add_for_day'),
            /* One day at a time, on the same screen that builds a whole
               period: the rules, the split periods and the working toggle are
               already there, and a second editor would be a second set of
               them to keep in step. */
            'url' => StaffSection::route('schedule.assign.form', $staff)
                .'?days=1&from='.$date.'&return='.urlencode($back),
        ]];

        if ($working) {
            $menu[] = [
                'label' => __('schedule.delete_day'),
                'url' => StaffSection::route('schedule.day.destroy', [$staff, $date]),
                'method' => 'DELETE',
                'danger' => true,
                'tone' => 'danger',
                'confirm' => __('schedule.delete_day_confirm', [
                    'name' => $staff->displayName(),
                    'date' => $day->translatedFormat('j M Y'),
                ]),
                'confirm_title' => __('schedule.delete_day_title'),
                'confirm_label' => __('common.delete'),
            ];
        }

        $menu[] = ['separator' => true];
        $menu[] = ['label' => __('schedule.mark_on_leave'), 'disabled' => true];

        return $menu;
    }

    /**
     * Take one date off the rota.
     *
     * Deleted rather than cancelled, unlike a single shift: this is the
     * manager saying the day was never theirs to work, not that a shift they
     * were given has been called off. A cancelled shift is a fact about the
     * week worth keeping; a day removed from a draft schedule is not.
     */
    public function destroyScheduleDay(Request $request, Staff $staff, string $date): RedirectResponse
    {
        $this->authorize('update', $staff);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            abort(404);
        }

        StaffShift::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', $date)
            ->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('schedule.day_deleted', [
                'date' => CarbonImmutable::parse($date)->translatedFormat('j M Y'),
            ]),
        ]);
    }

    /**
     * Where the week starts.
     *
     * Today by default rather than the calendar's Monday: a rota is read
     * forwards, and the next thing anybody needs to know is who is in
     * tomorrow.
     */
    private function scheduleStart(Request $request): CarbonImmutable
    {
        $from = (string) $request->query('from');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1
            ? CarbonImmutable::parse($from)
            : CarbonImmutable::now()->startOfDay();
    }

    /**
     * The years worth offering in the filter.
     *
     * The ones this person has shifts in, plus last year, this year and the
     * next — a rota is planned forwards, and a filter that could not reach
     * next January would be useless every December.
     *
     * The year being read is added too, so a hand-edited query string is
     * still shown by the control rather than leaving it looking empty.
     *
     * @return array<int, int>
     */
    private function scheduleYears(Staff $staff, int $showing): array
    {
        return collect(StaffShift::query()->where('staff_id', $staff->id)
            ->selectRaw('distinct year(date) as y')->pluck('y'))
            ->map(fn ($year) => (int) $year)
            ->push(now()->year - 1, now()->year, now()->year + 1, $showing)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * A month at a time, for the summary cards.
     *
     * Hours and shifts, which the data supports. The booking counts the brief
     * also asks for need a booking module; a card reading "—" teaches the
     * reader to ignore the row, so the cards say what they know and the page
     * says once, underneath, what is still to come.
     *
     * @return array<int, array<string, mixed>>
     */
    private function monthlySummary(Staff $staff, int $year): array
    {
        $shifts = StaffShift::query()
            ->where('staff_id', $staff->id)
            ->whereYear('date', $year)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->groupBy(fn (StaffShift $shift) => (int) $shift->date->format('n'));

        return collect(range(1, 12))->map(function (int $month) use ($shifts, $year) {
            $inMonth = $shifts[$month] ?? collect();

            return [
                'month' => $month,
                'label' => CarbonImmutable::create($year, $month, 1)->translatedFormat('F'),
                'short' => CarbonImmutable::create($year, $month, 1)->translatedFormat('M'),
                'hours' => round($inMonth->sum(fn (StaffShift $shift) => $shift->workedMinutes()) / 60, 1),
                'shifts' => $inMonth->count(),
                'days' => $inMonth->pluck('date')->map->toDateString()->unique()->count(),
                'from' => CarbonImmutable::create($year, $month, 1)->toDateString(),
            ];
        })->all();
    }

    /**
     * The days the assign dialog opens on, already filled in.
     *
     * Filled from the business's own working hours, and divided by the rule's
     * shift periods where it has any — which is what makes the dialog a
     * confirmation rather than a form. Nothing is written until it is
     * submitted, and a day changed here changes only this schedule.
     *
     * The rule is passed in rather than read off the person: it is the rule
     * the manager has chosen on this screen that will judge what they submit,
     * and a proposal trimmed to a different one is a proposal the guard
     * refuses on arrival.
     *
     * @return array<int, array<string, mixed>>
     */
    private function assignableDays(
        Staff $staff,
        CarbonImmutable $from,
        CarbonImmutable $until,
        ?Location $location,
        ?ShiftRule $rule = null,
    ): array {
        $rule ??= $staff->shiftRule;
        $periods = $rule?->shiftPeriods->where('is_active', true)->values() ?? collect();
        $hours = $location?->hours->where('is_open', true) ?? collect();

        $days = [];

        for ($day = $from; $day->lte($until); $day = $day->addDay()) {
            $open = $hours->where('day_of_week', $day->dayOfWeek)->values();

            /* Where the rule divides the day into periods, those are the
               working blocks; otherwise the business's own hours are. A day
               the business is shut starts as a day off. */
            $blocks = $periods->isNotEmpty()
                ? $periods->map(fn ($period) => [
                    'shift_period_id' => $period->id,
                    'name' => $period->name,
                    'starts_at' => $period->timeValue('starts_at'),
                    'ends_at' => $period->timeValue('ends_at'),
                    'break_minutes' => $period->break_minutes,
                ])
                : $open->map(fn ($hour) => [
                    'shift_period_id' => null,
                    'name' => null,
                    'starts_at' => $hour->timeValue('opens_at'),
                    'ends_at' => $hour->timeValue('closes_at'),
                    'break_minutes' => null,
                ]);

            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('l, j M'),
                'working' => $open->isNotEmpty() && $blocks->isNotEmpty(),
                'periods' => $blocks->values()->all(),
            ];
        }

        return $this->withinWeeklyHours($days, $rule);
    }

    /**
     * Stop the proposal at the rule's own weekly ceiling.
     *
     * The blocks above come from the business's opening hours, which say
     * nothing about how long one person may work: a salon open six days at
     * eight hours proposes forty-eight, and a forty-hour rule then refuses the
     * whole range. That combination is ordinary rather than a misconfiguration
     * — the shop is open longer than any one person's week — and a dialog that
     * opens on a proposal it will not accept is a dead end, because the range
     * is written whole or not at all.
     *
     * So the later days of a week fall off once the ceiling is reached. They
     * keep their hours and can still be switched on; what changes is that the
     * schedule the manager is shown first is one they can actually save.
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array<int, array<string, mixed>>
     */
    private function withinWeeklyHours(array $days, ?ShiftRule $rule): array
    {
        $max = (int) ($rule?->max_hours_per_week ?? 0);

        if ($max <= 0 || $rule->allow_overtime) {
            return $days;
        }

        /* Counted per calendar week rather than across the range: a fortnight
           of thirty-five hours a week is not seventy hours in a week, and
           trimming it as though it were would empty the second week. */
        $spent = [];

        foreach ($days as $index => $day) {
            if (! $day['working']) {
                continue;
            }

            $week = CarbonImmutable::parse($day['date'])->startOfWeek()->toDateString();
            $minutes = collect($day['periods'])->sum(fn (array $period) => max(
                0,
                /* Start to end, in that order: Carbon 3 returns a signed
                   difference, and the other way round every day is negative
                   and the ceiling is never reached. */
                (int) CarbonImmutable::parse($period['starts_at'])->diffInMinutes(CarbonImmutable::parse($period['ends_at']))
                    - (int) ($period['break_minutes'] ?? 0)
            ));

            if (($spent[$week] ?? 0) + $minutes > $max * 60) {
                $days[$index]['working'] = false;

                continue;
            }

            $spent[$week] = ($spent[$week] ?? 0) + $minutes;
        }

        return $days;
    }

    /**
     * The dedicated Assign Schedule screen.
     *
     * Its own page rather than a dialog: a fortnight is twenty-eight time
     * fields, and a modal that tall is a page wearing a scrim — one that
     * cannot be linked to, cannot be returned to, and loses everything if it
     * is dismissed by accident.
     *
     * The range arrives in the URL, so the screen is bookmarkable and the back
     * button means what it says.
     */
    public function assignScheduleForm(Request $request, Staff $staff): View
    {
        $this->authorize('update', $staff);

        $from = $this->scheduleStart($request);

        /* A whole number of weeks, a single named day when the listing sends
           the manager here to fix one date, or an exact count of days when
           the dialog asks for a whole month. All three build the same screen;
           only the number of rows differs.

           Capped at a long month: the screen is a row per day, and the limit
           belongs to the screen rather than to the control that happened to
           name the range. */
        $days = min(31, max(0, (int) $request->query('days', 0)));
        $weeks = min(4, max(1, (int) $request->query('weeks', 1)));
        $until = $days > 0
            ? $from->copy()->addDays($days - 1)
            : $from->copy()->addWeeks($weeks)->subDay();

        $tenant = $request->user()->tenant;
        $shiftRulesOn = (bool) $tenant?->shift_rules_enabled;
        $location = $staff->location ?? Location::query()->orderByDesc('is_primary')->orderBy('name')->first();

        $period = SchedulePeriod::for($staff, $from, $until);

        /* A rule named in the address bar is one the manager picked here a
           moment ago — see the combo in AssignScheduleForm, which reloads
           the screen so the days below are rebuilt from it. Only this
           business's own rules are honoured. */
        $rule = $request->filled('shift_rule_id')
            ? ShiftRule::query()->with('shiftPeriods')->find($request->query('shift_rule_id'))
            : $staff->shiftRule;

        return view('settings.staff.assign-schedule', [
            'staff' => $staff->load(['location', 'shiftRule']),
            /* Where this was opened from, carried through the flow so that
               finishing puts the manager back on the period they were
               reading — not on a default week they then have to navigate to
               again. */
            'returnTo' => $this->scheduleReturnUrl($request->query('return'), $staff, $from->toDateString()),
            'weeks' => $weeks,
            'singleDay' => $days === 1,
            /* Whether the range is the whole number of weeks it was asked
               for. A period cut back to the end of the month is not, and
               calling two days "1 Week" on the screen that writes them would
               be the dialog's promise and the form's heading disagreeing. */
            'wholeWeeks' => $days === 0 || $days % 7 === 0,
            'from' => $from,
            'until' => $until,
            'period' => $period,
            'location' => $location,
            /* What is already on the rota wins over the pattern: opening this
               screen must not quietly redraft a week somebody has already
               been sent. The prefill only fills the days that have nothing. */
            'assignDays' => $this->withSubmittedDays($this->withExistingShifts(
                $this->assignableDays($staff, $from, $until, $location, $rule),
                $period,
            )),
            /* The rule the days above were built from, which is what the
               combo has to open on: chosen on this screen if it was, and
               otherwise the one the person is already on. */
            'chosenRule' => $rule,
            'shiftRulesOn' => $shiftRulesOn,
            'shiftRules' => $shiftRulesOn ? ShiftRule::availableAt($staff->location_id) : collect(),
        ]);
    }

    /**
     * The days this person is already working, in place of the proposal.
     *
     * A date with shifts on it is answered by those shifts — their times,
     * their breaks and whether they have been published — rather than by what
     * the rule would have suggested. A date with none keeps the proposal.
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array<int, array<string, mixed>>
     */
    private function withExistingShifts(array $days, SchedulePeriod $period): array
    {
        $byDate = $period->byDate();

        return array_map(function (array $day) use ($byDate) {
            $onDay = ($byDate[$day['date']] ?? collect())
                ->reject(fn (StaffShift $shift) => $shift->isCancelled());

            if ($onDay->isEmpty()) {
                return $day + ['status' => null];
            }

            return [
                'date' => $day['date'],
                'label' => $day['label'],
                'working' => true,
                'periods' => $onDay->map(fn (StaffShift $shift) => [
                    'shift_period_id' => $shift->shift_period_id,
                    'name' => null,
                    'starts_at' => $shift->timeValue('starts_at'),
                    'ends_at' => $shift->timeValue('ends_at'),
                    'break_minutes' => $shift->break_minutes ?: null,
                ])->values()->all(),
                /* Shown against the row, so a manager can see which days of
                   the period have already gone out before they change them. */
                'status' => $onDay->every(fn (StaffShift $shift) => $shift->isPublished())
                    ? 'published'
                    : 'draft',
            ];
        }, $days);
    }

    /**
     * The manager's own edits, back on the days the dialog reopens with.
     *
     * A refusal re-renders this page from scratch, so without this the dialog
     * would come back holding the defaults it was first built from — and
     * somebody who unticked Saturday to get under a weekly limit would watch
     * Saturday tick itself again, with the same complaint against it.
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array<int, array<string, mixed>>
     */
    private function withSubmittedDays(array $days): array
    {
        $submitted = old('days');

        if (! is_array($submitted)) {
            return $days;
        }

        return array_map(function (array $day) use ($submitted) {
            $row = $submitted[$day['date']] ?? null;

            if (! is_array($row)) {
                return $day;
            }

            $day['working'] = (bool) ($row['working'] ?? false);
            $day['periods'] = collect($row['periods'] ?? [])
                ->map(fn ($period, $index) => [
                    'shift_period_id' => $period['shift_period_id'] ?? null,
                    /* The rule's own name for the block, which the posted row
                       does not carry — taken back from the day it belongs to
                       so a named period stays named. */
                    'name' => $day['periods'][$index]['name'] ?? null,
                    'starts_at' => $period['starts_at'] ?? '',
                    'ends_at' => $period['ends_at'] ?? '',
                    'break_minutes' => ($period['break_minutes'] ?? '') === ''
                        ? null
                        : (int) $period['break_minutes'],
                ])
                ->values()
                ->all();

            return $day;
        }, $days);
    }

    /**
     * Write the dialog's answer onto the rota.
     *
     * Replaces the range rather than reconciling it: the dialog posts the
     * whole period every time, and working out which days moved would be more
     * code and more ways to be wrong than writing them again. Inside a
     * transaction, so a refusal halfway cannot leave a week with Monday
     * deleted and nothing to replace it.
     */
    public function assignSchedule(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'shift_rule_id' => [
                'nullable',
                Rule::exists('shift_rules', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'days' => ['array'],
            'days.*.working' => ['nullable', 'boolean'],
            'days.*.periods' => ['array'],
            'days.*.periods.*.starts_at' => ['nullable', 'date_format:H:i'],
            'days.*.periods.*.ends_at' => ['nullable', 'date_format:H:i'],
            'days.*.periods.*.break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'days.*.periods.*.shift_period_id' => ['nullable', 'integer'],
            /* Save and Save & Publish are the same write; the flag decides
               whether it also leaves the building. */
            'publish' => ['nullable', 'boolean'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $rule = isset($data['shift_rule_id']) && $data['shift_rule_id'] !== ''
            ? ShiftRule::query()->with('shiftPeriods')->find($data['shift_rule_id'])
            : null;

        $proposed = $this->proposedShifts($data);

        $replacing = StaffShift::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', '>=', $data['from'])
            ->whereDate('date', '<=', $data['until'])
            ->pluck('id')
            ->all();

        /* Whether this person has already been told about any of these days.
           Asked here because assigning replaces the range outright: once the
           old rows are deleted there is nothing left to ask. */
        $isRepublish = StaffShift::query()
            ->whereIn('id', $replacing)
            ->whereNotNull('published_at')
            ->exists();

        $location = $staff->location ?? Location::query()->orderByDesc('is_primary')->orderBy('name')->first();
        $guard = new ScheduleGuard($staff, $rule, $location);
        $problems = $guard->problems($proposed, $replacing);

        if ($problems !== []) {
            /* Reported against the day each belongs to, so the dialog can put
               a message beside the row rather than all of them at the top.

               Returned rather than thrown so the refusal can also raise a
               toast: the dialog reopens with the messages in it, but a manager
               watching the table underneath needs to be told that the reason
               nothing changed is that the schedule was refused, not that the
               page failed to update. withInput keeps their own edits, which a
               bare redirect would throw away along with the attempt. */
            return back()
                ->withInput()
                ->withErrors(collect($problems)->mapWithKeys(fn (array $messages, string $date) => [
                    'schedule.'.$date => $messages,
                ])->all())
                ->with('toast', [
                    'type' => 'error',
                    'message' => trans_choice('schedule.refused', count($problems), ['count' => count($problems)]),
                ]);
        }

        $publishing = (bool) ($data['publish'] ?? false);
        $publisher = $request->user()->id;

        DB::transaction(function () use ($staff, $proposed, $rule, $replacing, $publishing, $publisher) {
            StaffShift::query()->whereIn('id', $replacing)->delete();

            foreach ($proposed as $period) {
                StaffShift::create([
                    'tenant_id' => $staff->tenant_id,
                    'staff_id' => $staff->id,
                    'location_id' => $staff->location_id,
                    'shift_period_id' => $period['shift_period_id'] ?? null,
                    'date' => $period['date'],
                    'starts_at' => $period['starts_at'],
                    'ends_at' => $period['ends_at'],
                    'break_minutes' => (int) ($period['break_minutes'] ?? 0),
                    'type' => 'regular',
                    'status' => 'scheduled',
                    /* Assigned is not the same as told. Save leaves a draft
                       the manager can still change; only Save & Publish sends
                       it anywhere. */
                    'publish_status' => $publishing ? 'published' : 'draft',
                    'published_at' => $publishing ? now() : null,
                    'published_by' => $publishing ? $publisher : null,
                ]);
            }

            /* Assigning from a rule is also how somebody is put on one: this
               screen is where a manager decides which pattern the period
               follows. */
            if ($rule !== null && $staff->shift_rule_id !== $rule->id) {
                $staff->forceFill(['shift_rule_id' => $rule->id])->save();
            }
        });

        $back = $this->scheduleReturnUrl($data['return_to'] ?? null, $staff, $data['from']);

        if ($proposed->isEmpty()) {
            return redirect($back)->with('toast', [
                'type' => 'success',
                'message' => __('schedule.nothing_to_assign'),
            ]);
        }

        if (! $publishing) {
            return redirect($back)->with('toast', [
                'type' => 'success',
                'message' => __('schedule.draft_saved'),
            ]);
        }

        return redirect($back)->with('toast', [
            'type' => 'success',
            'message' => $this->publishedMessage(
                $this->notifyOfPublishedSchedule($request, $staff, $data['from'], $data['until'], $isRepublish),
                $staff,
                $isRepublish,
            ),
        ]);
    }

    /**
     * Where to put the manager once the schedule is saved.
     *
     * Their own page where they came from one — the same period, the same
     * year filter, the same everything — because a redirect to a default week
     * throws away a choice they made two screens ago and makes them make it
     * again.
     *
     * The candidate arrives in a query string and then in a form field, so it
     * is not trusted: only this staff member's own schedule path is honoured,
     * and only on this host. Anything else falls back to the period that was
     * just written, which is never wrong, only less specific.
     */
    private function scheduleReturnUrl(?string $candidate, Staff $staff, string $from): string
    {
        /* The listing reads months, not weeks, so the fallback names the
           month the period starts in — the filters the page actually has,
           rather than a range it would ignore. */
        $start = CarbonImmutable::parse($from);

        $fallback = StaffSection::route('schedule', $staff).'?'.http_build_query([
            'period' => 'month',
            'year' => $start->year,
            'month' => $start->month,
        ]);

        if (! is_string($candidate) || trim($candidate) === '') {
            return $fallback;
        }

        $parts = parse_url($candidate);

        if ($parts === false || ! isset($parts['path'])) {
            return $fallback;
        }

        /* An absolute URL is honoured only when it is this application's. A
           redirect target taken from the request is an open redirect the
           moment it is allowed to name its own host. */
        if (isset($parts['host']) && $parts['host'] !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return $fallback;
        }

        $allowed = [
            parse_url(route('staff.schedule', $staff), PHP_URL_PATH),
            parse_url(route('settings.staff.schedule', $staff), PHP_URL_PATH),
            /* The team board sends managers into this flow a cell at a time,
               and finishing has to put them back on the month they clicked
               rather than on the one person they happened to fix. */
            parse_url(route('staff.schedules'), PHP_URL_PATH),
        ];

        if (! in_array($parts['path'], $allowed, true)) {
            return $fallback;
        }

        /* Rebuilt from the parts rather than passed through, so no fragment,
           no credentials and no host survive into the redirect. */
        return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Keep the period as a draft.
     *
     * Assigning already wrote the shifts, so this does not create anything —
     * it is the manager saying "not yet" out loud, and the page needs an
     * action that means that. What it does write is the guarantee behind the
     * word: every unpublished shift in the range is left explicitly draft.
     *
     * Published shifts are not touched. Reverting them would un-tell somebody
     * about a week they have already been emailed, which is not what a button
     * named Save Draft should do — an edit to a published shift is what moves
     * it back to draft, and that happens where the edit does.
     */
    public function saveScheduleDraft(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $period = $this->schedulePeriod($request, $staff);

        if (! $period->hasShifts()) {
            return back()->with('toast', ['type' => 'error', 'message' => __('schedule.nothing_to_save')]);
        }

        StaffShift::query()
            ->where('staff_id', $staff->id)
            ->inRange($period->from->toDateString(), $period->until->toDateString())
            ->where('publish_status', '!=', 'published')
            ->update(['publish_status' => 'draft']);

        return back()->with('toast', ['type' => 'success', 'message' => __('schedule.draft_saved')]);
    }

    /**
     * Tell the staff member about this period.
     *
     * The whole range in one email, however many weeks it covers. Somebody
     * sent four emails for a four-week rota has to reassemble it themselves,
     * and will read the last as a correction to the first.
     *
     * The shifts are marked published before the mail is queued, and on
     * purpose: a schedule visible in the app that never generated an email is
     * a smaller problem than an email describing a schedule the database
     * never committed.
     */
    public function publishSchedule(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $period = $this->schedulePeriod($request, $staff);

        if (! $period->hasShifts()) {
            return back()->with('toast', ['type' => 'error', 'message' => __('schedule.nothing_to_publish')]);
        }

        /* Asked before the write, which is the last moment it can be
           answered: stamping published_at on every shift is exactly what
           erases the difference between a first publication and a second. */
        $isRepublish = $period->publishedAt() !== null;

        StaffShift::query()
            ->where('staff_id', $staff->id)
            ->inRange($period->from->toDateString(), $period->until->toDateString())
            ->where('status', '!=', 'cancelled')
            ->update([
                'publish_status' => 'published',
                'published_at' => now(),
                'published_by' => $request->user()->id,
            ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $this->publishedMessage(
                $this->notifyOfPublishedSchedule(
                    $request, $staff, $period->from->toDateString(), $period->until->toDateString(), $isRepublish,
                ),
                $staff,
                $isRepublish,
            ),
        ]);
    }

    /** The staff member was told. */
    private const MAIL_SENT = 'sent';

    /** Nowhere to send it — something a manager can go and fix. */
    private const MAIL_NO_ADDRESS = 'no-address';

    /** There was an address and the send failed; the schedule stands. */
    private const MAIL_FAILED = 'failed';

    /**
     * Queue the published-schedule email, if there is anywhere to send it.
     *
     * Answers whether it went, rather than deciding what to say about it: the
     * two callers publish for different reasons and word the result their own
     * way. No address is not a refusal — the schedule is real either way, and
     * a missing address is something a manager can go and fix.
     *
     * Read back from the database rather than from what was just posted, so
     * the email quotes the period that was actually committed.
     */
    private function notifyOfPublishedSchedule(
        Request $request,
        Staff $staff,
        string $from,
        string $until,
        bool $isRepublish = false,
    ): string {
        $email = $staff->work_email ?: $staff->email;

        if ($email === null || $email === '') {
            return self::MAIL_NO_ADDRESS;
        }

        try {
            /* Delivered in this request unless a queue worker is configured
               — see config/shifts.php. Publishing that says "they have been
               emailed" has to mean it.

               Which is also why this is wrapped: sending in the request means
               a mail provider that is down would otherwise take the whole
               publication with it, and the schedule is already written. The
               shifts are published either way; only the sentence changes. */
            SendSchedulePublishedEmail::dispatch($email, SchedulePublishedMail::forPeriod(
                SchedulePeriod::for(
                    $staff->loadMissing('location'),
                    CarbonImmutable::parse($from),
                    CarbonImmutable::parse($until),
                ),
                $request->user()->tenant?->name ?? config('app.name'),
                $isRepublish,
            ))->onConnection(config('shifts.mail_connection'));
        } catch (Throwable $e) {
            /* The provider's message goes to the log and nowhere else: it can
               name hosts and credentials, none of which belong in front of a
               manager. */
            Log::error('Published schedule email could not be sent.', [
                'staff_id' => $staff->id,
                'tenant_id' => $staff->tenant_id,
                'from' => $from,
                'until' => $until,
                'exception' => $e->getMessage(),
            ]);

            return self::MAIL_FAILED;
        }

        return self::MAIL_SENT;
    }

    /**
     * What to tell the manager about the email.
     *
     * Said here rather than at each call site, because both of them publish
     * for different reasons and neither should be the place the wording of a
     * failed send is decided.
     */
    private function publishedMessage(string $outcome, Staff $staff, bool $isRepublish = false): string
    {
        return match ($outcome) {
            self::MAIL_SENT => __(
                $isRepublish ? 'schedule.republished' : 'schedule.published',
                ['name' => $staff->displayName()],
            ),
            self::MAIL_FAILED => __('schedule.published_email_failed', ['name' => $staff->displayName()]),
            default => __('schedule.published_without_email', ['name' => $staff->displayName()]),
        };
    }

    /**
     * The period a Save Draft or Publish applies to.
     *
     * Taken from the posted range rather than from whatever the page happened
     * to be showing: the buttons sit above and below a table that can be four
     * weeks long, and both must mean the same period as the heading.
     */
    private function schedulePeriod(Request $request, Staff $staff): SchedulePeriod
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return SchedulePeriod::for(
            $staff->loadMissing('location'),
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['until']),
        );
    }

    /**
     * The dialog's answer as a flat list of shifts.
     *
     * A day switched off contributes nothing, whatever times its inputs still
     * hold — somebody marking Sunday off should not have to clear the times to
     * make it stick.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array<string, mixed>>
     */
    private function proposedShifts(array $data): Collection
    {
        return collect($data['days'] ?? [])
            ->flatMap(function (array $day, string $date) {
                if (! ($day['working'] ?? false)) {
                    return [];
                }

                return collect($day['periods'] ?? [])
                    ->filter(fn (array $period) => filled($period['starts_at'] ?? null)
                        && filled($period['ends_at'] ?? null))
                    ->map(fn (array $period) => [
                        'date' => $date,
                        'starts_at' => $period['starts_at'],
                        'ends_at' => $period['ends_at'],
                        'break_minutes' => (int) ($period['break_minutes'] ?? 0),
                        /* The dialog sends it; a request typed by hand may
                           not, and a missing key is not a fatal error. */
                        'shift_period_id' => ($period['shift_period_id'] ?? null) ?: null,
                    ])
                    ->values();
            })
            ->values();
    }

    /** The Services tab — what this person may be booked for. */
    public function services(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);

        $staff->load(['services.category', 'services.prices']);

        return view('settings.staff.services', [
            'staff' => $staff,
            'tab' => 'services',
            /* Only what they are not already on: a picker offering services
               somebody already performs is a list to read past. */
            'available' => Service::query()
                ->with('category')
                ->whereNotIn('id', $staff->services->pluck('id'))
                ->orderBy('name')
                ->get(),
            'currency' => Currencies::primaryFor($request->user()->tenant),
        ]);
    }

    /** The Notes tab — what the business has written down about them. */
    public function notes(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);

        return view('settings.staff.notes', [
            'staff' => $staff,
            'tab' => 'notes',
            'notes' => StaffNote::query()->where('staff_id', $staff->id)
                ->with('author')->newestFirst()->get(),
        ]);
    }

    /**
     * Add services this person may perform.
     *
     * syncWithoutDetaching, not sync: this form posts what is being added,
     * not the whole list, and sync would take away everything it did not
     * mention.
     */
    public function attachServices(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate([
            'service_ids' => ['required', 'array'],
            'service_ids.*' => [
                Rule::exists('services', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ]);

        $staff->services()->syncWithoutDetaching($data['service_ids']);

        return back()->with('toast', [
            'type' => 'success',
            'message' => trans_choice('staff.services_added', count($data['service_ids']), [
                'count' => count($data['service_ids']),
            ]),
        ]);
    }

    /**
     * Take a service off this person.
     *
     * The service itself is untouched — this only removes their ability to be
     * booked for it.
     */
    public function detachService(Request $request, Staff $staff, Service $service): RedirectResponse
    {
        $this->authorize('update', $staff);

        $staff->services()->detach($service->id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('staff.service_removed', ['name' => $service->name]),
        ]);
    }

    /** Put this person on a working pattern, or take them off one. */
    public function assignShiftRule(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate([
            'shift_rule_id' => [
                'nullable',
                Rule::exists('shift_rules', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ]);

        $this->guardShiftRuleIsAvailable($data, $staff);

        $staff->forceFill(['shift_rule_id' => $data['shift_rule_id'] ?: null])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $staff->shift_rule_id
                ? __('staff.shift_rule_assigned', ['name' => $staff->shiftRule->name])
                : __('staff.shift_rule_cleared'),
        ]);
    }

    public function storeNote(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate(
            ['body' => ['required', 'string', 'max:5000']],
            ['body.required' => __('staff.notes.body_required')],
        );

        StaffNote::create([
            'tenant_id' => $request->user()->tenant->getTenantKey(),
            'staff_id' => $staff->id,
            'created_by' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('staff.notes.added')]);
    }

    /**
     * Remove a note.
     *
     * Your own, or anybody's if you may delete the staff member — the same
     * authority that can remove the person can tidy what was written about
     * them.
     */
    public function destroyNote(Request $request, Staff $staff, StaffNote $note): RedirectResponse
    {
        $this->authorize('update', $staff);

        abort_unless(
            $note->staff_id === $staff->id
                && ($note->wasWrittenBy($request->user()) || $request->user()->can('delete', $staff)),
            403,
        );

        $note->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('staff.notes.deleted')]);
    }

    public function edit(Request $request, Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('settings.staff.edit', [
            ...$this->formData($request, $staff),
            'staff' => $staff->load(['roleRecord', 'services', 'resources', 'shiftRule']),
        ]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $this->validated($request, $staff);

        // The same capitalisation rule the create path applies. Without it an
        // edited name follows a different rule from a created one, and which
        // you get depends on which screen last touched the record.
        $data = InputCase::apply($data, [
            'first_name', 'middle_name', 'last_name', 'preferred_name',
            'job_title', 'bio', 'emergency_contact_name', 'emergency_contact_relationship',
        ]);

        $role = Role::query()->find($data['role_id']);

        if ($role === null || ! RoleGuard::canAssignRole($request->user(), $role)) {
            throw ValidationException::withMessages(['role_id' => __('staff.validation.role_not_yours')]);
        }

        /**
         * Changing your own role is refused outright, per §32.
         *
         * Otherwise the narrowest path to more authority is to open your own
         * record and pick a bigger role — the one edit nobody should be able
         * to make regardless of what they are otherwise allowed to do.
         */
        if ($staff->user_id === $request->user()->id && $staff->role_id !== $role->id) {
            throw ValidationException::withMessages([
                'role_id' => __('staff.validation.own_role'),
            ]);
        }

        if ($request->filled('avatar_path')) {
            $data['avatar_path'] = $request->string('avatar_path')->toString();
        } elseif ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('staff', 'brand');
        }

        $before = $staff->only(['first_name', 'last_name', 'role', 'location_id', 'is_active']);

        try {
            DB::transaction(function () use ($staff, $data, $role, $request) {
                /**
                 * A whitelist, not an exclusion list.
                 *
                 * The validated set carries fields that are not columns —
                 * send_invitation, invitation_message — and excluding the ones
                 * that happen to be known today means the next field added to
                 * the form becomes a fatal "unknown column" the first time
                 * somebody saves.
                 */
                $staff->fill([
                    ...collect($data)->only([
                        'first_name', 'middle_name', 'last_name', 'preferred_name', 'pronouns',
                        'job_title', 'employee_ref', 'bio', 'avatar_path',
                        'email', 'work_email', 'phone', 'phone_type', 'secondary_phone', 'address',
                        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
                        'location_id', 'employment_type', 'provider_type', 'specialities',
                        'date_of_birth', 'started_on', 'shift_rule_id', 'login_enabled',
                    ])->all(),
                    'role' => $role->key,
                    'role_id' => $role->id,
                    'is_active' => ($data['account_status'] ?? 'active') === 'active',
                    'membership_status' => $data['account_status'] ?? 'active',
                ]);

                // Read before save(): afterwards the model considers itself
                // clean and getDirty() is empty, so the history would record
                // that something changed without saying what.
                $changed = $staff->getDirty();
                $previous = collect($staff->getOriginal())->only(array_keys($changed))->all();

                $staff->save();
                $staff->services()->sync($data['service_ids'] ?? []);
                $staff->resources()->sync($data['resource_ids'] ?? []);

                AuditLog::record('staff.edited', $request->user(), $staff,
                    $previous, $changed, $staff->displayName());
            });
        } catch (Throwable $e) {
            Log::error('Staff member could not be updated.', [
                'staff_id' => $staff->id,
                'exception' => $e->getMessage(),
            ]);

            return back()->withInput()->with('toast', [
                'type' => 'danger',
                'message' => __('staff.save_failed'),
            ]);
        }

        if ($before['role'] !== $staff->role) {
            AuditLog::record('staff.role_changed', $request->user(), $staff,
                ['role' => $before['role']], ['role' => $staff->role], $staff->displayName());
        }

        return redirect()
            ->route(StaffSection::name('show'), $staff)
            ->with('toast', ['type' => 'success', 'message' => __('staff.saved_person', ['name' => $staff->displayName()])]);
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $name = $staff->displayName();

        DB::transaction(function () use ($staff, $request, $name) {
            /**
             * The record is removed; the person's account is not.
             *
             * A user may belong to another business, and their login is not
             * this business's to delete. Clearing tenant_id is what actually
             * removes their access here.
             */
            $staff->user?->forceFill(['tenant_id' => null])->save();

            AuditLog::record('staff.deleted', $request->user(), null,
                ['name' => $name, 'role' => $staff->role], [], $name);

            $staff->delete();
        });

        return redirect()
            ->route(StaffSection::name('index'))
            ->with('toast', ['type' => 'success', 'message' => __('staff.deleted', ['name' => $name])]);
    }

    /**
     * Receive a profile image on its own, so the form can show real progress.
     *
     * Returns the stored path rather than keeping it in the session: the
     * create form may be abandoned, and a session holding a file nobody will
     * ever reference is a leak that only shows up as disk usage.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $this->authorize('create', Staff::class);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ], [
            'image.max' => __('staff.validation.avatar_max'),
            'image.mimes' => __('staff.validation.avatar_mimes'),
        ]);

        $path = $request->file('image')->store('staff', 'brand');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('brand')->url($path),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Staff $staff = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'pronouns' => ['nullable', Rule::in(array_keys(config('staff.pronouns')))],
            'job_title' => ['nullable', 'string', 'max:100'],
            'employee_ref' => ['nullable', 'string', 'max:40'],

            /* A birthday is in the past and a first day may be in the future
               — someone hired to start next month is still added today. */
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'started_on' => ['nullable', 'date'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            /**
             * A path this application wrote, not an arbitrary string.
             *
             * It comes back from the browser, so without the shape check a
             * crafted value could point the avatar at any file on the disk.
             */
            'avatar_path' => ['nullable', 'string', 'max:255', 'regex:/^staff\\/[A-Za-z0-9._-]+$/'],

            /**
             * Unique within the business, not globally.
             *
             * The same person can work for two salons on the platform, so a
             * global unique would stop the second one adding them at all.
             */
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('staff', 'email')
                    ->where('tenant_id', $request->user()->tenant_id)
                    // Editing someone must not collide with themselves.
                    ->ignore($staff?->id),
            ],
            'work_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_type' => ['nullable', Rule::in(array_keys(config('staff.phone_types')))],
            'secondary_phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],

            'role_id' => ['required', Rule::exists('roles', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'employment_type' => ['nullable', Rule::in(array_keys(config('staff.employment_types')))],
            'provider_type' => ['nullable', Rule::in(array_keys(config('staff.provider_types')))],
            'specialities' => ['nullable', 'array'],
            'specialities.*' => [Rule::in(array_keys(config('staff.specialities')))],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [
                Rule::exists('services', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],

            /* The chairs or rooms this person works at. Tenant-scoped for the
               same reason the services are: the form only decides what is
               drawn, and this is a POST body. */
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => [
                Rule::exists('resources', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],

            /**
             * The working pattern this person is put on.
             *
             * Optional — plenty of businesses run a rota without one — but a
             * rule that is named has to be one this person could actually be
             * put on: active, and offered at their branch. Checked here as
             * well as filtered in the form, because the form only decides
             * which options are drawn and this is a POST body.
             */
            'shift_rule_id' => [
                'nullable',
                Rule::exists('shift_rules', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],

            'account_status' => ['required', Rule::in(['active', 'inactive', 'on-leave'])],
            'login_enabled' => ['nullable', 'boolean'],
            'send_invitation' => ['nullable', 'boolean'],
            'invitation_message' => ['nullable', 'string', 'max:500'],
        ], [
            'first_name.required' => __('staff.validation.first_name_required'),
            'last_name.required' => __('staff.validation.last_name_required'),
            'email.required' => __('staff.validation.email_required'),
            'email.email' => __('staff.validation.email_invalid'),
            'email.unique' => __('staff.validation.email_taken'),
            'work_email.email' => __('staff.validation.email_invalid'),
            'role_id.required' => __('staff.validation.role_required'),
            'avatar.max' => __('staff.validation.avatar_max'),
        ]);

        $data['login_enabled'] = $request->boolean('login_enabled');
        $data['send_invitation'] = $request->boolean('send_invitation');

        $this->guardShiftRuleIsAvailable($data, $staff);

        return $data;
    }

    /**
     * A named rule has to be one this person could be put on.
     *
     * Left alone when it is the rule they already had: a rule that has since
     * been switched off, or whose branches have changed, stays on the people
     * already on it — it is new assignments it is kept out of. Refusing the
     * save would mean nobody could edit that person's phone number until
     * somebody sorted out the rule.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardShiftRuleIsAvailable(array $data, ?Staff $staff): void
    {
        $chosen = $data['shift_rule_id'] ?? null;

        if ($chosen === null || (string) $chosen === (string) $staff?->shift_rule_id) {
            return;
        }

        $rule = ShiftRule::query()->with('locations')->find($chosen);
        $locationId = isset($data['location_id']) && $data['location_id'] !== ''
            ? (int) $data['location_id']
            : null;

        if ($rule === null || ! $rule->isAvailableAt($locationId)) {
            throw ValidationException::withMessages([
                'shift_rule_id' => __('staff.validation.shift_rule_unavailable'),
            ]);
        }
    }

    /**
     * Options both the create form and, later, the edit form need.
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?Staff $staff = null): array
    {
        /**
         * Shift rules, but only where the business uses them.
         *
         * Two conditions, and the card is drawn only when both hold: the
         * feature is on, and there is at least one active rule to choose. A
         * card offering an empty dropdown is a question with no answers.
         */
        $shiftRulesOn = (bool) $request->user()->tenant?->shift_rules_enabled;

        $shiftRules = $shiftRulesOn
            ? ShiftRule::availableAt($this->chosenLocationId($request, $staff))
            : collect();

        return [
            'shiftRulesOn' => $shiftRulesOn,
            'shiftRules' => $shiftRules,
            /* Every active rule and the branches each is restricted to, so the
               form can narrow the list when the location changes without
               asking the server again. */
            'shiftRuleLocations' => $shiftRulesOn
                ? ShiftRule::query()->active()->with('locations')->get()
                    ->mapWithKeys(fn (ShiftRule $rule) => [
                        $rule->id => $rule->location_scope === 'specific'
                            ? $rule->locations->pluck('id')->all()
                            : [],
                    ])
                : collect(),
            // Only roles this user is allowed to hand out are offered, so the
            // form cannot draw a choice the server will refuse.
            'roles' => Role::query()->orderBy('display_order')->get()
                ->filter(fn (Role $role) => RoleGuard::canAssignRole($request->user(), $role))
                ->values(),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            /* Retired chairs are left out: nobody is assigned to a room that
               is no longer in use. One already assigned stays — the form adds
               it back to the list. */
            'resources' => Resource::query()->active()->inOrder()->get(['id', 'name']),
        ];
    }

    /**
     * The branch the form is currently about.
     *
     * Old input first, because a refused submission is re-rendered with what
     * was typed and the rule list has to match the location that was chosen
     * rather than the one on the record.
     */
    private function chosenLocationId(Request $request, ?Staff $staff): ?int
    {
        $chosen = old('location_id', $request->input('location_id', $staff?->location_id));

        return $chosen === null || $chosen === '' ? null : (int) $chosen;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')) ?: null,
            'role' => $request->query('role') ?: null,
            'location' => $request->query('location') ?: null,
            'service' => $request->query('service') ?: null,
            'provider_type' => $request->query('provider_type') ?: null,
            'employment_type' => $request->query('employment_type') ?: null,
            'status' => array_key_exists((string) $request->query('status'), config('staff.statuses'))
                ? $request->query('status')
                : null,
            'sort' => array_key_exists((string) $request->query('sort'), config('staff.sorts'))
                ? $request->query('sort')
                : 'name',
        ];
    }

    /**
     * The directory the page and the grid both read.
     *
     * One method rather than the same chain in two places: the screen shows a
     * count of what matched and the grid shows the rows that matched, and two
     * copies of the query is the arrangement where those two eventually
     * describe different lists.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Staff>
     */
    private function directory(array $filters): Collection
    {
        $staff = Staff::query()
            ->with(['roleRecord', 'location', 'user'])
            ->withCount('services')
            ->tap(fn (Builder $q) => $this->applySearch($q, $filters['search']))
            ->tap(fn (Builder $q) => $this->applyFilters($q, $filters))
            ->tap(fn (Builder $q) => $this->applySort($q, $filters['sort']))
            ->get();

        /**
         * Status is derived, so it cannot be filtered in SQL without
         * duplicating the rule in two places. The directory is a page of
         * staff, not a report over millions of rows, so it is filtered in
         * memory where the single definition lives.
         */
        if ($filters['status'] !== null) {
            $staff = $staff->filter(fn (Staff $member) => $member->status() === $filters['status'])->values();
        }

        return $staff;
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        // §3: name, email, phone or job title. Grouped so the search does not
        // swallow the filters applied alongside it — an ungrouped chain of
        // orWhere turns every other condition into a suggestion.
        $query->where(function (Builder $q) use ($search) {
            $like = '%'.$search.'%';

            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('preferred_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('work_email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('job_title', 'like', $like);
        });
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['role'], fn (Builder $q, $role) => $q->whereHas('roleRecord', fn (Builder $r) => $r->where('key', $role)))
            ->when($filters['location'], fn (Builder $q, $location) => $q->where('location_id', $location))
            ->when($filters['service'], fn (Builder $q, $service) => $q->whereHas('services', fn (Builder $s) => $s->where('services.id', $service)))
            ->when($filters['provider_type'], fn (Builder $q, $type) => $q->where('provider_type', $type))
            ->when($filters['employment_type'], fn (Builder $q, $type) => $q->where('employment_type', $type));
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'recent' => $query->orderByDesc('created_at'),
            // Ordering by the joined role would need a join; ordering by the
            // id keeps roles of the same kind together, which is what the
            // sort is for.
            'role' => $query->orderBy('role_id')->orderBy('first_name'),
            'location' => $query->orderBy('location_id')->orderBy('first_name'),
            'status' => $query->orderBy('is_active')->orderBy('first_name'),
            default => $query->orderBy('first_name')->orderBy('last_name'),
        };
    }
}
