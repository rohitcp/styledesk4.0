<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SendReviewRequest;
use App\Models\Booking;
use App\Models\BookingReview;
use App\Models\Client;
use App\Models\ReviewSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Asking the client what they thought.
 *
 * One place decides whether a completed appointment earns a review request,
 * when it should go out and how — because that decision is made from three
 * screens (the Complete button, the manual Send Review Request, and one day a
 * backfill) and three copies of it is how a business ends up asking twice.
 *
 * Nothing here throws into the caller. Completing an appointment must not fail
 * because the review module could not be reached: the visit happened either
 * way, and a red page at the front desk over an email nobody has read yet is
 * the wrong trade.
 */
class ReviewRequests
{
    /**
     * Write the request down and queue the asking.
     *
     * Returns the row when one was created, null when this booking earns no
     * request — reviews switched off, no client, nobody to write to, or a
     * request already made. The caller does not need to know which; the
     * screen behaves the same for all four.
     */
    public static function scheduleFor(Booking $booking, ?int $userId = null): ?BookingReview
    {
        try {
            $settings = ReviewSettings::forTenant($booking->tenant);

            if (! $settings->is_enabled) {
                return null;
            }

            $client = $booking->client;

            if ($client === null) {
                return null;
            }

            /* Nothing this business can actually send on. §6: an unavailable
               method is skipped, and a client with neither is not asked at
               all rather than asked into the void. */
            $channel = self::channelFor($settings, $client);

            if ($channel === null) {
                return null;
            }

            /* `booking_id` is unique on the table, so a booking completed
               twice — reopened, finished again — cannot collect a second
               request. Checked here as well so the second attempt is a quiet
               no rather than a constraint violation. */
            if (BookingReview::query()->where('booking_id', $booking->id)->exists()) {
                return null;
            }

            $due = self::dueAt($booking, (string) $settings->delay);

            $review = BookingReview::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->id,
                'client_id' => $client->id,
                /* Copied off the booking rather than read through it later:
                   §30 wants the review to describe the visit that happened,
                   and a booking reassigned next week must not move what
                   somebody said about this one. */
                'staff_id' => $booking->staff_id,
                'location_id' => $booking->location_id,
                'service_id' => self::primaryServiceId($booking),
                'token' => BookingReview::newToken(),
                'channel' => $channel,
                'scheduled_for' => $due,
                'status' => 'new',
                'updated_by' => $userId,
            ]);

            SendReviewRequest::dispatch($review)->delay($due);

            return $review;
        } catch (\Throwable $e) {
            /* Swallowed on purpose, and loudly logged. The appointment is
               finished whether or not this worked. */
            Log::warning('Could not schedule a review request.', [
                'booking_id' => $booking->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * When the asking is due.
     *
     * Measured from now rather than from the appointment's end time. The
     * business is saying "a while after they leave", and a booking completed
     * an hour late would otherwise have its request already overdue the
     * moment it was written.
     */
    public static function dueAt(Booking $booking, string $delay): CarbonImmutable
    {
        $rule = config('reviews.delays.'.$delay) ?? config('reviews.delays.1h');

        if ($rule['minutes'] !== null) {
            return CarbonImmutable::now()->addMinutes((int) $rule['minutes']);
        }

        /* Next day is not a duration. A business choosing it means "not
           tonight" — so it is tomorrow morning, at an hour a client will not
           mind being asked. */
        return CarbonImmutable::now()
            ->addDay()
            ->setTime((int) $rule['next_morning_hour'], 0);
    }

    /**
     * How this client can be asked, or null if they cannot be.
     *
     * The business's choice narrowed by what StyleDesk can deliver and by
     * what this client has. A salon set to SMS is asking for something no
     * driver exists for yet, so email is used where the client has one —
     * §6's "skip the unavailable method" read the only way that helps
     * anybody.
     */
    public static function channelFor(ReviewSettings $settings, Client $client): ?string
    {
        $wanted = (string) ($settings->channel ?? 'email');

        $emailAllowed = in_array($wanted, ['email', 'both'], true)
            || ! in_array('sms', ReviewSettings::availableChannels(), true);

        if ($emailAllowed && self::hasEmail($client)) {
            return 'email';
        }

        return null;
    }

    /** A working address the client has not asked us to stop using. */
    public static function hasEmail(Client $client): bool
    {
        return filled($client->email) && (bool) $client->comm_email;
    }

    /**
     * The service the review is about.
     *
     * The first line of the booking. A visit with three services rated as one
     * is a compromise, but attributing the rating to all three would triple
     * every service's review count — and a client tapping four stars is
     * rating the visit, not filling in a form per line.
     */
    private static function primaryServiceId(Booking $booking): ?int
    {
        return $booking->services()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('service_id');
    }
}
