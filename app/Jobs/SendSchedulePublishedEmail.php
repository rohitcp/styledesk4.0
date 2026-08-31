<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SchedulePublishedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Deliver one published-schedule email.
 *
 * Queued so publishing returns straight away: a manager who has just confirmed
 * a four-week rota should not be held on the dialog for the length of a mail
 * provider's connection timeout, and a provider failure is not something they
 * can act on from there.
 *
 * The shifts are already marked published by the time this runs. That order is
 * deliberate — a schedule the staff member can see in the app but that never
 * got an email is a smaller problem than an email describing a schedule the
 * database never committed.
 */
class SendSchedulePublishedEmail implements ShouldQueue
{
    use Queueable;

    /** Three attempts, backing off 10s then 30s — the same shape as the invitation mail. */
    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(
        public string $email,
        public SchedulePublishedMail $mail,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send($this->mail);
    }

    /**
     * The provider's message goes to the log and nowhere else: it can name
     * hosts and credentials, none of which belong in front of a manager.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Published schedule email failed to send.', [
            'staff_id' => $this->mail->staff->id,
            'tenant_id' => $this->mail->staff->tenant_id,
            'from' => $this->mail->startsOn,
            'until' => $this->mail->endsOn,
            'exception' => $e->getMessage(),
        ]);
    }
}
