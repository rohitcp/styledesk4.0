<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Where a text message goes.
 *
 * In development it goes nowhere: the SMS catcher listens for the notification
 * on its way out, writes it into the inbox at /dev/sms and halts the send, so
 * this class is never reached. That is the whole arrangement — the booking
 * code sends a confirmation the same way in every environment, and only the
 * environment decides whether a phone rings.
 *
 * Anywhere the catcher is switched off there has to be something to fall
 * through to, or Laravel answers "Driver [sms] not supported" — an error about
 * the framework rather than about the account nobody connected. This says the
 * true thing instead. It becomes a sending provider the day StyleDesk has one.
 */
class SmsChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        throw new RuntimeException(
            'No SMS provider is connected, so this message cannot be sent. '
            .'In local development the SMS catcher handles it — check that '
            .'sms-catcher.enabled is true.'
        );
    }
}
