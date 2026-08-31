<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Is this address really yours?", sent to the address being claimed.
 *
 * Delivered on an anonymous route rather than to the user, because the user's
 * address is precisely the one this must not go to: a link sent to the old
 * address would prove nothing about the new one, which is the only question
 * being asked. Everything the email says is therefore passed in — there is no
 * notifiable to read it from.
 *
 * The account keeps its existing address until this is answered, so an email
 * that never arrives costs nothing but the change.
 */
class VerifyEmailChange extends Notification
{
    public function __construct(
        private readonly string $firstName,
        private readonly string $newEmail,
        private readonly string $url,
        private readonly int $expiresInHours,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.auth_from.address'), config('mail.auth_from.name'))
            ->subject(__('account.profile.email_subject'))
            ->view('emails.account.verify-email-change', [
                'firstName' => $this->firstName,
                'url' => $this->url,
                'expiresInHours' => $this->expiresInHours,
                'newEmail' => $this->newEmail,
            ]);
    }
}
