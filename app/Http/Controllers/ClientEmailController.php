<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientEmailMessage;
use App\Support\ClientEmailSender;
use App\Support\ClientEmailTemplates;
use App\Support\EmailSender;
use App\Support\TimeFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Writing to a client from their profile.
 *
 * A drawer over the profile rather than a page of its own: the sender is
 * usually looking at the very thing they are writing about — a balance, a
 * missed appointment — and sending them to a second screen loses it.
 *
 * Sending and reading are separate permissions on purpose. A receptionist who
 * may write to a client is not automatically somebody who should be able to
 * read every message the business has ever sent them, and the reverse is true
 * of a manager reviewing what went out.
 */
class ClientEmailController extends Controller
{
    /**
     * What the drawer needs to open: who it is going to, who it is from, and
     * the templates it can start from.
     */
    public function compose(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'email.send');

        $tenant = $request->user()->tenant;
        $from = ClientEmailTemplates::sender($tenant);
        $provider = ClientEmailSender::providerFor($tenant);

        /* The appointment the templates should read against.
         *
         * Without one, "Thank you for coming in on {{booking_date}}" renders
         * with nothing to put there and the variable is left standing in the
         * text — deliberately, because a placeholder in somebody's inbox is
         * reported within the hour where a silently blanked sentence is never
         * noticed at all. The drawer asks again with a booking once the
         * sender picks one, and the wording fills in.
         *
         * Scoped to this client's own bookings: another client's id would
         * write somebody else's appointment into this message.
         */
        $context = $request->filled('booking_id')
            ? $client->bookings()->whereKey($request->integer('booking_id'))->first()
            : null;

        return response()->json([
            'to' => [
                'name' => $client->displayName(),
                'email' => $client->email,
                /* Every address on file, not only the cached primary: a
                   client whose work address is the one they answer should be
                   reachable at it without editing their record first. */
                'options' => $client->emails()
                    ->orderByDesc('is_primary')
                    ->orderBy('position')
                    ->get()
                    ->map(fn ($row) => [
                        'email' => $row->email,
                        'label' => $row->typeLabel(),
                    ])
                    ->values()
                    ->all(),
            ],
            'from' => [
                'name' => $from['name'],
                'email' => $from['from'],
                /* Where a reply lands, which is not always where it was sent
                   from: "Smile Spa via StyleDesk" goes out on StyleDesk's
                   own sending domain and comes back to the salon. Read-only
                   here — it is a setting, not a per-message decision.
                   Gmail sends carry no reply-to at all, because a reply to
                   the connected mailbox already arrives in it. */
                'reply_to' => $provider === 'gmail'
                    ? $from['from']
                    : ($from['reply_to'] ?? $from['from']),
                /* What the client will actually see in their inbox. Shown
                   because "Smile Spa via StyleDesk" surprises an owner who
                   expected their own address — and so does the qualifier
                   still sitting there once they have connected their own. */
                'label' => EmailSender::displayName($from['name'], $from['from']),
            ],
            'enabled' => ClientEmailSender::readyFor($tenant),
            'provider' => $provider,
            /* Every reason the drawer might have to refuse, answered before it
               opens rather than after the sender has typed a message. */
            'blocked' => $this->blockedReason($client, $tenant),
            'templates' => ClientEmailTemplates::all($client, $context),
            /* Which booking the wording above was rendered against, so the
               drawer knows whether what it is holding is current. */
            'context_booking_id' => $context?->id,
            'bookings' => $client->bookings()
                ->latest('date')
                ->limit(20)
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'label' => $booking->date->translatedFormat('j M Y')
                        .' · '.TimeFormat::time($booking->starts_at)
                        .' · '.$booking->services->pluck('name')->implode(', '),
                ])->values(),
            'limits' => config('client_email.limits'),
        ]);
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'email.send');

        $limits = config('client_email.limits');

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:'.$limits['subject']],
            'message' => ['required', 'string', 'max:'.$limits['message']],
            'template_key' => ['nullable', Rule::in(config('client_email.templates'))],
            /* Scoped to this client's own bookings: a booking id from another
               client would attach somebody else's appointment to the record. */
            'booking_id' => [
                'nullable',
                Rule::exists('bookings', 'id')->where('client_id', $client->id),
            ],
            /* Which of this client's addresses. Shape only here; that it is
               one of theirs is checked by the sender, which is the boundary
               every path to a send passes through. */
            'to' => ['nullable', 'email', 'max:255'],
        ]);

        $email = ClientEmailSender::send(
            client: $client,
            subject: $data['subject'],
            body: $data['message'],
            sender: $request->user(),
            booking: isset($data['booking_id']) ? Booking::find($data['booking_id']) : null,
            templateKey: $data['template_key'] ?? null,
            to: $data['to'] ?? null,
        );

        /* A send that failed on the wire is reported as one. The row exists
           either way — the desk has to know an attempt was made — but telling
           them it worked would be worse than telling them nothing. */
        if ($email->status === ClientEmailMessage::STATUS_FAILED) {
            throw ValidationException::withMessages([
                'subject' => __('client_email.errors.failed'),
            ]);
        }

        return response()->json([
            'message' => __('client_email.send.sent', ['name' => $client->displayName()]),
            'email' => $this->row($email),
        ], 201);
    }

    /** The client's email history, newest first. */
    public function index(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'email.view_history');

        return response()->json([
            'emails' => $client->emailMessages()
                ->with('sentBy:id,first_name,last_name,display_name')
                ->newest()
                ->limit(100)
                ->get()
                ->map(fn (ClientEmailMessage $email) => $this->row($email))
                ->values(),
        ]);
    }

    /** One message in full, for the reader who clicked it. */
    public function show(Request $request, Client $client, ClientEmailMessage $email): JsonResponse
    {
        $this->allow($request, 'email.view_history');

        abort_unless($email->client_id === $client->id, 404);

        return response()->json($this->row($email) + [
            'message' => $email->message,
            'recipient' => $email->recipient_email,
            'booking_id' => $email->booking_id,
        ]);
    }

    /**
     * Why this client cannot be written to, or null if they can.
     *
     * Ordered from the business's problem to the client's: an owner who has
     * not switched the feature on should be told that, not that this
     * particular client has no address.
     */
    private function blockedReason(Client $client, $tenant): ?string
    {
        /* The business's own problems first, from ClientEmailSender so the
           drawer and the endpoint cannot disagree about why. */
        if (($reason = ClientEmailSender::blockingReason($tenant)) !== null) {
            return $reason;
        }

        /* Then this client's. Last, because an owner who has not switched the
           feature on should be told that rather than that this particular
           client has no address. */
        return filter_var((string) $client->email, FILTER_VALIDATE_EMAIL)
            ? null
            : __('client_email.errors.no_address');
    }

    /** @return array<string, mixed> */
    private function row(ClientEmailMessage $email): array
    {
        return [
            'id' => $email->id,
            'subject' => $email->subject,
            'status' => $email->status,
            'status_label' => $email->statusLabel(),
            'status_tone' => $email->statusTone(),
            'provider_label' => $email->providerLabel(),
            'sent_by' => $email->senderLabel(),
            'at' => TimeFormat::dateTime($email->sent_at ?? $email->created_at),
        ];
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
