<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BackofficeAuditLog;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Every business subscribed to StyleDesk.
 *
 * "Client" here means a customer of StyleDesk — a salon, clinic or studio —
 * and not the salon's own clients, which is what `App\Models\Client` is. The
 * two live in different consoles and mean different people; the navigation
 * word is the platform's, so this controller answers to it.
 *
 * Read-only. Suspending, editing and impersonating a business are `clients.manage`
 * and arrive with that phase — a list that can only be read needs no
 * confirmation dialogs and cannot be the thing that took a salon offline.
 *
 * No tenancy is initialised here, which is what makes the counts correct:
 * TenantScope is a no-op while `tenancy()->initialized` is false, so the
 * `withCount` sub-queries are constrained by the relation's own tenant_id and
 * each row counts its own rows. Initialising a tenant on this page would scope
 * the whole screen to one of them.
 */
class ClientController extends Controller
{
    /** Enough to fill a desk screen without turning the page into a scroll. */
    private const PER_PAGE = 25;

    /**
     * The five states a reader sees, and the rows each one means.
     *
     * Access and billing are two columns — `status` says whether anybody may
     * sign in, `subscription_status` says whether they have paid — and the
     * screen shows one word made from both. Disabled wins over everything
     * because it is the state that stops people working; the rest describe a
     * business that is still running.
     *
     * The business rule this serves: Active → Past Due → Disabled. Past Due is
     * a bill that has not been paid, Disabled is access actually withdrawn, and
     * the list must not blur them.
     *
     * @var array<string, array<int, string>|null> Display state => the
     *                                             subscription_status values it covers, or null for "disabled".
     */
    private const STATUSES = [
        'active' => ['active'],
        'trial' => ['trialing', 'trial'],
        'past_due' => ['past_due'],
        'cancelled' => ['canceled', 'cancelled'],
        'disabled' => null,
    ];

    /** The columns a reader may order by, and the SQL they mean. */
    private const SORTS = [
        'created' => 'created_at',
        'name' => 'name',
        'status' => 'status',
    ];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => array_key_exists($status, self::STATUSES) ? $status : '',
            'sort' => array_key_exists((string) $request->query('sort'), self::SORTS)
                ? (string) $request->query('sort')
                : 'created',
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];

        return view('backoffice.clients.index', [
            'clients' => $this->page($filters),
            'filters' => $filters,
            'stats' => $this->stats(),
            'statuses' => array_keys(self::STATUSES),
        ]);
    }

    /**
     * One business, in full.
     *
     * Read-only like the list. Everything on this page is either a column on
     * the row or a count of rows that point at it — nothing is computed twice
     * and nothing is editable, so there is no state here to get wrong.
     */
    public function show(Tenant $tenant): View
    {
        $tenant->loadCount(['users', 'locations', 'clients', 'staff', 'services']);
        $tenant->load([
            'owner:id,first_name,last_name,display_name,email,phone',
            'disabledBy:id,name,email',
            'locations' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
            'users:id,tenant_id,first_name,last_name,display_name,email,last_login_at',
        ]);

        return view('backoffice.clients.show', [
            'client' => $tenant,

            /* This business's own Backoffice history. Read from the audit log
               rather than a second table: one record of who did what, and the
               screen is a view onto it. */
            'activity' => BackofficeAuditLog::query()
                ->where('subject_type', Tenant::class)
                ->where('subject_id', $tenant->getTenantKey())
                ->newest()
                ->limit(50)
                ->get(),

            'reasons' => config('backoffice.disable_reasons'),
            'reasonRequiringNote' => config('backoffice.disable_reason_requiring_note'),
        ]);
    }

    /**
     * Switch a business off.
     *
     * Nothing is deleted and nothing is unwound: bookings, clients and staff
     * stay exactly as they are. What changes is that nobody belonging to the
     * business can sign in, enforced on every request by
     * EnsureBusinessIsActive rather than only at the login form.
     *
     * A reason is required. The first question after "we cannot sign in" is
     * "why", and an administrator who has to type an answer is one who has
     * decided rather than mis-clicked.
     */
    public function disable(Request $request, Tenant $tenant): RedirectResponse
    {
        $requiresNote = (string) config('backoffice.disable_reason_requiring_note');

        $data = $request->validate([
            /* In the list or not at all. A key typed into the form by hand is
               one nothing can label, count or translate later. */
            'reason' => ['required', 'string', Rule::in(config('backoffice.disable_reasons'))],

            /* Optional, except for the reason that says nothing on its own.
               Enforced here and not only in the browser: the modal is a
               convenience, this is the rule. */
            'note' => [
                $request->input('reason') === $requiresNote ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'note.required' => __('backoffice.clients.note_required'),
        ]);

        /* Already off. Answered as success rather than as an error: a double
           submit should not produce a second audit entry saying it happened
           twice, nor overwrite who did it the first time. */
        if ($tenant->isDisabled()) {
            return redirect()->route('backoffice.clients.show', $tenant);
        }

        $before = $tenant->status;

        $tenant->disable($request->user('backoffice'), $data['reason'], $data['note'] ?? null);

        BackofficeAuditLog::record(
            action: 'client.disabled',
            actor: $request->user('backoffice'),
            subject: $tenant,
            before: ['status' => $before],
            after: [
                'status' => Tenant::STATUS_DISABLED,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
            ],
            subjectLabel: $tenant->name,
        );

        return redirect()->route('backoffice.clients.show', $tenant)
            ->with('status', __('backoffice.clients.disabled_done', ['name' => $tenant->name]));
    }

    /**
     * Open the door again.
     *
     * The note is optional and internal, like the disable note — it is the
     * answer to "why is this account back" for whoever reads the history next
     * year.
     */
    public function enable(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($tenant->isActive()) {
            return redirect()->route('backoffice.clients.show', $tenant);
        }

        $restored = $tenant->previous_status ?: Tenant::STATUS_ACTIVE;

        $tenant->enable($request->user('backoffice'), $data['note'] ?? null);

        BackofficeAuditLog::record(
            action: 'client.enabled',
            actor: $request->user('backoffice'),
            subject: $tenant,
            before: ['status' => Tenant::STATUS_DISABLED],
            after: ['status' => $restored, 'note' => $data['note'] ?? null],
            subjectLabel: $tenant->name,
        );

        return redirect()->route('backoffice.clients.show', $tenant)
            ->with('status', __('backoffice.clients.enabled_done', ['name' => $tenant->name]));
    }

    /**
     * @param  array{search: string, status: string, subscription: string, sort: string, direction: string}  $filters
     * @return LengthAwarePaginator<int, Tenant>
     */
    private function page(array $filters): LengthAwarePaginator
    {
        return Tenant::query()
            /* The real columns: `name` on User is a computed accessor over
               first_name/last_name, not something the select can ask for. */
            ->with('owner:id,first_name,last_name,display_name,email')
            ->withCount(['users', 'locations', 'clients', 'staff'])
            ->when($filters['search'] !== '', fn (Builder $q) => $this->search($q, $filters['search']))
            ->when($filters['status'] !== '', fn (Builder $q) => $this->ofStatus($q, $filters['status']))
            ->orderBy(self::SORTS[$filters['sort']], $filters['direction'])
            /* A tie-break, or two businesses created in the same second swap
               places between pages and a row is seen twice or not at all. */
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Narrow to one of the five display states.
     *
     * "Active" also covers a business with no subscription row at all — an
     * account that predates billing is running, and hiding it behind a filter
     * that says otherwise would make the list lie about its own total.
     */
    private function ofStatus(Builder $query, string $status): Builder
    {
        if ($status === 'disabled') {
            return $query->where('status', Tenant::STATUS_DISABLED);
        }

        return $query
            ->where('status', Tenant::STATUS_ACTIVE)
            ->where(function (Builder $q) use ($status): void {
                $q->whereIn('subscription_status', self::STATUSES[$status]);

                if ($status === 'active') {
                    $q->orWhereNull('subscription_status');
                }
            });
    }

    /**
     * Match the things a reader would actually type.
     *
     * The owner is reached through the relation rather than a join, because a
     * business without an owner must still appear when its own name matches —
     * a join would drop it.
     */
    private function search(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('legal_name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('business_email', 'like', $like)
                ->orWhere('country_code', 'like', $like)
                ->orWhere('id', 'like', $like)
                ->orWhereHas('owner', fn (Builder $owner) => $owner
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('display_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    /* Typed in full — "Dana Reeves" matches neither half on
                       its own, and a reader searching a person types the name
                       they see on the screen. */
                    ->orWhereRaw("concat_ws(' ', first_name, last_name) like ?", [$like]));
        });
    }

    /**
     * The figures above the table.
     *
     * Counted from the same table the list reads, so a reader who doubts one
     * can filter and count the rows themselves.
     *
     * @return array{total: int, active: int, trialing: int, past_due: int, disabled: int}
     */
    private function stats(): array
    {
        return [
            'total' => Tenant::query()->count(),
            'active' => $this->ofStatus(Tenant::query(), 'active')->count(),
            'trialing' => $this->ofStatus(Tenant::query(), 'trial')->count(),
            'past_due' => $this->ofStatus(Tenant::query(), 'past_due')->count(),
            'disabled' => Tenant::query()->where('status', Tenant::STATUS_DISABLED)->count(),
        ];
    }
}
