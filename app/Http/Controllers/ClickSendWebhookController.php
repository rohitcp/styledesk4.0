<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Messaging\InboundSms;
use App\Messaging\SmsProviders;
use App\Models\SmsMessage;
use App\Models\SmsWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Where ClickSend reports back.
 *
 * Two things arrive here: a client's reply, and what a carrier managed to do
 * with a message StyleDesk sent. Both are public and unauthenticated — a
 * carrier has no cookie — so both are checked against a shared secret before
 * a single row moves.
 *
 * ClickSend does not sign its webhooks, so the secret travels in the URL and
 * the address is the credential. That is weaker than a signature and is worth
 * saying plainly: the endpoint is guessable only if the secret leaks, and it
 * must never be the same string as anything else.
 */
class ClickSendWebhookController extends Controller
{
    /** A client's reply. */
    public function inbound(Request $request, InboundSms $inbound): Response
    {
        if (! $this->allowed($request)) {
            return response('', 404);
        }

        $from = (string) $request->input('from', $request->input('originalsenderid', ''));
        $text = (string) $request->input('body', $request->input('message', ''));

        if ($from === '' || $text === '') {
            return response('', 400);
        }

        /* Recognised before it is acted on: a carrier retries anything that
           is not a 2xx, and a reply acted on twice cancels an appointment
           somebody only asked about once. */
        $id = (string) $request->input('message_id', $request->input('messageid', ''));

        if ($id !== '' && ! $this->firstSighting($id, 'sms.inbound')) {
            return response('', 200);
        }

        $inbound->record($from, $text, $id ?: null);

        return response('', 200);
    }

    /**
     * What the carrier managed.
     *
     * ClickSend posts a delivery receipt per message. The custom string it
     * carries is StyleDesk's own row id, which is what makes matching it back
     * reliable rather than a lookup on a provider id that may not be echoed.
     */
    public function delivery(Request $request): Response
    {
        if (! $this->allowed($request)) {
            return response('', 404);
        }

        $id = (string) $request->input('message_id', $request->input('messageid', ''));
        $reference = (string) $request->input('custom_string', '');
        $status = strtolower((string) $request->input('status', ''));

        if ($id !== '' && ! $this->firstSighting($id.':'.$status, 'sms.delivery')) {
            return response('', 200);
        }

        $message = SmsMessage::withoutGlobalScopes()
            ->when($reference !== '', fn ($query) => $query->whereKey($reference))
            ->when($reference === '', fn ($query) => $query->where('provider_message_id', $id))
            ->first();

        /* A receipt for a message this installation never sent — a number
           shared with another environment, or a record since deleted. Not an
           error, and not worth making the carrier repeat itself. */
        if ($message === null) {
            return response('', 200);
        }

        $mapped = match ($status) {
            'delivered', 'completed' => 'delivered',
            'sent' => 'sent',
            'failed', 'undelivered', 'hard_bounce', 'soft_bounce' => 'failed',
            'expired' => 'expired',
            'cancelled', 'rejected' => 'rejected',
            default => null,
        };

        if ($mapped === null) {
            return response('', 200);
        }

        /* Never backwards. A `sent` receipt arriving after `delivered` — they
           are not ordered — must not un-deliver a message. */
        if ($message->hasArrived() && $mapped !== 'failed') {
            return response('', 200);
        }

        $message->forceFill(array_filter([
            'status' => $mapped,
            'delivered_at' => $mapped === 'delivered' ? now() : null,
            'failed_at' => in_array($mapped, ['failed', 'rejected', 'expired'], true) ? now() : null,
            'error_message' => $mapped === 'failed' ? (string) $request->input('error_text', $status) : null,
        ], fn ($value) => $value !== null))->save();

        return response('', 200);
    }

    /**
     * Is this really ClickSend?
     *
     * A shared secret on the address. Compared in constant time, and a
     * mismatch answers 404 rather than 403 — an endpoint that admits it
     * exists is an endpoint worth guessing at.
     */
    private function allowed(Request $request): bool
    {
        $secret = (string) SmsProviders::credential('clicksend_webhook_secret', 'services.clicksend.webhook_secret');

        if ($secret === '') {
            Log::warning('A ClickSend webhook arrived but no secret is configured.');

            return false;
        }

        return hash_equals($secret, (string) $request->route('secret'));
    }

    /** False where this event has been seen before. */
    private function firstSighting(string $id, string $type): bool
    {
        return SmsWebhookEvent::query()->firstOrCreate(
            ['event_id' => 'clicksend:'.$type.':'.$id],
            [
                'event_type' => $type,
                'provider' => 'clicksend',
                'status' => 'processed',
                'received_at' => now(),
                'processed_at' => now(),
            ],
        )->wasRecentlyCreated;
    }
}
