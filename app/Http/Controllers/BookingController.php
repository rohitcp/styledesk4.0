<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\BookingConfirmationMail;
use App\Mail\BookingPaymentLinkMail;
use App\Models\Booking;
use App\Models\BookingLead;
use App\Models\BookingPaymentLink;
use App\Models\BookingService;
use App\Models\Client;
use App\Models\ClientSettings;
use App\Models\Location;
use App\Models\Promotion;
use App\Models\ReasonCode;
use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\Staff;
use App\Models\TipSettings;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentRequest;
use App\Support\BookingAvailability;
use App\Support\BookingDuplicates;
use App\Support\BookingTotals;
use App\Support\ClientActivityLog;
use App\Support\ClientBookingContext;
use App\Support\ClientInsights;
use App\Support\Currencies;
use App\Support\Money;
use App\Support\Promotions;
use App\Support\ResourceAllocator;
use App\Support\TimeFormat;
use App\Support\Tips;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * Appointments: the diary, and the screen that adds to it.
 *
 * The booking screen is one page rather than a wizard, because the five
 * decisions it holds — what, with whom, when, how it is paid for and how the
 * client is told — are not sequential. A receptionist with a client on the
 * phone answers them in whatever order the client says them, and a wizard
 * makes that impossible.
 */
class BookingController extends Controller
{
    /**
     * The views a desk actually works in.
     *
     * `primary` is the four that sit across the top; the rest live behind a
     * More menu. A page with nine tabs across it is a page nobody reads the
     * end of, and the four that matter are the four a receptionist uses
     * between nine and six.
     */
    /**
     * How far ahead an appointment still counts as "early".
     *
     * An hour: near enough that a client turning up is early rather than
     * mistaken, and far enough that the desk can see who is coming next.
     */
    private const ARRIVAL_WINDOW = 60;

    private const TABS = [
        'today' => ['primary' => true],
        'next-3' => ['primary' => true],
        'month' => ['primary' => true],
        'check-in' => ['primary' => true],
        'all' => ['primary' => false],
        'completed' => ['primary' => false],
        'cancelled' => ['primary' => false],
        'no-shows' => ['primary' => false],
        'declined' => ['primary' => false],
    ];

    public function index(Request $request): View
    {
        $this->allow($request, 'calendar.view');

        $filters = $this->filters($request);
        $month = CarbonImmutable::parse($filters['month'].'-01');

        return view('bookings.index', [
            'filters' => $filters,
            'tabs' => self::TABS,
            'counts' => $this->tabCounts($request),
            /* Only where it is worth the room: five numbers above a table
               the reader is about to look at anyway is noise on every tab
               except the one they work in all day. */
            'summary' => $filters['tab'] === 'today' ? $this->todaySummary($request) : [],
            'month' => $month,
            'staff' => Staff::query()->where('is_active', true)->orderBy('first_name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'hasBookings' => Booking::query()->exists(),
        ]);
    }

    /** The rows the listing grid asks for, as JSON. */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request, 'calendar.view');

        $filters = $this->filters($request);

        $bookings = Booking::query()
            ->with(['client', 'staff', 'services', 'createdBy', 'location'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $q) use ($filters) {
                $like = '%'.$filters['search'].'%';

                $q->where('guest_name', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('client', fn (Builder $c) => $c
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('email', 'like', $like));
            }))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['payment'] !== '', fn (Builder $query) => $query->where('payment_status', $filters['payment']))
            ->when($filters['staff'] !== '', fn (Builder $query) => $query->where('staff_id', $filters['staff']))
            ->when($filters['location'] !== '', fn (Builder $query) => $query->where('location_id', $filters['location']))
            ->when($filters['service'] !== '', fn (Builder $query) => $query->whereHas(
                'services', fn (Builder $line) => $line->where('service_id', $filters['service'])
            ))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('date', $filters['date']))
            /* The tab last, so a filter the reader set inside it narrows
               what the tab shows rather than replacing it. */
            ->tap(fn (Builder $query) => $this->forTab($query, $filters['tab'], $filters['month']))
            ->tap(fn (Builder $query) => $this->orderFor($query, $filters['tab']))
            ->paginate(
                perPage: min(100, max(1, (int) $request->query('size', 25))),
                page: max(1, (int) $request->query('page', 1)),
            );

        return response()->json([
            'last_page' => $bookings->lastPage(),
            'last_row' => $bookings->total(),
            'total' => $bookings->total(),
            'data' => $bookings->getCollection()->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'name' => $booking->clientName(),
                /* Its own column rather than a badge beside the name: the
                   reference is what a booking is quoted by over the phone,
                   and a column is what a reader scans. */
                'reference' => $booking->reference,
                'initials' => $this->initialsOf($booking->clientName()),
                'date' => $booking->date->translatedFormat('j M Y'),
                'time' => $booking->timeLabel(),
                'services' => $booking->services->pluck('name')->implode(', '),
                'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
                'total' => Money::format($booking->total_minor / 100, $booking->currency_code),
                'duration' => trans_choice('bookings.summary.minutes', (int) $booking->minutes, ['count' => (int) $booking->minutes]),
                'payment' => $booking->paymentStatusLabel(),
                'payment_class' => $booking->paymentStatusClass(),
                'status' => $booking->statusLabel(),
                'status_class' => $booking->statusClass(),
                /* Who took it. A person's name where a person took it, and
                   where the booking came in by itself, what it came in
                   through — "Online booking" is an answer; a blank is not. */
                'booked_by' => $booking->createdBy?->name
                    ?? __('bookings.sources.'.($booking->source ?: 'other')),
                /* The booking's own page. It pointed at the client while
                   there was nothing else to point at; there is now, and a
                   row that opens somebody's profile instead of the
                   appointment it names is a row that lies. */
                'location' => $booking->location?->name,
                /* Whether they are here yet, and — on the queue — how far off
                   the appointment time they are. "12 min late" is what the
                   desk acts on; the scheduled time alone makes them do the
                   arithmetic themselves. */
                'checkin' => $this->checkInLabel($booking),
                'arrival' => $this->arrivalLabel($booking),
                'arrival_class' => $this->arrivalClass($booking),
                'url' => route('bookings.show', $booking),
                'menu' => $this->rowMenu($booking),
            ])->all(),
        ]);
    }

    /**
     * The New Booking screen.
     *
     * Everything it offers is handed over with the page rather than fetched
     * as the reader types: a salon's services and team are a page of records,
     * not a catalogue, and a search that goes to the server for each keystroke
     * is slower than one that does not need to.
     */
    public function create(Request $request): View
    {
        $this->allow($request, 'appointments.create');

        $currency = Currencies::resolve();

        /* A booking being picked back up. Continue Booking on a lead reopens
           this screen with everything that was saved into it, and finishing
           it converts that lead — under its own reference — rather than
           starting a second attempt at the same appointment. */
        $lead = BookingLead::query()
            ->with('client')
            ->whereNotIn('status', BookingLead::SETTLED)
            ->find($request->query('lead'));

        /* A booking started from somebody's profile arrives with them
           already chosen: the receptionist asked for it from a page that
           knows who it is for, and asking again is asking twice. */
        /* Either spelling of the parameter. Only the id travels — the
           client is read back from it here, so a profile left open in a tab
           since Tuesday cannot carry Tuesday's phone number into today's
           booking. */
        $forClient = $lead?->client ?? Client::query()
            ->with(['favoriteServices', 'preferredStaff', 'preferredLocation'])
            ->find($request->query('client') ?? $request->query('client_id'));

        return view('bookings.create', [
            'client' => $forClient === null ? null : [
                'id' => $forClient->id,
                'name' => $forClient->displayName(),
                'initials' => $forClient->initials(),
                'ref' => $forClient->client_ref,
                'mobile' => $forClient->mobile,
                'email' => $forClient->email,
                /* What this client is known to want, and where and with whom
                   they usually have it. Carried so the screen opens on their
                   answers rather than on the defaults — which is the whole
                   point of starting a booking from their profile. */
                'favorite_service_ids' => $forClient->favoriteServices->pluck('id')->values(),
                'preferred_staff_id' => $forClient->preferred_staff_id,
                'preferred_location_id' => $forClient->preferred_location_id,
            ],
            /* Everything that was answered before the call dropped. The
               screen is restored from this rather than started again: a
               receptionist picking somebody else's call back up should not
               have to ask which branch, which stylist and what time for a
               second time. */
            'lead' => $lead === null ? null : [
                'id' => $lead->id,
                'reference' => $lead->reference,
                'status' => $lead->status,
                'status_label' => $lead->statusLabel(),
                'client' => $lead->client === null ? null : [
                    'id' => $lead->client->id,
                    'name' => $lead->client->displayName(),
                    'initials' => $lead->client->initials(),
                    'ref' => $lead->client->client_ref,
                    'mobile' => $lead->client->mobile,
                    'email' => $lead->client->email,
                ],
                'guest_name' => $lead->guest_name,
                'guest_phone' => $lead->guest_phone,
                'guest_email' => $lead->guest_email,
                'service_ids' => collect($lead->services ?? [])->pluck('id')->filter()->values()->all(),
                'location_id' => $lead->location_id,
                'staff_id' => $lead->staff_id,
                'date' => $lead->expected_date?->toDateString(),
                'starts_at' => $lead->starts_at === null ? null : substr((string) $lead->starts_at, 0, 5),
                'source' => $lead->source,
                'payment_type' => $lead->payment_type,
                /* Back as the field shows it, not as it is stored: the form
                   asks for 25, the column holds 2500. */
                'deposit' => $lead->deposit_minor > 0
                    ? number_format($lead->deposit_minor / 100, 2, '.', '')
                    : null,
                'collection_method' => $lead->collection_method,
                'waiver_reason' => $lead->waiver_reason,
                'confirmation' => $lead->confirmation,
                'notes' => $lead->notes,
                'client_note' => $lead->client_note,
                'current_step' => $lead->current_step,
            ],
            'walkIn' => $request->boolean('walk-in'),
            'currency' => $currency,
            'services' => Service::query()->active()->with(['prices', 'resources', 'locations'])->inOrder()->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category_id' => $service->service_category_id,
                    'minutes' => (int) $service->duration_minutes,
                    'price' => $service->priceLabel($currency),
                    'price_minor' => $service->priceMinorFor($currency, 'card'),
                    /* Both, so the screen can requote itself the moment
                       somebody says the client is paying cash — without
                       going back to the server for a number it already
                       had. */
                    'cash_price_minor' => $service->priceMinorFor($currency, 'cash'),
                    'two_prices' => $service->hasTwoPricesIn($currency),
                    /* The chair, room or machine the service needs. Shown in
                       the summary because a booking that quietly needs the
                       only colour bar is a booking somebody has to know
                       about. */
                    'resources' => $service->resources->pluck('name')->values(),
                    /* Where it is offered. Empty means everywhere — the
                       convention Service::offeredAt() already reads — so the
                       screen filters on a non-empty list and leaves the rest
                       alone. */
                    'location_ids' => $service->locations->pluck('id')->values(),
                    /* What this service insists on being paid up front. The
                       rule rather than the figure: a percentage of a bill
                       that then grows a tip is a percentage of the new bill,
                       and the screen has to be able to say so without asking
                       the server again. Null for the services that ask for
                       nothing, which is most of them. */
                    'deposit' => $service->requiredDepositFor($currency),
                ])->values(),
            /* Only the categories something is actually offered in: a list of
               every category the product ships with is a filter that mostly
               returns nothing. */
            'categories' => ServiceCategory::query()
                ->whereIn('id', Service::query()->active()->whereNotNull('service_category_id')
                    ->distinct()->pluck('service_category_id'))
                ->orderBy('name')
                ->pluck('name', 'id'),
            /* Only people who actually do services: booking a receptionist is
               a mistake the list should not offer. */
            'staff' => Staff::query()->where('is_active', true)->where('provides_services', true)
                ->orderBy('first_name')->get()
                ->map(fn (Staff $member) => [
                    'id' => $member->id,
                    'name' => $member->displayName(),
                    'initials' => $member->initials(),
                    'title' => $member->job_title,
                    /* Which branch they work at. Null is not "nowhere": staff
                       without a location are the ones who work across all of
                       them, and hiding them when a branch is chosen would
                       empty the list for most businesses. */
                    'location_id' => $member->location_id,
                ])->values(),
            'locations' => Location::query()->orderByDesc('is_primary')->orderBy('name')->get(),
            /* What the third column needs to ask for money: the ways this
               business can be paid, and the tax the summary has to show
               before a booking exists to read it from. */
            'methods' => $this->paymentMethods(),
            'tax' => [
                'rate' => (float) (tenant()?->default_tax_rate ?? 0),
                'behavior' => (string) (tenant()?->default_tax_behavior ?? 'none'),
            ],
        ]);
    }

    /**
     * Clients matching what has been typed into the booking screen.
     *
     * Asked of the server rather than shipped with the page: a business has
     * as many clients as it has, and the list is the one thing on this screen
     * that does not fit in a page.
     */
    public function clients(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.$term.'%';

        return response()->json([
            'data' => Client::query()
                ->where(fn (Builder $q) => $q
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('preferred_name', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('client_ref', 'like', $like))
                ->orderBy('first_name')
                ->limit(8)
                ->get()
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->displayName(),
                    'initials' => $client->initials(),
                    'ref' => $client->client_ref,
                    'mobile' => $client->mobile,
                    'email' => $client->email,
                ])->all(),
        ]);
    }

    /**
     * Add a client from inside the booking screen.
     *
     * Four fields, because that is what taking a booking actually needs — the
     * rest of the client record can be filled in later from their profile,
     * and a receptionist with somebody on the phone will not fill it in now.
     *
     * The duplicate check is the same one the full form runs, and for the
     * same reason: taking a booking is exactly when a second record for an
     * existing client gets created, and it is the worst moment for it,
     * because their history is what makes this screen fast. It warns and
     * never blocks — two people can share a phone, and a wrongly merged
     * history is not something a receptionist can unpick.
     */
    public function storeClient(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');
        abort_unless($request->user()->hasPermission('clients.create', 'own'), 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ]);

        /* One of the two, so the confirmation has somewhere to go. */
        if (blank($data['email'] ?? null) && blank($data['mobile'] ?? null)) {
            throw ValidationException::withMessages([
                'mobile' => __('bookings.new_client.needs_contact'),
            ]);
        }

        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);

        $candidate = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'emails' => array_filter([$data['email'] ?? null]),
            'phones' => array_filter([$data['mobile'] ?? null]),
        ];

        if (! ($data['confirm_duplicate'] ?? false) && $settings->duplicate_warning) {
            $matches = Client::possibleDuplicates(
                $tenant->getTenantKey(), $candidate, $settings->duplicate_rules ?? [],
            );

            if ($matches->isNotEmpty()) {
                return response()->json([
                    'duplicates' => $matches->map(fn (Client $match) => [
                        'id' => $match->id,
                        'name' => $match->displayName($settings->name_format),
                        'initials' => $match->initials(),
                        'ref' => $match->client_ref,
                        'mobile' => $match->mobile,
                        'email' => $match->email,
                    ])->values()->all(),
                ], 409);
            }
        }

        $client = DB::transaction(function () use ($tenant, $data) {
            $client = $tenant->clients()->create([
                'client_ref' => Client::nextRef($tenant->getTenantKey()),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'status' => Client::STATUS_ACTIVE,
            ]);

            /* The contact rows the rest of the app reads, so a client added
               here is not a client with a phone number the phones table has
               never heard of. */
            if (filled($data['mobile'] ?? null)) {
                $client->syncPhones([['number' => $data['mobile'], 'type' => 'mobile', 'is_primary' => true]]);
            }

            if (filled($data['email'] ?? null)) {
                $client->syncEmails([['email' => $data['email'], 'type' => 'personal', 'is_primary' => true]]);
            }

            return $client->fresh();
        });

        /* The same shape the search returns, so the screen adopts it without
           knowing where it came from. */
        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->displayName(),
                'initials' => $client->initials(),
                'ref' => $client->client_ref,
                'mobile' => $client->mobile,
                'email' => $client->email,
            ],
        ], 201);
    }

    /**
     * Is this walk-in already on file?
     *
     * A walk-in is booked without a client record, which is right for
     * somebody who came in once and is wrong for a regular whose name the
     * receptionist typed rather than searched for. The second is easy to do
     * and expensive to undo: their history, their preferences and their
     * preferred stylist all stay attached to the record nobody used, and the
     * desk ends up with two of the same person.
     *
     * So the number and the address are checked against the book as they are
     * typed, using the same duplicate engine the client form runs — one set
     * of rules, which the business configures once.
     *
     * A warning, never a block. Two people share a phone; a family shares an
     * address; and a wrongly merged history is not something a receptionist
     * can unpick. The reader decides which of the two this is.
     *
     * Posted rather than asked in the query string: a phone number and an
     * email address in a URL is personal data written into every access log
     * between here and the server.
     */
    /**
     * Whether this client is already booked for these services that day.
     *
     * Asked as the screen is filled in — after the client, after the
     * services, after the time — and once more as Confirm is pressed, because
     * the booking somebody else took while this one was being typed is
     * exactly the one worth catching.
     *
     * A warning, never a refusal: a client really does come back at three for
     * the blow-dry they had at ten. What is refused is the room being used
     * twice, and that is availability's job rather than this one's.
     */
    public function duplicates(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'client_id' => ['nullable', 'integer', $this->ownRow('clients', $request)],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', $this->ownRow('services', $request)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'minutes' => ['nullable', 'integer', 'min:0'],
            'location_id' => ['nullable', 'integer', $this->ownRow('locations', $request)],
            'booking_id' => ['nullable', 'integer'],
        ]);

        $services = array_values($data['services'] ?? []);
        $minutes = (int) ($data['minutes'] ?? 0) ?: BookingDuplicates::minutesOf($services);

        $matches = BookingDuplicates::on(
            $data['client_id'] ?? null,
            $services,
            $data['date'] ?? null,
            $data['starts_at'] ?? null,
            $minutes,
            $data['location_id'] ?? null,
            $data['booking_id'] ?? null,
        );

        return response()->json([
            'level' => BookingDuplicates::highest($matches, $services, $data['starts_at'] ?? null, $minutes, $data['location_id'] ?? null),
            'matches' => $matches
                ->map(fn (Booking $booking) => BookingDuplicates::describe(
                    $booking,
                    $services,
                    $data['starts_at'] ?? null,
                    $minutes,
                    $data['location_id'] ?? null,
                ))
                ->values()
                ->all(),
        ]);
    }

    public function matchClient(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);

        /* Nothing to match on. A name alone is not enough to claim two people
           are one — this business has more than one Sarah — and the rules the
           duplicate engine runs on are the number and the address. */
        if (blank($data['mobile'] ?? null) && blank($data['email'] ?? null)) {
            return response()->json(['matches' => []]);
        }

        /* The business turned the warning off. Answering anyway would be this
           screen overruling a setting every other screen obeys. */
        if (! $settings->duplicate_warning) {
            return response()->json(['matches' => []]);
        }

        $matches = Client::possibleDuplicates(
            $tenant->getTenantKey(),
            [
                'first_name' => $data['name'] ?? null,
                'emails' => array_filter([$data['email'] ?? null]),
                'phones' => array_filter([$data['mobile'] ?? null]),
            ],
            $settings->duplicate_rules ?? [],
        );

        return response()->json([
            'matches' => $matches->map(fn (Client $match) => [
                'id' => $match->id,
                'name' => $match->displayName($settings->name_format),
                'initials' => $match->initials(),
                'ref' => $match->client_ref,
                'mobile' => $match->mobile,
                'email' => $match->email,
            ])->values()->all(),
        ]);
    }

    /**
     * Everything the booking screen knows about one client.
     *
     * Fetched when a client is chosen rather than shipped with the page: it
     * is five questions of history, and asking them for every client in the
     * business up front would be a page nobody waits for.
     */
    public function context(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        return response()->json(
            ClientBookingContext::for($client->load('bookingPreferences'))->toArray(),
        );
    }

    /**
     * The times this booking could start at, for what has been chosen so far.
     *
     * Asked again whenever the location, the services, the staff member or
     * the date changes, because each of them can empty the list — and a list
     * of times that ignores them is a list of appointments the person on the
     * phone will be told about afterwards.
     *
     * Worked out on the server for the same reason the totals are: the
     * browser would have to be handed every rota, closure, block and existing
     * booking to answer it, which is both a slower page and a description of
     * the salon's day given to anyone who opens the console.
     */
    public function availability(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            /* Scoped to this business's own rows, not merely to rows that
               exist: an unscoped `exists` accepts another salon's location id
               and the answer, whatever it came out as, would be an answer
               about their day. */
            'location_id' => ['nullable', 'integer', $this->ownRow('locations', $request)],
            'staff_id' => ['nullable', 'integer', $this->ownRow('staff', $request)],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', $this->ownRow('services', $request)],
            /* The booking being edited does not clash with itself. */
            'ignore' => ['nullable', 'integer'],
        ]);

        $location = isset($data['location_id'])
            ? Location::query()->find($data['location_id'])
            : null;

        /* Cast, not trusted. These arrive as query-string text and the
           `integer` rule only checks that they look like numbers — it does
           not turn "5" into 5, and the availability reader is typed. */
        $availability = BookingAvailability::for(
            $location,
            $data['date'],
            isset($data['staff_id']) ? (int) $data['staff_id'] : null,
            array_map('intval', $data['service_ids'] ?? []),
            isset($data['ignore']) ? (int) $data['ignore'] : null,
        );

        return response()->json($availability + [
            'message' => $availability['reason'] === null
                ? null
                : __('bookings.when.'.$availability['reason']),
        ]);
    }

    /**
     * Which rooms one service could go in, and which are free.
     *
     * Asked by the booking screen whenever the day, the time, the branch or
     * the services change — so it answers for one line at a time, which is
     * the only shape that works for a booking with a massage at ten and a
     * facial at eleven.
     *
     * Unavailable rooms come back rather than being dropped: a receptionist
     * looking for Single Room 03 and not finding it will assume the mapping
     * is wrong, where "unavailable" answers the question they actually had.
     */
    public function resources(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'service_id' => ['required', 'integer', $this->ownRow('services', $request)],
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'location_id' => ['nullable', 'integer', $this->ownRow('locations', $request)],
            /* The booking being edited does not clash with itself. */
            'ignore' => ['nullable', 'integer'],
        ]);

        $service = Service::query()->findOrFail((int) $data['service_id']);

        $options = ResourceAllocator::optionsForService(
            $service,
            $data['date'],
            $data['starts_at'],
            isset($data['location_id']) ? (int) $data['location_id'] : null,
            isset($data['ignore']) ? (int) $data['ignore'] : null,
        );

        $free = $options->firstWhere('available', true);

        return response()->json([
            'options' => $options->all(),
            /* What the screen would pick if nobody chose — the first free
               one in preference order, which for a single massage means a
               single room before a couple room. */
            'assigned' => $free['id'] ?? null,
            /* Whether this service needs a room at all. A service mapped to
               nothing is not a service with no rooms free. */
            'required' => $options->isNotEmpty(),
            'message' => $options->isNotEmpty() && $free === null
                ? __('bookings.resources.none_available')
                : null,
        ]);
    }

    /** A row belonging to the business making the request, and no other. */
    private function ownRow(string $table, Request $request): Exists
    {
        return Rule::exists($table, 'id')
            ->where('tenant_id', $request->user()->tenant?->getTenantKey());
    }

    /**
     * Keep the booking being written down, as it is written.
     *
     * A booking is taken over the phone, and a phone call is interrupted. The
     * screen saves what it has as soon as it knows who the appointment is for
     * — a name is the one thing that makes the rest worth keeping — and saves
     * again as the answers arrive, so a call that drops leaves something the
     * desk can ring back about instead of nothing at all.
     *
     * What it saves into is a lead, not a booking. A booking in progress is
     * not an appointment: it holds no slot, tells nobody anything, and has no
     * business in the diary beside the ones that were actually taken. It
     * lives in Bookings → Leads until somebody confirms it, and `store()`
     * turns it into an appointment then — under the same reference.
     *
     * One row per attempt. The reference is handed out on the first save and
     * every later save writes into the same lead, because a receptionist who
     * has read a number out over the phone has to be able to find it.
     */
    public function autosave(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'lead_id' => ['nullable', 'integer', Rule::exists('booking_leads', 'id')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'source' => ['nullable', Rule::in(config('bookings.sources'))],
            'payment_type' => ['nullable', Rule::in(config('bookings.payment_types'))],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'collection_method' => ['nullable', Rule::in(config('bookings.collection_methods'))],
            /* How the client is paying, which decides which of the two
               prices applies. Card unless somebody says otherwise. */
            'payment_method' => ['nullable', Rule::in(ServicePrice::METHODS)],
            'waiver_reason' => ['nullable', 'string', 'max:300'],
            'confirmation' => ['nullable', Rule::in(config('bookings.confirmations'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_note' => ['nullable', 'string', 'max:2000'],
            /* How far through the five cards the booking has got. */
            'current_step' => ['nullable', Rule::in(config('bookings.lead_steps'))],
        ]);

        /* The one thing that has to be there. A row saved before anybody is
           named is a lead nobody could ever match to a caller, and the queue
           would collect one for every screen that was opened and closed. */
        if (empty($data['client_id']) && trim((string) ($data['guest_name'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'client_id' => __('bookings.validation.who'),
            ]);
        }

        /* Only a lead still being worked on, and exactly the set the screen
           was allowed to open with. One already converted is an appointment,
           and one somebody cancelled or wrote off is a decision the screen
           does not get to reverse by being left open. Any narrower here and a
           lead the desk can reopen is one that says "Not saved" at every
           answer typed into it. */
        $lead = empty($data['lead_id'])
            ? null
            : BookingLead::query()->whereNotIn('status', BookingLead::SETTLED)->find($data['lead_id']);

        abort_if(! empty($data['lead_id']) && $lead === null, 404);

        $currency = Currencies::resolve();
        $services = $this->chosenServices($data['services'] ?? [], $currency);
        $pricedFor = $this->pricedFor($data);
        $prices = $services->map(fn (Service $service) => $this->priceOf($service, $currency, $pricedFor));

        /* The same three answers as a booking taken in one go. A draft that
           is being confirmed comes through here rather than store(), and a
           tip agreed on the screen must not be lost on the way. */
        [$promotion, $discountMinor] = $this->couponFor($data, $services, $prices, $currency, $request);

        $totals = BookingTotals::of($prices, $currency, $discountMinor);

        [$tipPercent, $tipMinor] = $this->tipFor($data, $totals->totalMinor);

        $step = $data['current_step'] ?? $lead?->current_step ?? 'service';

        $attributes = [
            'client_id' => $data['client_id'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            'guest_phone' => $data['guest_phone'] ?? null,
            'guest_email' => $data['guest_email'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'staff_id' => $data['staff_id'] ?? null,
            /* A snapshot rather than a relation, as the lead has always kept
               them: it records what was asked for, and a service deleted next
               month must not empty it. */
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'minutes' => (int) $service->duration_minutes,
                'price_minor' => $this->priceOf($service, $currency),
            ])->all(),
            'minutes' => (int) $services->sum(fn (Service $service) => (int) $service->duration_minutes),
            'subtotal_minor' => $totals->subtotalMinor,
            'discount_minor' => $totals->discountMinor,
            'tax_minor' => $totals->taxMinor,
            'total_minor' => $totals->totalMinor,
            'currency_code' => $currency,
            'expected_date' => $data['date'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'source' => $data['source'] ?? 'front-desk',
            'payment_type' => $data['payment_type'] ?? 'none',
            'deposit_minor' => (int) round(((float) ($data['deposit'] ?? 0)) * 100),
            'collection_method' => $data['collection_method'] ?? null,
            'waiver_reason' => ($data['collection_method'] ?? null) === 'waive'
                ? ($data['waiver_reason'] ?? null)
                : null,
            'confirmation' => $data['confirmation'] ?? null,
            'notes' => $data['notes'] ?? null,
            'client_note' => $data['client_note'] ?? null,
            'current_step' => $step,
            /* Touched, so the ageing that turns a quiet lead into a call to
               chase measures from the last thing that happened. */
            'last_activity_at' => now(),
        ];

        $created = $lead === null;
        $movedOn = $lead !== null && $lead->current_step !== $step;

        if ($lead === null) {
            $lead = BookingLead::create($attributes + [
                /* A booking's own number, not a lead's. This is a booking
                   being written rather than a note about a call, the desk
                   reads the reference out while it is still a draft, and the
                   appointment it becomes has to answer to it afterwards. */
                'reference' => Booking::nextReference(),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $lead->note('created');
        } else {
            /* Moving past the first card is the difference between a booking
               somebody started and one they are working through. Anything
               further along than that — a lead already chased, or called —
               is left where a person put it: the desk's judgement outranks
               the screen's. */
            $lead->update($attributes + [
                'status' => in_array($lead->status, ['draft', 'new', 'in-progress'], true)
                    ? ($step === 'service' ? $lead->status : 'in-progress')
                    : $lead->status,
            ]);
        }

        /* Only a step that actually moved. A card saved twice is the same
           conversation carrying on, and a timeline that recorded every
           keystroke would bury the two lines anybody reads. */
        if ($movedOn) {
            $lead->note('step', $lead->current_step);
        }

        return response()->json([
            'lead' => [
                'id' => $lead->id,
                'reference' => $lead->reference,
                'status' => $lead->status,
                'status_label' => $lead->statusLabel(),
                'saved_at' => $lead->updated_at?->toIso8601String(),
            ],
        ], $created ? 201 : 200);
    }

    /**
     * Take the booking.
     *
     * The times are worked out here rather than trusted from the browser: the
     * length of an appointment is the sum of its services, and a client who
     * was quoted an hour must not be given forty minutes because a form field
     * said so.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'services' => ['required', 'array', 'min:1'],
            /* The duplicate warning was shown and answered. A booking that
               arrives without it has not been past the check. */
            'duplicate_ack' => ['nullable', 'boolean'],
            /* Which room each service should go in, where somebody chose
               rather than letting the engine pick. Keyed by service id. */
            'resources' => ['nullable', 'array'],
            'resources.*' => ['nullable', 'integer'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'source' => ['nullable', Rule::in(config('bookings.sources'))],
            'payment_type' => ['nullable', Rule::in(config('bookings.payment_types'))],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'collection_method' => ['nullable', Rule::in(config('bookings.collection_methods'))],
            /* How the client is paying, which decides which of the two
               prices applies. Card unless somebody says otherwise. */
            'payment_method' => ['nullable', Rule::in(ServicePrice::METHODS)],
            /* What was agreed while the booking was taken. Kept so the
               till starts from it rather than from nothing: a
               receptionist who settled fifteen per cent with the client
               should not have to remember it at the counter. */
            'coupon' => ['nullable', 'string', 'max:40'],
            'tip_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'waiver_reason' => ['nullable', 'string', 'max:300'],
            'confirmation' => ['nullable', Rule::in(config('bookings.confirmations'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_note' => ['nullable', 'string', 'max:2000'],
            'draft' => ['nullable', 'boolean'],
            /* The booking-in-progress this is the end of. The screen has been
               auto-saving into it, and it carries the reference the desk may
               already have read out over the phone. */
            'lead_id' => ['nullable', 'integer', Rule::exists('booking_leads', 'id')],
        ]);

        /* Somebody has to be named. A client on file or a walk-in's name are
           both answers; nothing at all is not. */
        if (empty($data['client_id']) && trim((string) ($data['guest_name'] ?? '')) === '') {
            return back()->withInput()->withErrors([
                'client_id' => __('bookings.validation.who'),
            ]);
        }

        /* The booking-in-progress this finishes, where there is one. Its
           reference becomes the appointment's — that is the whole promise of
           the draft: the number quoted on the phone is the number on the
           booking. Only one still being worked on: a lead already converted
           is an appointment that exists. */
        $lead = empty($data['lead_id'])
            ? null
            : BookingLead::query()->whereNotIn('status', BookingLead::SETTLED)->find($data['lead_id']);

        $currency = Currencies::resolve();

        $services = Service::query()->with('prices')
            ->whereIn('id', $data['services'])
            ->get()
            /* In the order they were chosen, which is the order they will be
               worked in and the order the summary showed. */
            ->sortBy(fn (Service $service) => array_search($service->id, $data['services'], true))
            ->values();

        $minutes = (int) $services->sum(fn (Service $service) => (int) $service->duration_minutes);
        $starts = CarbonImmutable::parse($data['date'].' '.$data['starts_at']);

        /* The same client, booked for the same service, twice on one day.

           Asked again here rather than trusted from the screen: a booking
           taken by somebody else while this one was being filled in is
           exactly the duplicate the screen could not have seen.

           Still a warning rather than a rule — the reader who answered it
           sends the acknowledgement with the booking, and one that arrives
           without an answer has not been past the check. */
        if (! ($data['duplicate_ack'] ?? false)) {
            $repeats = BookingDuplicates::on(
                $data['client_id'] ?? null,
                $data['services'],
                $data['date'],
                $starts->format('H:i'),
                $minutes,
                $data['location_id'] ?? null,
            );

            if ($repeats->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'duplicate' => __('bookings.duplicate.blocked'),
                ]);
            }
        }

        /* The bill, worked out once and written down. Every screen that shows
           it afterwards reads what was stored rather than the price list,
           which is what stops a price edited in March rewriting what somebody
           was charged in February. */
        /* Which of the two prices this booking is worked out at. Card
           unless the money is actually being taken in cash. */
        $pricedFor = $this->pricedFor($data);

        $prices = $services->map(fn (Service $service) => $this->priceOf($service, $currency, $pricedFor));

        /* The coupon, checked again here rather than trusted from the screen:
           the quote the reader saw was advisory, and a promotion can be used
           up between quoting it and pressing Confirm. */
        [$promotion, $discountMinor] = $this->couponFor($data, $services, $prices, $currency, $request);

        $totals = BookingTotals::of($prices, $currency, $discountMinor);

        /* What was agreed as a tip. Not money yet — that lands on a payment
           — but the answer the till should open with. */
        [$tipPercent, $tipMinor] = $this->tipFor($data, $totals->totalMinor);

        /* A deposit larger than the bill is money the desk would have to give
           back before the appointment has even been worked. Refused here as
           well as in the browser, because the browser is where it is easy to
           check and the server is where it has to be true. */
        $depositMinor = (int) round(((float) ($data['deposit'] ?? 0)) * 100);

        if (($data['payment_type'] ?? 'none') === 'deposit' && $depositMinor > $totals->totalMinor) {
            throw ValidationException::withMessages([
                'deposit' => __('bookings.payment.too_much'),
            ]);
        }

        /* A deposit the service itself insists on.
           Added up across the lines rather than read off one of them: a
           booking of three services where two take a deposit owes both.
           Never more than the bill — a flat deposit larger than what is
           being charged is asking for money back before the appointment.

           Checked here as well as in the browser for the usual reason: the
           browser is where it is convenient and the server is where it has
           to be true. Paying the whole bill satisfies it; paying nothing
           does not. */
        $requiredDepositMinor = min(
            (int) $services->sum(fn (Service $service) => $service->requiredDepositMinorFor($currency, $pricedFor)),
            $totals->totalMinor,
        );

        if ($requiredDepositMinor > 0) {
            $payingBy = $data['payment_type'] ?? 'none';

            if ($payingBy === 'none') {
                throw ValidationException::withMessages([
                    'payment_type' => __('bookings.payment.deposit_required_error'),
                ]);
            }

            if ($payingBy === 'deposit' && $depositMinor < $requiredDepositMinor) {
                throw ValidationException::withMessages([
                    'deposit' => __('bookings.payment.too_little', [
                        'amount' => $totals->money($requiredDepositMinor),
                    ]),
                ]);
            }
        }

        /* Nothing to collect means no method to collect it by. Cleared rather
           than refused: the screen hides the question when the answer stops
           applying, and a form that errored on a field it had just hidden
           would be arguing with itself. */
        $collectionMethod = ($data['payment_type'] ?? 'none') === 'none'
            ? null
            : ($data['collection_method'] ?? 'later');

        /* Waiving is a decision somebody has to be accountable for, so it
           needs both a person allowed to make it and a reason worth reading
           back. The permission is the money one rather than the booking one:
           taking an appointment and letting somebody off paying for it are
           not the same authority. */
        if ($collectionMethod === 'waive') {
            abort_unless($request->user()->hasPermission('payments.apply_discount', 'own'), 403);

            if (trim((string) ($data['waiver_reason'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'waiver_reason' => __('bookings.payment.waiver_needed'),
                ]);
            }
        }

        $booking = DB::transaction(function () use ($data, $lead, $services, $minutes, $starts, $currency, $totals, $request, $depositMinor, $collectionMethod, $pricedFor, $promotion, $tipPercent, $tipMinor) {
            $attributes = [
                'client_id' => $data['client_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                /* Where it happens, decided here rather than asked for on the
                   form. The receptionist chose a treatment and a time; which
                   of six identical rooms it lands in is the building's
                   business, and asking would be asking them to do the
                   engine's arithmetic. Null where the business maps no
                   resources, or where nothing is free — the slot reader has
                   already refused the time in the second case. */
                'resource_id' => ResourceAllocator::assign(
                    $services->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    $data['date'],
                    $starts->format('H:i'),
                    $minutes,
                )?->id,
                'date' => $data['date'],
                'starts_at' => $starts->format('H:i'),
                'ends_at' => $starts->addMinutes($minutes)->format('H:i'),
                'minutes' => $minutes,
                'status' => ($data['draft'] ?? false) ? 'draft' : 'confirmed',
                'source' => $data['source'] ?? 'front-desk',
                'is_walk_in' => empty($data['client_id']),
                'subtotal_minor' => $totals->subtotalMinor,
                'discount_minor' => $totals->discountMinor,
                'tax_minor' => $totals->taxMinor,
                'total_minor' => $totals->totalMinor,
                'currency_code' => $currency,
                'priced_for' => $pricedFor,
                'promotion_id' => $promotion?->id,
                'tip_percent' => $tipPercent,
                'tip_minor' => $tipMinor,
                /* Stated rather than left to the column default: the panel is
                   answered from this instance, and an attribute the database
                   filled in is one the reply would not have. */
                'payment_status' => 'unpaid',
                'paid_minor' => 0,
                'payment_type' => $data['payment_type'] ?? 'none',
                'deposit_minor' => $depositMinor,
                'collection_method' => $collectionMethod,
                'waiver_reason' => $collectionMethod === 'waive' ? ($data['waiver_reason'] ?? null) : null,
                'waived_by' => $collectionMethod === 'waive' ? $request->user()->id : null,
                'waived_at' => $collectionMethod === 'waive' ? now() : null,
                'notes' => $data['notes'] ?? null,
                /* The client as they are today. Tags and insights are worked
                   out from the diary and move as the person does; this
                   booking should keep saying what was true when it was
                   taken. */
                'client_snapshot' => $this->clientSnapshot($data['client_id'] ?? null),
                'confirmation' => $data['confirmation'] ?? 'none',
                /* A draft has been promised to nobody, so it is not confirmed
                   however the form was filled in. */
                'confirmed_at' => ($data['draft'] ?? false) ? null : now(),
            ];

            /* The draft's own number, carried onto the appointment it became.
               A booking that was quoted as BK-…-00125 on the phone and then
               filed under a second number is a booking the caller cannot ask
               about. Only a reference that was issued as one — a lead written
               the old way carries a BL- number, which is a record of a call
               and not a booking's name. */
            $booking = Booking::create($attributes + [
                'reference' => str_starts_with((string) $lead?->reference, 'BK-')
                    ? $lead->reference
                    : Booking::nextReference(),
                'created_by' => $request->user()->id,
            ]);

            $this->writeServiceLines($booking, $services, $currency, $this->chosenResources($data));

            /* A note about the person goes on the person. It is a different
               thing from the note about the appointment, which is why the
               form asks for them separately. */
            if (! empty($data['client_note']) && ! empty($data['client_id'])) {
                Client::find($data['client_id'])?->clientNotes()->create([
                    'tenant_id' => $booking->tenant_id,
                    'body' => $data['client_note'],
                    'created_by' => $request->user()->id,
                ]);
            }

            /* The conversation this came from is finished. Marked rather
               than deleted: a lead that became a booking is the useful half
               of any answer about how many did not. */
            if ($lead) {
                $lead->update([
                    'status' => 'converted',
                    'current_step' => 'completed',
                    'booking_id' => $booking->id,
                    'converted_at' => now(),
                    'last_activity_at' => now(),
                ]);

                $lead->note('converted', $booking->reference, $request->user()->id);
            }

            return $booking;
        });

        /* Written to the client's history the moment it exists. The profile
           used to reconstruct its timeline from whatever still existed, which
           can only ever say what is true now — this says what happened. */
        ClientActivityLog::bookingCreated($booking);
        ClientActivityLog::paymentDue($booking);

        /* The client was told a link is on its way, so it goes now rather
           than on a queue — the same reason the confirmation is sent inline.
           A failure here does not undo the booking: the appointment is real,
           and the panel says the link did not go so the desk can ring
           instead. */
        $linkError = $collectionMethod === 'link' ? $this->sendPaymentLink($booking, $request) : null;

        /* The booking screen asks in JSON and stays where it is: the third
           column moves from summary to payment without the receptionist
           losing the page, and a redirect would throw away the context the
           whole screen exists to keep. */
        if ($request->expectsJson()) {
            return response()->json([
                'booking' => $this->panel($booking->fresh()),
                'link_error' => $linkError,
            ], 201);
        }

        return redirect()->route('bookings.index')->with('toast', [
            'type' => 'success',
            'message' => $booking->status === 'draft'
                ? __('bookings.saved_draft')
                : __('bookings.booked', ['name' => $booking->clientName()]),
        ]);
    }

    /**
     * Change a booking that has been taken but not paid for.
     *
     * The third column reserves the appointment before it asks for money, so
     * "back to booking summary" has to be able to move a real booking rather
     * than make a second one. Editing rewrites the same row — including the
     * bill, which is recalculated from whatever the services now are.
     *
     * Refused once money is against it: a paid booking whose total quietly
     * changed is a receipt that no longer matches what was charged.
     */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        abort_if($booking->paidMinor() !== 0, 422);

        $data = $this->validateBooking($request);
        $currency = Currencies::resolve();
        $services = $this->chosenServices($data['services'], $currency);
        $minutes = (int) $services->sum(fn (Service $service) => (int) $service->duration_minutes);
        $starts = CarbonImmutable::parse($data['date'].' '.$data['starts_at']);
        $pricedFor = $this->pricedFor($data);
        $prices = $services->map(fn (Service $service) => $this->priceOf($service, $currency, $pricedFor));

        /* The same three answers as a booking taken in one go. A draft
           being confirmed comes through here rather than store(), and a
           tip or coupon agreed on the screen must not be lost on the
           way. */
        [$promotion, $discountMinor] = $this->couponFor($data, $services, $prices, $currency, $request);

        $totals = BookingTotals::of($prices, $currency, $discountMinor);

        [$tipPercent, $tipMinor] = $this->tipFor($data, $totals->totalMinor);

        /* Read before the row moves. "Rescheduled" without the previous time
           answers half the question, and the half it drops is the one
           somebody is usually looking for. */
        $was = [
            'date' => $booking->date?->isoFormat('D MMM Y'),
            'time' => $booking->timeLabel(),
            /* The start alone decides whether this was a reschedule. The
               label beside it carries the end time too, and the end moves
               whenever a service is added — lengthening an appointment is
               not a change to when the client is due. */
            'on' => $booking->date?->toDateString(),
            'starts_at' => $booking->startsAt(),
        ];

        DB::transaction(function () use ($booking, $data, $services, $minutes, $starts, $currency, $totals, $pricedFor, $promotion, $tipPercent, $tipMinor) {
            $booking->update([
                'client_id' => $data['client_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                /* Worked out again, because both halves of the answer may
                   have moved: the time, and what is being done in it. A
                   massage that became a reflexology belongs in a chair.
                   Ignoring itself, or it would be found to be holding the
                   room it is asking for. */
                'resource_id' => ResourceAllocator::assign(
                    $services->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    $data['date'],
                    $starts->format('H:i'),
                    $minutes,
                    $booking->id,
                )?->id ?? $booking->resource_id,
                'date' => $data['date'],
                'starts_at' => $starts->format('H:i'),
                'ends_at' => $starts->addMinutes($minutes)->format('H:i'),
                'minutes' => $minutes,
                'source' => $data['source'] ?? $booking->source,
                'is_walk_in' => empty($data['client_id']),
                'subtotal_minor' => $totals->subtotalMinor,
                'discount_minor' => $totals->discountMinor,
                'tax_minor' => $totals->taxMinor,
                'total_minor' => $totals->totalMinor,
                'currency_code' => $currency,
                'priced_for' => $pricedFor,
                'promotion_id' => $promotion?->id,
                'tip_percent' => $tipPercent,
                'tip_minor' => $tipMinor,
                'notes' => $data['notes'] ?? null,
                'confirmation' => $data['confirmation'] ?? $booking->confirmation,
            ]);

            /* Replaced rather than reconciled: the lines are a copy of what
               was chosen, and matching them up one by one would be work in
               aid of keeping ids nothing refers to. */
            $booking->services()->delete();
            $this->writeServiceLines($booking, $services, $currency, $this->chosenResources($data));
        });

        $booking->refresh();

        /* Only when it actually moved. Editing a booking's services is not a
           reschedule, and an entry saying it was would be a history nobody
           can trust to mean what it says. */
        if ($booking->date?->toDateString().' '.$booking->startsAt() !== $was['on'].' '.$was['starts_at']) {
            ClientActivityLog::bookingRescheduled($booking, $was, $request->user()->id);
        }

        return response()->json(['booking' => $this->panel($booking)]);
    }

    /**
     * One client's bookings and leads, for the panel on their profile.
     *
     * Answered as JSON so the profile can switch between the two without
     * leaving the page: a receptionist checking what somebody has booked is
     * mid-conversation, and a page load costs them the tab they were on.
     *
     * Confirmed bookings only in the first segment — a lead is not an
     * appointment, and the two are counted differently by everybody who
     * reads them.
     */
    public function forClient(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'calendar.view');

        $service = (int) $request->query('service', 0);
        $month = (string) $request->query('month', '');
        $year = (string) $request->query('year', '');
        /* Which half of the history. Two questions, never one list: "when
           are they next in" is asked while somebody is on the phone, and
           "what have they had done" is asked while somebody is in the
           chair. Anything else — cancelled, no-show, declined — is in
           neither, and is read from the full list with the segment off. */
        $when = (string) $request->query('when', '');

        $bookings = Booking::query()
            ->with(['staff', 'services'])
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['draft'])
            ->when($service > 0, fn (Builder $query) => $query
                ->whereHas('services', fn (Builder $line) => $line->where('service_id', $service)))
            ->when(preg_match('/^\d{4}-\d{2}$/', $month) === 1, fn (Builder $query) => $query
                ->whereYear('date', (int) substr($month, 0, 4))
                ->whereMonth('date', (int) substr($month, 5, 2)))
            /* A year on its own: the month combo was cleared, and "every
               August" is not what anybody meant by it. */
            ->when($month === '' && preg_match('/^\d{4}$/', $year) === 1, fn (Builder $query) => $query
                ->whereYear('date', (int) $year))
            /* Today counts as upcoming whatever the clock says: a 2pm
               appointment is still the answer to "when are they next in" at
               half past, because they are in the chair. */
            ->when($when === 'upcoming', fn (Builder $query) => $query
                ->whereDate('date', '>=', now()->toDateString())
                ->whereIn('status', ['pending', 'confirmed', 'arrived']))
            ->when($when === 'completed', fn (Builder $query) => $query
                ->where('status', 'completed'))
            /* Forward in time: a client's history is read as a story of
               what they have had done, and a story is read from its
               beginning. The months group in the same direction, so the
               list never doubles back on itself. */
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        /* Grouped by the month they happened in. A flat list of forty is a
           scroll; twelve labelled months is a thing somebody can find a
           visit in. */
        $groups = $bookings
            ->groupBy(fn (Booking $booking) => $booking->date->format('Y-m'))
            ->map(fn ($month, string $key) => [
                'key' => $key,
                'label' => $month->first()->date->translatedFormat('F Y'),
                'bookings' => $month->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'reference' => $booking->reference,
                    'when' => $booking->date->translatedFormat('j M Y').' · '.TimeFormat::time($booking->startsAt()),
                    'services' => $booking->services->pluck('name')->implode(', '),
                    'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
                    'meta' => trans_choice('bookings.summary.minutes', (int) $booking->minutes, ['count' => (int) $booking->minutes])
                        .' · '.Money::format($booking->total_minor / 100, $booking->currency_code),
                    'status' => $booking->statusLabel(),
                    'status_class' => $booking->statusClass(),
                    'payment' => $booking->paymentStatusLabel(),
                    'payment_class' => $booking->paymentStatusClass(),
                    'drawer_url' => route('bookings.drawer', $booking),
                ])->values(),
            ])->values();

        return response()->json([
            'groups' => $groups,
            /* The months and services this client actually has, so the
               filters cannot offer a combination that finds nothing. */
            'months' => Booking::query()
                ->where('client_id', $client->id)
                ->whereNotIn('status', ['draft'])
                ->orderByDesc('date')
                ->get(['date'])
                ->unique(fn (Booking $booking) => $booking->date->format('Y-m'))
                ->map(fn (Booking $booking) => [
                    'value' => $booking->date->format('Y-m'),
                    'label' => $booking->date->translatedFormat('F Y'),
                ])->values(),
            'services' => BookingService::query()
                ->whereIn('booking_id', Booking::query()->where('client_id', $client->id)->select('id'))
                ->whereNotNull('service_id')
                ->get(['service_id', 'name'])
                ->unique('service_id')
                ->sortBy('name')
                ->map(fn (BookingService $line) => ['value' => (string) $line->service_id, 'label' => $line->name])
                ->values(),
            'leads' => BookingLead::query()
                ->where('client_id', $client->id)
                ->where('status', '!=', 'converted')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BookingLead $lead) => [
                    'id' => $lead->id,
                    'reference' => $lead->reference,
                    'services' => collect($lead->services ?? [])->pluck('name')->implode(', '),
                    'step' => $lead->stepLabel(),
                    'status' => $lead->statusLabel(),
                    'status_class' => $lead->statusClass(),
                    'value' => Money::format($lead->total_minor / 100, $lead->currency_code),
                    'created' => $lead->created_at?->translatedFormat('j M Y'),
                    'activity' => ($lead->last_activity_at ?? $lead->created_at)?->translatedFormat('j M Y'),
                    'drawer_url' => route('bookings.leads.show', $lead),
                ])->values(),
        ]);
    }

    /**
     * One booking, for the drawer that opens over a listing.
     *
     * The same shape the lead drawer answers in — a header, blocks of label
     * and value, and a footer — so one renderer draws both and a booking
     * cannot quietly grow a different-looking panel.
     */
    public function drawer(Request $request, Booking $booking): JsonResponse
    {
        $this->allow($request, 'calendar.view');

        $booking->load(['client', 'staff', 'location', 'services.resource', 'resource', 'payments.recordedBy', 'createdBy']);
        $totals = BookingTotals::for($booking);
        $none = __('leads.drawer.not_selected');

        return response()->json([
            'name' => $booking->clientName(),
            'reference' => $booking->reference,
            'status' => $booking->statusLabel(),
            'status_class' => $booking->statusClass(),
            'step' => $booking->paymentStatusLabel(),
            'sections' => [
                [
                    'title' => __('bookings.sections.summary'),
                    'rows' => [
                        __('bookings.summary.when') => $booking->date->translatedFormat('l j F Y'),
                        __('bookings.summary.starts') => TimeFormat::time($booking->startsAt()),
                        __('bookings.summary.ends') => TimeFormat::time($booking->endsAt()),
                        __('bookings.summary.duration') => trans_choice('bookings.summary.minutes', (int) $booking->minutes, ['count' => (int) $booking->minutes]),
                        __('bookings.summary.services') => $booking->services->pluck('name')->implode(', '),
                        __('bookings.summary.staff') => $booking->staff?->displayName() ?? __('bookings.any_staff'),
                        __('bookings.summary.location') => $booking->location?->name ?: $none,
                        /* The rooms this booking was given, not every room
                           its services could have used — which on a spa with
                           six of them read as though one client had been
                           handed the whole building. Older bookings recorded
                           one room for the whole appointment, so they answer
                           from the booking itself. */
                        __('bookings.summary.resource') => $booking->services
                            ->map(fn ($line) => $line->resource?->name)
                            ->filter()
                            ->unique()
                            ->implode(', ')
                            ?: ($booking->resource?->name ?: $none),
                        __('bookings.details.note') => $booking->notes ?: $none,
                        __('leads.drawer.created_by') => $booking->createdBy?->name ?: $none,
                    ],
                ],
                [
                    'title' => __('bookings.detail.payment_summary'),
                    'rows' => collect($totals->lines())->mapWithKeys(fn (array $line) => [$line['label'] => $line['value']])->all()
                        + [
                            __('bookings.summary.deposit') => $booking->deposit_minor > 0 ? $totals->money((int) $booking->deposit_minor) : $none,
                            __('bookings.summary.paid') => $totals->money($booking->paidMinor()),
                            __('bookings.summary.due') => $totals->money($booking->dueMinor()),
                            __('bookings.pay.title') => $booking->payments->map(fn ($payment) => $payment->methodLabel())->unique()->implode(', ') ?: $none,
                        ],
                ],
            ],
            /* What actually changed hands, each with what it was. */
            'transactions' => $booking->payments->map(fn ($payment) => [
                'label' => $payment->methodLabel(),
                'amount' => $payment->amountLabel(),
                'at' => TimeFormat::dateTime($payment->paid_at),
                'by' => $payment->recordedBy?->name,
                'reference' => $payment->reference,
            ])->values(),
            'urls' => [
                'show' => route('bookings.show', $booking),
            ],
        ]);
    }

    /**
     * Write down a booking somebody has started.
     *
     * Called when the services are settled — the first moment there is
     * anything worth keeping — and answered with a reference the desk can
     * quote back. It is a background note, not a step: the screen has already
     * moved on to the next question by the time this returns, and a failure
     * here must never stop somebody taking an appointment.
     *
     * Saving the same step twice edits the same lead. Two references for one
     * conversation would be two calls to return.
     */
    public function storeLead(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'lead_id' => ['nullable', 'integer', Rule::exists('booking_leads', 'id')],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'date' => ['nullable', 'date_format:Y-m-d'],
            /* How far the booking screen has got. Reported on every step so
               the desk can see where a call ended, not just that it did. */
            'current_step' => ['nullable', Rule::in(config('bookings.lead_steps'))],
            'deposit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $currency = Currencies::resolve();
        $services = $this->chosenServices($data['services'], $currency);

        $step = $data['current_step'] ?? 'service';

        $attributes = [
            'client_id' => $data['client_id'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            /* A snapshot rather than a relation: the lead records what was
               asked for, and a service deleted next month must not empty it. */
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'minutes' => (int) $service->duration_minutes,
                'price_minor' => $this->priceOf($service, $currency),
            ])->all(),
            'minutes' => (int) $services->sum(fn (Service $service) => (int) $service->duration_minutes),
            'total_minor' => (int) $services->sum(fn (Service $service) => $this->priceOf($service, $currency)),
            'currency_code' => $currency,
            'expected_date' => $data['date'] ?? null,
            'current_step' => $step,
            'deposit_minor' => (int) round(((float) ($data['deposit'] ?? 0)) * 100),
            /* Touched, so the ageing that turns a quiet lead into a call to
               chase measures from the last thing that happened rather than
               from when the phone first rang. */
            'last_activity_at' => now(),
        ];

        $lead = BookingLead::query()->find($data['lead_id'] ?? null);

        $movedOn = $lead && $lead->current_step !== $step;

        if ($lead && in_array($lead->status, BookingLead::CHASEABLE, true)) {
            /* Moving past the first card is the difference between a booking
               somebody started and one they are working through. A lead
               already chased or already called is left where a person put
               it: the desk's judgement outranks the screen's. */
            $lead->update($attributes + [
                'status' => $step === 'service' ? $lead->status : 'in-progress',
            ]);
        } else {
            $lead = BookingLead::create($attributes + [
                'reference' => BookingLead::nextReference(),
                'status' => 'new',
                'created_by' => $request->user()->id,
            ]);

            $lead->note('created');
        }

        /* Only a step that actually moved. A card saved twice is the same
           conversation carrying on, and a timeline that recorded every
           keystroke would bury the two lines anybody reads. */
        if ($movedOn) {
            $lead->note('step', $lead->current_step);
        }

        return response()->json(['lead' => [
            'id' => $lead->id,
            'reference' => $lead->reference,
        ]], 201);
    }

    /**
     * One booking, in three columns.
     *
     * Laid out like the client profile because it is read the same way: the
     * person on the left, the work in the middle, and what to do about them
     * on the right. The client context is read live from the record — that
     * is where it belongs — except the tags and insights, which are read
     * from the snapshot the booking took, so an appointment from March keeps
     * saying what was true in March.
     */
    public function show(Request $request, Booking $booking): View
    {
        $this->allow($request, 'calendar.view');

        $booking->load([
            'client.bookingPreferences', 'client.preferences', 'client.phones', 'client.emails',
            'staff', 'location', 'resource', 'services.service.category', 'services.resource',
            'payments.recordedBy',
        ]);

        $client = $booking->client;
        $snapshot = $booking->client_snapshot ?? [];

        /* What this reader can do to this booking now. Read once here rather
           than asked per button in the template, because the same list drives
           which dialogues get rendered at all. */
        $actions = $booking->availableActions($request->user());

        return view('bookings.show', [
            'actions' => $actions,

            /* The business's own reason lists, one per act, and only the ones
               they have switched on. Loaded only for the acts on offer: three
               unused dropdowns of two hundred rows each is a page nobody
               needs to be sent. */
            'reasons' => collect($actions)
                /* Not every act asks one. Arriving for an appointment does
                   not need to be explained. */
                ->filter(fn (string $action) => config('bookings.status_actions.'.$action.'.reason') !== null)
                ->mapWithKeys(fn (string $action) => [
                    $action => ReasonCode::query()
                        ->ofType(config('bookings.status_actions.'.$action.'.reason'))
                        ->usable()->get(),
                ]),

            /* Everything that has been done to this booking, newest first and
               in the words the reasons had on the day. */
            'history' => $booking->statusChanges()->with('changedBy')->newest()->get(),

            /* When the client arrived, where they have. Read from the history
               rather than from a column, so it still says what it said after
               the appointment has moved on. */
            'checkIn' => $booking->checkIn(),

            /* Only where a reschedule is on offer, and only where this reader
               may move the work to somebody else. */
            /* Whoever could work it, plus whoever is working it now.

               The second half is not redundant: a booking's own staff member
               may since have been made inactive or stopped providing
               services, and a list that leaves them out is one where moving
               the appointment by an hour quietly reassigns it to nobody. */
            'staffOptions' => in_array('reschedule', $actions, true) && $request->user()->hasPermission('appointments.edit', 'own')
                ? Staff::query()
                    ->where(fn (Builder $query) => $query
                        ->where(fn (Builder $bookable) => $bookable
                            ->where('is_active', true)->where('provides_services', true))
                        ->orWhereKey($booking->staff_id))
                    ->orderBy('first_name')->get()
                : collect(),
            'locationOptions' => in_array('reschedule', $actions, true) && $request->user()->hasPermission('appointments.edit', 'own')
                ? Location::query()->orderBy('name')->get()
                : collect(),

            'booking' => $booking,
            'totals' => BookingTotals::for($booking),
            /* What the Take Payment panel needs: the bill as the panel reads
               it, and the ways this business can be paid. The same payload
               the booking screen's third column is answered with, so one
               component serves both. */
            'panel' => $this->panel($booking),
            /* Not `methods`: this view already has one of those. The client
               partials it reuses build a `$methods` of communication
               channels in their own @php block, and a second variable of
               that name is the first one silently replaced. */
            'payMethods' => $this->paymentMethods(),
            'canTakePayment' => $request->user()->hasPermission('appointments.create', 'own'),
            'client' => $client,
            'settings' => ClientSettings::forTenant($request->user()->tenant),

            /* Whoever holds this page open may not be allowed to see a phone
               number; the client profile makes the same check. */
            'canViewContact' => $request->user()->hasPermission('clients.view_contact', 'own'),

            /* The next thing in the diary for this person after this one —
               the question a receptionist asks while they are still on the
               phone. */
            'nextBooking' => $client === null ? null : Booking::query()
                ->with(['staff', 'services'])
                ->where('client_id', $client->id)
                ->whereKeyNot($booking->id)
                /* Confirmed only, as on the client profile: an arrived
                   booking is one they are at, not one still to come. */
                ->where('status', 'confirmed')
                ->where(fn (Builder $query) => $query
                    ->whereDate('date', '>', $booking->date)
                    ->orWhere(fn (Builder $sameDay) => $sameDay
                        ->whereDate('date', $booking->date)
                        ->where('starts_at', '>', $booking->starts_at)))
                ->orderBy('date')->orderBy('starts_at')
                ->first(),

            /* From the snapshot where there is one, and from the client where
               there is not: a booking taken before the snapshot existed still
               has a reader who wants to know. */
            'tags' => $snapshot['tags'] ?? ($client?->behavioralTags()->pluck('label')->all() ?? []),
            'insights' => $snapshot['insights'] ?? ($client === null ? [] : ClientInsights::for($client)),
            'snapshotTakenAt' => isset($snapshot['taken_at'])
                ? CarbonImmutable::parse($snapshot['taken_at'])
                : null,
        ]);
    }

    /**
     * The same booking, laid out to be printed or handed over.
     *
     * One view for both the confirmation and the receipt, because they are
     * the same facts with a different heading, and two templates would drift
     * apart the first time a line was added to either.
     */
    public function receipt(Request $request, Booking $booking): View
    {
        $this->allow($request, 'calendar.view');

        return view('bookings.receipt', [
            'booking' => $booking->load(['client', 'staff', 'location', 'services', 'payments']),
            'totals' => BookingTotals::for($booking),
            'isReceipt' => $request->boolean('receipt'),
            /* Opened straight into the print dialogue, which is what every
               browser offers as "Save as PDF". The same page either way — a
               separate PDF renderer would be a second document that could
               disagree with the one on screen. */
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    /**
     * Write down a payment.
     *
     * Almost every payment a salon takes happens somewhere else — in the
     * drawer, on the terminal by the till, in somebody's banking app — and
     * arrives here as a person saying it happened. That is what this records.
     *
     * The one exception would be a card charged by StyleDesk itself, which
     * needs a provider connected; with none, the panel offers the terminal
     * instead of pretending. Card details are never posted here, never
     * stored, and never logged: what is kept is what a paper receipt keeps.
     */
    public function pay(Request $request, Booking $booking): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(config('bookings.methods')))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
            'manual' => ['nullable', 'boolean'],
            /* The tip, in whole currency units like the amount beside it. Its
               own field rather than folded into the amount, because it is not
               the salon's money in the same way and a total that has absorbed
               it can never be taken apart again. */
            'tip' => ['nullable', 'numeric', 'min:0'],
        ]);

        /* A card StyleDesk would have to charge itself needs a provider. A
           card already taken on the terminal beside the till is not a charge
           at all — it is a note that money arrived, like cash — so it is
           recorded whether or not anything is connected. */
        if ($data['method'] === 'card' && ! $request->boolean('manual') && config('bookings.card_provider') === null) {
            throw ValidationException::withMessages([
                'method' => __('bookings.pay.no_card_provider'),
            ]);
        }

        $amount = (int) round(((float) $data['amount']) * 100);
        $received = isset($data['received']) ? (int) round(((float) $data['received']) * 100) : null;
        $tip = isset($data['tip']) ? (int) round(((float) $data['tip']) * 100) : 0;

        $tipPanel = Tips::panel($booking, TipSettings::forTenant($booking->tenant));

        /* A tip on a bill where nothing is tipped is money nobody can account
           for later, so it is refused rather than quietly kept. */
        if ($tip > 0 && ! ($tipPanel['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'tip' => __('tips.panel.not_eligible'),
            ]);
        }

        /* Where the business insists the client answers, a payment that
           arrives without an answer has skipped the question. Nought is an
           answer — but only where declining is on offer. */
        if (($tipPanel['require_selection'] ?? false) && ! $request->has('tip')) {
            throw ValidationException::withMessages([
                'tip' => __('tips.panel.required'),
            ]);
        }

        if ($tip === 0 && ($tipPanel['require_selection'] ?? false) && ! ($tipPanel['allow_no_tip'] ?? true)) {
            throw ValidationException::withMessages([
                'tip' => __('tips.panel.required'),
            ]);
        }

        /* Money handed over that is less than the bill is a part payment, not
           an error: the rest is still owed and the status will say so. What
           is refused is being given less than the line claims to be. */
        if ($received !== null && $received < $amount + $tip) {
            throw ValidationException::withMessages([
                'received' => __('bookings.pay.short_cash'),
            ]);
        }

        /*
         * Through the gateway, not straight into the table.
         *
         * Today every path lands on the manual gateway, which records money
         * that arrived by other means — which is exactly what this method did
         * before. The seam is what matters: when a processor is connected,
         * this line stops changing and ManualGateway stops being the answer.
         * See App\Payments\PaymentGateway.
         */
        $gateway = app(PaymentGatewayManager::class)->for($booking->tenant);

        $payment = $gateway->charge($booking, new PaymentRequest(
            amountMinor: $amount,
            method: $data['method'],
            tipMinor: $tip,
            receivedMinor: $received,
            reference: $data['reference'] ?? null,
            note: $data['note'] ?? null,
            userId: $request->user()?->id,
        ));

        /* What the gateway does not: the client's history, and the requests
           to pay that this payment answers. Both belong to StyleDesk rather
           than to whoever moved the money, so they sit here rather than in
           every implementation of the interface. */
        ClientActivityLog::paymentReceived($payment, $booking);

        /* A link is only ever paid because money arrived, so this is the one
           place that can say so. Every open link is offered the same news: a
           booking asked for twice has two of them. */
        $booking->paymentLinks()->whereNotIn('status', ['paid'])->get()
            ->each(fn (BookingPaymentLink $link) => $link->settleAgainst($booking));

        return response()->json([
            'booking' => $this->panel($booking->fresh()),
            'payment' => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => $payment->amountLabel(),
                'change' => $payment->change_minor === null
                    ? null
                    : BookingTotals::for($booking)->money((int) $payment->change_minor),
            ],
        ], 201);
    }

    /**
     * Send the client their confirmation.
     *
     * Email only for now: text messages need a sending account this business
     * has not connected, and a button that silently does nothing is worse
     * than one that says why it cannot.
     */
    public function sendConfirmation(Request $request, Booking $booking): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'sms'])],
        ]);

        if ($data['channel'] === 'sms') {
            throw ValidationException::withMessages([
                'channel' => __('bookings.confirmation.no_sms'),
            ]);
        }

        $to = $booking->client?->email ?: $booking->guest_email;

        if (! $to) {
            throw ValidationException::withMessages([
                'channel' => __('bookings.confirmation.no_email'),
            ]);
        }

        /* Sent rather than queued, for the reason the schedule mail is: this
           one is sent while somebody is standing at the desk, and "we have
           emailed it to you" has to be true by the time they leave. */
        Mail::to($to)->send(new BookingConfirmationMail(
            $booking->load(['client', 'staff', 'location', 'services', 'payments']),
            tenant()?->name ?? config('app.name'),
        ));

        return response()->json(['sent_to' => $to]);
    }

    /**
     * One booking as the third column reads it.
     *
     * Summary, payment and confirmation are three views of the same facts, so
     * they are answered by one payload rather than three that could disagree
     * about what was charged.
     *
     * @return array<string, mixed>
     */
    private function panel(Booking $booking): array
    {
        $booking->loadMissing(['client', 'staff', 'location', 'services', 'payments.recordedBy', 'paymentLinks', 'waivedBy']);
        $totals = BookingTotals::for($booking);

        /* A deposit is collected once. After it is in, "what to collect now"
           is simply whatever is still owed. */
        $collectMinor = $booking->payment_type === 'deposit'
            && $booking->deposit_minor > 0
            && $booking->paidMinor() === 0
                ? min((int) $booking->deposit_minor, $booking->dueMinor())
                : $booking->dueMinor();

        return [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status,
            'client' => $booking->clientName(),
            'client_url' => $booking->client ? route('clients.show', $booking->client) : null,
            'services' => $booking->services->map(fn ($line) => [
                'name' => $line->name,
                'minutes' => (int) $line->minutes,
                'price' => $totals->money((int) $line->price_minor),
            ])->values(),
            'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
            'location' => $booking->location?->name,
            'date' => $booking->date->translatedFormat('D, j M Y'),
            'time' => $booking->timeLabel(),
            'minutes' => (int) $booking->minutes,
            'duration' => trans_choice('bookings.summary.minutes', (int) $booking->minutes, ['count' => (int) $booking->minutes]),
            'lines' => $totals->lines(),
            'total' => $totals->money((int) $booking->total_minor),
            'total_minor' => (int) $booking->total_minor,
            'paid' => $totals->money($booking->paidMinor()),
            'paid_minor' => $booking->paidMinor(),
            'due' => $totals->money($booking->dueMinor()),
            'due_minor' => $booking->dueMinor(),
            'due_amount' => number_format($booking->dueMinor() / 100, 2, '.', ''),
            /* What may be tipped on, and what to offer. Absent where the
               business does not take tips or where nothing on the bill is
               tipped — a section that appears empty is worse than one that
               does not appear. */
            'tips' => Tips::panel($booking, TipSettings::forTenant($booking->tenant)) + [
                /* What was agreed when the booking was taken, so the till
                   opens on it rather than on nothing. A percentage is worked
                   out again against what is actually owed; an amount
                   somebody typed is offered exactly as typed. */
                'chosen_percent' => $booking->tip_percent,
                'chosen_minor' => $booking->tip_percent === null ? $booking->tip_minor : null,
                'currency' => (string) $booking->currency_code,
                'labels' => [
                    'title' => __('tips.panel.title'),
                    'eligible' => __('tips.panel.eligible'),
                    'custom' => __('tips.panel.custom'),
                    'none' => __('tips.panel.none'),
                    'selected' => __('tips.panel.selected'),
                    'required' => __('tips.panel.required'),
                ],
            ],
            'payment_status' => $booking->payment_status,
            'payment_status_label' => $booking->paymentStatusLabel(),
            'payment_type' => $booking->payment_type,
            'deposit_minor' => (int) $booking->deposit_minor,
            'collection_method' => $booking->collection_method,
            'collection_label' => $booking->collection_method
                ? __('bookings.payment.actions.'.$booking->collection_method)
                : null,
            /* Who let this booking off the money, and why. Read back beside
               the bill, because a waived deposit with nobody's name against
               it is a decision nobody can answer for. */
            'waiver' => $booking->waived_at === null ? null : [
                'reason' => $booking->waiver_reason,
                'by' => $booking->waivedBy?->name,
                'at' => TimeFormat::dateTime($booking->waived_at),
            ],
            /* The requests to pay that went out, newest first. */
            'links' => $booking->paymentLinks->map(fn (BookingPaymentLink $link) => [
                'id' => $link->id,
                'amount' => BookingTotals::for($booking)->money((int) $link->amount_minor),
                'status' => $link->currentStatus(),
                'status_label' => $link->statusLabel(),
                'status_class' => $link->statusClass(),
                'sent_to' => $link->sent_to,
                'sent_at' => TimeFormat::dateTime($link->sent_at),
            ])->values(),
            /* What to ask for now, which is not always what is owed.
               A booking taken with a deposit collects the deposit today and
               chases the balance later — asking for the whole bill because
               that is what the booking is worth would be the screen charging
               somebody money they were told they did not owe yet. Once the
               deposit is in, what is left is simply the balance. */
            /* Which price list the booking was totalled against. The till
               is held to it: a cash booking settled on a card collects the
               cash total for a card sale, and the salon is short the
               difference on every service that charges two prices. */
            'priced_for' => $booking->priced_for,

            /* The whole bill, line by line, for the till.
               The summary card omits a line worth nothing on purpose — a
               discount of $0.00 is a discount nobody gave. The pay card is
               the opposite case: somebody is about to take money and has to
               be able to see that tax really is nil and no coupon was
               applied, rather than wonder whether the line is missing or the
               figure is. So every line is stated here, zeros included. */
            'breakdown' => $totals->breakdownFor($booking),
            'tip_paid_minor' => (int) $booking->payments->sum('tip_minor'),
            'tip_paid' => $totals->money((int) $booking->payments->sum('tip_minor')),

            'collect_minor' => $collectMinor,
            'collect_amount' => number_format($collectMinor / 100, 2, '.', ''),
            'collect' => $totals->money($collectMinor),
            /* What is still owed after this one is taken, which is the other
               half of the same sentence: $64.80 now, $194.40 on the day. */
            'remaining' => $totals->money(max(0, $booking->dueMinor() - $collectMinor)),
            'remaining_minor' => max(0, $booking->dueMinor() - $collectMinor),
            /* What actually happened, not just what is owed: a bill settled
               half in cash and half on a card is one total and two rows, and
               the desk reads the rows to answer "did that go through". */
            'payments' => $booking->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'method_label' => $payment->methodLabel(),
                'amount' => $payment->amountLabel(),
                'reference' => $payment->reference,
                'status_label' => __('bookings.payment_statuses.'.$payment->status.'.label'),
                'at' => TimeFormat::dateTime($payment->paid_at),
                'by' => $payment->recordedBy?->name,
                'change' => $payment->change_minor
                    ? BookingTotals::for($booking)->money((int) $payment->change_minor)
                    : null,
                /* The tip beside the bill rather than inside it: it is owed
                   to whoever did the work, and a total that has absorbed it
                   can never be taken apart again. */
                'tip' => $payment->tip_minor
                    ? BookingTotals::for($booking)->money((int) $payment->tip_minor)
                    : null,
            ])->values(),
            'urls' => [
                'show' => route('bookings.show', $booking),
                'print' => route('bookings.receipt', $booking),
                'receipt' => route('bookings.receipt', ['booking' => $booking, 'receipt' => 1]),
                'pay' => route('bookings.pay', $booking),
                'confirmation' => route('bookings.confirmation', $booking),
            ],
        ];
    }

    /**
     * Ask the client to pay, by link.
     *
     * The amount is what this booking is collecting rather than what it is
     * worth: a deposit link asks for the deposit. Copied onto the link so it
     * keeps asking for that after somebody adds a service — what was quoted
     * is not rewritten by what changed afterwards.
     *
     * Email only, for the reason the confirmation is: text messages need a
     * sending account this business has not connected, and a button that
     * silently does nothing is worse than one that says why it cannot.
     *
     * Returns what went wrong, or null. Never throws: the appointment has
     * already been taken, and a booking rolled back because an email bounced
     * would be the wrong half undone.
     */
    private function sendPaymentLink(Booking $booking, Request $request): ?string
    {
        $to = $booking->client?->email ?: $booking->guest_email;

        if (! $to) {
            return __('bookings.payment.link_no_email');
        }

        $amount = $booking->payment_type === 'deposit' && $booking->deposit_minor > 0
            ? min((int) $booking->deposit_minor, $booking->dueMinor())
            : $booking->dueMinor();

        if ($amount <= 0) {
            return null;
        }

        $link = $booking->paymentLinks()->create([
            'tenant_id' => $booking->tenant_id,
            'amount_minor' => $amount,
            'currency_code' => $booking->currency_code,
            'token' => BookingPaymentLink::newToken(),
            'status' => 'sent',
            'channel' => 'email',
            'sent_to' => $to,
            'sent_at' => now(),
            'expires_at' => now()->addHours((int) config('bookings.payment_link_hours')),
            'created_by' => $request->user()->id,
        ]);

        try {
            Mail::to($to)->send(new BookingPaymentLinkMail(
                $link->load('booking.services'),
                tenant()?->name ?? config('app.name'),
            ));
        } catch (\Throwable $exception) {
            /* The row stays. "We tried to send this and the mail bounced" is
               a more useful thing for the desk to find than no record at
               all, and the status says it never got anywhere. */
            report($exception);

            return __('bookings.confirmation.link_failed');
        }

        return null;
    }

    /**
     * The ways this business can be paid, as the panel offers them.
     *
     * A method whose account has not been set up is still listed, but says so
     * and cannot be chosen: hiding it would leave a receptionist looking for
     * Venmo and concluding StyleDesk has none.
     *
     * @return array<int, array<string, mixed>>
     */
    private function paymentMethods(): array
    {
        $tenant = tenant();

        return collect(config('bookings.methods'))
            ->map(function (array $method, string $key) use ($tenant) {
                $handle = $method['handle'] ? (string) ($tenant?->{$method['handle']} ?? '') : '';

                return [
                    'key' => $key,
                    'name' => __('bookings.methods.'.$key.'.name'),
                    'hint' => __('bookings.methods.'.$key.'.hint'),
                    'manual' => (bool) $method['manual'],
                    'handle' => $handle ?: null,
                    /* Cash and card need no account of ours — a card is taken
                       one way or another, and where StyleDesk cannot charge it
                       the terminal by the till can. The rest are unusable
                       until somebody has said where the money goes. */
                    'ready' => $method['handle'] === null || $handle !== '',
                    /* Whether StyleDesk itself can charge it. Only a card can,
                       and only with a provider connected; without one the
                       panel records what the terminal took instead. */
                    'charges' => $key === 'card' && config('bookings.card_provider') !== null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What both taking and editing a booking ask for.
     *
     * @return array<string, mixed>
     */
    private function validateBooking(Request $request): array
    {
        return $request->validate([
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['required', 'date_format:H:i'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'source' => ['nullable', Rule::in(config('bookings.sources'))],
            'confirmation' => ['nullable', Rule::in(config('bookings.confirmations'))],
            'notes' => ['nullable', 'string', 'max:2000'],

            /* The same three the booking screen sends when it takes one in
               a single go. A draft being confirmed comes through here, and a
               tip agreed on screen must not be dropped on the way. */
            'payment_method' => ['nullable', Rule::in(ServicePrice::METHODS)],
            'coupon' => ['nullable', 'string', 'max:40'],
            'tip_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tip_amount' => ['nullable', 'numeric', 'min:0'],
            'resources' => ['nullable', 'array'],
            'resources.*' => ['nullable', 'integer'],
        ]);
    }

    /**
     * The chosen services, in the order they were chosen.
     *
     * Which is the order they will be worked in and the order the summary
     * showed, and neither of those is the order of the ids in the table.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Service>
     */
    private function chosenServices(array $ids, string $currency): Collection
    {
        return Service::query()->with('prices')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Service $service) => array_search($service->id, $ids, true))
            ->values();
    }

    /**
     * What a service costs on this booking, paid this way.
     *
     * Card unless the booking is being settled in cash. Card is the default
     * rather than the cheaper of the two: the screen has to quote *a* price
     * before anybody has said how they are paying, and quoting the lower one
     * and then charging more is the version a client complains about.
     */
    private function priceOf(Service $service, string $currency, string $method = 'card'): int
    {
        return $service->priceMinorFor($currency, $method);
    }

    /**
     * The coupon this booking was taken with, and what it takes off.
     *
     * Checked here rather than trusted from the screen: the quote the reader
     * saw was advisory, and a promotion can be used up, expire or stop
     * applying between quoting it and pressing Confirm.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?Promotion, 1: int}
     */
    private function couponFor(array $data, $services, $prices, string $currency, Request $request): array
    {
        if (blank($data['coupon'] ?? null)) {
            return [null, 0];
        }

        $promotion = Promotions::byCode((string) $data['coupon']);

        if ($promotion === null) {
            return [null, 0];
        }

        $lines = $services->values()->map(fn (Service $service, int $index) => [
            'service_id' => (int) $service->id,
            'category_id' => $service->service_category_id === null ? null : (int) $service->service_category_id,
            'price_minor' => (int) $prices[$index],
        ]);

        $client = isset($data['client_id']) ? Client::query()->find($data['client_id']) : null;

        $refusal = Promotions::refusal(
            $promotion, $lines, $client,
            isset($data['location_id']) ? (int) $data['location_id'] : null,
        );

        /* Refused at the last moment: the booking is still taken, at full
           price. Losing the appointment over a coupon would be the wrong
           trade. */
        return $refusal === null
            ? [$promotion, Promotions::discountMinor($promotion, $lines)]
            : [null, 0];
    }

    /**
     * The tip that was agreed, as a percentage and as an amount.
     *
     * A percentage is kept as a percentage so the till can work it out again
     * against whatever is actually owed; an amount somebody typed is kept as
     * the amount, because that was the decision.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?int, 1: ?int}
     */
    private function tipFor(array $data, int $totalMinor): array
    {
        if (isset($data['tip_amount'])) {
            return [null, (int) round(((float) $data['tip_amount']) * 100)];
        }

        if (isset($data['tip_percent'])) {
            $percent = (int) $data['tip_percent'];

            return [$percent, Tips::percentOf($totalMinor, $percent)];
        }

        return [null, null];
    }

    /**
     * Which price this booking is worked out at.
     *
     * Cash only where the money is actually being taken in cash. A booking
     * that will be paid by card at the desk next week is a card booking, and
     * pricing it as cash would quote a number nobody is going to charge.
     *
     * @param  array<string, mixed>  $data
     */
    private function pricedFor(array $data): string
    {
        return ($data['payment_method'] ?? null) === 'cash' ? 'cash' : 'card';
    }

    /**
     * Copy the services onto the booking.
     *
     * Copied rather than referenced: a service renamed or repriced next month
     * must not rewrite an appointment already taken.
     *
     * @param  Collection<int, Service>  $services
     */
    /**
     * The rooms somebody chose, keyed by service.
     *
     * Only rows that actually name a room: a blank means "you decide", which
     * is the usual answer and is not the same as a choice.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function chosenResources(array $data): array
    {
        return collect($data['resources'] ?? [])
            ->filter(fn ($resourceId) => $resourceId !== null && $resourceId !== '')
            ->mapWithKeys(fn ($resourceId, $serviceId) => [(int) $serviceId => (int) $resourceId])
            ->all();
    }

    /**
     * @param  array<int, int>  $chosenResources  service id => resource id
     */
    private function writeServiceLines(Booking $booking, $services, string $currency, array $chosenResources = []): void
    {
        foreach ($services as $index => $service) {
            /* A room the receptionist picked, or the first free one in
               preference order. Worked out per line rather than per booking:
               a massage at ten and a facial at eleven are two rooms.

               Ignoring this booking, so a service being re-saved does not
               find itself holding the room it is asking for. */
            $manual = array_key_exists($service->id, $chosenResources);

            $resource = $manual
                ? Resource::query()->find($chosenResources[$service->id])
                : ResourceAllocator::assignForService(
                    $service,
                    $booking->date->toDateString(),
                    $booking->startsAt(),
                    $booking->location_id,
                    $booking->id,
                );

            $booking->services()->create([
                'service_id' => $service->id,
                'resource_id' => $resource?->id,
                'resource_manual' => $manual && $resource !== null,
                'name' => $service->name,
                'minutes' => (int) $service->duration_minutes,
                /* What was charged, and what each price was on the day.
                   Prices change; last March's booking has to keep saying
                   what last March's prices were. */
                'price_minor' => $this->priceOf($service, $currency, $booking->priced_for ?: 'card'),
                'card_price_minor' => $this->priceOf($service, $currency, 'card'),
                'cash_price_minor' => $this->priceOf($service, $currency, 'cash'),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * What was true of this client when the appointment was taken.
     *
     * Read whole and never queried, so it is one column rather than five:
     * nothing filters bookings by what a tag said at the time, and the point
     * of it is only ever to be read back beside the booking it belongs to.
     *
     * @return array<string, mixed>|null
     */
    private function clientSnapshot(?int $clientId): ?array
    {
        $client = $clientId === null ? null : Client::query()->with('bookingPreferences')->find($clientId);

        if ($client === null) {
            return null;
        }

        return [
            'taken_at' => now()->toIso8601String(),
            'tags' => $client->behavioralTags()->pluck('label')->all(),
            'insights' => ClientInsights::for($client),
            'preferences' => $client->bookingPreferences->map(fn ($preference) => [
                'label' => $preference->label,
                'source' => $preference->source,
            ])->values()->all(),
        ];
    }

    /**
     * One row's actions.
     *
     * Only what this reader may actually do, and only what the app can
     * actually do: rescheduling and cancelling both change the diary and
     * neither flow exists, so they are shown disabled rather than left out.
     * An action that quietly is not there reads as a permission the reader
     * lacks, which is a different and worse thing to believe.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowMenu(Booking $booking): array
    {
        $user = request()->user();
        $canBook = $user?->hasPermission('appointments.create', 'own') ?? false;

        /* What can be done to this booking from where it has got to, decided
           by the same list the booking page reads. An action offered here and
           refused there would be a menu that lies.

           They lead to the booking rather than acting from the row, because
           every one of them asks a question first — a reason, a note, a new
           time — and a dropdown is not the place to answer it. Check-in is
           the exception, and it has its own button on the queue. */
        $actions = collect($booking->availableActions($user))
            ->map(fn (string $action) => [
                'label' => __('bookings.status.'.$action.'.action'),
                'url' => route('bookings.show', $booking).'#'.$action,
            ])
            ->all();

        return array_values(array_filter([
            ['label' => __('leads.actions.view_booking'), 'url' => route('bookings.show', $booking)],
            $booking->client
                ? ['label' => __('leads.actions.view_client'), 'url' => route('clients.show', $booking->client)]
                : null,
            $actions === [] ? null : ['separator' => true],
            ...$actions,
            $canBook && $booking->client ? ['separator' => true] : null,
            /* Same shape again, prefilled with this client: "book again" is
               the commonest thing a desk does with a past appointment. */
            $canBook && $booking->client
                ? ['label' => __('bookings.detail.book_again'), 'url' => route('bookings.create', ['client' => $booking->client_id])]
                : null,
            ['separator' => true],
            ['label' => __('bookings.confirmation.print'), 'url' => route('bookings.receipt', $booking)],
        ]));
    }

    /** Whether the client is here yet. */
    private function checkInLabel(Booking $booking): string
    {
        return match ($booking->status) {
            'arrived' => __('bookings.tabs.arrival.checked_in'),
            'completed' => __('bookings.tabs.arrival.done'),
            'no-show' => __('bookings.tabs.arrival.absent'),
            'confirmed' => $booking->isToday()
                ? __('bookings.tabs.arrival.waiting')
                : __('bookings.tabs.arrival.not_due'),
            default => '—',
        };
    }

    /**
     * How far off the appointment time they are.
     *
     * "12 min late" is what somebody at the desk acts on. The scheduled time
     * alone makes them read a clock and do the arithmetic themselves, forty
     * times a morning.
     *
     * Only for today's bookings that nobody has arrived for: a completed
     * appointment being nine minutes late is not news.
     */
    private function arrivalLabel(Booking $booking): ?string
    {
        if ($booking->status !== 'confirmed' || ! $booking->isToday()) {
            return null;
        }

        $minutes = (int) round(now()->diffInMinutes(
            $booking->date->copy()->setTimeFromTimeString($booking->startsAt()), false
        ));

        /* Only near the appointment. Somebody due at eight is not "266 min
           early" at half three — they are simply later, and a column of
           four-figure numbers is a column nobody reads. */
        if ($minutes > self::ARRIVAL_WINDOW) {
            return __('bookings.tabs.arrival.later');
        }

        if ($minutes > 0) {
            return __('bookings.tabs.arrival.early', ['count' => $minutes]);
        }

        /* The minute either side of the hour is "now" rather than "1 min
           late": a desk does not chase somebody who is not yet late. */
        return $minutes >= -1
            ? __('bookings.tabs.arrival.due')
            : __('bookings.tabs.arrival.late', ['count' => abs($minutes)]);
    }

    /** Late enough to chase reads differently from merely due. */
    private function arrivalClass(Booking $booking): ?string
    {
        if ($booking->status !== 'confirmed' || ! $booking->isToday()) {
            return null;
        }

        $minutes = (int) round(now()->diffInMinutes(
            $booking->date->copy()->setTimeFromTimeString($booking->startsAt()), false
        ));

        return match (true) {
            $minutes > self::ARRIVAL_WINDOW => 'styledesk_badge--setup',
            $minutes > 0 => 'styledesk_badge--info',
            $minutes >= -1 => 'styledesk_badge--active',
            /* Ten minutes is where a receptionist starts telephoning. */
            $minutes >= -10 => 'styledesk_badge--attention',
            default => 'styledesk_badge--danger',
        };
    }

    /**
     * The permission this screen needs, refused as a 403 rather than as an
     * empty page: a booking screen a reader may not use is not a booking
     * screen with nothing in it.
     */
    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }

    /**
     * What the listing is asking for.
     *
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        $payment = (string) $request->query('payment', '');
        $tab = (string) $request->query('tab', '');

        return [
            /* Today, because the question a front desk opens this page with
               is "who is coming in", not "show me every appointment ever
               taken". */
            'tab' => array_key_exists($tab, self::TABS) ? $tab : 'today',
            'search' => trim((string) $request->query('search', '')),
            'status' => array_key_exists($status, config('bookings.statuses')) ? $status : '',
            'payment' => array_key_exists($payment, config('bookings.payment_statuses')) ? $payment : '',
            'staff' => (string) $request->query('staff', ''),
            'location' => (string) $request->query('location', ''),
            'service' => (string) $request->query('service', ''),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) === 1
                ? (string) $request->query('date')
                : '',
            /* Which month the Month tab is showing. Its own parameter rather
               than a pair of dates, so the arrows either side of it have
               something simple to move. */
            'month' => preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) === 1
                ? (string) $request->query('month')
                : now()->format('Y-m'),
        ];
    }

    /**
     * Narrow the diary to the tab being read.
     *
     * The tabs are not saved searches. Each one is a question somebody at the
     * desk actually asks — who is in today, what is coming, who has not
     * arrived yet — and the ordering changes with it: a working view reads
     * forward from now, and a historical one reads backwards from the most
     * recent.
     */
    private function forTab(Builder $query, string $tab, string $month): Builder
    {
        $today = now()->startOfDay();

        return match ($tab) {
            'next-3' => $query->whereBetween('date', [
                $today->copy()->addDay()->toDateString(),
                $today->copy()->addDays(3)->toDateString(),
            ]),

            'month' => $query->whereBetween('date', [
                CarbonImmutable::parse($month.'-01')->startOfMonth()->toDateString(),
                CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString(),
            ]),

            /* The queue: today's confirmed bookings, which by definition are
               the ones nobody has checked in yet — checking somebody in is
               what moves them off this list. */
            'check-in' => $query->whereDate('date', $today)->where('status', 'confirmed'),

            'completed' => $query->where('status', 'completed'),
            'cancelled' => $query->where('status', 'cancelled'),
            'no-shows' => $query->where('status', 'no-show'),
            'declined' => $query->where('status', 'declined'),

            /* Every booking there has ever been. */
            'all' => $query,

            default => $query->whereDate('date', $today),
        };
    }

    /** Forward for a working view, backwards for a historical one. */
    private function orderFor(Builder $query, string $tab): Builder
    {
        return in_array($tab, ['today', 'next-3', 'check-in', 'month'], true)
            ? $query->orderBy('date')->orderBy('starts_at')
            : $query->orderByDesc('date')->orderBy('starts_at');
    }

    /**
     * How many each tab would show, for the ones where a number helps.
     *
     * Not every tab: a count beside all nine would be noise, and the one that
     * actually means "somebody has to do something" is the queue.
     *
     * @return array<string, int>
     */
    private function tabCounts(Request $request): array
    {
        $today = now()->startOfDay();

        return [
            'today' => Booking::query()->whereDate('date', $today)->whereNotIn('status', ['draft'])->count(),
            'next-3' => Booking::query()->whereBetween('date', [
                $today->copy()->addDay()->toDateString(),
                $today->copy()->addDays(3)->toDateString(),
            ])->whereNotIn('status', ['draft'])->count(),
            'check-in' => Booking::query()->whereDate('date', $today)->where('status', 'confirmed')->count(),
        ];
    }

    /**
     * How today is going, in five numbers.
     *
     * Shown above the Today table and clickable, because "six waiting to
     * check in" is only useful if pressing it shows you which six.
     *
     * @return array<int, array<string, mixed>>
     */
    private function todaySummary(Request $request): array
    {
        $today = now()->startOfDay();
        $counts = Booking::query()
            ->whereDate('date', $today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $cards = [
            ['key' => '', 'label' => __('bookings.tabs.summary.total'), 'count' => (int) $counts->except('draft')->sum()],
            ['key' => 'arrived', 'label' => __('bookings.tabs.summary.checked_in'), 'count' => (int) ($counts['arrived'] ?? 0)],
            ['key' => 'confirmed', 'label' => __('bookings.tabs.summary.pending'), 'count' => (int) ($counts['confirmed'] ?? 0)],
            ['key' => 'completed', 'label' => __('bookings.tabs.summary.completed'), 'count' => (int) ($counts['completed'] ?? 0)],
            ['key' => 'no-show', 'label' => __('bookings.tabs.summary.no_show'), 'count' => (int) ($counts['no-show'] ?? 0)],
        ];

        return $cards;
    }

    private function initialsOf(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }
}
