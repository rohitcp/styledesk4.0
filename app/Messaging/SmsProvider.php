<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;

/**
 * Something that can put a text message on its way.
 *
 * The application never names a provider. Booking code asks the messaging
 * service to send; which company carries it is a line in the configuration,
 * and swapping it must not mean touching the booking screen.
 */
interface SmsProvider
{
    /** What this provider is called in a record and in the configuration. */
    public function name(): string;

    /**
     * Hand the message over.
     *
     * Never throws for an ordinary refusal — a bad number is an answer, not
     * an exception — so the caller can write it down and move on.
     */
    public function send(SmsMessage $message): SmsResult;
}
