<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ClickSend, over the REST v3 API.
 *
 * The only class in StyleDesk that knows ClickSend exists. Everything above
 * it asks the messaging service to send and is told whether it was accepted —
 * which is what makes changing carrier a change to one file rather than to
 * the booking system.
 *
 * The credentials never leave the server and are never written to a log. What
 * is logged on a failure is the status and the carrier's own error text,
 * which is what somebody debugging needs and is not a credential.
 */
class ClickSendProvider implements SmsProvider
{
    public function name(): string
    {
        return 'clicksend';
    }

    public function send(SmsMessage $message): SmsResult
    {
        $username = (string) SmsProviders::credential('clicksend_username', 'services.clicksend.username');
        $key = (string) SmsProviders::credential('clicksend_key', 'services.clicksend.key');

        if ($username === '' || $key === '') {
            return SmsResult::refused('not_configured', 'No ClickSend credentials are configured.');
        }

        if (blank($message->from_number)) {
            return SmsResult::refused('no_sender', __('sms.errors.no_sender'));
        }

        try {
            $response = Http::withBasicAuth($username, $key)
                ->acceptJson()
                ->asJson()
                /* Short, because a confirmation is sent while somebody is
                   standing at the desk. The job retries; the request does
                   not sit there. */
                ->timeout(20)
                ->post(rtrim((string) config('services.clicksend.url'), '/').'/sms/send', [
                    'messages' => [[
                        'source' => 'styledesk',
                        'from' => $message->from_number,
                        'to' => $message->to_number,
                        'body' => $message->body,
                        /* Carried back on the delivery receipt, so a status
                           update finds its message even if the carrier's own
                           id is not what arrives. */
                        'custom_string' => (string) $message->id,
                    ]],
                ]);
        } catch (Throwable $exception) {
            /* The network, not the carrier. Worth another attempt, so it is
               reported as an ordinary refusal and the job decides. */
            return SmsResult::refused('transport', $exception->getMessage());
        }

        $entry = $response->json('data.messages.0') ?? [];
        $status = strtoupper((string) ($entry['status'] ?? ''));

        /* ClickSend answers 200 for a request it understood, and says per
           message whether it took it. A body that reads SUCCESS is the only
           acceptance; everything else is a refusal with a reason. */
        if ($response->successful() && $status === 'SUCCESS') {
            return SmsResult::accepted($entry['message_id'] ?? null);
        }

        Log::warning('ClickSend refused a message.', [
            'sms_message_id' => $message->id,
            'http' => $response->status(),
            'status' => $status ?: null,
        ]);

        return SmsResult::refused(
            $status ?: (string) $response->status(),
            (string) ($entry['status'] ?? $response->json('response_msg') ?? 'ClickSend refused the message.'),
        );
    }
}
