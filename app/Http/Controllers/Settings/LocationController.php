<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationHour;
use App\Models\Staff;
use App\Support\EmailAddress;
use App\Support\InputCase;
use App\Support\LocationOptions;
use App\Support\ReturnTo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use RuntimeException;
use Throwable;

/**
 * The Locations settings module: branches and how each one operates.
 *
 * A branch's address, contact details, manager and opening hours live here.
 * Which services it offers, which staff work there and its booking rules are
 * separate modules — summarised on the view screen with a link out, so two
 * pages never write the same field.
 *
 * Access is Owner/Administrator through the `can-manage-settings` middleware
 * on the route group, then narrowed per action by LocationPolicy: the group
 * decides who reaches the module, the policy decides what they may do to a
 * particular branch.
 */
class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Location::class);

        $tenant = $request->user()->tenant;

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $locations = $tenant->locations()
            ->with(['manager', 'hours'])
            ->withCount('staff')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                foreach (['name', 'code', 'city', 'address_line1', 'email', 'phone'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            }))
            ->when(in_array($status, [Location::STATUS_ACTIVE, Location::STATUS_INACTIVE], true),
                fn ($query) => $query->where('status', $status))
            ->inDisplayOrder()
            ->get();

        return view('settings.locations.index', [
            'locations' => $locations,
            'filters' => ['search' => $search, 'status' => $status],
            'activeCount' => $tenant->locations()->active()->count(),
            'totalCount' => $tenant->locations()->count(),
        ]);
    }

    public function show(Request $request, Location $location): View
    {
        $this->authorize('view', $location);

        $location->load(['manager', 'assistantManagers', 'hours', 'staff']);

        return view('settings.locations.show', [
            'location' => $location,
            'hoursByDay' => $this->hoursByDay($location),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Location::class);

        return view('settings.locations.create', [
            'location' => null,
            ...$this->formData($request, null),
            ...$this->returnTo($request, route('settings.locations.index')),
        ]);
    }

    public function edit(Request $request, Location $location): View
    {
        $this->authorize('update', $location);

        $location->load(['assistantManagers', 'hours']);

        return view('settings.locations.edit', [
            'location' => $location,
            ...$this->formData($request, $location),
            ...$this->returnTo($request, route('settings.locations.show', $location)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Location::class);

        $tenant = $request->user()->tenant;
        $data = $this->validated($request, null);

        try {
            $location = DB::transaction(function () use ($tenant, $data) {
                $location = $tenant->locations()->create($this->columns($data));

                $this->syncPrimary($location, (bool) ($data['is_primary'] ?? false));
                $this->syncAssistants($location, $data['assistant_manager_ids'] ?? []);
                $this->syncHours($location, $data['hours'] ?? []);

                return $location;
            });
        } catch (Throwable $e) {
            return $this->saveFailed($request, $e, null);
        }

        return redirect()
            ->to(ReturnTo::resolve($request, route('settings.locations.show', $location)))
            ->with('toast', ['type' => 'success', 'message' => __('locations.created')]);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        $data = $this->validated($request, $location);

        try {
            DB::transaction(function () use ($location, $data) {
                $location->fill($this->columns($data));

                if ($location->save() !== true) {
                    // save() returning false means the write did not happen.
                    // Success must never be reported on the strength of having
                    // reached this line.
                    throw new RuntimeException('The location record reported an unsuccessful save.');
                }

                $this->syncPrimary($location, (bool) ($data['is_primary'] ?? false));
                $this->syncAssistants($location, $data['assistant_manager_ids'] ?? []);
                $this->syncHours($location, $data['hours'] ?? []);
            });
        } catch (Throwable $e) {
            return $this->saveFailed($request, $e, $location);
        }

        return redirect()
            ->to(ReturnTo::resolve($request, route('settings.locations.show', $location)))
            ->with('toast', ['type' => 'success', 'message' => __('locations.saved')]);
    }

    /**
     * Take a branch out of service, or bring it back.
     *
     * One action rather than two endpoints, because it is one decision with
     * two directions and the policy already knows which is available. Not
     * folded into update(): retiring a branch is an act somebody chose from a
     * menu, and it should not be reachable by posting the edit form with one
     * field changed.
     */
    public function setStatus(Request $request, Location $location): RedirectResponse
    {
        $activating = $request->input('status') === Location::STATUS_ACTIVE;

        $this->authorize($activating ? 'activate' : 'deactivate', $location);

        $location->forceFill([
            'status' => $activating ? Location::STATUS_ACTIVE : Location::STATUS_INACTIVE,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $activating
                ? __('locations.made_active', ['name' => $location->name])
                : __('locations.made_inactive', ['name' => $location->name]),
        ]);
    }

    // ------------------------------------------------------------ helpers

    /**
     * Is this code already on another of this business's locations?
     *
     * Asked while the reader types, so the answer arrives beside the field
     * instead of after a submission they have to redo. The same question the
     * unique rule asks on the way in — one of them without the other is how a
     * form ends up accepting what the server refuses.
     */
    public function codeInUse(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('value'));

        if ($code === '') {
            return response()->json(['ok' => true]);
        }

        $taken = Location::withoutGlobalScopes()
            ->where('tenant_id', $request->user()->tenant?->getTenantKey())
            /* The location being edited is not a duplicate of itself. */
            ->when($request->query('ignore'), fn ($query, $id) => $query->whereKeyNot($id))
            ->where('code', $code)
            ->exists();

        return response()->json($taken
            ? ['ok' => false, 'message' => __('locations.validation.code_unique')]
            : ['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Location $location): array
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            /**
             * Unique within the business, not globally.
             *
             * The code is what tells two branches apart on a receipt or in a
             * report, so two branches sharing one defeats the field. Another
             * business using the same code is none of our concern.
             */
            'code' => [
                'nullable', 'string', 'max:20',
                Rule::unique('locations', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($location?->id),
            ],
            'type' => ['nullable', Rule::in(array_keys(config('locations.types')))],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(array_keys(config('locations.statuses')))],

            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'suite' => ['nullable', 'string', 'max:60'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', Rule::in(array_keys(config('locations.countries')))],
            'timezone' => ['required', 'timezone'],

            /**
             * Managers are scoped to this tenant's own staff.
             *
             * `exists:staff,id` alone would accept another business's staff id
             * and put a stranger's name on the branch — and, worse, expose
             * that name on a screen that reads it back.
             */
            'manager_staff_id' => ['nullable', $this->staffRule($tenantId)],
            'assistant_manager_ids' => ['nullable', 'array'],
            'assistant_manager_ids.*' => [$this->staffRule($tenantId)],

            'phone' => ['required', 'string', 'max:32'],
            /* The dialling code, chosen beside the number. The column existed
               and the form never posted it, so every number saved here was a
               number with no country against it. */
            'phone_country' => ['nullable', 'string', 'size:2'],
            'phone_secondary' => ['nullable', 'string', 'max:32'],
            'phone_secondary_country' => ['nullable', 'string', 'size:2'],
            /* Held to what the browser holds them to. Laravel's `email` rule
               on its own accepts "desk@salon" — an address with no dot in the
               domain, legal on a local network and reaching nobody a client
               lives on. App\Support\EmailAddress is the one answer both ends
               read. */
            'email' => EmailAddress::rules(required: true),
            'booking_email' => EmailAddress::rules(),
            'support_email' => EmailAddress::rules(),
            'website' => ['nullable', 'url', 'max:255'],
            'extension' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:120'],

            'hours' => ['array'],
            'hours.*' => ['array'],
            /**
             * The day's own open/closed toggle, declared so it survives.
             *
             * validated() returns only what the rules name, and with the
             * period rules present but no rule for this key, each day came
             * back as a bare list of periods with the toggle stripped out.
             * syncHours() then read every day as closed and the whole week
             * saved as shut — a form that reported success and stored
             * nothing.
             */
            'hours.*.is_open' => ['nullable', 'boolean'],
            'hours.*.*.opens_at' => ['nullable', 'date_format:H:i'],
            /**
             * after: opens_at, not merely a valid time.
             *
             * A period that closes before it opens is not a slow typist's
             * problem to discover later — it is a day the booking engine will
             * offer no slots for, with nothing on this screen to explain why.
             */
            'hours.*.*.closes_at' => ['nullable', 'date_format:H:i', 'after:hours.*.*.opens_at'],
        ], [
            'name.required' => __('locations.validation.name_required'),
            'code.unique' => __('locations.validation.code_unique'),
            'status.required' => __('locations.validation.status_required'),
            'address_line1.required' => __('locations.validation.address_required'),
            'city.required' => __('locations.validation.city_required'),
            'state.required' => __('locations.validation.state_required'),
            'postal_code.required' => __('locations.validation.postal_required'),
            'country.required' => __('locations.validation.country_required'),
            'country.in' => __('locations.validation.country_in'),
            'timezone.required' => __('locations.validation.timezone_required'),
            'timezone.timezone' => __('locations.validation.timezone_in'),
            'phone.required' => __('locations.validation.phone_required'),
            'email.required' => __('locations.validation.email_required'),
            /* Both the rule and the pattern say the same sentence: which of
               the two refused an address is not a distinction the reader can
               act on. */
            'email.email' => __('locations.validation.email_invalid'),
            'email.regex' => __('locations.validation.email_invalid'),
            'booking_email.email' => __('locations.validation.email_invalid'),
            'booking_email.regex' => __('locations.validation.email_invalid'),
            'support_email.email' => __('locations.validation.email_invalid'),
            'support_email.regex' => __('locations.validation.email_invalid'),
            'website.url' => __('locations.validation.url_invalid'),
            'hours.*.*.closes_at.after' => __('locations.validation.closes_after_opens'),
            'manager_staff_id.exists' => __('locations.validation.staff_invalid'),
            'assistant_manager_ids.*.exists' => __('locations.validation.staff_invalid'),
        ]);

        // The project capitalisation rule. Emails, URLs and the code are
        // deliberately excluded: case in those is not the business's to
        // choose, and a code is often deliberately upper-case shorthand.
        return InputCase::apply($data, ['name', 'city', 'state', 'address_line1', 'address_line2', 'suite', 'contact_person']);
    }

    private function staffRule(?string $tenantId): Exists
    {
        return Rule::exists('staff', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId));
    }

    /**
     * Only the values that are columns on `locations`.
     *
     * A whitelist rather than the validated array, because that array also
     * carries `hours`, `assistant_manager_ids` and `is_primary` — none of
     * which are columns, and fill()ing them raises an error that names an
     * internal field on a screen the user cannot fix it from.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        return collect($data)
            ->only([
                'name', 'code', 'type', 'status',
                'address_line1', 'address_line2', 'suite', 'city', 'state', 'postal_code', 'country', 'timezone',
                'manager_staff_id', 'phone', 'phone_country', 'phone_secondary',
                'phone_secondary_country', 'email', 'booking_email',
                'support_email', 'website', 'extension', 'contact_person',
            ])
            ->all();
    }

    /**
     * Exactly one primary branch per business.
     *
     * §1 says only one location is normally the primary one, and a screen that
     * merely offers the choice cannot keep that true — two people saving two
     * branches as primary would both succeed. Demoting the others here makes
     * it a property of the data rather than a convention.
     *
     * A business with one location always has a primary one, whatever the form
     * said: nothing else can be the default, and "no primary location" is a
     * state other modules would have to invent an answer for.
     */
    private function syncPrimary(Location $location, bool $wantsPrimary): void
    {
        $siblings = Location::withoutGlobalScopes()
            ->where('tenant_id', $location->tenant_id)
            ->where('id', '!=', $location->id);

        $isOnly = ! $siblings->clone()->exists();
        $hasOtherPrimary = $siblings->clone()->where('is_primary', true)->exists();

        // Promoted when asked, when it is the only branch, or when no other
        // branch holds the title — the last case is what stops a business
        // ending up with none after the primary one is demoted.
        $primary = $wantsPrimary || $isOnly || ! $hasOtherPrimary;

        if ($primary) {
            $siblings->clone()->update(['is_primary' => false]);
        }

        $location->forceFill(['is_primary' => $primary])->save();
    }

    /**
     * @param  array<int, mixed>  $staffIds
     */
    private function syncAssistants(Location $location, array $staffIds): void
    {
        $ids = collect($staffIds)->map(fn ($id) => (int) $id)->filter()->unique();

        // The manager is not also their own assistant. Allowing it would put
        // the same name in two rows of the same card, which reads as a bug
        // rather than as a decision.
        $ids = $ids->reject(fn (int $id) => $id === (int) $location->manager_staff_id);

        $location->assistantManagers()->sync($ids->all());
    }

    /**
     * Replace this location's opening hours with what was submitted.
     *
     * Rewritten rather than reconciled: periods have no identity of their own
     * — "the second period on Tuesday" is a position, not a thing — so
     * matching submitted rows to stored ones would be guessing. Inside the
     * caller's transaction, so a failure half-way cannot leave a location with
     * Monday deleted and nothing to replace it.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $hours
     */
    private function syncHours(Location $location, array $hours, ?string $effectiveFrom = null): void
    {
        $effectiveFrom ??= $location->currentScheduleDate() ?? Location::EPOCH;

        /**
         * Deleted through scheduleHours(), not hours().
         *
         * hours() picks the current schedule with a correlated subquery over
         * location_hours, and MySQL refuses to delete from a table its own
         * subquery reads (error 1093). Naming the schedule is also more
         * honest about what is being replaced.
         */
        $location->scheduleHours($effectiveFrom)->delete();

        $rows = [];

        foreach (array_keys(config('locations.weekdays')) as $day) {
            $periods = $hours[$day] ?? [];

            // The day toggle. A day with no is_open flag is closed, whatever
            // times its inputs still hold — someone who closes Sunday should
            // not have to clear the times to make it stick.
            if (! ($periods['is_open'] ?? false)) {
                continue;
            }

            unset($periods['is_open']);

            $order = 0;

            foreach ($periods as $period) {
                $opens = $period['opens_at'] ?? null;
                $closes = $period['closes_at'] ?? null;

                // An empty extra period is someone who added a row and changed
                // their mind, not a period from midnight to midnight.
                if (! $opens || ! $closes) {
                    continue;
                }

                $rows[] = [
                    'location_id' => $location->id,
                    'effective_from' => $effectiveFrom,
                    'day_of_week' => $day,
                    'sort_order' => $order++,
                    'is_open' => true,
                    'opens_at' => $opens,
                    'closes_at' => $closes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            DB::table('location_hours')->insert($rows);
        }
    }

    /**
     * Every weekday with its periods, including the closed ones.
     *
     * The view needs all seven rows whether or not the location opens on
     * them; asking Blade to fill the gaps would put the business's week in a
     * template.
     *
     * @return array<int, array{label: string, periods: \Illuminate\Support\Collection<int, LocationHour>}>
     */
    private function hoursByDay(Location $location): array
    {
        $byDay = $location->hours->groupBy('day_of_week');

        $days = [];

        foreach (LocationOptions::weekdays() as $day => $label) {
            $days[$day] = [
                'label' => $label,
                'periods' => $byDay->get($day, collect())->where('is_open', true)->values(),
            ];
        }

        return $days;
    }

    /**
     * The lists both form screens need.
     *
     * Assembled once so create and edit cannot disagree about which staff are
     * offered as managers — a difference that would only ever be noticed by
     * someone unable to pick, on one screen, the person they picked on the
     * other.
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?Location $location): array
    {
        $tenant = $request->user()->tenant;

        return [
            'staffOptions' => $this->assignableStaff($tenant->getTenantKey(), $location),
            'types' => LocationOptions::types(),
            'countries' => LocationOptions::countries(),
            'timezones' => config('locations.timezones'),
            'weekdays' => LocationOptions::weekdays(),
            'hoursByDay' => $location ? $this->hoursByDay($location) : $this->defaultHours(),
            'assistantIds' => $location?->assistantManagers->pluck('id')->all() ?? [],
        ];
    }

    /**
     * Staff who can be named as a manager of this branch.
     *
     * §3 says only active staff assigned to the location normally appear —
     * "normally" because a branch being set up has nobody assigned to it yet,
     * and a dropdown with no options would make the field impossible to fill.
     * So: staff at this location first, then everyone else who is active.
     *
     * @return Collection<int, Staff>
     */
    private function assignableStaff(string $tenantId, ?Location $location): Collection
    {
        return Staff::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            /**
             * Staff at this branch float to the top of the list.
             *
             * A CASE rather than MySQL's <=>, which sqlite does not have: the
             * test suite would fail on a syntax the production database
             * happens to accept, and that is a difference worth not having.
             */
            ->when($location, fn ($query) => $query->orderByRaw(
                'case when location_id = ? then 0 else 1 end', [$location->id]
            ))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * The week a new location starts with: open Monday to Friday, 9 to 5.
     *
     * A blank week would be a form where every day has to be switched on
     * before the location can trade, and the overwhelmingly common answer is
     * the same one onboarding already assumes.
     *
     * @return array<int, array{label: string, periods: \Illuminate\Support\Collection<int, LocationHour>}>
     */
    private function defaultHours(): array
    {
        $days = [];

        foreach (config('locations.weekdays') as $day => $label) {
            $open = $day >= 1 && $day <= 5;

            $days[$day] = [
                'label' => $label,
                'periods' => $open
                    ? collect([new LocationHour(['opens_at' => '09:00', 'closes_at' => '17:00'])])
                    : collect(),
            ];
        }

        return $days;
    }

    /**
     * One place to end a failed save.
     *
     * The reason is logged, never shown: a driver message can carry table
     * names, credentials and the shape of the schema, none of which the
     * person who pressed Save can act on.
     */
    private function saveFailed(Request $request, Throwable $e, ?Location $location): RedirectResponse
    {
        Log::error('A location could not be saved.', [
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'location_id' => $location?->id,
            'exception' => $e->getMessage(),
        ]);

        // Stay on the form. Redirecting away would discard everything typed,
        // for a failure that was not the user's doing.
        return back()->withInput()->with('toast', [
            'type' => 'danger',
            'message' => __('locations.save_failed'),
        ]);
    }
}
