<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\Client;
use App\Models\SmsMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A message from a client, and what to do about it.
 *
 * Every inbound text is written down before anything is decided, because the
 * decision can be wrong and the message is evidence either way. What happens
 * next depends on two things: what the client said, and whether StyleDesk can
 * tell which business they said it to.
 *
 *   STOP   honoured everywhere, immediately, without needing to know which
 *          business. It is a person telling the network to stop.
 *   YES    confirms the appointment, but only where one business is in play.
 *   CANCEL asks for a cancellation. It never cancels: a booking called off by
 *          a text nobody read is a chair nobody filled and a client charged a
 *          late fee. It raises a request for the desk.
 *   HELP   answered with the salon's own number, where one is known.
 *   else   filed for a person to read.
 */
class InboundSms
{
    public function record(string $from, string $text, ?string $providerMessageId = null): SmsMessage
    {
        $from = SmsReplies::normalise($from);
        $keyword = SmsReplies::keyword($text);

        $candidates = SmsReplies::candidatesFor($from);
        $answering = SmsReplies::resolve($candidates);

        /* Opting out needs no conversation. Done first and regardless of
           which business is being replied to, because the client has told the
           carrier as well and StyleDesk texting on would be paying to be
           blocked. */
        if ($keyword === 'stop' || $keyword === 'start') {
            $this->setOptOut($from, $keyword === 'stop');
        }

        $message = $this->write($from, $text, $keyword, $answering, $candidates, $providerMessageId);

        if ($answering !== null && $keyword !== null) {
            $this->act($message, $answering, $keyword);
        }

        return $message;
    }

    /**
     * The row, whatever else happens.
     *
     * @param  Collection<int, SmsMessage>  $candidates
     */
    private function write(
        string $from,
        string $text,
        ?string $keyword,
        ?SmsMessage $answering,
        $candidates,
        ?string $providerMessageId,
    ): SmsMessage {
        /* Needing review is the default, not the exception. A reply is only
           settled where StyleDesk both knows which conversation it belongs to
           and knows what to do with it. */
        $status = match (true) {
            $keyword === 'stop' || $keyword === 'start' => 'handled',
            $answering === null => 'needs_review',
            $keyword === null => 'needs_review',
            default => 'handled',
        };

        return SmsMessage::withoutGlobalScopes()->create([
            'tenant_id' => $answering?->tenant_id,
            'direction' => 'inbound',
            'client_id' => $answering?->client_id,
            'booking_id' => $answering?->booking_id,
            'type' => 'reply',
            'from_number' => $from,
            'to_number' => config('services.clicksend.from'),
            'body' => $text,
            'segments' => SmsSegments::count($text),
            'provider' => 'clicksend',
            'provider_message_id' => $providerMessageId,
            'status' => 'delivered',
            'reply_keyword' => $keyword,
            'reply_status' => $status,
            /* Who it might have been, where it was not clear. Read by
               whoever picks the message up. */
            'reply_candidates' => $answering === null && $candidates->isNotEmpty()
                ? $candidates->map(fn (SmsMessage $one) => [
                    'tenant_id' => $one->tenant_id,
                    'booking_id' => $one->booking_id,
                ])->values()->all()
                : null,
            'delivered_at' => now(),
            'handled_at' => $status === 'handled' ? now() : null,
        ]);
    }

    /** What the word actually does to the booking behind it. */
    private function act(SmsMessage $reply, SmsMessage $answering, string $keyword): void
    {
        $booking = $answering->booking;

        if ($booking === null) {
            return;
        }

        match ($keyword) {
            'yes' => $booking->forceFill([
                'client_confirmation' => 'confirmed',
                'client_confirmed_at' => now(),
            ])->save(),
            /* Requested, never done. A booking called off by a text nobody
               read is a chair nobody filled — and a client charged a late fee
               for a cancellation the salon never saw. */
            'cancel' => $booking->forceFill([
                'client_confirmation' => 'cancellation_requested',
            ])->save(),
            default => null,
        };
    }

    /**
     * Stop texting this number, or start again.
     *
     * Every client on it, across every business: a person who says stop has
     * said it to the number, and honouring it for one business only is
     * honouring it for none.
     */
    private function setOptOut(string $number, bool $stop): void
    {
        DB::transaction(function () use ($number, $stop) {
            Client::withoutGlobalScopes()
                ->where('mobile', $number)
                ->get()
                ->each(fn (Client $client) => $client->forceFill($stop
                    ? [
                        'sms_opted_out_at' => now(),
                        'sms_opt_out_source' => 'sms_reply',
                        'comm_sms' => false,
                        'marketing_sms' => false,
                    ]
                    : [
                        'sms_opted_out_at' => null,
                        'sms_opt_out_source' => null,
                        'comm_sms' => true,
                    ])->save());
        });
    }
}
