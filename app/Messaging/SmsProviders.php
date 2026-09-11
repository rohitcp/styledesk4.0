<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\PlatformSmsSettings;

/**
 * Which carrier, if any, carries a message.
 *
 * One decision, made in one place, because it is the decision that spends
 * money. Everything above the messaging service asks it to send; this says
 * what "send" means on this installation today.
 *
 * The rule that outranks every other:
 *
 *   Development never reaches a real carrier.
 *
 * Not by convention and not by remembering to set something — by refusing.
 * A configured provider, a back-office choice, a copied production database:
 * none of them can make a local machine text a client. The only way past it
 * is a developer deliberately setting SMS_ALLOW_LIVE_IN_LOCAL, which defaults
 * to false and should stay that way.
 *
 * The reason it is this emphatic is that the failure is silent and expensive.
 * A test suite or a seeded database can hold a thousand real phone numbers,
 * and nobody finds out until the clients do.
 */
class SmsProviders
{
    /** The platform row, held for the life of the request. */
    private static ?PlatformSmsSettings $platform = null;

    public static function resolve(): SmsProvider
    {
        if (! self::mayReachACarrier()) {
            return new LocalSmsProvider;
        }

        return match (self::configured()) {
            'clicksend' => new ClickSendProvider,
            'telnyx' => new TelnyxProvider,
            'local' => new LocalSmsProvider,
            /* Switched off: there is a carrier configured and the business
               has said not to use it. Refused rather than quietly logged, so
               "why did nothing go out" has an answer. */
            default => new NullSmsProvider,
        };
    }

    /**
     * Is this installation allowed to talk to a real carrier at all?
     *
     * Production and staging, yes. Anywhere else only on an explicit,
     * deliberately-named switch.
     */
    public static function mayReachACarrier(): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return true;
        }

        return (bool) config('sms.allow_live_in_local', false);
    }

    /**
     * What the installation has been told to use.
     *
     * The back office first, because that is where somebody can change it
     * without a deployment; the environment behind it, so a fresh
     * installation works before anybody has opened the screen.
     *
     * Switched off at the back office means off, whatever the environment
     * says — a platform-wide stop has to be a stop.
     */
    public static function configured(): string
    {
        $platform = self::platform();

        if ($platform->exists) {
            return $platform->is_enabled ? (string) $platform->provider : 'disabled';
        }

        return (string) config('sms.provider', 'disabled');
    }

    /**
     * The platform's row, read once per request.
     *
     * Every message asks which carrier it is going on, and a query per
     * message is a query per message on a morning's reminders.
     */
    public static function platform(): PlatformSmsSettings
    {
        return self::$platform ??= PlatformSmsSettings::current();
    }

    /** Forget it, after the back office has changed it. */
    public static function forget(): void
    {
        self::$platform = null;
    }

    /**
     * One credential, from the back office or the environment behind it.
     *
     * The back office wins where it holds a value, so a key rotated on the
     * screen takes effect without a deployment.
     */
    public static function credential(string $field, string $configKey): ?string
    {
        $platform = self::platform();

        return filled($platform->{$field})
            ? (string) $platform->{$field}
            : (config($configKey) ?: null);
    }

    /**
     * The number the active carrier sends from.
     *
     * Each carrier owns its own numbers, so this follows the provider rather
     * than sitting in one shared setting: pointing StyleDesk at ClickSend
     * while it still sends a Telnyx number is a message refused for a source
     * the carrier has never heard of.
     */
    public static function senderNumber(): ?string
    {
        $number = match (self::configured()) {
            'telnyx' => self::credential('telnyx_from', 'services.telnyx.from'),
            default => self::credential('clicksend_from', 'services.clicksend.from'),
        };

        /* Normalised here as well as on the way in: the value comes straight
           from the environment, and a stray character reaches the carrier as
           an invalid source number — a refusal that names neither the file
           nor the line it came from. */
        $clean = preg_replace('/[^\d+]/', '', (string) $number) ?? '';

        return $clean === '' ? null : $clean;
    }

    /** Every provider a deployment may be set to, for a settings screen. */
    public static function selectable(): array
    {
        return ['clicksend', 'telnyx', 'disabled'];
    }
}
