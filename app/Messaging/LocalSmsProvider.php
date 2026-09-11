<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;
use App\Notifications\CaughtSms;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The provider for a working day.
 *
 * Nothing reaches a phone. The message is handed to the SMS catcher, which
 * shows it at /dev/sms in a phone-shaped preview, and the record is written
 * exactly as a real send would write it — same body, same segment count, same
 * statuses — so everything built on top can be read and tested without an
 * account anywhere and without spending a penny.
 *
 * The body arrives already rendered. Local and production use one set of
 * templates, and a message that read differently in development would be a
 * message nobody had actually checked.
 */
class LocalSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'local';
    }

    public function send(SmsMessage $message): SmsResult
    {
        /* Announced as a notification on the `sms` channel, because that is
           what the catcher listens for: it intercepts the send, writes the
           message into its own inbox and stops it going any further. */
        if (config('sms-catcher.enabled')) {
            Notification::route('sms', $message->to_number)
                ->notify(new CaughtSms($message->body, $message->type, $message->from_number));
        } else {
            Log::info('SMS (not sent — local provider).', [
                'to' => $message->to_number,
                'type' => $message->type,
                'segments' => $message->segments,
                'body' => $message->body,
            ]);
        }

        return SmsResult::accepted('local_'.Str::uuid()->toString());
    }
}
