<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\ClientSettings;
use App\Models\LoyaltyReward;
use App\Models\LoyaltySettings;
use App\Support\LoyaltyPoints;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * One client's rewards balance.
 *
 * The tab itself is rendered with the profile — it is a view of one record
 * rather than a place of its own, the same as every other tab there. What
 * lives here is the one thing that changes a balance by hand.
 *
 * Adjusting points is issuing the business money, which is why it is its own
 * permission and why every adjustment is signed. §7 asks for the points, the
 * type, the reason, the note, who and when; the ledger row carries all seven
 * and nothing can edit one afterwards.
 */
class ClientLoyaltyController extends Controller
{
    /**
     * Clients → Loyalty: everybody who has joined the scheme.
     *
     * A list of members rather than of clients, which is why it is its own
     * screen and not a filter on the client list: what it answers is "who is
     * in the programme and what are they sitting on", and the columns that
     * answer it — balance, lifetime, redeemed — mean nothing against somebody
     * who never joined.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('clients.view', 'own'), 403);

        $settings = LoyaltySettings::forTenant($request->user()->tenant);

        return view('clients.loyalty.index', [
            'settings' => $settings,
            'filters' => $this->listFilters($request),
            /* Whether this reader may go and switch it on, so the empty
               state offers the link only to somebody it would work for. */
            'canConfigure' => (bool) $request->user()?->hasPermission('loyalty.manage_settings', 'own'),
        ]);
    }

    /**
     * Clients → Loyalty → one client.
     *
     * The Loyalty module's own page for a member, rather than a jump into the
     * client profile's Rewards tab. They show the same balance because they
     * render the same partial, but they are opened for different reasons: the
     * profile tab is one of nine things somebody looks at while reading a
     * client, and this is the screen somebody is on when the job in hand is
     * the rewards themselves.
     */
    public function show(Request $request, Client $client): View
    {
        abort_unless($request->user()?->hasPermission('clients.view', 'own'), 403);
        abort_unless($client->tenant_id === $request->user()->tenant?->getTenantKey(), 404);

        $settings = LoyaltySettings::forTenant($request->user()->tenant);

        /* The scheme is off, so there is no module to be inside. The list
           says so and offers the switch; a member's page cannot. */
        abort_unless($settings->is_enabled, 404);

        $client->load(['preferredLocation', 'loyaltyEnrolledBy', 'loyaltyEnrollmentLocation']);

        return view('clients.loyalty.show', [
            'client' => $client,
            /* The header writes names the way this business writes them, and
               reads the format from here rather than fetching the settings
               again for every client it draws. */
            'clientSettings' => ClientSettings::forTenant($request->user()->tenant),
            'loyalty' => $settings,
            'loyaltySummary' => LoyaltyPoints::summaryFor($client, $settings),
            /* The relations the history table reads. Eager, because a line
               names its appointment, its branch and the stylist who did the
               work. */
            'loyaltyHistory' => LoyaltyPoints::historyFor($client)->load(['booking.staff', 'location', 'createdBy']),
            'loyaltyRewards' => LoyaltyReward::query()->active()->inOrder()->with('service')->get(),
            'loyaltyExpiryDays' => LoyaltyPoints::expiringWindowDays(),
            /* Moving a balance by hand is its own authority, separate from
               reading one. */
            'canAdjustPoints' => (bool) $request->user()?->hasPermission('loyalty.adjust_points', 'own'),
        ]);
    }

    /**
     * The members the grid asks for, as JSON.
     *
     * The balances are summed in one query across the whole page rather than
     * read per row: four figures per client through the points engine is
     * four queries each, and a page of twenty-five is a hundred round trips
     * for a list somebody scrolls.
     */
    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('clients.view', 'own'), 403);

        $tenant = $request->user()->tenant;
        $settings = LoyaltySettings::forTenant($tenant);

        abort_unless($settings->is_enabled, 404);

        $filters = $this->listFilters($request);

        $members = Client::query()
            ->whereNotNull('loyalty_enrolled_at')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';

                $query->where(fn (Builder $q) => $q
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('loyalty_member_id', 'like', $like)
                    ->orWhereRaw("concat_ws(' ', first_name, last_name) like ?", [$like]));
            })
            /* Newest members first: the list is opened to see who has just
               joined far more often than to find somebody, and finding
               somebody is what the search box is for. */
            ->orderByDesc('loyalty_enrolled_at')
            ->paginate(
                perPage: min(100, max(1, (int) $request->query('size', 25))),
                page: max(1, (int) $request->query('page', 1)),
            );

        $totals = $this->balancesFor($members->getCollection()->pluck('id')->all());

        return response()->json([
            'last_page' => $members->lastPage(),
            'last_row' => $members->total(),
            'total' => $members->total(),
            'data' => $members->getCollection()
                ->map(fn (Client $client) => $this->memberRow($client, $totals[$client->id] ?? []))
                ->all(),
        ]);
    }

    /**
     * Every figure the list needs, for a whole page of members, in one query.
     *
     * Expiry is applied to the balance and deliberately not to the lifetime
     * columns: "lifetime earned" is a history rather than a balance, and
     * points that have since lapsed were still earned.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function balancesFor(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        return ClientLoyaltyPoint::query()
            ->selectRaw('client_id')
            ->selectRaw('sum(case when expires_at is null or expires_at > now() then points else 0 end) as balance')
            ->selectRaw('sum(case when points > 0 then points else 0 end) as earned')
            ->selectRaw("sum(case when type = 'redeemed' then -points else 0 end) as redeemed")
            ->selectRaw('max(created_at) as last_activity')
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id')
            ->map(fn ($row) => [
                'balance' => max(0, (int) $row->balance),
                'earned' => (int) $row->earned,
                'redeemed' => (int) $row->redeemed,
                'last_activity' => $row->last_activity,
            ])
            ->all();
    }

    /**
     * One member, in the shape the grid reads.
     *
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    private function memberRow(Client $client, array $totals): array
    {
        $last = $totals['last_activity'] ?? null;

        return [
            'id' => $client->id,
            'name' => $client->displayName(),
            'member_id' => $client->loyalty_member_id ?? '—',
            'mobile' => $client->mobile ?: '—',
            'email' => $client->email ?: '—',
            /* A string and a sibling class, which is the contract the shared
               grid's badge formatter reads: it escapes the value and takes
               the tone from `<field>_class`. An array here rendered as
               nothing at all, which is why the column looked missing. */
            'status' => $client->loyaltyStatusLabel() ?? '—',
            'status_class' => $client->loyaltyStatusClass(),
            'enrolled' => $client->loyalty_enrolled_at?->translatedFormat('j M Y') ?? '—',
            'balance' => number_format($totals['balance'] ?? 0),
            'earned' => number_format($totals['earned'] ?? 0),
            'redeemed' => number_format($totals['redeemed'] ?? 0),
            /* Tiers are not built. An empty column is the honest answer —
               inventing "Member" for everybody would be a tier nobody set. */
            'tier' => '—',
            'last_activity' => $last === null ? '—' : Carbon::parse($last)->translatedFormat('j M Y'),
            /* What a click on the row opens: the module's own page for this
               member, not a jump into the client profile's tab strip. The
               grid reads `url`; without it the row is inert. */
            'url' => route('clients.loyalty.show', $client),
            /* `menu`, not `actions`: the grid builds its panel from this key,
               so an `actions` array left it with nothing to draw — which is
               what read as a menu being clipped. */
            'menu' => [
                ['label' => __('common.view'), 'url' => route('clients.loyalty.show', $client)],
                [
                    'label' => __('loyalty.members.open_profile'),
                    'url' => route('clients.show', $client),
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function listFilters(Request $request): array
    {
        return ['search' => trim((string) $request->query('search', ''))];
    }

    public function adjust(Request $request, Client $client): RedirectResponse
    {
        abort_unless($client->tenant_id === $request->user()->tenant?->getTenantKey(), 404);
        abort_unless($request->user()->hasPermission('loyalty.adjust_points', 'own'), 403);

        $settings = LoyaltySettings::forTenant($request->user()->tenant);

        /* A scheme that is switched off does not quietly accept points. The
           form is not rendered in that state either, but a POST that arrived
           anyway must be refused rather than banked. */
        abort_unless($settings->is_enabled, 403);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['add', 'remove'])],
            /* Positive, always. The direction is a separate answer, so
               "-100" typed into a box labelled Points cannot mean the
               opposite of what the person choosing "Remove" intended. */
            'points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', Rule::in(config('loyalty.adjustment_reasons'))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        LoyaltyPoints::adjust(
            client: $client,
            points: (int) $data['points'],
            isAddition: $data['direction'] === 'add',
            reason: $data['reason'],
            note: $data['note'] ?? null,
            userId: $request->user()->id,
        );

        return redirect()
            ->route('clients.show', $client)
            ->withFragment('rewards')
            ->with('toast', [
                'type' => 'success',
                'message' => __('loyalty.client.adjusted'),
            ]);
    }
}
