<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The verification email, carrying both ways of verifying.
 *
 * A subclass rather than a `VerifyEmail::toMailUsing` callback, which is what
 * this used to be: the code is minted per send and has to travel with the
 * notification, and a static callback has nowhere to receive it. The signed
 * URL is still built by the parent, so its expiry and signature are Laravel's.
 */
class VerifyEmail extends BaseVerifyEmail
{
    public function __construct(
        private readonly string $code,
        private readonly int $expiresInMinutes,
    ) {}

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.auth_from.address'), config('mail.auth_from.name'))
            ->subject('Verify your email to get started with StyleDesk')
            ->view('emails.auth.verify-email', [
                'firstName' => $notifiable->first_name ?: (string) $notifiable->email,
                'url' => $this->verificationUrl($notifiable),
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}
