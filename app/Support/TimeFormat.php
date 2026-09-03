<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Whether times are shown as 1:30 PM or 13:30, and the formatting that follows.
 *
 * One place, because the answer comes from the business's own setting and the
 * alternative is every screen reading `time_format` and formatting its own way.
 * A business that switched to 24-hour and still saw "9:00 AM" on one card would
 * be right to read that as a fault.
 *
 * Times are always *stored* as 24-hour "H:i". This is display only.
 */
class TimeFormat
{
    /** What the app falls back to when there is no business to ask. */
    public const DEFAULT = '12';

    /**
     * Resolved on every call, deliberately not memoised.
     *
     * A static cache here saves one property read on an already-loaded
     * relation, and costs correctness under any long-running worker: the
     * first tenant to render a page would decide the format for every tenant
     * served by that process afterwards. The saving is not worth a business
     * seeing another business's clock.
     */
    public static function use12Hours(): bool
    {
        /**
         * `auth()` rather than `tenant()`: App Settings runs on the central
         * domain where tenancy is not initialised, and the user's business is
         * the one whose preference matters.
         *
         * Through AccountPreferences, so a colleague who chose a 24-hour clock
         * in My Account sees one everywhere the app prints a time — the
         * business's setting is the default, not the ceiling.
         */
        /* The salon guard by name. The platform console authenticates a
           BackofficeAdmin, and AccountPreferences::timeFormat takes a ?User —
           an untyped auth()->user() hands it the wrong model the moment the
           console is the active guard. An administrator has no clock
           preference anyway, so naming 'web' settles the console on the
           default, which is the 12-hour one. */
        return AccountPreferences::timeFormat(Auth::guard('web')->user()) !== '24';
    }

    /**
     * "9:00 AM" or "09:00", from stored "09:00" or "09:00:00".
     *
     * Null in, null out: a missing time is not an error to shout about here,
     * it is a period that was never set, and the caller decides what to say
     * about that.
     */
    public static function time(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $time = Carbon::createFromFormat('H:i', mb_substr($stored, 0, 5));

        return self::use12Hours() ? $time->format('g:i A') : $time->format('H:i');
    }

    /**
     * The clock a `format()` call should use: "g:i A" or "H:i".
     *
     * Exposed so callers formatting a whole timestamp — a date and a time in
     * one string — can build their pattern around it instead of hard-coding
     * "H:i" and quietly ignoring the setting. Most of the app did exactly
     * that, which is how a business on a 12-hour clock still read
     * "last updated 2 Sep 2026 · 14:09".
     */
    public static function clock(): string
    {
        return self::use12Hours() ? 'g:i A' : 'H:i';
    }

    /**
     * A stored timestamp as "2 Sep 2026 · 2:09 PM".
     *
     * The house format for "when did this happen" throughout the app. The
     * separator and the date shape are fixed here so twenty screens cannot
     * each pick their own; only the clock follows the reader's setting.
     */
    public static function dateTime(?\DateTimeInterface $moment, string $separator = ' · '): ?string
    {
        if ($moment === null) {
            return null;
        }

        return Carbon::instance($moment)->translatedFormat('j M Y'.$separator.self::clock());
    }

    /** "9:00 AM – 6:00 PM", or null when either end is missing. */
    public static function range(?string $from, ?string $to): ?string
    {
        $opens = self::time($from);
        $closes = self::time($to);

        return $opens === null || $closes === null ? null : $opens.' – '.$closes;
    }
}
