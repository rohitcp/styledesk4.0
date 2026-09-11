<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A text message on its way to the development inbox.
 *
 * The SMS catcher hooks Laravel's notification system: it listens for the
 * `sms` channel, reads `toSms()`, writes the message into its own store and
 * halts the send. This is the shape it expects, and nothing more — the body
 * has already been rendered from the same template production uses, so there
 * is no second version of any message to keep in step.
 *
 * Never used outside local development. See App\Messaging\LocalSmsProvider.
 */
class CaughtSms extends Notification
{
    public function __construct(
        private readonly string $body,
        private readonly string $type = 'sms',
        private readonly ?string $from = null,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(mixed $notifiable): string
    {
        return $this->body;
    }

    /**
     * What the inbox files it under.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return ['type' => $this->type, 'from' => $this->from];
    }
}
