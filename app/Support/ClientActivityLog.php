<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientNote;

/**
 * Writing down what happened to a client.
 *
 * One place that knows the shape of every entry, rather than a dozen call
 * sites each inventing their own: the timeline reads them all back through
 * one renderer, and two spellings of "booking.cancelled" is a filter that
 * silently stops matching half the history.
 *
 * Every method is fire-and-forget. Recording is never the point of the
 * request it happens in — a booking that failed to take because its history
 * could not be written would be the tail wagging the dog — so nothing here
 * throws, and a failure leaves the thing that happened intact.
 */
class ClientActivityLog
{
    /**
     * A booking was taken.
     */
    public static function bookingCreated(Booking $booking): void
    {
        if ($booking->client_id === null) {
            return;
        }

        self::write($booking->client_id, 'booking.created', 'bookings', [
            'booking_id' => $booking->id,
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'user_id' => $booking->created_by,
            'description' => self::bookingLine($booking),
            'meta' => self::bookingMeta($booking),
        ]);
    }

    /**
     * A booking moved.
     *
     * Both the old slot and the new one, because "rescheduled" without the
     * previous time is an entry that answers half the question — and the half
     * it drops is the one somebody is usually looking for.
     *
     * @param  array{date: ?string, time: ?string}  $was
     */
    public static function bookingRescheduled(Booking $booking, array $was, ?int $userId = null, ?string $reason = null, ?string $note = null): void
    {
        if ($booking->client_id === null) {
            return;
        }

        self::write($booking->client_id, 'booking.rescheduled', 'bookings', [
            'booking_id' => $booking->id,
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'user_id' => $userId,
            'description' => self::bookingLine($booking),
            'changes' => [[
                'field' => __('clients.module.workspace.activity.fields.when'),
                'from' => trim(($was['date'] ?? '').' · '.($was['time'] ?? ''), ' ·'),
                'to' => $booking->date->isoFormat('D MMM Y').' · '.$booking->timeLabel(),
            ]],
            'meta' => self::bookingMeta($booking) + array_filter([
                'reason' => $reason,
                'note' => $note,
            ]),
        ]);
    }

    public static function bookingCancelled(Booking $booking, ?string $reason = null, ?int $userId = null, ?string $note = null): void
    {
        self::statusChanged($booking, 'booking.cancelled', $reason, $note, $userId);
    }

    /** The client arrived and the desk said so. */
    public static function bookingCheckedIn(Booking $booking, ?string $note = null, ?int $userId = null): void
    {
        self::statusChanged($booking, 'booking.checked_in', null, $note, $userId);
    }

    /** Nobody came. */
    public static function bookingNoShow(Booking $booking, ?string $reason = null, ?string $note = null, ?int $userId = null): void
    {
        self::statusChanged($booking, 'booking.no_show', $reason, $note, $userId);
    }

    /** A request the business turned down. */
    public static function bookingDeclined(Booking $booking, ?string $reason = null, ?string $note = null, ?int $userId = null): void
    {
        self::statusChanged($booking, 'booking.declined', $reason, $note, $userId);
    }

    /**
     * A booking that ended in something other than the work being done.
     *
     * The three of them are one shape — a status, a reason and a note — so
     * they are one method: three copies would be three places for the next
     * reader to check when the timeline prints one of them differently.
     */
    private static function statusChanged(Booking $booking, string $type, ?string $reason, ?string $note, ?int $userId): void
    {
        if ($booking->client_id === null) {
            return;
        }

        self::write($booking->client_id, $type, 'bookings', [
            'booking_id' => $booking->id,
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'user_id' => $userId,
            'description' => self::bookingLine($booking),
            'meta' => self::bookingMeta($booking) + array_filter([
                'reason' => $reason,
                'note' => $note,
            ]),
        ]);
    }

    /* ------------------------------------------------------------ notes -- */

    public static function noteAdded(ClientNote $note): void
    {
        self::note($note, 'note.added');
    }

    public static function noteUpdated(ClientNote $note): void
    {
        self::note($note, 'note.updated');
    }

    /**
     * The note is gone; the record of it is not.
     *
     * Passed the note as it was rather than re-read, because by the time this
     * is useful the row no longer exists.
     */
    public static function noteDeleted(ClientNote $note, ?int $userId = null): void
    {
        self::note($note, 'note.deleted', $userId);
    }

    public static function noteImportanceChanged(ClientNote $note, bool $important, ?int $userId = null): void
    {
        self::note($note, $important ? 'note.marked_important' : 'note.unmarked_important', $userId);
    }

    public static function notePrivacyChanged(ClientNote $note, bool $private, ?int $userId = null): void
    {
        self::note($note, $private ? 'note.made_private' : 'note.made_shared', $userId);
    }

    /* ----------------------------------------------------------- client -- */

    /**
     * The profile was edited.
     *
     * One entry per save, not per field: somebody who changed a phone number,
     * an address and a branch did one thing, and three entries for it is a
     * timeline that buries everything else that day.
     *
     * @param  array<int, array{field: string, from: ?string, to: ?string}>  $changes
     */
    public static function clientUpdated(Client $client, array $changes, ?int $userId = null): void
    {
        if ($changes === []) {
            return;
        }

        self::write($client->id, 'client.updated', 'client', [
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'user_id' => $userId,
            'changes' => array_values($changes),
        ]);
    }

    /* ------------------------------------------------------------- tags -- */

    public static function tagAdded(Client $client, string $label, string $kind = 'tag', ?int $userId = null): void
    {
        self::write($client->id, 'tag.added', 'tags', [
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'user_id' => $userId,
            'description' => $label,
            'meta' => ['kind' => $kind],
        ]);
    }

    public static function tagRemoved(Client $client, string $label, string $kind = 'tag', ?int $userId = null): void
    {
        self::write($client->id, 'tag.removed', 'tags', [
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'user_id' => $userId,
            'description' => $label,
            'meta' => ['kind' => $kind],
        ]);
    }

    /* --------------------------------------------------------- payments -- */

    /**
     * Money arrived.
     *
     * Part paid and paid in full are two different entries, because they are
     * two different things to read on a Monday morning: one is settled, the
     * other is a balance somebody has to chase.
     */
    public static function paymentReceived(BookingPayment $payment, Booking $booking): void
    {
        if ($booking->client_id === null) {
            return;
        }

        $totals = BookingTotals::for($booking);
        $due = $booking->dueMinor();

        self::write($booking->client_id, $due > 0 ? 'payment.partial' : 'payment.received', 'payments', [
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'subject_type' => BookingPayment::class,
            'subject_id' => $payment->id,
            'user_id' => $payment->recorded_by,
            'description' => $payment->amountLabel(),
            'meta' => [
                'method' => $payment->methodLabel(),
                'reference' => $booking->reference,
                'total' => $totals->money((int) $booking->total_minor),
                'paid' => $totals->money($booking->paidMinor()),
                'due' => $totals->money($due),
            ],
        ]);
    }

    /**
     * A bill exists and nothing has been paid against it.
     *
     * Recorded when the booking is taken rather than when somebody notices,
     * so the timeline shows the debt from the day it was owed.
     */
    public static function paymentDue(Booking $booking): void
    {
        if ($booking->client_id === null || $booking->dueMinor() <= 0) {
            return;
        }

        $totals = BookingTotals::for($booking);

        self::write($booking->client_id, 'payment.due', 'payments', [
            'booking_id' => $booking->id,
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'user_id' => $booking->created_by,
            'description' => $totals->money($booking->dueMinor()),
            'meta' => ['reference' => $booking->reference],
        ]);
    }

    /* ---------------------------------------------------------- writing -- */

    private static function note(ClientNote $note, string $type, ?int $userId = null): void
    {
        if ($note->client_id === null) {
            return;
        }

        self::write($note->client_id, $type, 'notes', [
            'subject_type' => ClientNote::class,
            'subject_id' => $note->id,
            'user_id' => $userId ?? $note->created_by,
            'description' => $note->body,
            /* Marked on the row so the timeline can withhold the body without
               loading the note back — which, for a deletion, is gone. */
            'is_private' => (bool) $note->is_private,
        ]);
    }

    /** "All-Over Color with Mei Chen", as much of it as there is. */
    private static function bookingLine(Booking $booking): string
    {
        $booking->loadMissing(['services', 'staff']);

        return collect([
            $booking->services->pluck('name')->implode(', ') ?: null,
            $booking->staff?->displayName(),
        ])->filter()->implode(' · ');
    }

    /** @return array<string, mixed> */
    private static function bookingMeta(Booking $booking): array
    {
        return array_filter([
            'reference' => $booking->reference,
            'when' => $booking->date?->isoFormat('D MMM Y').' · '.$booking->timeLabel(),
        ]);
    }

    /**
     * The write itself.
     *
     * Swallows everything. Recording is never the point of the request it
     * happens in: a booking that failed to take because its history could not
     * be written would be the tail wagging the dog.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function write(int $clientId, string $type, string $category, array $attributes): void
    {
        try {
            ClientActivity::create($attributes + [
                'tenant_id' => tenant()?->getTenantKey() ?? Client::query()->whereKey($clientId)->value('tenant_id'),
                'client_id' => $clientId,
                'type' => $type,
                'category' => $category,
                'user_id' => $attributes['user_id'] ?? auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
