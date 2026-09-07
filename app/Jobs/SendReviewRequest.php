<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ReviewRequestMail;
use App\Models\BookingReview;
use App\Models\ReviewSettings;
use App\Support\ReviewRequests;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Ask one client what they thought, an hour or a day after they left.
 *
 * Queued with a delay rather than sent at completion, because the delay is the
 * feature: §5 lets the business choose it, and a request that arrives while
 * the client is still paying reads as being hurried out of the door.
 *
 * Everything it was queued on can have changed by the time it runs — that is
 * what a delay of a day means — so it re-reads the decision rather than
 * trusting the one made when it was dispatched.
 */
class SendReviewRequest implements ShouldQueue
{
    use Queueable;

    /**
     * Three attempts, backing off 10s then 30s.
     *
     * Enough to ride out a provider rate-limit or a dropped connection
     * without turning a misconfiguration into a job that retries for a week.
     * A request that never sends stays unsent on the booking's Customer
     * Review panel, where somebody can press Send again.
     */
    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(public BookingReview $review) {}

    public function handle(): void
    {
        $review = $this->review->fresh();

        if ($review === null) {
            return;
        }

        /* Already asked, or already answered. A manual resend between this
           being queued and running is the ordinary way that happens, and
           sending again would put two identical emails in one inbox. */
        if ($review->sent_at !== null || $review->isSubmitted()) {
            return;
        }

        $booking = $review->booking;
        $client = $review->client;

        if ($booking === null || $client === null) {
            return;
        }

        /* The business may have switched reviews off in the meantime, and a
           day-long delay is long enough for that to be a real decision rather
           than a race. Honour it: a salon that turned the asking off should
           not have yesterday's queue keep asking on their behalf. */
        $settings = ReviewSettings::forTenant($booking->tenant);

        if (! $settings->is_enabled || ! ReviewRequests::hasEmail($client)) {
            return;
        }

        Mail::to($client->email)->send(new ReviewRequestMail(
            $review,
            $booking->tenant?->name ?? config('app.name'),
        ));

        /* Stamped only once it has been sent. Marking it at queue time was
           the mistake the invitation module already made and fixed: with no
           worker running, the booking would report a request that was still
           sitting in the jobs table. */
        $review->forceFill(['sent_at' => now()])->save();
    }

    /**
     * Runs after the final attempt has failed.
     *
     * The provider's message goes to the log and nowhere else — it can name
     * hosts and credentials, none of which belong on a booking screen. The
     * row stays unsent, which is what the panel reads.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Review request email failed to send.', [
            'booking_review_id' => $this->review->id,
            'tenant_id' => $this->review->tenant_id,
            'exception' => $e->getMessage(),
        ]);
    }
}
