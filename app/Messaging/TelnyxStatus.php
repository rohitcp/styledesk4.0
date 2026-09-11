<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;

/**
 * What Telnyx finally managed, written onto the message.
 *
 * Its own class because the mapping is the delicate part: a carrier's words
 * are not StyleDesk's, and getting one of them wrong means a message that
 * failed reads as delivered.
 */
class TelnyxStatus
{
    /**
     * Telnyx reports the outcome on the `to` entries rather than on the
     * message, because one message can be addressed to several numbers. One
     * recipient is the only case StyleDesk sends, so the first is the answer.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function apply(SmsMessage $message, array $payload): void
    {
        $to = $payload['to'][0] ?? [];
        $status = (string) ($to['status'] ?? $payload['status'] ?? '');

        $mapped = match ($status) {
            'delivered' => 'delivered',
            'sent', 'sending' => 'sent',
            'delivery_failed', 'sending_failed', 'failed' => 'failed',
            'expired' => 'expired',
            'rejected' => 'rejected',
            default => null,
        };

        if ($mapped === null) {
            return;
        }

        /* Never backwards. A `sent` event that arrives after `delivered` —
           they are not ordered — must not un-deliver a message. */
        if ($message->hasArrived() && $mapped !== 'failed') {
            return;
        }

        $errors = $payload['errors'][0] ?? null;

        $message->forceFill(array_filter([
            'status' => $mapped,
            'delivered_at' => $mapped === 'delivered' ? now() : null,
            'failed_at' => in_array($mapped, ['failed', 'rejected', 'expired'], true) ? now() : null,
            'error_code' => isset($errors['code']) ? (string) $errors['code'] : null,
            'error_message' => $errors['detail'] ?? $errors['title'] ?? null,
        ], fn ($value) => $value !== null))->save();
    }
}
