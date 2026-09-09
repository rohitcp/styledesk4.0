<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BackofficeAuditLog;
use App\Models\ClientEmailMessage;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Password;
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
     * The faces of one client record, in the order the tabs are shown.
     *
     * Public because the route constrains its own segment against this list:
     * a tab that is not here must 404 rather than fall through to the default
     * and show the Overview under a URL that promised something else.
     *
     * @var array<int, string>
     */
    public const TABS = ['overview', 'services', 'email-log', 'sms-log', 'team', 'subscription'];

    /** Where the page opens, and where an unknown tab lands. */
    public const DEFAULT_TAB = 'overview';

    /** The row counts a reader may choose between on any listing tab. */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /** Services: the columns a reader may order by, and the SQL they mean. */
    private const SERVICE_SORTS = [
        'name' => 'name',
        'duration' => 'duration_minutes',
        'status' => 'is_active',
        'created' => 'created_at',
    ];

    /** The email log's, ordered newest-first by default. */
    private const EMAIL_SORTS = [
        'sent' => 'created_at',
        'recipient' => 'recipient_email',
        'subject' => 'subject',
        'status' => 'status',
    ];

    /**
     * The team's.
     *
     * Deliberately without `status`. Staff::status() is derived from four
     * columns and a precedence — archived beats suspended beats an unaccepted
     * invitation — and an ORDER BY that re-stated the rule would be a second
     * definition free to disagree with the first.
     */
    private const TEAM_SORTS = [
        'name' => 'first_name',
        'email' => 'email',
        'created' => 'created_at',
    ];

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
     * Six tabs, one route. Each is a URL rather than a panel a script shows
     * and hides, so a tab can be bookmarked, sent to a colleague and reloaded
     * without losing the search and the page the reader was on — and so the
     * listing tabs can page and sort on the server like every other table in
     * this console.
     *
     * Only the open tab's rows are read. A client with four thousand emails
     * should not pay for them on the Overview.
     */
    public function show(Request $request, Tenant $tenant, string $tab = self::DEFAULT_TAB): View
    {
        $tab = in_array($tab, self::TABS, true) ? $tab : self::DEFAULT_TAB;

        /* The header, the status banner and the tab counts are on every tab,
           so they are loaded once here rather than in each branch below. */
        $tenant->loadCount(['users', 'locations', 'clients', 'staff', 'services']);
        $tenant->load([
            'owner:id,first_name,last_name,display_name,email,phone',
            'disabledBy:id,name,email',
        ]);

        $admin = $request->user('backoffice');

        return view('backoffice.clients.show', array_merge([
            'client' => $tenant,
            'tab' => $tab,
            'tabs' => $this->tabs($tenant),
            'quickActions' => $this->quickActions($tenant, (bool) $admin?->can('clients.manage')),
            'reasons' => config('backoffice.disable_reasons'),
            'reasonRequiringNote' => config('backoffice.disable_reason_requiring_note'),
        ], $this->tabData($request, $tenant, $tab)));
    }

    /**
     * Email the client's owner a password reset link.
     *
     * The platform's only "do something for them" action that exists today.
     * It goes through Laravel's own broker rather than a bespoke mail, so the
     * link an administrator sends is the same one the salon's own Forgot
     * Password screen sends — one token format, one expiry, one place it can
     * be wrong.
     *
     * The result is reported honestly. A broker that refuses because the last
     * link was sent a minute ago is not a success, and telling an
     * administrator it worked would have them wait for an email nobody sent.
     */
    public function sendPasswordReset(Request $request, Tenant $tenant): RedirectResponse
    {
        $owner = $tenant->owner;

        if ($owner === null) {
            return $this->backToTab($tenant, 'overview')
                ->with('status', __('backoffice.clients.reset_no_owner'));
        }

        $result = Password::broker()->sendResetLink(['email' => $owner->email]);

        BackofficeAuditLog::record(
            action: 'client.password_reset_sent',
            actor: $request->user('backoffice'),
            subject: $tenant,
            after: ['email' => $owner->email, 'result' => $result],
            subjectLabel: $tenant->name,
        );

        return $this->backToTab($tenant, 'overview')->with('status', $result === Password::RESET_LINK_SENT
            ? __('backoffice.clients.reset_sent', ['email' => $owner->email])
            : __('backoffice.clients.reset_failed'));
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

    /** Back to one tab of the client record, with nothing else carried. */
    private function backToTab(Tenant $tenant, string $tab): RedirectResponse
    {
        return redirect()->route('backoffice.clients.show', ['tenant' => $tenant, 'tab' => $tab]);
    }

    /**
     * The tab strip.
     *
     * The counts come from the header's own `loadCount`, so naming a tab
     * costs nothing — the figures are already on the row by the time this
     * runs. The two unbuilt tabs carry no count on purpose: a number beside
     * "Coming soon" reads as data that exists behind a locked door.
     *
     * @return array<int, array{key: string, label: string, url: string, count: int|null}>
     */
    private function tabs(Tenant $tenant): array
    {
        $counts = [
            'services' => $tenant->services_count,
            'team' => $tenant->staff_count,
        ];

        return array_map(fn (string $tab): array => [
            'key' => $tab,
            'label' => __('backoffice.tabs.'.str_replace('-', '_', $tab)),
            'url' => route('backoffice.clients.show', ['tenant' => $tenant, 'tab' => $tab]),
            'count' => $counts[$tab] ?? null,
        ], self::TABS);
    }

    /**
     * What the open tab needs, and nothing the others would.
     *
     * @return array<string, mixed>
     */
    private function tabData(Request $request, Tenant $tenant, string $tab): array
    {
        return match ($tab) {
            'services' => $this->servicesTab($request, $tenant),
            'email-log' => $this->emailLogTab($request, $tenant),
            'team' => $this->teamTab($request, $tenant),
            'overview' => $this->overviewTab($tenant),
            default => [],
        };
    }

    /**
     * The summary of the account.
     *
     * @return array<string, mixed>
     */
    private function overviewTab(Tenant $tenant): array
    {
        $tenant->load([
            'locations' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
        ]);

        /* The most recent sign-in by anybody at the business. "Last activity"
           on this screen means somebody was working in StyleDesk, which is a
           question about people rather than about rows changing — a nightly
           job touching a table is not a salon being open. */
        $lastActivity = $tenant->users()->max('last_login_at');

        return [
            /* This business's own Backoffice history. Read from the audit log
               rather than a second table: one record of who did what, and the
               screen is a view onto it. */
            'activity' => BackofficeAuditLog::query()
                ->where('subject_type', Tenant::class)
                ->where('subject_id', $tenant->getTenantKey())
                ->newest()
                ->limit(50)
                ->get(),

            'lastActivity' => $lastActivity === null ? null : Carbon::parse($lastActivity),

            'usage' => [
                'locations' => $tenant->locations_count,
                'team' => $tenant->staff_count,
                'services' => $tenant->services_count,
                'clients' => $tenant->clients_count,
                'users' => $tenant->users_count,
                'bookings' => $tenant->bookings()->count(),
            ],
        ];
    }

    /**
     * The services this business sells.
     *
     * Listing only for this phase — no add, no edit, no delete. The console
     * reads a customer's configuration to answer a support question; changing
     * it from here would be changing a salon's price list without the salon.
     *
     * @return array<string, mixed>
     */
    private function servicesTab(Request $request, Tenant $tenant): array
    {
        $filters = $this->listing($request, self::SERVICE_SORTS, 'name', 'asc');
        $filters['status'] = $this->oneOf($request->query('status'), ['active', 'inactive']);

        $like = $this->like($filters['search']);

        return [
            'serviceFilters' => $filters,
            'services' => $tenant->services()
                /* Category, rooms and price are three tables; without this the
                   table below asks for each of them once per row. */
                ->with(['category:id,name', 'locations:id,name', 'prices'])
                ->when($filters['search'] !== '', fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))
                ))
                ->when($filters['status'] !== '', fn (Builder $q) => $q
                    ->where('is_active', $filters['status'] === 'active'))
                ->orderBy(self::SERVICE_SORTS[$filters['sort']], $filters['direction'])
                /* A tie-break, or two rows sorted on the same value swap
                   places between pages and one is seen twice or not at all. */
                ->orderBy('id')
                ->paginate($filters['per_page'])
                ->withQueryString(),
        ];
    }

    /**
     * What this business has emailed its own clients.
     *
     * The rows are copies of what went out rather than a view onto what would
     * go out now, which is what makes the log worth reading a year later —
     * see the migration on client_email_messages.
     *
     * @return array<string, mixed>
     */
    private function emailLogTab(Request $request, Tenant $tenant): array
    {
        $statuses = [
            ClientEmailMessage::STATUS_QUEUED,
            ClientEmailMessage::STATUS_SENT,
            ClientEmailMessage::STATUS_DELIVERED,
            ClientEmailMessage::STATUS_FAILED,
        ];

        $filters = $this->listing($request, self::EMAIL_SORTS, 'sent', 'desc');
        $filters['status'] = $this->oneOf($request->query('status'), $statuses);

        $like = $this->like($filters['search']);

        return [
            'emailStatuses' => $statuses,
            'emailFilters' => $filters,
            'emails' => ClientEmailMessage::query()
                ->where('tenant_id', $tenant->getTenantKey())
                ->with('sentBy:id,first_name,last_name,display_name,email')
                ->when($filters['search'] !== '', fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('recipient_email', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('sender_email', 'like', $like)
                        ->orWhere('template_key', 'like', $like)
                ))
                ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
                ->orderBy(self::EMAIL_SORTS[$filters['sort']], $filters['direction'])
                ->orderBy('id', 'desc')
                ->paginate($filters['per_page'])
                ->withQueryString(),
        ];
    }

    /**
     * The people who work there.
     *
     * Filtered by location rather than by status. Staff::status() is derived
     * from four columns and a precedence, and a WHERE that re-stated it would
     * be a second definition of who counts as active — the model says so
     * itself. Location is a column, means one thing, and is what a reader
     * chasing "who is at the North branch" actually asks.
     *
     * @return array<string, mixed>
     */
    private function teamTab(Request $request, Tenant $tenant): array
    {
        $filters = $this->listing($request, self::TEAM_SORTS, 'name', 'asc');

        $locations = $tenant->locations()->orderBy('name')->pluck('name', 'id');
        $filters['location'] = $locations->has((int) $request->query('location'))
            ? (string) $request->query('location')
            : '';

        $like = $this->like($filters['search']);

        return [
            'teamLocations' => $locations,
            'teamFilters' => $filters,
            'team' => $tenant->staff()
                ->with(['location:id,name', 'roleRecord', 'user:id,last_login_at'])
                ->when($filters['search'] !== '', fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('preferred_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('work_email', 'like', $like)
                        ->orWhere('job_title', 'like', $like)
                        /* Typed in full — "Dana Reeves" matches neither half
                           on its own, and a reader searching for a person
                           types the name they see on the screen. */
                        ->orWhereRaw("concat_ws(' ', first_name, last_name) like ?", [$like])
                ))
                ->when($filters['location'] !== '', fn (Builder $q) => $q
                    ->where('location_id', $filters['location']))
                ->orderBy(self::TEAM_SORTS[$filters['sort']], $filters['direction'])
                ->orderBy('id')
                ->paginate($filters['per_page'])
                ->withQueryString(),
        ];
    }

    /**
     * The four things every listing tab reads from the query string.
     *
     * A value that is not offered is dropped rather than passed through: the
     * sort key reaches an ORDER BY and the page size reaches a LIMIT, and
     * neither should be anything a reader can type into the address bar.
     *
     * @param  array<string, string>  $sorts
     * @return array{search: string, sort: string, direction: string, per_page: int, status: string, location: string}
     */
    private function listing(Request $request, array $sorts, string $defaultSort, string $defaultDirection): array
    {
        $sort = (string) $request->query('sort', '');
        $perPage = (int) $request->query('per_page', 0);

        return [
            'search' => trim((string) $request->query('search', '')),
            'sort' => array_key_exists($sort, $sorts) ? $sort : $defaultSort,
            'direction' => in_array($request->query('direction'), ['asc', 'desc'], true)
                ? (string) $request->query('direction')
                : $defaultDirection,
            'per_page' => in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE,
            'status' => '',
            'location' => '',
        ];
    }

    /**
     * A query value, but only if it is one the screen offers.
     *
     * @param  array<int, string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? (string) $value : '';
    }

    /** A LIKE pattern with the wildcards a reader typed treated as text. */
    private function like(string $term): string
    {
        return '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
    }

    /**
     * Everything an administrator can do to this client, as data.
     *
     * A list rather than a row of buttons in the view, so adding an action is
     * adding an entry here — which is what the brief asks for, and what keeps
     * the control from being rebuilt each time the product grows one.
     *
     * `disabled` entries are shown and refused rather than hidden. A reader
     * who came looking for "Resend welcome email" learns it is coming instead
     * of wondering whether they missed it; the actions behind them do not
     * exist in the product yet, and a control that pretended otherwise would
     * be a support ticket about an email nobody sent.
     *
     * Permission decides what is offered; it does not decide what is allowed.
     * Every entry that changes something goes through a route with its own
     * `backoffice.can:` gate, and the server asks again.
     *
     * @return array<int, array<string, mixed>>
     */
    private function quickActions(Tenant $tenant, bool $canManage): array
    {
        $appUrl = 'https://'.$tenant->slug.'.'.config('tenancy.tenant_domain_suffix');
        $tab = fn (string $tab): string => route('backoffice.clients.show', ['tenant' => $tenant, 'tab' => $tab]);

        $go = __('backoffice.clients.action_groups.go');
        $copy = __('backoffice.clients.action_groups.copy');
        $tell = __('backoffice.clients.action_groups.tell');
        $account = __('backoffice.clients.action_groups.account');

        $actions = [
            ['key' => 'open_app', 'group' => $go, 'type' => 'link', 'external' => true,
                'label' => __('backoffice.clients.actions.open_app'), 'url' => $appUrl],

            ['key' => 'view_profile', 'group' => $go, 'type' => 'link',
                'label' => __('backoffice.clients.actions.view_profile'), 'url' => $tab('overview')],

            ['key' => 'view_services', 'group' => $go, 'type' => 'link',
                'label' => __('backoffice.clients.actions.view_services'), 'url' => $tab('services')],

            ['key' => 'view_team', 'group' => $go, 'type' => 'link',
                'label' => __('backoffice.clients.actions.view_team'), 'url' => $tab('team')],

            ['key' => 'view_email_logs', 'group' => $go, 'type' => 'link',
                'label' => __('backoffice.clients.actions.view_email_logs'), 'url' => $tab('email-log')],

            ['key' => 'copy_url', 'group' => $copy, 'type' => 'copy', 'value' => $appUrl,
                'label' => __('backoffice.clients.actions.copy_url'),
                'copied' => __('backoffice.clients.copied_url')],

            ['key' => 'copy_id', 'group' => $copy, 'type' => 'copy', 'value' => (string) $tenant->getTenantKey(),
                'label' => __('backoffice.clients.actions.copy_id'),
                'copied' => __('backoffice.clients.copied_id')],

            /* No mailable behind either of these yet. Listed, refused, and
               honest about why — the alternative is a control that appears to
               send an email the product cannot send. */
            ['key' => 'send_email', 'group' => $tell, 'type' => 'form', 'disabled' => true,
                'label' => __('backoffice.clients.actions.send_email')],

            ['key' => 'resend_welcome', 'group' => $tell, 'type' => 'form', 'disabled' => true,
                'label' => __('backoffice.clients.actions.resend_welcome')],
        ];

        /* Nothing to reset without an owner, and an entry that could only
           report "there is nobody to send this to" is not an action. */
        if ($canManage && $tenant->owner !== null) {
            $actions[] = [
                'key' => 'send_password_reset', 'group' => $tell, 'type' => 'form',
                'label' => __('backoffice.clients.actions.send_password_reset'),
                'url' => route('backoffice.clients.password-reset', $tenant),
            ];
        }

        /* The two that change the account open the confirmations the page
           already carries — the same reason field, the same wording, the same
           server rule. A second dialog saying it differently is a second
           chance to say it wrong. */
        if ($canManage && $tenant->isActive()) {
            $actions[] = [
                'key' => 'deactivate', 'group' => $account, 'type' => 'modal', 'modal' => 'disable-client',
                'label' => __('backoffice.clients.actions.deactivate'),
            ];
        }

        if ($canManage && $tenant->isDisabled()) {
            $actions[] = [
                'key' => 'activate', 'group' => $account, 'type' => 'modal', 'modal' => 'enable-client',
                'label' => __('backoffice.clients.actions.activate'),
            ];
        }

        return $actions;
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
