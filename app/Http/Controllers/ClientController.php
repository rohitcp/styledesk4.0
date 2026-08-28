<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\ClientSettings;
use App\Models\ClientTag;
use App\Models\Staff;
use App\Models\Tenant;
use App\Support\ClientOptions;
use App\Support\InputCase;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The Clients module: the people, not the settings that describe them.
 *
 * What a client record carries, which fields are required and how names are
 * written are all decided in App Settings → Clients. This controller reads
 * that configuration rather than restating it — a form that asked for a field
 * the business had switched off, or failed to require one it had marked
 * required, would make that screen a suggestion.
 *
 * §Access Control asks for the two to stay separate, which is why this sits
 * outside the settings route group and checks `clients.*` permissions instead.
 */
class ClientController extends Controller
{
    /**
     * The listing, or the empty state — never both.
     *
     * §Empty State is explicit that a business with no clients sees an
     * onboarding page rather than an empty table: no filters, no search, no
     * pagination and no "0 results". A search box over nothing is a control
     * that can only ever fail.
     */
    public function index(Request $request): View
    {
        $this->authorizeClients($request, 'clients.view');

        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);

        /**
         * "Has this business any clients at all" is asked separately from the
         * filtered query. Using the filtered count would show the onboarding
         * page to someone whose search simply matched nothing, and offer them
         * "Add your first client" when they have four hundred.
         */
        if (! $tenant->clients()->exists()) {
            return view('clients.empty', [
                'canCreate' => $request->user()->hasPermission('clients.create', 'own'),
            ]);
        }

        return view('clients.index', [
            'settings' => $settings,
            'filters' => $this->filters($request),

            /**
             * Whether anything matches is asked here rather than left to the
             * grid. An empty grid can say "no matches"; it cannot know that
             * every client this business has is archived, or offer the link
             * to go and look at them.
             */
            'hasMatches' => $this->query($request, $tenant, $settings)->exists(),
            'statuses' => ClientOptions::statuses(),
            'locations' => $tenant->locations()->active()->inDisplayOrder()->get(),
            'staff' => $tenant->staff()->where('is_active', true)->orderBy('first_name')->get(),
            'tags' => $tenant->clientTags()->active()->inOrder()->get(),
            'canCreate' => $request->user()->hasPermission('clients.create', 'own'),
            'canEdit' => $request->user()->hasPermission('clients.edit', 'own'),
            'canArchive' => $request->user()->hasPermission('clients.archive', 'own'),
            'stats' => $this->stats($tenant),
        ]);
    }

    /**
     * The four figures above the toolbar.
     *
     * Counted across the whole business rather than through the current
     * filters: they are the reason to change the filters, so a set that moved
     * with them would only ever agree with the grid and never tell the reader
     * anything new.
     *
     * @return array<string, mixed>
     */
    private function stats(Tenant $tenant): array
    {
        $thisMonth = $tenant->clients()->where('created_at', '>=', now()->startOfMonth())->count();

        $lastMonth = $tenant->clients()
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()])
            ->count();

        return [
            'total' => $tenant->clients()->count(),
            'new_this_month' => $thisMonth,

            /**
             * The change on last month, and null when there is nothing to
             * compare with. A first month of trading is not "+100%", and a
             * month after none at all is not a trend.
             */
            'new_trend' => $lastMonth > 0 ? (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100) : null,

            // Bookings have not shipped, so this is nought for everyone
            // today — the same nought the grid's Next booking column shows.
            'upcoming' => $tenant->clients()
                ->whereNotNull('next_booking_at')->where('next_booking_at', '>=', now())->count(),

            'inactive' => $tenant->clients()->where('status', Client::STATUS_INACTIVE)->count(),
        ];
    }

    /**
     * The rows behind the grid, one page at a time.
     *
     * The grid asks for these as the reader scrolls rather than the page
     * shipping every client it has: a salon with four thousand clients would
     * otherwise send four thousand rows to draw twenty.
     *
     * Filtering and sorting stay here rather than moving into the browser for
     * the same reason they always have — the query is what knows which
     * clients this business, and this member of staff, may see.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeClients($request, 'clients.view');

        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);
        $format = $settings->name_format;

        /**
         * The grid asks for its own page size and the server decides what it
         * is allowed to be: a page parameter that reached the query unchecked
         * would let anyone ask for every client in one request.
         */
        $size = min(200, max(1, (int) $request->query('size', 100)));

        $page = $this->query($request, $tenant, $settings)
            ->paginate($size, ['*'], 'page', max(1, (int) $request->query('page', 1)));

        return response()->json([
            /**
             * Tabulator's own shape for a progressively loaded table: the
             * rows, and how many pages exist so it knows when to stop asking.
             */
            'last_page' => $page->lastPage(),

            /**
             * last_row is what the counter reads. Without it the grid works
             * the total out as pages × page size and says "of 500" when
             * there are 427 — a rounded-up number presented as a count.
             */
            'last_row' => $page->total(),
            'total' => $page->total(),
            'data' => collect($page->items())->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->displayName($format),
                'initials' => $client->initials(),
                'ref' => $client->client_ref,
                'mobile' => $client->mobile,
                'email' => $client->email,
                'staff' => $client->preferredStaff?->displayName(),
                'location' => $client->preferredLocation?->name,

                // Rendered here rather than in the browser: the same phrases
                // the rest of the app uses, already in the reader's language.
                'last_visit' => $client->last_visit_at?->diffForHumans() ?? __('clients.module.never_visited'),
                'next_booking' => $client->next_booking_at?->diffForHumans() ?? __('clients.module.nothing_booked'),
                'status' => $client->statusLabel(),
                'status_class' => $client->statusClass(),
                'archived' => $client->isArchived(),

                'url' => route('clients.show', $client),
                'edit_url' => route('clients.edit', $client),
                'archive_url' => route('clients.archive', $client),
            ])->all(),
        ]);
    }

    /**
     * What the reader asked to see, from the query string.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        /**
         * Location, staff and tag are lists: a receptionist covering two
         * branches is asking about both, and a filter that could only hold
         * one would make them look twice.
         *
         * Status stays single. "Active and archived at once" is not a
         * question anyone asks — including archived rows is what the setting
         * in App Settings decides.
         */
        $list = fn (string $key) => collect((array) $request->query($key, []))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status'),
            'location' => $list('location'),
            'staff' => $list('staff'),
            'tag' => $list('tag'),

            // Set by the cards above the toolbar rather than by a control:
            // "the ones added this month", "the ones with something booked".
            'created' => $request->query('created') === 'this_month' ? 'this_month' : null,
            'upcoming' => $request->boolean('upcoming') ? '1' : null,
        ];
    }

    /**
     * The filtered client query, shared by the page and the grid's rows.
     *
     * One place, so the count a screen shows and the rows it lists can never
     * disagree about which clients this business has.
     */
    private function query(Request $request, Tenant $tenant, ClientSettings $settings): HasMany
    {
        $filters = $this->filters($request);

        return $tenant->clients()
            ->with(['preferredLocation', 'preferredStaff'])
            ->matching($filters['search'], $settings->search_fields ?? [])
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            /**
             * Archived clients are out of the list unless asked for, per §11 —
             * but only when the business has not chosen to include them, and
             * always when someone has explicitly filtered for archived.
             */
            ->when(
                $filters['status'] !== Client::STATUS_ARCHIVED,
                fn ($q) => $q->bookable($settings->archived_in_search)
            )
            ->when($filters['location'], fn ($q, array $ids) => $q->whereIn('preferred_location_id', $ids))
            ->when($filters['staff'], fn ($q, array $ids) => $q->whereIn('preferred_staff_id', $ids))
            ->when($filters['tag'], fn ($q, array $ids) => $q->whereHas('tags', fn ($t) => $t->whereKey($ids)))
            ->when($filters['created'], fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->when($filters['upcoming'], fn ($q) => $q->whereNotNull('next_booking_at')->where('next_booking_at', '>=', now()))
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    public function create(Request $request): View
    {
        $this->authorizeClients($request, 'clients.create');

        return view('clients.create', $this->formState($request->user()->tenant));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeClients($request, 'clients.create');

        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);

        $data = $this->validated($request, $settings, $tenant);

        /**
         * The duplicate check warns; it never blocks and never merges.
         *
         * §7 is explicit. Someone who has looked at the matches and knows the
         * two are different people confirms and continues — the warning has
         * done its job by then, and refusing would leave them unable to add a
         * client that genuinely exists.
         */
        if (! $request->boolean('confirm_duplicate') && $settings->duplicate_warning) {
            $matches = Client::possibleDuplicates(
                $tenant->getTenantKey(), $data, $settings->duplicate_rules ?? []
            );

            if ($matches->isNotEmpty()) {
                return back()
                    ->withInput()
                    ->with('duplicates', $matches->map(fn (Client $c) => [
                        'id' => $c->id,
                        'name' => $c->displayName($settings->name_format),
                        'ref' => $c->client_ref,
                        'email' => $c->email,
                        'mobile' => $c->mobile,
                        'url' => route('clients.show', $c),
                    ])->all());
            }
        }

        $client = DB::transaction(function () use ($tenant, $data, $request, $settings) {
            $client = $tenant->clients()->create([
                ...$this->columns($data, $settings),
                'client_ref' => Client::nextRef($tenant->getTenantKey()),
                ...$this->consent($data, $request),
            ]);

            $client->preferences()->sync($this->ownedIds($tenant, 'clientPreferences', $data['preferences'] ?? []));
            $client->tags()->sync($this->ownedIds($tenant, 'clientTags', $data['tags'] ?? []));
            $this->syncContacts($client, $data, $settings);

            return $client;
        });

        /**
         * To the profile, per §Once the First Client Is Added.
         *
         * The person who just typed a client's details wants to see them, not
         * to find them again in a list.
         */
        return redirect()
            ->route('clients.show', $client)
            ->with('toast', ['type' => 'success', 'message' => __('clients.module.created')]);
    }

    /**
     * The client workspace.
     *
     * Three columns rather than a long form: who they are stays on screen
     * while the middle column is worked in, per the specification. What this
     * hands the view is only what the app actually knows — the counts and
     * timelines that belong to bookings are not invented here, because a
     * profile that shows "24 visits" for a client with no appointments is a
     * page that lies to whoever reads it next.
     */
    public function show(Request $request, Client $client): View
    {
        $this->authorizeClients($request, 'clients.view');
        $this->assertOwned($client, $request->user()->tenant);

        $user = $request->user();

        $client->load([
            'preferredLocation', 'preferredStaff', 'preferences', 'tags',
            'consentRecordedBy', 'phones', 'emails',
        ]);

        /**
         * Notes are their own permission. Someone who may see the client list
         * has not necessarily been given what colleagues wrote about them.
         */
        $canViewNotes = $user->hasPermission('clients.view_notes', 'own');
        $canAddNotes = $user->hasPermission('clients.add_notes', 'own');

        /**
         * Newest first, and never more than this reader may see.
         *
         * A private note stays in the list — its existence is not the secret
         * — but `readable` decides whether its body is put on the page at
         * all. Nothing downstream re-derives that: the timeline, the profile
         * card and the tab all read this one answer.
         */
        $notes = $canViewNotes
            ? $client->clientNotes()
                ->with(['author', 'accessUsers'])
                ->latest()
                ->get()
                ->each(fn (ClientNote $note) => $note->readable = $note->isVisibleTo($user))
            : collect();

        return view('clients.show', [
            'client' => $client,

            // What this client carries, and everything this business could
            // put on them — the modal needs both to show ticks against a list.
            /* Only the tags this business still applies: a deactivated one
               stays on the clients that carry it, but is not something to
               put on anybody new. */
            'availableTags' => ClientTag::query()->active()->inOrder()->get(),
            'assignedBehavioral' => $client->behavioralTags(),
            'availableBehavioral' => $user->tenant->behavioralTags()
                ->where('is_active', true)->get()
                ->sortBy(fn ($tag) => array_search($tag->tag_key, array_keys(config('behavioral_tags.tags')), true))
                ->values(),
            'settings' => ClientSettings::forTenant($user->tenant),
            'notes' => $notes,
            /* An important note nobody may read cannot be shown on the
               profile: the card prints its body. */
            'importantNotes' => $notes->where('is_important', true)->where('readable', true),

            /* Who a private note can be addressed to: everyone on the team
               with an account, since a note is shared with a person rather
               than with a job. */
            'noteAudience' => $canAddNotes
                ? Staff::query()->whereNotNull('user_id')->with('user')->orderBy('first_name')->get()
                : collect(),
            'activity' => $this->activity($client, $notes, $canViewNotes),
            'canEdit' => $user->hasPermission('clients.edit', 'own'),
            'canArchive' => $user->hasPermission('clients.archive', 'own'),
            'canDelete' => $user->hasPermission('clients.delete', 'own'),
            'canViewContact' => $user->hasPermission('clients.view_contact', 'own'),
            'canViewNotes' => $canViewNotes,
            'canAddNotes' => $canAddNotes,
            'canViewHistory' => $user->hasPermission('clients.view_history', 'own'),
        ]);
    }

    /**
     * The activity timeline, from what the app can actually witness.
     *
     * Today that is the record's own life: created, consent given, notes
     * written, archived. Bookings, messages and payments will join it as
     * those modules arrive — each is a real event with a real timestamp, and
     * none of them is invented here to make the tab look busy.
     *
     * @param  Collection<int, ClientNote>  $notes
     * @return Collection<int, array<string, mixed>>
     */
    private function activity(Client $client, $notes, bool $canViewNotes)
    {
        $events = collect();

        $events->push([
            'type' => 'changes',
            'icon' => 'user',
            'at' => $client->created_at,
            'title' => __('clients.module.workspace.activity.created'),
            'meta' => __('clients.module.workspace.activity.created_meta', ['ref' => $client->client_ref]),
        ]);

        if ($client->consent_recorded_at) {
            $events->push([
                'type' => 'changes',
                'icon' => 'envelope',
                'at' => $client->consent_recorded_at,
                'title' => __('clients.module.workspace.activity.consent'),
                'meta' => $client->consentRecordedBy?->name,
            ]);
        }

        if ($canViewNotes) {
            foreach ($notes as $note) {
                $events->push([
                    'type' => 'notes',
                    'icon' => 'clipboard-list',
                    'at' => $note->created_at,
                    'title' => $note->is_important
                        ? __('clients.module.workspace.activity.important_note')
                        : __('clients.module.workspace.activity.note'),
                    'meta' => $note->authorName(),
                    /* A private note appears in the timeline as an event
                       with no body: "a note was written" is not the secret,
                       what it says is. */
                    'body' => $note->readable ? $note->body : null,
                ]);
            }
        }

        if ($client->isArchived()) {
            $events->push([
                'type' => 'changes',
                'icon' => 'box',
                'at' => $client->updated_at,
                'title' => __('clients.module.workspace.activity.archived'),
                'meta' => null,
            ]);
        }

        return $events->filter(fn (array $event) => $event['at'] !== null)
            ->sortByDesc('at')
            ->values();
    }

    public function edit(Request $request, Client $client): View
    {
        $this->authorizeClients($request, 'clients.edit', 'own');
        $this->assertOwned($client, $request->user()->tenant);

        $client->load(['preferences', 'tags', 'phones', 'emails']);

        /**
         * The client last, not first: formState carries a null `client` for
         * the add screen, and spreading it after would blank the record this
         * page exists to edit.
         */
        return view('clients.edit', [
            ...$this->formState($request->user()->tenant),
            'client' => $client,
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClients($request, 'clients.edit', 'own');
        $this->assertOwned($client, $request->user()->tenant);

        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);

        $data = $this->validated($request, $settings, $tenant, $client);

        DB::transaction(function () use ($client, $data, $settings, $request, $tenant) {
            $client->forceFill([
                ...$this->columns($data, $settings),
                ...$this->consent($data, $request, $client),
            ])->save();

            $client->preferences()->sync($this->ownedIds($tenant, 'clientPreferences', $data['preferences'] ?? []));
            $client->tags()->sync($this->ownedIds($tenant, 'clientTags', $data['tags'] ?? []));
            $this->syncContacts($client, $data, $settings);
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('toast', ['type' => 'success', 'message' => __('clients.module.saved')]);
    }

    /**
     * Set the behavioural tags a client carries.
     *
     * The whole set at once rather than one add and one remove: the modal
     * shows every tag with a tick, so what it posts is the answer to "which
     * of these", and applying that as a replacement is the only way the
     * screen and the record cannot disagree.
     */
    /**
     * The client tags on one client, as a whole set.
     *
     * The modal asks "which of your tags apply to this client", so the answer
     * is the complete list — sending only what changed would need the server
     * to guess whether a missing tag was left alone or taken off.
     *
     * Answers JSON to the modal, which updates the card in place, and a
     * redirect to anything else: the same form works with scripting off.
     */
    public function tags(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $this->authorizeClients($request, 'clients.edit', 'own');
        $this->assertOwned($client, $request->user()->tenant);

        /**
         * Only tags this business currently applies. A deactivated tag stays
         * on the clients that already carry it — §Tags is explicit about that
         * — but nobody may put one on from here, so it is not in the list the
         * modal offers and not accepted if a stale form posts it.
         */
        $active = ClientTag::query()->active()->pluck('id')->all();

        $data = $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => [Rule::in($active)],
        ]);

        /**
         * Deactivated tags the client already has are carried through the
         * sync untouched. Passing only what the modal offered would strip
         * them, which is a removal nobody asked for.
         */
        $keep = $client->tags()->where('is_active', false)->pluck('client_tags.id')->all();

        $client->tags()->sync(array_merge($keep, array_map('intval', $data['tags'] ?? [])));
        $client->load('tags');

        $message = __('clients.module.workspace.tags.updated');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'tags' => $client->tags->map(fn (ClientTag $tag) => [
                    'id' => $tag->id,
                    'label' => $tag->label,
                    'hex' => $tag->hex(),
                ])->values(),
            ]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function behavioralTags(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClients($request, 'clients.edit', 'own');
        $this->assertOwned($client, $request->user()->tenant);

        $tenant = $request->user()->tenant;

        /**
         * Only tags this business applies. A tag it has switched off is not
         * one anybody here should be putting on a client, and validating
         * against the catalogue alone would let a stale form do exactly that.
         */
        $active = $tenant->behavioralTags()->where('is_active', true)->pluck('tag_key')->all();

        $data = $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => [Rule::in($active)],
        ]);

        $client->syncBehavioralTags($data['tags'] ?? []);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('clients.behavioral.updated'),
        ]);
    }

    /**
     * Active or inactive, and nothing else.    /**
     * Active or inactive, and nothing else.
     *
     * Archiving has its own action because it means something different;
     * this is the everyday "they have stopped coming for now".
     */
    public function status(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClients($request, 'clients.edit', 'own');
        $this->assertOwned($client, $request->user()->tenant);

        $data = $request->validate([
            'status' => ['required', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE])],
        ]);

        $client->forceFill(['status' => $data['status']])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('clients.module.saved'),
        ]);
    }

    /**
     * Archive, which is not deletion.
     *
     * §11: an archived client stays out of booking search and stays in every
     * appointment, transaction and report that already names them. Removing
     * the row would take that history with it.
     */
    public function archive(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClients($request, 'clients.archive', 'own');
        $this->assertOwned($client, $request->user()->tenant);

        $archiving = ! $client->isArchived();

        $client->forceFill([
            'status' => $archiving ? Client::STATUS_ARCHIVED : Client::STATUS_ACTIVE,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $archiving
                ? __('clients.module.archived', ['name' => $client->displayName()])
                : __('clients.module.restored', ['name' => $client->displayName()]),
        ]);
    }

    // -------------------------------------------------------------- helpers

    /**
     * Validation built from the business's own field configuration.
     *
     * A field the business switched off is not asked for; a field it marked
     * required is required. Reading the configuration rather than restating it
     * is what stops the settings screen from becoming decorative.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ClientSettings $settings, Tenant $tenant, ?Client $client = null): array
    {
        $types = [
            'first_name' => ['string', 'max:100'],
            'last_name' => ['string', 'max:100'],
            'preferred_name' => ['string', 'max:100'],
            'mobile' => ['string', 'max:32'],
            'email' => ['email', 'max:255'],
            'date_of_birth' => ['date', 'before:today'],
            'gender' => ['string', 'max:40'],
            'address' => ['string', 'max:255'],
            'city' => ['string', 'max:120'],
            'state' => ['string', 'max:120'],
            'postal_code' => ['string', 'max:20'],
            'country' => [Rule::in(array_keys(config('locations.countries')))],
            'notes' => ['string', 'max:5000'],
            'preferred_location' => ['integer'],
            'preferred_staff' => ['integer'],
            'avatar' => ['string', 'max:255'],
        ];

        $rules = [
            'status' => ['required', Rule::in(array_keys(config('clients.statuses')))],
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['integer'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer'],
            'comm_email' => ['nullable', 'boolean'],
            'comm_sms' => ['nullable', 'boolean'],
            'comm_phone' => ['nullable', 'boolean'],
            'marketing_email' => ['nullable', 'boolean'],
            'marketing_sms' => ['nullable', 'boolean'],
            'confirm_duplicate' => ['nullable', 'boolean'],

            /**
             * Contact rows. Keyed by a token the form invented, so the rules
             * address them with a wildcard rather than by position.
             */
            'phones' => ['nullable', 'array', 'max:'.config('clients.max_phones')],
            'phones.*.number' => ['nullable', 'string', 'max:32'],
            'phones.*.country' => ['nullable', Rule::in(array_keys(config('locations.countries')))],
            'phones.*.type' => ['nullable', Rule::in(array_keys(config('clients.phone_types')))],
            'phones.*.priority' => ['nullable', Rule::in(['primary', 'secondary'])],

            'emails' => ['nullable', 'array', 'max:'.config('clients.max_emails')],
            'emails.*.email' => ['nullable', 'email', 'max:255'],
            'emails.*.type' => ['nullable', Rule::in(array_keys(config('clients.email_types')))],
            'emails.*.priority' => ['nullable', Rule::in(['primary', 'secondary'])],
        ];

        foreach ($settings->orderedFields() as $field) {
            $key = $field['key'];

            // A field the business does not collect is not validated, and is
            // dropped on save — accepting it here would store data the
            // business has said it does not want.
            if (! $field['enabled'] || ! isset($types[$this->inputName($key)])) {
                continue;
            }

            $name = $this->inputName($key);

            $rules[$name] = array_merge(
                [$field['required'] ? 'required' : 'nullable'],
                $types[$name],
            );
        }

        // Scoped to this business's own records, for the same reason every
        // other module does it: a bare exists rule would accept a stranger's
        // id and attach it to a client.
        if (isset($rules['preferred_location'])) {
            $rules['preferred_location'][] = Rule::exists('locations', 'id')
                ->where(fn ($q) => $q->where('tenant_id', $tenant->getTenantKey()));
        }

        if (isset($rules['preferred_staff'])) {
            $rules['preferred_staff'][] = Rule::exists('staff', 'id')
                ->where(fn ($q) => $q->where('tenant_id', $tenant->getTenantKey()));
        }

        $validator = Validator::make($request->all(), $rules, [
            'first_name.required' => __('clients.module.validation.first_name_required'),
            'email.email' => __('clients.module.validation.email_invalid'),
            'emails.*.email.email' => __('clients.module.validation.email_invalid'),
            'date_of_birth.before' => __('clients.module.validation.dob_past'),
            'preferred_location.exists' => __('clients.validation.location_invalid'),
            'preferred_staff.exists' => __('clients.validation.staff_invalid'),
        ]);

        /**
         * The same number or address twice on one client.
         *
         * Refused rather than quietly de-duplicated: the person typing meant
         * to record two contacts, and silently keeping one would leave them
         * believing both were saved. Compared on digits alone, so
         * "+1 202-555-1043" and "12025551043" are recognised as one number.
         */
        $validator->after(function ($validator) use ($request, $settings) {
            $this->rejectRepeats(
                $validator,
                collect($request->input('phones', []))->map(fn ($row) => Client::compareNumber($row['number'] ?? '')),
                'phones',
                __('clients.module.validation.phone_repeated')
            );

            $this->rejectRepeats(
                $validator,
                collect($request->input('emails', []))->map(fn ($row) => Str::lower(trim((string) ($row['email'] ?? '')))),
                'emails',
                __('clients.module.validation.email_repeated')
            );

            /**
             * A required contact field is required of the rows, not of the
             * hidden cache column — which is written from them and so is
             * always empty at this point on a new client.
             */
            foreach ([['mobile', 'phones', 'number'], ['email', 'emails', 'email']] as [$key, $group, $valueKey]) {
                if (! $settings->field($key)['required']) {
                    continue;
                }

                $given = collect($request->input($group, []))
                    ->filter(fn ($row) => filled($row[$valueKey] ?? null));

                if ($given->isEmpty()) {
                    $validator->errors()->add($group, __('clients.module.validation.'.$key.'_required'));
                }
            }
        });

        return $validator->validate();
    }

    /**
     * Flag any value that appears more than once in a contact group.
     *
     * @param  Collection<int|string, string>  $values
     */
    private function rejectRepeats($validator, $values, string $group, string $message): void
    {
        $seen = [];

        foreach ($values as $key => $value) {
            if ($value === '') {
                continue;
            }

            if (in_array($value, $seen, true)) {
                $validator->errors()->add($group.'.'.$key, $message);

                continue;
            }

            $seen[] = $value;
        }
    }

    /**
     * Store the contact rows, and mark which of each is primary.
     *
     * Only for the groups the business collects: a business that switched
     * email off should not be quietly storing addresses because a stale form
     * posted them. The model settles priority and writes the primary back to
     * the client — this only decides which rows to hand it.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncContacts(Client $client, array $data, ClientSettings $settings): void
    {
        if ($settings->field('mobile')['enabled']) {
            $client->syncPhones($this->withPrimary($data['phones'] ?? []));
        }

        if ($settings->field('email')['enabled']) {
            $client->syncEmails($this->withPrimary($data['emails'] ?? []));
        }
    }

    /**
     * Turn each row's priority into the flag the model stores.
     *
     * The form guarantees one Primary and the model settles it again anyway,
     * so a posted set with none — an old tab, a script, JavaScript off —
     * still saves with the first row primary rather than with none.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withPrimary(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row) => $row + ['is_primary' => ($row['priority'] ?? null) === 'primary'])
            ->values()
            ->all();
    }

    /** The form field name for a catalogue key. */
    private function inputName(string $key): string
    {
        return match ($key) {
            'preferred_location' => 'preferred_location',
            'preferred_staff' => 'preferred_staff',
            default => $key,
        };
    }

    /**
     * Only what the business collects, and only real columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data, ClientSettings $settings): array
    {
        $values = ['status' => $data['status']];

        foreach ($settings->orderedFields() as $field) {
            $key = $field['key'];

            if (! $field['enabled']) {
                continue;
            }

            $column = match ($key) {
                'preferred_location' => 'preferred_location_id',
                'preferred_staff' => 'preferred_staff_id',
                'avatar' => 'avatar_path',
                default => $key,
            };

            $input = $this->inputName($key);

            if (! array_key_exists($input, $data)) {
                continue;
            }

            $values[$column] = $data[$input] ?: null;
        }

        // The project capitalisation rule. Emails are excluded: case there is
        // not the business's to choose.
        return InputCase::apply($values, [
            'first_name', 'last_name', 'preferred_name', 'gender',
            'address', 'city', 'state', 'notes',
        ]);
    }

    /**
     * Communication and consent.
     *
     * The moment consent is first given is stamped once and never moved: the
     * date a client agreed is a fact about that day, and re-stamping it on
     * every later save would quietly erase when they actually said yes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function consent(array $data, Request $request, ?Client $client = null): array
    {
        $values = [];

        foreach (['comm_email', 'comm_sms', 'comm_phone', 'marketing_email', 'marketing_sms'] as $switch) {
            $values[$switch] = (bool) ($data[$switch] ?? false);
        }

        $consented = $values['marketing_email'] || $values['marketing_sms'];
        $alreadyRecorded = $client?->consent_recorded_at !== null;

        if ($consented && ! $alreadyRecorded) {
            $values['consent_recorded_at'] = now();
            $values['consent_recorded_by'] = $request->user()->id;
        }

        // Withdrawing clears the record, because keeping "consented on 3 March"
        // beside "no marketing" states two things that cannot both be true now.
        if (! $consented) {
            $values['consent_recorded_at'] = null;
            $values['consent_recorded_by'] = null;
        }

        return $values;
    }

    /**
     * Ids that actually belong to this business.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function ownedIds(Tenant $tenant, string $relation, array $ids): array
    {
        return $tenant->{$relation}()
            ->whereIn('id', collect($ids)->map(fn ($id) => (int) $id)->filter()->all())
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formState(Tenant $tenant): array
    {
        $settings = ClientSettings::forTenant($tenant);

        return [
            'client' => null,
            'settings' => $settings,
            'fields' => collect($settings->orderedFields())->filter(fn (array $f) => $f['enabled'])->values()->all(),
            'locations' => $tenant->locations()->active()->inDisplayOrder()->get(),
            'staff' => $tenant->staff()->where('is_active', true)->orderBy('first_name')->get(),
            'preferences' => $settings->preferences_enabled ? $tenant->clientPreferences()->active()->inOrder()->get() : collect(),
            'tags' => $settings->tags_enabled ? $tenant->clientTags()->active()->inOrder()->get() : collect(),
        ];
    }

    /**
     * The narrowest scope that could hold the permission, not the widest.
     *
     * A receptionist holds clients.view at their location and a service
     * provider at the clients assigned to them; demanding 'all' would refuse
     * both, and refusing the front desk access to clients would be refusing
     * them the job. Which *records* a scope reaches is a separate question,
     * answered by the query — this only answers whether the door opens.
     */
    private function authorizeClients(Request $request, string $permission, string $scope = 'own'): void
    {
        abort_unless($request->user()->hasPermission($permission, $scope), 403);
    }

    private function assertOwned(Client $client, Tenant $tenant): void
    {
        abort_unless($client->tenant_id === $tenant->getTenantKey(), 404);
    }
}
