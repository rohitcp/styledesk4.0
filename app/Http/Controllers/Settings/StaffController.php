<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Staff\CreateStaffMember;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use App\Support\RoleGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('settings.staff.index', [
            'staff' => $staff,
            'filters' => $filters,
            'roles' => Role::query()->orderBy('display_order')->get(),
            'locations' => Location::query()->orderByDesc('is_primary')->get(['id', 'name']),
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
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
                'role_id' => 'You cannot assign that role.',
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
                'message' => "We couldn't add that staff member right now. Please try again.",
            ]);
        }

        $message = $staff->invite_status === 'sent'
            ? $staff->displayName().' was added and an invitation is on its way to '.$staff->email.'.'
            : $staff->displayName().' was added to your team.';

        return redirect()
            ->route('settings.staff.index')
            ->with('toast', ['type' => 'success', 'message' => $message]);
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
            'image.max' => 'The profile image must be 2 MB or smaller.',
            'image.mimes' => 'Use a JPG, PNG or WEBP image.',
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
    private function validated(Request $request): array
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
                Rule::unique('staff', 'email')->where('tenant_id', $request->user()->tenant_id),
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
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Primary email is required.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'Someone on your team already uses that email address.',
            'work_email.email' => 'Enter a valid email address.',
            'role_id.required' => 'Choose a role for this person.',
            'avatar.max' => 'The profile image must be 2 MB or smaller.',
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
