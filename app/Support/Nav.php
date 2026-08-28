<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Resolution shared by the icon rail and the mobile drawer.
 *
 * A real class rather than closures in an included Blade partial: @include
 * renders the partial with its own extracted variables, so anything the
 * partial defines is invisible to the view that included it. The two
 * navigation partials therefore cannot share helpers that way — and they must
 * share them, or the same config entry can resolve to two different links.
 */
class Nav
{
    /**
     * A navigation item's label, translated where a translation exists.
     *
     * Resolved from the item's `key` — `navigation.clients` — rather than from
     * the English string, so a label reads the same in the rail, the drawer
     * and a tooltip without three copies to keep in step.
     *
     * Falls back to the literal label in the config when there is no key for
     * it. That is not a gap to be tidied away: the deeper menu entries name
     * screens that do not exist yet, and translating a label for a page nobody
     * can open would be work spent ahead of the work it describes.
     *
     * @param  array<string, mixed>  $item
     */
    public static function label(array $item): string
    {
        $key = isset($item['key']) ? 'navigation.'.$item['key'] : null;

        if ($key !== null && trans()->has($key)) {
            return __($key);
        }

        return $item['label'] ?? '';
    }

    /** @param array<string, mixed> $item */
    public static function href(array $item): string
    {
        return isset($item['route']) ? route($item['route']) : '#';
    }

    /** @param array<string, mixed> $item */
    /**
     * Whether this is the section the reader is in.
     *
     * The whole section, not the one screen it starts on: a client's profile
     * is still Clients, and an icon that goes dark the moment you open a
     * record leaves the reader with no answer to "where am I".
     *
     * Matched on the route name's own prefix — `clients.index` lights up for
     * `clients.*`. That deliberately does not catch `settings.clients.show`,
     * which belongs to App Settings and has the gear to light up instead.
     */
    public static function isActive(array $item): bool
    {
        /** An item may name its own patterns when the prefix is not enough. */
        if (! empty($item['active'])) {
            return Request::routeIs(...(array) $item['active']);
        }

        $routes = collect([$item['route'] ?? null])
            ->merge(collect($item['children'] ?? [])->pluck('route'))
            ->filter()
            ->flatMap(fn (string $route) => [$route, Str::before($route, '.').'.*'])
            ->unique()
            ->all();

        return $routes !== [] && Request::routeIs(...$routes);
    }

    /**
     * The marker for a screen that is designed but not built.
     *
     * Kept on the element rather than dropped, so the remaining work stays
     * greppable instead of becoming an anonymous "#".
     *
     * @param  array<string, mixed>  $item
     */
    public static function pending(array $item): HtmlString
    {
        return new HtmlString(
            isset($item['pending']) ? ' data-pending-route="'.e($item['pending']).'"' : ''
        );
    }
}
