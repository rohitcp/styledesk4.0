<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\Client;

/**
 * Whether this client may be texted this.
 *
 * Asked once, in the messaging service, so no caller can forget it. A booking
 * confirmation and a birthday greeting are not the same permission: the first
 * is about something the client asked for, the second is the business getting
 * in touch — and the marketing switch is the one that must never be inferred
 * from the other.
 *
 * An opt-out beats everything. A client who replied STOP has told the carrier
 * and the business, and no setting on this side outranks that.
 */
class SmsConsent
{
    /**
     * Which switch on the client each kind of message answers to.
     *
     * Anything not named here is treated as transactional, which is the safe
     * reading for a service message and the wrong one for marketing — so a
     * marketing message must be named.
     */
    private const MARKETING = ['birthday', 'promotion', 'campaign'];

    public static function allows(?Client $client, string $type): bool
    {
        /* Nobody on file — a walk-in given a confirmation on the spot. There
           is no consent record to consult and no profile to opt out from, so
           the number the desk was handed is the permission. */
        if ($client === null) {
            return true;
        }

        if ($client->sms_opted_out_at !== null) {
            return false;
        }

        return self::isMarketing($type)
            ? (bool) $client->marketing_sms
            : (bool) $client->comm_sms;
    }

    public static function isMarketing(string $type): bool
    {
        return in_array($type, self::MARKETING, true);
    }
}
