<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\BookingLead;
use App\Models\BookingService;
use App\Models\Client;
use App\Models\ClientSettings;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Support\BookingTotals;
use App\Support\ClientBookingContext;
use App\Support\ClientInsights;
use App\Support\Currencies;
use App\Support\Money;
use App\Support\TimeFormat;
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
    public function index(Request $request): View
    {
        $this->allow($request, 'calendar.view');

        return view('bookings.index', [
            'filters' => $this->filters($request),
            'staff' => Staff::query()->where('is_active', true)->orderBy('first_name')->get(),
            'hasBookings' => Booking::query()->exists(),
        ]);
    }

    /** The rows the listing grid asks for, as JSON. */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request, 'calendar.view');

        $filters = $this->filters($request);

        $bookings = Booking::query()
            ->with(['client', 'staff', 'services', 'createdBy'])
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
            ->when($filters['staff'] !== '', fn (Builder $query) => $query->where('staff_id', $filters['staff']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('date', $filters['date']))
            ->orderByDesc('date')
            ->orderBy('starts_at')
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

        /* A lead being returned to: the screen opens with the client and the
           services that were chosen when the call dropped, and finishing it
           converts that lead rather than opening a second one. */
        $lead = BookingLead::query()
            ->with('client')
            ->whereNotIn('status', ['converted', 'cancelled', 'lost', 'expired'])
            ->find($request->query('lead'));

        /* A booking started from somebody's profile arrives with them
           already chosen: the receptionist asked for it from a page that
           knows who it is for, and asking again is asking twice. */
        $forClient = $lead?->client ?? Client::query()->find($request->query('client'));

        return view('bookings.create', [
            'client' => $forClient === null ? null : [
                'id' => $forClient->id,
                'name' => $forClient->displayName(),
                'initials' => $forClient->initials(),
                'ref' => $forClient->client_ref,
                'mobile' => $forClient->mobile,
                'email' => $forClient->email,
            ],
            'lead' => $lead === null ? null : [
                'id' => $lead->id,
                'reference' => $lead->reference,
                'client' => $lead->client === null ? null : [
                    'id' => $lead->client->id,
                    'name' => $lead->client->displayName(),
                    'initials' => $lead->client->initials(),
                    'ref' => $lead->client->client_ref,
                    'mobile' => $lead->client->mobile,
                    'email' => $lead->client->email,
                ],
                'guest_name' => $lead->guest_name,
                'service_ids' => collect($lead->services ?? [])->pluck('id')->filter()->values()->all(),
                'date' => $lead->expected_date?->toDateString(),
            ],
            'walkIn' => $request->boolean('walk-in'),
            'currency' => $currency,
            'services' => Service::query()->active()->with(['prices', 'resources'])->inOrder()->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category_id' => $service->service_category_id,
                    'minutes' => (int) $service->duration_minutes,
                    'price' => $service->priceLabel($currency),
                    'price_minor' => (int) ($service->prices->firstWhere('currency_code', $currency)?->price_minor ?? 0),
                    /* The chair, room or machine the service needs. Shown in
                       the summary because a booking that quietly needs the
                       only colour bar is a booking somebody has to know
                       about. */
                    'resources' => $service->resources->pluck('name')->values(),
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
            'services.*' => ['integer', Rule::exists('services', 'id')],
            'source' => ['nullable', Rule::in(config('bookings.sources'))],
            'payment_type' => ['nullable', Rule::in(config('bookings.payment_types'))],
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'deposit_action' => ['nullable', Rule::in(config('bookings.deposit_actions'))],
            'confirmation' => ['nullable', Rule::in(config('bookings.confirmations'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_note' => ['nullable', 'string', 'max:2000'],
            'draft' => ['nullable', 'boolean'],
            /* The lead this booking grew out of, where the screen wrote one. */
            'lead_id' => ['nullable', 'integer', Rule::exists('booking_leads', 'id')],
        ]);

        /* Somebody has to be named. A client on file or a walk-in's name are
           both answers; nothing at all is not. */
        if (empty($data['client_id']) && trim((string) ($data['guest_name'] ?? '')) === '') {
            return back()->withInput()->withErrors([
                'client_id' => __('bookings.validation.who'),
            ]);
        }

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

        /* The bill, worked out once and written down. Every screen that shows
           it afterwards reads what was stored rather than the price list,
           which is what stops a price edited in March rewriting what somebody
           was charged in February. */
        $totals = BookingTotals::of(
            $services->map(fn (Service $service) => (int) ($service->prices->firstWhere('currency_code', $currency)?->price_minor ?? 0)),
            $currency,
        );

        $booking = DB::transaction(function () use ($data, $services, $minutes, $starts, $currency, $totals, $request) {
            $booking = Booking::create([
                'reference' => $this->reference(),
                'client_id' => $data['client_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
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
                /* Stated rather than left to the column default: the panel is
                   answered from this instance, and an attribute the database
                   filled in is one the reply would not have. */
                'payment_status' => 'unpaid',
                'paid_minor' => 0,
                'payment_type' => $data['payment_type'] ?? 'none',
                'deposit_minor' => (int) round(((float) ($data['deposit'] ?? 0)) * 100),
                'deposit_action' => $data['deposit_action'] ?? null,
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
                'created_by' => $request->user()->id,
            ]);

            foreach ($services as $index => $service) {
                $booking->services()->create([
                    'service_id' => $service->id,
                    'name' => $service->name,
                    'minutes' => (int) $service->duration_minutes,
                    'price_minor' => (int) ($service->prices->firstWhere('currency_code', $currency)?->price_minor ?? 0),
                    'sort_order' => $index,
                ]);
            }

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
            if (! empty($data['lead_id'])) {
                $lead = BookingLead::query()->find($data['lead_id']);

                $lead?->update([
                    'status' => 'converted',
                    'current_step' => 'completed',
                    'booking_id' => $booking->id,
                    'converted_at' => now(),
                    'last_activity_at' => now(),
                ]);

                $lead?->note('converted', $booking->reference, $request->user()->id);
            }

            return $booking;
        });

        /* The booking screen asks in JSON and stays where it is: the third
           column moves from summary to payment without the receptionist
           losing the page, and a redirect would throw away the context the
           whole screen exists to keep. */
        if ($request->expectsJson()) {
            return response()->json(['booking' => $this->panel($booking)], 201);
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
        $totals = BookingTotals::of(
            $services->map(fn (Service $service) => $this->priceOf($service, $currency)),
            $currency,
        );

        DB::transaction(function () use ($booking, $data, $services, $minutes, $starts, $currency, $totals) {
            $booking->update([
                'client_id' => $data['client_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
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
                'notes' => $data['notes'] ?? null,
                'confirmation' => $data['confirmation'] ?? $booking->confirmation,
            ]);

            /* Replaced rather than reconciled: the lines are a copy of what
               was chosen, and matching them up one by one would be work in
               aid of keeping ids nothing refers to. */
            $booking->services()->delete();
            $this->writeServiceLines($booking, $services, $currency);
        });

        return response()->json(['booking' => $this->panel($booking->fresh())]);
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
            ->orderByDesc('date')
            ->orderByDesc('starts_at')
            ->get();

        /* Grouped by the month they happened in, newest first: that is how
           anybody reads a history, and a flat list of forty is a scroll. */
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

        $booking->load(['client', 'staff', 'location', 'services.service.resources', 'payments.recordedBy', 'createdBy']);
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
                        __('bookings.summary.resource') => $booking->services
                            ->flatMap(fn ($line) => $line->service?->resources->pluck('name') ?? collect())
                            ->unique()->implode(', ') ?: $none,
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
                'at' => $payment->paid_at?->translatedFormat('j M Y · H:i'),
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
            'staff', 'location', 'services.service.category', 'services.service.resources',
            'payments.recordedBy',
        ]);

        $client = $booking->client;
        $snapshot = $booking->client_snapshot ?? [];

        return view('bookings.show', [
            'booking' => $booking,
            'totals' => BookingTotals::for($booking),
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

        /* Money handed over that is less than the bill is a part payment, not
           an error: the rest is still owed and the status will say so. What
           is refused is being given less than the line claims to be. */
        if ($received !== null && $received < $amount) {
            throw ValidationException::withMessages([
                'received' => __('bookings.pay.short_cash'),
            ]);
        }

        $payment = DB::transaction(function () use ($booking, $data, $amount, $received, $request) {
            $payment = $booking->payments()->create([
                'tenant_id' => $booking->tenant_id,
                'method' => $data['method'],
                'status' => 'paid',
                'amount_minor' => $amount,
                'currency_code' => $booking->currency_code,
                'received_minor' => $received,
                'change_minor' => $received === null ? null : max(0, $received - $amount),
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'paid_at' => now(),
                'recorded_by' => $request->user()->id,
            ]);

            $booking->load('payments')->settlePaymentStatus();

            return $payment;
        });

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
        $booking->loadMissing(['client', 'staff', 'location', 'services', 'payments']);
        $totals = BookingTotals::for($booking);

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
            'payment_status' => $booking->payment_status,
            'payment_status_label' => $booking->paymentStatusLabel(),
            'payments' => $booking->payments->map(fn ($payment) => [
                'method' => $payment->method,
                'method_label' => $payment->methodLabel(),
                'amount' => $payment->amountLabel(),
                'reference' => $payment->reference,
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

    private function priceOf(Service $service, string $currency): int
    {
        return (int) ($service->prices->firstWhere('currency_code', $currency)?->price_minor ?? 0);
    }

    /**
     * Copy the services onto the booking.
     *
     * Copied rather than referenced: a service renamed or repriced next month
     * must not rewrite an appointment already taken.
     *
     * @param  Collection<int, Service>  $services
     */
    private function writeServiceLines(Booking $booking, $services, string $currency): void
    {
        foreach ($services as $index => $service) {
            $booking->services()->create([
                'service_id' => $service->id,
                'name' => $service->name,
                'minutes' => (int) $service->duration_minutes,
                'price_minor' => $this->priceOf($service, $currency),
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
        $canBook = request()->user()?->hasPermission('appointments.create', 'own') ?? false;

        return array_values(array_filter([
            ['label' => __('leads.actions.view_booking'), 'url' => route('bookings.show', $booking)],
            $booking->client
                ? ['label' => __('leads.actions.view_client'), 'url' => route('clients.show', $booking->client)]
                : null,
            $canBook ? ['separator' => true] : null,
            /* Same shape again, prefilled with this client: "book again" is
               the commonest thing a desk does with a past appointment. */
            $canBook && $booking->client
                ? ['label' => __('bookings.detail.book_again'), 'url' => route('bookings.create', ['client' => $booking->client_id])]
                : null,
            $canBook ? ['label' => __('bookings.detail.reschedule'), 'disabled' => true] : null,
            $canBook ? ['label' => __('bookings.detail.cancel_booking'), 'disabled' => true] : null,
            ['separator' => true],
            ['label' => __('bookings.confirmation.print'), 'url' => route('bookings.receipt', $booking)],
        ]));
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

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => array_key_exists($status, config('bookings.statuses')) ? $status : '',
            'staff' => (string) $request->query('staff', ''),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) === 1
                ? (string) $request->query('date')
                : '',
        ];
    }

    /**
     * A short, human reference for one booking.
     *
     * Said over the phone more often than it is read, so it avoids the
     * characters that sound alike — no O against 0, no I against 1.
     */
    private function reference(): string
    {
        do {
            $reference = 'BK-'.substr(str_shuffle('ACDEFGHJKLMNPQRTUVWXY2346789'), 0, 6);
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function initialsOf(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }
}
