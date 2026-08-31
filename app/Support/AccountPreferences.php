<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * How the app reads dates, times and weeks for the person looking at it.
 *
 * Three answers, narrowest first — the person's own choice, then the
 * business's setting, then StyleDesk's default — which is deliberately the
 * same shape as App\Support\Locale. A colleague who prefers 24-hour clocks
 * changes only their own screen; the business decides what everybody else
 * sees.
 *
 * Every getter takes the user rather than reading auth() itself, so a job
 * rendering a message for somebody else cannot accidentally format it the way
 * the person who triggered it likes.
 */
class AccountPreferences
{
    public const DEFAULT_DATE_FORMAT = 'm/d/Y';

    public const DEFAULT_FIRST_DAY = 0;

    public const DEFAULT_CALENDAR_VIEW = 'week';

    /** The calendar views the day-to-day screens can open on. */
    public const CALENDAR_VIEWS = ['day', 'week', 'month'];

    /** The toggles under Calendar preferences, with what they mean when unset. */
    public const CALENDAR_TOGGLES = [
        'show_weekends' => true,
        'show_cancelled' => false,
        'show_resource_color' => true,
        'show_staff_color' => true,
    ];

    public static function dateFormat(?User $user): string
    {
        return $user?->preferences?->date_format
            ?? $user?->tenant?->date_format
            ?? self::DEFAULT_DATE_FORMAT;
    }

    public static function timeFormat(?User $user): string
    {
        return (string) ($user?->preferences?->time_format
            ?? $user?->tenant?->time_format
            ?? TimeFormat::DEFAULT);
    }

    /**
     * The zone dates are shown in.
     *
     * Falls through to the app's own timezone rather than to UTC: a business
     * that never set one is not in UTC, it just never said, and config
     * app.timezone is the closest thing to an answer we have.
     */
    public static function timezone(?User $user): string
    {
        return $user?->preferences?->timezone
            ?? $user?->tenant?->timezone
            ?? config('app.timezone', 'UTC');
    }

    public static function firstDayOfWeek(?User $user): int
    {
        return (int) ($user?->preferences?->first_day_of_week
            ?? $user?->tenant?->first_day_of_week
            ?? self::DEFAULT_FIRST_DAY);
    }

    public static function calendarView(?User $user): string
    {
        $view = $user?->preferences?->calendar_view;

        return in_array($view, self::CALENDAR_VIEWS, true) ? $view : self::DEFAULT_CALENDAR_VIEW;
    }

    /**
     * A calendar toggle, with the product's answer when nobody has chosen.
     *
     * Null and false are different answers here: null is "never asked", which
     * is why the column is nullable and why a reset writes null rather than
     * the default's current value. A default that changes should move the
     * people who never expressed a view.
     */
    public static function calendarToggle(?User $user, string $key): bool
    {
        $stored = $user?->preferences?->{$key};

        return $stored === null ? self::CALENDAR_TOGGLES[$key] : (bool) $stored;
    }

    /**
     * The zones offered on the preferences screen.
     *
     * The whole IANA list, because a business's staff are wherever they are,
     * and the combo box is searchable — this is the one place where a long
     * list is easier than a curated short one that is missing your city.
     *
     * @return array<string, string>
     */
    public static function timezones(): array
    {
        return collect(\DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $zone) => [$zone => str_replace('_', ' ', $zone)])
            ->all();
    }
}
