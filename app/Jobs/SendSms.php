<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Messaging\MessagingService;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Send a text message, away from the request that caused it.
 *
 * A booking confirmation must not make the desk wait on an API call to a
 * carrier, and a morning's reminders must not be a thousand of them in one
 * request. The consent check and the record both happen inside the job, so a
 * message that is refused is refused once and written down once however many
 * times the job is retried.
 *
 * Idempotent by the event key, which the messaging service enforces with a
 * unique index rather than a read: two workers can both find nothing a moment
 * apart, and only one row can be written.
 */
class SendSms implements ShouldQueue
{
    use Queueable;

    /**
     * Three attempts, spread out.
     *
     * A carrier having a bad minute is worth waiting for; a bad number is
     * not, and the provider says which by refusing rather than throwing.
     */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $to,
        private readonly string $body,
        private readonly string $type,
        private readonly array $attributes = [],
    ) {}

    public function handle(MessagingService $messaging): void
    {
        /* The queue has no tenant of its own. Without this the record would
           be written against whichever business the worker last served, or
           none at all — and an SMS log is the last place a message should be
           able to appear under the wrong name. */
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $messaging->send($this->to, $this->body, $this->type, $this->attributes);
        } finally {
            tenancy()->end();
        }
    }
}
