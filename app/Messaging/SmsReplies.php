<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\SmsMessage;
use Illuminate\Support\Collection;

/**
 * Working out who a reply is from, and about what.
 *
 * One number carries every business's texts, so a reply arrives identifying
 * nobody: a phone number, a few words, and no clue which salon it concerns.
 * The only evidence is what StyleDesk sent that number recently.
 *
 * The rule this exists to keep is the one that cannot be got wrong: where the
 * evidence points at more than one business, nothing is decided. A guess that
 * lands on the wrong salon shows one business another business's client, and
 * a wrong cancellation is worse than an unanswered text. Ambiguity is marked
 * for a person instead.
 *
 * STOP is the exception, and deliberately so. It is not about a booking — it
 * is a person telling the network to stop — so it is honoured for every
 * business at once without needing to know which one asked.
 */
class SmsReplies
{
    /** How far back the evidence is worth trusting. */
    private const WINDOW_DAYS = 7;

    /**
     * The outbound messages this reply could be answering.
     *
     * Ordered by what a person would look at first: a booking actually
     * waiting on an answer, then the soonest appointment, then simply the
     * most recent thing said.
     *
     * @return Collection<int, SmsMessage>
     */
    public static function candidatesFor(string $from): Collection
    {
        return SmsMessage::withoutGlobalScopes()
            ->where('direction', 'outbound')
            ->where('to_number', self::normalise($from))
            ->whereNotNull('tenant_id')
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->with(['booking:id,reference,client_confirmation,date,starts_at,status'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            /* One per business: two reminders from the same salon are one
               conversation, and counting them twice would make an unambiguous
               reply look ambiguous. */
            ->unique('tenant_id')
            ->sortBy([
                /* A booking that asked a question outranks one that did not. */
                fn (SmsMessage $message) => $message->booking?->client_confirmation === 'pending' ? 0 : 1,
                /* Then the appointment that is soonest. */
                fn (SmsMessage $message) => $message->booking?->date?->toDateString() ?? '9999-12-31',
                /* Then whatever was said most recently. */
                fn (SmsMessage $message) => -$message->created_at->timestamp,
            ])
            ->values();
    }

    /**
     * Which conversation this reply belongs to, or null where it is not clear.
     *
     * Null is a real answer and the caller must handle it: it means a person
     * has to look, not that the reply can be dropped.
     *
     * @param  Collection<int, SmsMessage>  $candidates
     */
    public static function resolve(Collection $candidates): ?SmsMessage
    {
        /* Nothing recent to answer: the reply stands alone. */
        if ($candidates->isEmpty()) {
            return null;
        }

        /* More than one business has texted this person lately, so "YES"
           could mean either. StyleDesk does not guess between salons. */
        if ($candidates->pluck('tenant_id')->unique()->count() > 1) {
            return null;
        }

        return $candidates->first();
    }

    /**
     * What the client meant, where they used one of the words we act on.
     *
     * Matched on the whole message, case-insensitively and ignoring
     * punctuation: "stop" is an opt-out and "stop by at 4" is a sentence.
     * Anything else is free text — MVP does not try to read it, it files it
     * for somebody who can.
     */
    public static function keyword(string $text): ?string
    {
        $word = strtoupper(trim(preg_replace('/[^\p{L}\s]/u', '', $text) ?? ''));

        foreach (['stop', 'start', 'help', 'yes', 'cancel'] as $group) {
            if (in_array($word, config('sms.keywords.'.$group, []), true)) {
                return $group;
            }
        }

        return null;
    }

    /** A number as the records hold it: digits and a leading plus. */
    public static function normalise(string $number): string
    {
        $clean = preg_replace('/[^\d+]/', '', $number) ?? '';

        /* A carrier may hand back a bare national number where StyleDesk
           stored an international one. Ten digits with no country code is a
           US number, which is the only market this sends to today. */
        if (! str_starts_with($clean, '+') && strlen($clean) === 10) {
            return '+1'.$clean;
        }

        if (! str_starts_with($clean, '+') && strlen($clean) === 11 && str_starts_with($clean, '1')) {
            return '+'.$clean;
        }

        return $clean;
    }
}
