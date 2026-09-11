<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\Client;
use App\Models\SmsMessage;
use App\Models\SmsSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * StyleDesk SMS.
 *
 * The one way a text message leaves this application. Booking code, membership
 * code and the birthday sweep all arrive here; none of them knows which
 * carrier is under it, which is what makes changing carrier a change to one
 * class rather than to the booking system.
 *
 * Two things happen before a provider is ever called, and they happen in this
 * order on purpose:
 *
 *   1. Consent. A client who has opted out is not texted, whatever the code
 *      asking for it believes.
 *   2. The record. Written first, so a message that fails on the way out is
 *      still a message somebody can be shown an account of.
 */
class MessagingService
{
    public function __construct(private readonly SmsProvider $provider) {}

    /**
     * Write a message down and hand it over.
     *
     * @param  array<string, mixed>  $attributes  client_id, booking_id, client_membership_id, event_key
     */
    public function send(string $to, string $body, string $type, array $attributes = []): ?SmsMessage
    {
        $client = isset($attributes['client_id'])
            ? Client::query()->find($attributes['client_id'])
            : null;

        if (! SmsConsent::allows($client, $type)) {
            return null;
        }

        $message = $this->record($to, $body, $type, $attributes);

        /* Already sent. The unique key on the event caught a second attempt —
           a retried job, or two people pressing the button — and the honest
           answer is the message that already went. */
        if ($message === null) {
            return SmsMessage::query()
                ->where('event_key', $attributes['event_key'])
                ->first();
        }

        return $this->dispatch($message);
    }

    /**
     * Hand an already-recorded message to the provider.
     *
     * Its own method because a retry from the SMS log starts here: the record
     * exists, the consent was checked when it was written, and what is being
     * asked for is another attempt at the same message.
     */
    public function dispatch(SmsMessage $message): SmsMessage
    {
        $message->forceFill(['status' => 'sending'])->save();

        $result = $this->provider->send($message);

        $message->forceFill($result->accepted
            ? [
                'status' => 'sent',
                'sent_at' => now(),
                'provider_message_id' => $result->providerMessageId,
                'error_code' => null,
                'error_message' => null,
            ]
            : [
                'status' => 'failed',
                'failed_at' => now(),
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ])->save();

        return $message;
    }

    /**
     * The row, before anything is sent.
     *
     * Null where this exact message has already been written down. The unique
     * index does the deciding rather than a read-then-write, because two
     * queue workers can both find nothing a moment apart.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function record(string $to, string $body, string $type, array $attributes): ?SmsMessage
    {
        try {
            return DB::transaction(fn () => SmsMessage::create([
                'client_id' => $attributes['client_id'] ?? null,
                'booking_id' => $attributes['booking_id'] ?? null,
                'client_membership_id' => $attributes['client_membership_id'] ?? null,
                'type' => $type,
                /* The business's own number where it has one, and the
                   platform's otherwise. Stamped on the record rather than
                   read at send time: which number a message went from is a
                   fact about that message, and a business that changes
                   number next month must not rewrite last month's log. */
                'from_number' => SmsSettings::forTenant(tenant())->senderNumber(),
                'to_number' => $to,
                'body' => $body,
                'segments' => SmsSegments::count($body),
                'provider' => $this->provider->name(),
                'status' => 'queued',
                'event_key' => $attributes['event_key'] ?? null,
                'queued_at' => now(),
                /* The salon user who asked for it, where a salon user did.
                   Explicitly the `web` guard: the back office authenticates
                   on its own, and its admin ids are not user ids — writing
                   one here points a foreign key at whichever unrelated
                   person happens to hold that number. */
                'created_by' => Auth::guard('web')->id(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
