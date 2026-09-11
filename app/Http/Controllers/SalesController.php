<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\Location;
use App\Models\MembershipPayment;
use App\Models\Staff;
use App\Support\ClientVisitSummary;
use App\Support\Currencies;
use App\Support\Money;
use App\Support\SalesLedger;
use App\Support\SalesPeriod;
use App\Support\SalesSummary;
use App\Support\TimeFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sales — what was sold, what was collected, and what is still owed.
 *
 * A transaction here is one payment row, not one booking: a bill settled half
 * in cash and half on a card is two transactions against one appointment, and
 * collapsing them would lose the answer to "did that card payment go
 * through". The booking's own totals travel with each row so the table can
 * show what the appointment came to beside what this payment covered.
 */
class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request, 'payments.view_transactions');

        $period = $this->period($request);
        $filters = $this->filters($request);

        return view('sales.index', [
            'period' => $period,
            'filters' => $filters,
            'summary' => SalesSummary::for($period, $filters),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'staff' => Staff::query()->get()->mapWithKeys(fn (Staff $s) => [$s->id => $s->displayName()])->all(),
            'methods' => collect(array_keys(config('bookings.methods')))
                ->mapWithKeys(fn (string $key) => [$key => __('bookings.methods.'.$key.'.name')])
                ->all(),
            'statuses' => collect(array_keys(config('bookings.payment_statuses')))
                ->mapWithKeys(fn (string $key) => [$key => __('bookings.payment_statuses.'.$key.'.label')])
                ->all(),
        ]);
    }

    /** The table's rows, a page at a time. */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request, 'payments.view_transactions');

        $period = $this->period($request);
        $filters = $this->filters($request);

        /* One list from two tables. Money reaches this business as a payment
           against a booking and as a payment against a membership, and a
           Sales page that showed only the first was one that quietly
           under-reported every membership sold. */
        $page = SalesLedger::page(
            $this->query($period, $filters),
            $this->membershipQuery($period, $filters),
            perPage: min(100, max(1, (int) $request->query('size', 25))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return response()->json([
            'last_page' => $page['last_page'],
            'last_row' => $page['total'],
            'total' => $page['total'],
            'data' => $page['items']
                ->map(fn ($payment) => $payment instanceof MembershipPayment
                    ? $this->membershipRow($payment)
                    : $this->row($payment))
                ->all(),
        ]);
    }

    /**
     * A client, in the same drawer a booking opens in.
     *
     * Built here rather than on ClientController because it is this screen's
     * question — "who is this transaction for" — answered in the shape the
     * shared drawer renders. The client module's own profile is the full
     * answer, and the footer button goes there.
     */
    public function clientDrawer(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'payments.view_transactions');

        $client->load('tags');
        $none = __('leads.drawer.not_selected');

        $owed = (int) $client->bookings()
            ->selectRaw('COALESCE(SUM(GREATEST(CAST(total_minor AS SIGNED) - CAST(paid_minor AS SIGNED), 0)), 0) as due')
            ->value('due');

        /* The four figures the client module already works out — visits,
           next appointment, lifetime spend — read from there rather than
           counted again here. "How many times have they been" has one
           answer, and a second implementation of it would eventually give a
           different one. */
        $summary = ClientVisitSummary::for($client);

        $currency = Currencies::resolve();
        $money = fn (int $minor) => Money::format($minor / 100, $currency);

        return response()->json([
            'name' => $client->displayName(),
            'reference' => $client->client_ref,
            /* The model's own label. `clients.statuses.<status>.label` is not
               a key that exists, and __() hands back the key it cannot find —
               which is how "clients.statuses.active.label" ended up printed
               on the badge. */
            'status' => $client->statusLabel(),
            'status_class' => $client->statusClass(),
            /*
             * The four figures as the cards the client's own profile draws,
             * not as label-and-value rows.
             *
             * Somebody who has seen this client's profile should recognise
             * these at a glance, and the same four numbers rendered two ways
             * across two screens is two things to learn about one client.
             * Same classes, same tones, same order.
             */
            'metrics' => [
                [
                    'tone' => 'violet',
                    'label' => __('clients.module.workspace.summary.next_appointment'),
                    'value' => data_get($summary, 'next_appointment.value'),
                    'detail' => data_get($summary, 'next_appointment.detail'),
                    'empty' => __('clients.module.workspace.summary.no_upcoming'),
                ],
                [
                    'tone' => 'teal',
                    'label' => __('clients.module.workspace.summary.total_visits'),
                    'value' => data_get($summary, 'total_visits.value'),
                    'detail' => data_get($summary, 'total_visits.detail'),
                    'empty' => __('clients.module.workspace.summary.no_visits_yet'),
                ],
                [
                    'tone' => 'amber',
                    'label' => __('clients.module.workspace.summary.lifetime_spend'),
                    'value' => data_get($summary, 'lifetime_spend.value'),
                    'detail' => data_get($summary, 'lifetime_spend.detail'),
                    'empty' => __('clients.module.workspace.summary.nothing_paid'),
                ],
                [
                    'tone' => 'blue',
                    'label' => __('sales.drawer.owed'),
                    /* Nothing owed is stated as nothing owed rather than as
                       $0.00 — a figure reads as a debt, and this is the one
                       card somebody scans for a problem. */
                    'value' => $owed > 0 ? $money($owed) : null,
                    'empty' => __('sales.drawer.nothing_owed'),
                ],
            ],
            'sections' => array_values(array_filter([
                [
                    'title' => __('leads.drawer.client'),
                    'rows' => [
                        __('email_templates.variables.names.email') => $client->email ?: $none,
                        __('email_templates.variables.names.phone') => $client->mobile ?: $none,
                        __('sales.drawer.last_visit') => data_get($summary, 'last_visit.value') ?: $none,
                    ],
                ],
                /* Only when they have any. A "Tags" heading over a dash is a
                   section that answers nothing. */
                $client->tags->isNotEmpty() ? [
                    'title' => __('sales.drawer.tags'),
                    'rows' => [
                        __('sales.drawer.tags') => $client->tags->pluck('label')->implode(', '),
                    ],
                ] : null,
            ])),
            'urls' => ['show' => route('clients.show', $client)],
        ]);
    }

    /**
     * A receipt, in the drawer, with a way to keep a copy.
     *
     * The full receipt is its own page and prints properly; this is the
     * summary somebody wants while scanning the table, plus the two ways out
     * of it.
     */
    public function receiptDrawer(Request $request, BookingPayment $payment): JsonResponse
    {
        $this->allow($request, 'payments.view_transactions');

        $payment->load(['booking.client', 'booking.services', 'booking.staff', 'booking.location', 'recordedBy']);

        $booking = $payment->booking;
        $currency = (string) ($payment->currency_code ?: $booking?->currency_code);
        $money = fn (?int $minor) => Money::format(((int) $minor) / 100, $currency);
        $none = __('leads.drawer.not_selected');

        $total = (int) ($booking?->total_minor ?? 0);
        $paid = (int) ($booking?->paid_minor ?? 0);

        return response()->json([
            'name' => $booking?->client?->displayName() ?? __('sales.walk_in'),
            'reference' => self::reference($payment),
            'status' => __('bookings.payment_statuses.'.$payment->status.'.label'),
            'status_class' => config('bookings.payment_statuses.'.$payment->status.'.class', 'styledesk_badge--soon'),
            'step' => $payment->methodLabel(),
            'sections' => [
                [
                    'title' => __('sales.drawer.transaction'),
                    'rows' => [
                        __('sales.columns.at') => TimeFormat::dateTime($payment->paid_at ?? $payment->created_at),
                        __('sales.columns.method') => $payment->methodLabel(),
                        __('sales.drawer.amount') => $money($payment->amount_minor),
                        __('sales.drawer.tip') => $payment->tip_minor ? $money($payment->tip_minor) : $none,
                        __('sales.drawer.recorded_by') => $payment->recordedBy?->name ?? __('sales.drawer.online'),
                        __('sales.drawer.processor_reference') => $payment->reference ?: $none,
                    ],
                ],
                [
                    'title' => __('sales.drawer.booking'),
                    'rows' => array_filter([
                        __('sales.columns.booking') => $booking?->reference,
                        __('sales.columns.services') => $booking?->services->pluck('name')->implode(', '),
                        __('sales.columns.staff') => $booking?->staff?->displayName(),
                        __('sales.columns.location') => $booking?->location?->name,
                        __('sales.columns.total') => $money($total),
                        __('sales.drawer.paid_total') => $money($paid),
                        __('sales.columns.balance') => $money(max(0, $total - $paid)),
                    ]),
                ],
            ],
            'urls' => array_filter([
                'show' => $booking ? route('bookings.receipt', $booking) : null,
                /* Saving a copy is the browser's print dialogue on the receipt
                   page, which every browser offers as "Save as PDF". A
                   server-rendered PDF would mean a new dependency, and this
                   produces the same file. */
                'download' => $booking ? route('bookings.receipt', ['booking' => $booking, 'print' => 1]) : null,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<BookingPayment>
     */
    private function query(SalesPeriod $period, array $filters): Builder
    {
        return BookingPayment::query()
            ->with([
                'booking:id,reference,client_id,staff_id,location_id,total_minor,paid_minor,date,starts_at',
                'booking.client:id,first_name,last_name,email,mobile',
                'booking.staff:id,first_name,last_name',
                'booking.location:id,name',
                'booking.services:id,booking_id,name',
                'recordedBy:id,first_name,last_name,display_name',
            ])
            ->whereBetween('paid_at', [$period->from, $period->to])
            /* Asked for memberships only: this half of the ledger answers
               nothing, and a query that matched none is cheaper than one
               whose rows are thrown away afterwards. */
            ->when($filters['type'] === 'membership', fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when($filters['method'], fn (Builder $q, $m) => $q->where('method', $m))
            ->when($filters['status'], fn (Builder $q, $s) => $q->where('status', $s))
            /* Location, staff and service live on the booking, so they filter
               through it rather than being denormalised onto the payment —
               a copy would be a second answer that drifts. */
            ->when(
                $filters['location'] || $filters['staff'] || $filters['service'] !== '',
                fn (Builder $q) => $q->whereHas('booking', fn (Builder $b) => $b
                    ->when($filters['location'], fn (Builder $x, $id) => $x->where('location_id', $id))
                    ->when($filters['staff'], fn (Builder $x, $id) => $x->where('staff_id', $id))
                    ->when($filters['service'] !== '', fn (Builder $x) => $x->whereHas(
                        'services',
                        fn (Builder $s) => $s->where('name', 'like', '%'.$filters['service'].'%'),
                    ))),
            )
            ->when($filters['search'] !== '', fn (Builder $q) => $this->search($q, $filters['search']))
            /* A balance still owing is a property of the booking, so the
               widget's filter reaches through to it. */
            ->when($filters['balance'], fn (Builder $q) => $q->whereHas(
                'booking',
                fn (Builder $b) => $b->whereColumn('paid_minor', '<', 'total_minor'),
            ))
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /**
     * What a reader would type into the box.
     *
     * The transaction reference is built from the id, so a search for
     * "TXN-20260902-000123" is answered by pulling the number back out of it
     * rather than by storing a second copy of the same fact.
     */
    private function search(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like, $term): void {
            $q->where('reference', 'like', $like)
                ->orWhereHas('booking', fn (Builder $b) => $b
                    ->where('reference', 'like', $like)
                    ->orWhereHas('client', fn (Builder $c) => $c
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhereRaw("concat_ws(' ', first_name, last_name) like ?", [$like])));

            if (($id = self::idFromReference($term)) !== null) {
                $q->orWhere('id', $id);
            }
        });
    }

    /**
     * The membership half of the ledger.
     *
     * Filtered on the same terms where they mean anything and refused where
     * they do not: a membership has no staff member and no service, so a
     * reader filtering by either is asking a question this half cannot
     * answer — and returning memberships anyway would be answering a
     * different one.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<MembershipPayment>
     */
    private function membershipQuery(SalesPeriod $period, array $filters): Builder
    {
        return MembershipPayment::query()
            ->with([
                'membership:id,reference,client_id,membership_plan_id,location_id,type,billing_frequency,currency_code,price_minor',
                'membership.client:id,first_name,last_name,email,mobile,client_ref',
                'membership.plan:id,name,internal_code,type',
                'membership.location:id,name',
                'recordedBy:id,first_name,last_name,display_name',
            ])
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->when($filters['type'] === 'service', fn (Builder $q) => $q->whereRaw('1 = 0'))
            /* No staff and no service on a membership. A filter for either is
               a filter this half of the ledger cannot satisfy. */
            ->when(
                $filters['staff'] || $filters['service'] !== '' || $filters['balance'],
                fn (Builder $q) => $q->whereRaw('1 = 0'),
            )
            ->when($filters['method'], fn (Builder $q, $m) => $q->where('method', $m))
            ->when($filters['status'], fn (Builder $q, $s) => $q->where('status', $s))
            ->when($filters['location'], fn (Builder $q, $id) => $q->whereHas(
                'membership',
                fn (Builder $m) => $m->where('location_id', $id),
            ))
            ->when($filters['search'] !== '', fn (Builder $q) => $this->searchMemberships($q, $filters['search']))
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /**
     * What a reader would type, looking for a membership sale.
     *
     * The plan's own code as well as the client's name: a membership is
     * referred to internally by that code, and somebody reconciling a
     * statement has it in front of them.
     *
     * @param  Builder<MembershipPayment>  $query
     * @return Builder<MembershipPayment>
     */
    private function searchMemberships(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like, $term): void {
            $q->where('reference', 'like', $like)
                ->orWhereHas('membership', fn (Builder $m) => $m
                    ->where('reference', 'like', $like)
                    ->orWhereHas('plan', fn (Builder $p) => $p
                        ->where('name', 'like', $like)
                        ->orWhere('internal_code', 'like', $like))
                    ->orWhereHas('client', fn (Builder $c) => $c
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhereRaw("concat_ws(' ', first_name, last_name) like ?", [$like])));

            if (($id = self::idFromReference($term)) !== null) {
                $q->orWhere('id', $id);
            }
        });
    }

    /**
     * One membership sale, in the shape the table reads.
     *
     * The same keys a booking payment answers with — the grid is one table
     * and a row that filled half of them would be a row with holes in it.
     * Where a membership genuinely has no answer, the column says so rather
     * than borrowing a booking's.
     *
     * @return array<string, mixed>
     */
    private function membershipRow(MembershipPayment $payment): array
    {
        $membership = $payment->membership;
        $plan = $membership?->plan;
        $client = $membership?->client;

        $currency = (string) ($payment->currency_code ?: $membership?->currency_code);
        $money = fn (?int $minor) => Money::format(((int) $minor) / 100, $currency);

        return [
            'id' => $payment->id,
            'reference' => self::membershipReference($payment),
            'at' => TimeFormat::dateTime($payment->paid_at ?? $payment->created_at),
            'type' => __('sales.row_types.membership'),
            /* What was sold, where a booking names its services. */
            'services' => $plan?->name ?? '—',
            'membership' => $plan?->name,
            'membership_type' => $membership === null ? null : __('membership.types.'.$membership->type),
            /* Both numbers: the membership this client holds, and the plan
               it was sold from. A reconciliation has one or the other in
               front of it. */
            'membership_number' => $membership?->reference,
            'membership_code' => $plan?->internal_code,
            /* A membership takes no slot, so there is no booking to open and
               no staff member who performed it. The column a booking fills
               with its reference is not left blank, though: the membership's
               own number is the thing a reader reconciles this row against. */
            'booking' => $membership?->reference,
            'booking_url' => $membership ? route('membership.sales.show', $membership) : null,
            'staff' => '—',
            'client' => $client?->displayName() ?? __('sales.walk_in'),
            'client_url' => $client ? route('clients.show', $client) : null,
            'location' => $membership?->location?->name,
            /* What the membership costs, and what this row moved. A renewal
               is its own transaction, so "total" is the membership's price
               rather than a running sum across cycles. */
            'total' => $money($membership?->price_minor),
            'amount' => $money($payment->amount_minor),
            'paid' => $money($payment->amount_minor),
            'balance' => $money(0),
            'method' => $payment->methodLabel(),
            'status' => __('bookings.payment_statuses.'.$payment->status.'.label'),
            'status_class' => config('bookings.payment_statuses.'.$payment->status.'.class', 'styledesk_badge--soon'),
            'processed_by' => $payment->recordedBy?->displayName(),
            'menu' => array_values(array_filter([
                $membership ? [
                    'label' => __('sales.actions.view_membership'),
                    'event' => 'sales:booking',
                    'payload' => ['url' => route('client-memberships.drawer', $membership)],
                ] : null,
                $client ? [
                    'label' => __('sales.actions.view_client'),
                    'event' => 'sales:client',
                    'payload' => ['url' => route('sales.client-drawer', $client)],
                ] : null,
                ['separator' => true],
                $membership ? [
                    'label' => __('sales.actions.open_membership'),
                    'url' => route('membership.sales.show', $membership),
                    'target' => '_blank',
                ] : null,
            ])),
        ];
    }

    /** "MTX-20260902-000123" — a membership sale, told apart from a booking's. */
    public static function membershipReference(MembershipPayment $payment): string
    {
        $date = ($payment->paid_at ?? $payment->created_at)?->format('Ymd') ?? '00000000';

        return 'MTX-'.$date.'-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function row(BookingPayment $payment): array
    {
        $booking = $payment->booking;
        $currency = (string) ($payment->currency_code ?: $booking?->currency_code);
        $money = fn (?int $minor) => Money::format(((int) $minor) / 100, $currency);

        $total = (int) ($booking?->total_minor ?? 0);
        $paid = (int) ($booking?->paid_minor ?? 0);

        return [
            'id' => $payment->id,
            'reference' => self::reference($payment),
            'at' => TimeFormat::dateTime($payment->paid_at ?? $payment->created_at),
            'type' => __('sales.row_types.service'),
            'booking' => $booking?->reference,
            'booking_url' => $booking ? route('bookings.show', $booking) : null,
            'client' => $booking?->client?->displayName() ?? __('sales.walk_in'),
            'client_url' => $booking?->client ? route('clients.show', $booking->client) : null,
            'services' => $booking?->services->pluck('name')->implode(', '),
            'staff' => $booking?->staff?->displayName() ?? __('bookings.any_staff'),
            'location' => $booking?->location?->name,
            'total' => $money($total),
            /* This payment's own amount, and what the booking has taken in
               total: "did this one go through" and "is the bill settled" are
               different questions and the table answers both. */
            'amount' => $money($payment->amount_minor),
            'paid' => $money($paid),
            'balance' => $money(max(0, $total - $paid)),
            'method' => $payment->methodLabel(),
            'status' => __('bookings.payment_statuses.'.$payment->status.'.label'),
            'status_class' => config('bookings.payment_statuses.'.$payment->status.'.class', 'styledesk_badge--soon'),
            'processed_by' => $payment->recordedBy?->displayName(),
            'menu' => array_values(array_filter([
                /* Announced rather than navigated to: the page opens the
                   booking in a drawer over the table, so the reader keeps
                   their filters, their page and their place. `event` is how
                   the grid hands a choice back to the page that knows what to
                   do with it. */
                $booking ? [
                    'label' => __('sales.actions.view_booking'),
                    'event' => 'sales:booking',
                    'payload' => ['url' => route('bookings.drawer', $booking)],
                ] : null,
                $booking?->client ? [
                    'label' => __('sales.actions.view_client'),
                    'event' => 'sales:client',
                    'payload' => ['url' => route('sales.client-drawer', $booking->client)],
                ] : null,
                [
                    'label' => __('sales.actions.view_receipt'),
                    'event' => 'sales:receipt',
                    'payload' => ['url' => route('sales.receipt-drawer', $payment)],
                ],
                ['separator' => true],
                /* The full page, in a tab of its own: somebody who wants the
                   whole booking usually wants to keep the table as well. */
                $booking ? [
                    'label' => __('sales.actions.open_booking'),
                    'url' => route('bookings.show', $booking),
                    'target' => '_blank',
                ] : null,
            ])),
        ];
    }

    /** "TXN-20260902-000123" — the date it moved and the row it is. */
    public static function reference(BookingPayment $payment): string
    {
        $date = ($payment->paid_at ?? $payment->created_at)?->format('Ymd') ?? '00000000';

        return 'TXN-'.$date.'-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }

    /** The row id back out of a reference somebody pasted into the search. */
    public static function idFromReference(string $term): ?int
    {
        return preg_match('/^TXN-\d{8}-(\d+)$/i', trim($term), $match) === 1
            ? (int) $match[1]
            : null;
    }

    private function period(Request $request): SalesPeriod
    {
        return SalesPeriod::fromRequest(
            $request->query('period'),
            $request->query('from'),
            $request->query('to'),
        );
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'location' => $request->query('location') ?: null,
            'staff' => $request->query('staff') ?: null,
            'service' => trim((string) $request->query('service', '')),
            'method' => $request->query('method') ?: null,
            'status' => $request->query('status') ?: null,
            /* What kind of thing was sold. A booking and a membership are
               both transactions and both belong here; the filter is for a
               reader reconciling one of them at a time. */
            'type' => in_array($request->query('type'), ['service', 'membership'], true)
                ? $request->query('type')
                : null,
            /* Set by the Outstanding Balance widget, which is a filter as much
               as a figure. */
            'balance' => $request->boolean('balance'),
        ];
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
