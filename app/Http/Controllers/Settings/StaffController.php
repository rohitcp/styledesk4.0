<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Staff\CreateStaffMember;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TeamInvitation;
use App\Support\InputCase;
use App\Support\RoleGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        /**
         * Paginated from the collection, not from the query.
         *
         * Status is derived, so the filter above runs in PHP — and a SQL
         * LIMIT applied before it would return a page of 25 rows that becomes
         * 9 after filtering, with a total that counts the unfiltered set. The
         * whole directory is loaded and then sliced, which is honest at the
         * scale a salon's staff list actually reaches. It would need
         * revisiting somewhere in the thousands, which is not a staff list.
         */
        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginated = new LengthAwarePaginator(
            $staff->forPage($page, self::PER_PAGE),
            $staff->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                // Without this, following a page link drops the search and
                // every filter, and page two of a filtered list is the whole
                // directory again.
                'query' => $request->query(),
            ]
        );

        return view('settings.staff.index', [
            'staff' => $paginated,
            'filters' => $filters,
            'roles' => Role::query()->orderBy('display_order')->get(),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
            // Counted over everything that matched, not over the page being
            // looked at: "6 active members" must not become "3" on page two.
            'activeCount' => $staff->filter(fn (Staff $m) => $m->status() === 'active')->count(),
            'pendingCount' => $staff->filter(fn (Staff $m) => $m->status() === 'pending-invite')->count(),
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

        return redirect()
            ->route('settings.staff.index')
            ->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function show(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);

        return view('settings.staff.show', [
            'staff' => $staff->load(['roleRecord', 'location', 'user', 'services']),
            'invitation' => TeamInvitation::query()->where('staff_id', $staff->id)->latest('id')->first(),
            'history' => AuditLog::query()
                ->where('subject_type', Staff::class)
                ->where('subject_id', $staff->id)
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function edit(Request $request, Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('settings.staff.edit', [
            ...$this->formData($request),
            'staff' => $staff->load(['roleRecord', 'services']),
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
                        'login_enabled',
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
            ->route('settings.staff.show', $staff)
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
            ->route('settings.staff.index')
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

            'account_status' => ['required', Rule::in(['active', 'inactive'])],
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

        return $data;
    }

    /**
     * Options both the create form and, later, the edit form need.
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        return [
            // Only roles this user is allowed to hand out are offered, so the
            // form cannot draw a choice the server will refuse.
            'roles' => Role::query()->orderBy('display_order')->get()
                ->filter(fn (Role $role) => RoleGuard::canAssignRole($request->user(), $role))
                ->values(),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ];
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
