<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingStatusChange;
use App\Models\ReasonCode;

/**
 * Writing down what was done to a booking, and why.
 *
 * One place that knows the shape of every entry, rather than four call sites
 * each inventing their own — the history is read back through one renderer,
 * and two spellings of a status is a filter that silently stops matching half
 * of it.
 *
 * The reason is written twice over: the code it was chosen from, and the
 * words that code had on the day. The words are what gets printed, so a
 * business renaming their list next spring has renamed their list and not
 * last March's no-show.
 */
class BookingStatusHistory
{
    /**
     * A status change, recorded and applied.
     *
     * The booking moves and the row is written in the same breath, because a
     * status nobody can account for is the thing this whole feature exists to
     * prevent.
     *
     * @param  array<string, mixed>  $extra  the slots and people a reschedule moves
     */
    public static function record(
        Booking $booking,
        string $toStatus,
        ?ReasonCode $reason,
        ?string $note,
        ?string $details,
        ?int $userId,
        array $extra = [],
    ): BookingStatusChange {
        return BookingStatusChange::create($extra + [
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'from_status' => $booking->getOriginal('status') ?? $booking->status,
            'to_status' => $toStatus,
            'reason_type' => $reason?->type,
            'reason_code_id' => $reason?->id,
            /* The words, not just the pointer. This is what the timeline
               prints for the rest of the booking's life. */
            'reason_label' => $reason?->name,
            'note' => $note,
            'details' => $details,
            'changed_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
