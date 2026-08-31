<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Request;

/**
 * Which door the reader came in by.
 *
 * The same staff screens are reachable from two places — the Staff module,
 * where the day is run from, and App Settings, where the business is
 * configured. §12 and §13: one set of records, one form, one set of rules,
 * two addresses.
 *
 * Two controllers would have made that a promise rather than a fact, so the
 * routes point at the same controller and the same views, and this is the one
 * thing that differs: which of the two prefixes a link, a redirect or a
 * breadcrumb belongs to. A view asks for `route('show', $member)` and gets
 * back the one for the section it is being rendered in.
 *
 * Settings is the narrower answer, so it is the one that has to be matched
 * for; anything else is the module.
 */
class StaffSection
{
    public const SETTINGS = 'settings.staff.';

    public const MODULE = 'staff.';

    /** Whether this request came in through App Settings. */
    public static function isSettings(): bool
    {
        return Request::routeIs('settings.staff.*');
    }

    /** The route-name prefix for the section being rendered. */
    public static function prefix(): string
    {
        return static::isSettings() ? static::SETTINGS : static::MODULE;
    }

    /**
     * A URL to one of the staff screens, in the section the reader is in.
     *
     * @param  mixed  $parameters
     */
    public static function route(string $name, $parameters = []): string
    {
        return route(static::prefix().$name, $parameters);
    }

    /** The route name itself, for a redirect. */
    public static function name(string $name): string
    {
        return static::prefix().$name;
    }
}
