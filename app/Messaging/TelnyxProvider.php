<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telnyx Programmable Messaging, v2.
 *
 * One of two carriers StyleDesk can be pointed at; the other is ClickSend.
 * Neither is named anywhere above the messaging service, which is what makes
 * switching between them a setting rather than a change to the booking
 * system.
 *
 * The API key never leaves the server and is never written to a log. What is
 * logged on a failure is the status and the carrier's own error code — what
 * somebody debugging needs, and not a credential.
 */
class TelnyxProvider implements SmsProvider
{
    public function name(): string
    {
        return 'telnyx';
    }

    public function send(SmsMessage $message): SmsResult
    {
        $key = (string) SmsProviders::credential('telnyx_key', 'services.telnyx.key');

        if ($key === '') {
            return SmsResult::refused('not_configured', 'No Telnyx API key is configured.');
        }

        /* Refused here rather than by Telnyx. Sending without a number makes
           it reach for a Number Pool that is deliberately off, and the error
           it hands back — "Number Pool is not enabled" — names neither the
           missing number nor the setting that should hold it. */
        if (blank($message->from_number)) {
            return SmsResult::refused('no_sender', __('sms.errors.no_sender'));
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post(rtrim((string) config('services.telnyx.url'), '/').'/messages', array_filter([
                    'from' => $message->from_number,
                    'to' => $message->to_number,
                    'text' => $message->body,
                    'type' => 'SMS',
                    /* No messaging_profile_id: the number decides which
                       profile carries the message, and naming both is how a
                       number on a different profile becomes a refusal nobody
                       can read.

                       The callback address is normally the profile's own, set
                       once on the console. This overrides it for local work,
                       where the profile cannot point at a .test hostname. */
                    'webhook_url' => config('services.telnyx.webhook_url') ?: null,
                    'use_profile_webhooks' => config('services.telnyx.webhook_url') ? false : null,
                ], fn ($value) => $value !== null));
        } catch (Throwable $exception) {
            /* The network, not the carrier. Worth another attempt, so it is
               reported as an ordinary refusal and the job decides. */
            return SmsResult::refused('transport', $exception->getMessage());
        }

        if ($response->successful()) {
            return SmsResult::accepted($response->json('data.id'));
        }

        $error = $response->json('errors.0') ?? [];

        Log::warning('Telnyx refused a message.', [
            'sms_message_id' => $message->id,
            'http' => $response->status(),
            'code' => $error['code'] ?? null,
        ]);

        return SmsResult::refused(
            (string) ($error['code'] ?? $response->status()),
            (string) ($error['detail'] ?? $error['title'] ?? 'Telnyx refused the message.'),
        );
    }
}
