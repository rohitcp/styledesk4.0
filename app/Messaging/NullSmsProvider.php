<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;

/**
 * SMS switched off.
 *
 * Refuses rather than pretends. A provider that quietly swallowed messages
 * would leave a business believing its clients were being texted, and the
 * record would say `sent` over a message that never existed — which is the
 * one lie the SMS log must not tell.
 */
class NullSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'disabled';
    }

    public function send(SmsMessage $message): SmsResult
    {
        return SmsResult::refused('sms_disabled', __('sms.errors.disabled'));
    }
}
