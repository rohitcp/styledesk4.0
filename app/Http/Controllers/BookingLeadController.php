<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookingLead;
use App\Support\Money;
use App\Support\TimeFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bookings that were started and not finished.
 *
 * Its own controller rather than a corner of BookingController: a lead is not
 * an appointment. It holds no slot, blocks no time and is worth nothing to
 * the diary — what it is worth is a phone call back, and that makes this a
 * list of work to do rather than a view of the day.
 *
 * The rows are written by the booking screen when the services are settled
 * (BookingController@storeLead), and marked converted by the booking they
 * become. Nothing here writes: this screen is for reading and returning to.
 */
class BookingLeadController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request);

        return view('bookings.leads.index', [
            'filters' => $this->filters($request),
            'hasLeads' => BookingLead::query()->where('status', '!=', 'converted')->exists(),
        ]);
    }

    /** The rows the grid asks for, as JSON. */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request);

        $filters = $this->filters($request);

        $leads = BookingLead::query()
            ->with(['client', 'booking', 'location', 'staff'])
            /* Bookings that were taken are not leads. They are kept in the
               table for the reporting — how many calls became appointments
               — but this screen is a queue of work still to do, and a
               finished booking on it is a job nobody has to do. Reachable by
               asking for it explicitly, which is what the reports will. */
            ->when($filters['status'] === '', fn (Builder $query) => $query->where('status', '!=', 'converted'))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $q) use ($filters) {
                $like = '%'.$filters['search'].'%';

                $q->where('reference', 'like', $like)
                    ->orWhere('guest_name', 'like', $like)
                    ->orWhereHas('client', fn (Builder $c) => $c
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('email', 'like', $like));
            }))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            /* Newest first: a lead is a call to return, and the call taken an
               hour ago is the one still worth returning. */
            ->orderByDesc('id')
            ->paginate(
                perPage: min(100, max(1, (int) $request->query('size', 25))),
                page: max(1, (int) $request->query('page', 1)),
            );

        return response()->json([
            'last_page' => $leads->lastPage(),
            'last_row' => $leads->total(),
            'total' => $leads->total(),
            'data' => $leads->getCollection()->map(fn (BookingLead $lead) => [
                'id' => $lead->id,
                'name' => $lead->forName(),
                'primary_badge' => $lead->reference,
                'initials' => $this->initialsOf($lead->forName()),
                'services' => collect($lead->services ?? [])->pluck('name')->implode(', '),
                /* Where it would be worked. A draft saved from the booking
                   screen knows its branch, and "which one" is the first
                   question anybody asks of a row they are picking up. */
                'location' => $lead->location?->name,
                'expected' => $lead->expected_date?->translatedFormat('j M Y'),
                'total' => Money::format($lead->total_minor / 100, $lead->currency_code),
                'started' => TimeFormat::dateTime($lead->created_at),
                /* Where the client stopped, beside what happened to the lead.
                   Two columns, because the desk needs both to know what the
                   call is about. */
                'step' => $lead->stepLabel(),
                'status' => $lead->statusLabel(),
                'status_class' => $lead->statusClass(),
                /* Where the row goes. A lead is a call to return, so an
                   open one reopens the booking screen with the client and
                   the services already chosen — finishing it converts this
                   lead rather than starting a second. One that already
                   became a booking goes to the booking. */
                /* No `url`: a click opens the drawer over this listing
                   rather than leaving it, so the desk can read three leads
                   without losing its filters and its place. The menu still
                   carries the ways off the page. */
                'drawer_url' => route('bookings.leads.show', $lead),
                'menu' => $this->rowMenu($lead),
            ])->all(),
        ]);
    }

    /**
     * One lead, for the drawer the listing opens over itself.
     *
     * Everything the front desk needs to pick up somebody else's call in one
     * answer: where it got to, who it is for, what they had chosen, what it
     * would cost, and what has happened to it so far. A field nobody has
     * filled in yet says so rather than coming back empty — a blank line
     * reads as broken, "Not selected" reads as a question still to ask.
     */
    public function show(Request $request, BookingLead $lead): JsonResponse
    {
        $this->allow($request);

        $lead->load(['client.bookingPreferences', 'booking', 'location', 'staff', 'createdBy', 'contactedBy', 'events.user']);

        $none = __('leads.drawer.not_selected');
        $services = collect($lead->services ?? []);
        $money = fn (int $minor) => Money::format($minor / 100, $lead->currency_code);

        return response()->json([
            'id' => $lead->id,
            'reference' => $lead->reference,
            'name' => $lead->forName(),
            'status' => $lead->statusLabel(),
            'status_key' => $lead->status,
            'status_class' => $lead->statusClass(),
            'step' => $lead->stepLabel(),
            'is_open' => $lead->booking === null,

            'summary' => [
                __('leads.drawer.created') => TimeFormat::dateTime($lead->created_at),
                __('leads.drawer.last_activity') => TimeFormat::dateTime($lead->last_activity_at ?? $lead->created_at),
                /* Who was at the desk when the call came in, and who has
                   spoken to them since. Two different people more often than
                   not, and the second is who to ask about it. */
                __('leads.drawer.created_by') => $lead->createdBy?->name ?: $none,
                __('leads.drawer.taken_by') => $lead->contactedBy?->name ?: $none,
            ],

            'client' => [
                __('leads.drawer.name') => $lead->forName(),
                __('leads.drawer.phone') => $lead->client?->mobile ?: $none,
                __('leads.drawer.email') => $lead->client?->email ?: $none,
                __('leads.drawer.preferences') => $lead->client?->bookingPreferences->pluck('label')->implode(' · ') ?: $none,
            ],
            'client_url' => $lead->client ? route('clients.show', $lead->client) : null,

            'booking' => [
                __('leads.drawer.services') => $services->pluck('name')->implode(', ') ?: $none,
                __('leads.drawer.duration') => $lead->minutes > 0
                    ? trans_choice('bookings.summary.minutes', $lead->minutes, ['count' => $lead->minutes])
                    : $none,
                __('leads.drawer.price') => $lead->total_minor > 0 ? $money((int) $lead->total_minor) : $none,
                __('leads.drawer.date') => $lead->expected_date?->translatedFormat('l j F Y') ?? $none,
                /* Answered where the booking screen got that far, and named
                   rather than hidden where it did not: what is missing is
                   what the call is about. */
                __('leads.drawer.time') => $lead->startsAtLabel() ?? $none,
                __('leads.drawer.staff') => $lead->staff?->displayName() ?? $none,
                __('leads.drawer.location') => $lead->location?->name ?? $none,
                /* The note about the appointment, and the note the desk
                   wrote about chasing the call. Two different things, and a
                   drawer that showed only one loses whichever it dropped. */
                __('leads.drawer.notes') => $lead->notes ?: ($lead->follow_up_note ?: $none),
            ],

            'payment' => [
                __('leads.drawer.estimated_total') => $money((int) $lead->total_minor),
                /* A deposit is asked for on the fourth card, which most
                   leads never reach; until one does there is nothing to
                   report but the bill itself. */
                __('leads.drawer.deposit_required') => $lead->deposit_minor > 0 ? $money((int) $lead->deposit_minor) : $none,
                __('leads.drawer.deposit_paid') => $lead->deposit_paid_minor > 0 ? $money((int) $lead->deposit_paid_minor) : $money(0),
                __('leads.drawer.outstanding') => $money(max(0, (int) $lead->total_minor - (int) $lead->deposit_paid_minor)),
                __('leads.drawer.payment_status') => $lead->statusLabel(),
            ],

            /* The five cards, with a tick on what was answered. Read at a
               glance: how much of this booking is already done. */
            'journey' => collect(config('bookings.lead_steps'))
                ->reject(fn (string $step) => $step === 'completed')
                ->map(fn (string $step, int $index) => [
                    'label' => __('leads.steps.'.$step),
                    'done' => $index < $this->stepIndex($lead->current_step),
                    'current' => $step === $lead->current_step,
                ])->values(),

            /* What has been written about this call so far. The notes live
               on the client — this is the slice of them that is about this
               lead. */
            'notes' => $lead->notes()->map(fn ($note) => [
                'body' => $note->body,
                'by' => $note->author?->name,
                'at' => TimeFormat::dateTime($note->created_at),
            ])->values(),
            'can_note' => $lead->client !== null,

            'events' => $lead->events->map(fn ($event) => [
                'label' => $event->label(),
                'at' => TimeFormat::dateTime($event->created_at),
                'by' => $event->user?->name,
            ])->values(),

            'urls' => [
                'complete' => route('bookings.create', ['lead' => $lead->id]),
                'cancel' => route('bookings.leads.cancel', $lead),
                'notes' => route('bookings.leads.notes', $lead),
                'booking' => $lead->booking ? route('bookings.show', $lead->booking) : null,
            ],
        ]);
    }

    /**
     * Stop chasing this one, and say why.
     *
     * The lead is kept: a cancelled call is half of every answer about how
     * many calls convert, and deleting it would leave the reports looking at
     * only the ones that went well.
     */
    public function cancel(Request $request, BookingLead $lead): JsonResponse
    {
        $this->allow($request);

        $data = $request->validate([
            'reason' => ['required', Rule::in(config('bookings.lead_cancel_reasons'))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        abort_if($lead->booking !== null, 422);

        $lead->update([
            'status' => 'cancelled',
            'reason_code' => $data['reason'],
            'reason_note' => $data['note'] ?? null,
            'last_activity_at' => now(),
        ]);

        $lead->note('cancelled', $data['reason'], $request->user()->id);

        return $this->show($request, $lead->fresh());
    }

    /**
     * A note written while chasing this lead.
     *
     * Kept on the client, because that is where the next person to look this
     * one up will read it — and tagged with the lead, because "rang, no
     * answer" means something different against a wedding enquiry than
     * against a walk-in.
     */
    public function note(Request $request, BookingLead $lead): JsonResponse
    {
        $this->allow($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        abort_if($lead->client === null, 422);

        $lead->client->clientNotes()->create([
            'tenant_id' => $lead->tenant_id,
            'booking_lead_id' => $lead->id,
            'body' => $data['body'],
            'format' => 'text',
            'created_by' => $request->user()->id,
        ]);

        /* Writing a note is contact: somebody has picked this lead up, and
           the queue should stop chasing them for it. */
        $lead->update([
            'status' => in_array($lead->status, BookingLead::CHASEABLE, true) || $lead->status === 'follow-up'
                ? 'contacted'
                : $lead->status,
            'contacted_by' => $request->user()->id,
            'contacted_at' => now(),
            'follow_up_note' => mb_substr($data['body'], 0, 500),
            'last_activity_at' => now(),
        ]);

        $lead->note('contacted', null, $request->user()->id);

        return $this->show($request, $lead->fresh());
    }

    /** Where in the five cards a step sits. */
    private function stepIndex(string $step): int
    {
        $index = array_search($step, config('bookings.lead_steps'), true);

        return $index === false ? 0 : (int) $index;
    }

    /**
     * One row's actions.
     *
     * Completing the booking is the only thing most of these rows are for, so
     * it leads — and it goes back into the booking screen carrying this lead
     * rather than starting a new one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowMenu(BookingLead $lead): array
    {
        if ($lead->booking) {
            return [
                ['label' => __('leads.actions.view_booking'), 'url' => route('bookings.show', $lead->booking)],
            ];
        }

        $menu = [
            ['label' => __('leads.actions.complete'), 'url' => route('bookings.create', ['lead' => $lead->id])],
        ];

        if ($lead->client) {
            $menu[] = ['label' => __('leads.actions.view_client'), 'url' => route('clients.show', $lead->client)];
        }

        return $menu;
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => array_key_exists($status, config('bookings.lead_statuses')) ? $status : '',
        ];
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('calendar.view', 'own'), 403);
    }

    private function initialsOf(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }
}
