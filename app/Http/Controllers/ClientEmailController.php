<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientEmailMessage;
use App\Support\ClientEmailSender;
use App\Support\ClientEmailTemplates;
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

        return response()->json([
            'to' => [
                'name' => $client->displayName(),
                'email' => $client->email,
            ],
            'from' => [
                'name' => $from['name'],
                'email' => $from['reply_to'] ?? $from['from'],
                /* What the client will actually see in their inbox. Shown
                   because "Smile Spa via StyleDesk" surprises an owner who
                   expected their own address, and the settings screen is
                   where that is changed. */
                'label' => __('client_email.send.from_via', ['name' => $from['name']]),
            ],
            'enabled' => ClientEmailSender::readyFor($tenant),
            'provider' => ClientEmailSender::providerFor($tenant),
            /* Every reason the drawer might have to refuse, answered before it
               opens rather than after the sender has typed a message. */
            'blocked' => $this->blockedReason($client, $tenant),
            'templates' => ClientEmailTemplates::all($client),
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
        ]);

        $email = ClientEmailSender::send(
            client: $client,
            subject: $data['subject'],
            body: $data['message'],
            sender: $request->user(),
            booking: isset($data['booking_id']) ? Booking::find($data['booking_id']) : null,
            templateKey: $data['template_key'] ?? null,
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
