<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;
use App\Models\SmsWebhookEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What Telnyx says happened.
 *
 * The counterpart to the ClickSend webhook, and it does the same two jobs:
 * settles what a carrier managed, and carries a client's reply back into
 * StyleDesk. The reply goes through InboundSms like any other, so the
 * matching rules and the STOP handling are the same whichever carrier the
 * business is on.
 *
 * Three rules, and each one is a bug somebody has shipped before:
 *
 *   1. Nothing unverified is acted on. The endpoint is public.
 *   2. Every event is written down before it is acted on, and recognised if
 *      it has been seen — Telnyx delivers at least once and retries anything
 *      that is not a 2xx.
 *   3. A retry answers 2xx. An event about a message this installation has
 *      never heard of is not an error to make the carrier keep repeating.
 */
class TelnyxWebhook
{
    public function __construct(private readonly InboundSms $inbound) {}

    /**
     * Verify and act on one event.
     *
     * @return int the status the endpoint should answer with
     */
    public function handle(string $payload, ?string $signature, ?string $timestamp): int
    {
        $refusal = $this->refusal($payload, $signature, $timestamp);

        if ($refusal !== null) {
            /* Refused without saying which, to the caller: an attacker
               learning why is an attacker learning how. The reason goes to
               the log instead, because the alternative is somebody wiring up
               a tunnel and staring at a bare 400 with no way to tell a wrong
               signing key from a clock that has drifted. */
            Log::warning('A Telnyx webhook was refused.', ['reason' => $refusal]);

            return 400;
        }

        $event = json_decode($payload, true);
        $id = $event['data']['id'] ?? null;

        if (! is_array($event) || ! is_string($id)) {
            return 400;
        }

        $record = SmsWebhookEvent::query()->firstOrCreate(
            ['event_id' => 'telnyx:'.$id],
            [
                'event_type' => (string) ($event['data']['event_type'] ?? 'unknown'),
                'provider' => 'telnyx',
                'status' => 'received',
                'received_at' => now(),
            ],
        );

        /* Seen before. Answered 2xx so the carrier stops repeating it, and
           acted on exactly once. */
        if (! $record->wasRecentlyCreated && $record->status === 'processed') {
            return 200;
        }

        try {
            $this->apply($event['data'] ?? []);

            $record->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
        } catch (Throwable $exception) {
            $record->forceFill(['status' => 'failed', 'error' => $exception->getMessage()])->save();

            Log::error('A Telnyx webhook could not be applied.', [
                'event_id' => $id,
                'exception' => $exception->getMessage(),
            ]);

            /* Told to try again: this one is StyleDesk's fault, not the
               carrier's, and the event is worth having. */
            return 500;
        }

        return 200;
    }

    /**
     * Why this request is not trusted, or null if it is.
     *
     * Telnyx signs every webhook with an ed25519 key shown on the console.
     * Without a configured key nothing is trusted — an endpoint that accepted
     * anything while somebody "gets round to the key" is an endpoint that
     * lets a stranger mark messages delivered and opt clients out.
     *
     * The reasons are named so a log can tell them apart. They are the things
     * that actually go wrong when somebody is setting this up: no key, no
     * headers, a clock that has drifted, and the wrong key pasted in.
     */
    private function refusal(string $payload, ?string $signature, ?string $timestamp): ?string
    {
        $key = (string) SmsProviders::credential('telnyx_public_key', 'services.telnyx.public_key');

        if ($key === '') {
            return 'no_public_key_configured';
        }

        if (! is_string($signature) || ! is_string($timestamp)) {
            return 'missing_signature_headers';
        }

        /* Older than five minutes is a replay, whatever it is signed with. */
        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return 'timestamp_outside_tolerance';
        }

        $decodedKey = base64_decode($key, true);
        $decodedSignature = base64_decode($signature, true);

        if ($decodedKey === false || $decodedSignature === false) {
            return 'signature_or_key_not_base64';
        }

        try {
            $valid = sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $timestamp.'|'.$payload,
                $decodedKey,
            );
        } catch (Throwable) {
            return 'signature_could_not_be_checked';
        }

        return $valid ? null : 'signature_does_not_match';
    }

    /**
     * Move the message this event is about.
     *
     * @param  array<string, mixed>  $data
     */
    private function apply(array $data): void
    {
        $type = (string) ($data['event_type'] ?? '');
        $payload = $data['payload'] ?? [];

        if ($type === 'message.received') {
            /* A client's reply. Routed through the same processor ClickSend
               replies use, so the matching and the STOP handling cannot
               differ by carrier. */
            $this->inbound->record(
                (string) ($payload['from']['phone_number'] ?? ''),
                (string) ($payload['text'] ?? ''),
                $payload['id'] ?? null,
            );

            return;
        }

        $message = SmsMessage::withoutGlobalScopes()
            ->where('provider_message_id', $payload['id'] ?? '')
            ->first();

        /* An event about a message this installation never sent — a number
           shared with another environment, or a record since deleted. Not an
           error, and not worth making the carrier repeat itself. */
        if ($message === null) {
            return;
        }

        TelnyxStatus::apply($message, $payload);
    }
}
