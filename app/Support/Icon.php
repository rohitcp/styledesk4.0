<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;

/**
 * Inline a vendored Font Awesome Pro Sharp Light icon.
 *
 * The icons are inlined rather than delivered as a webfont or through Font
 * Awesome's JS: an inline <svg> inherits currentColor, so the existing
 * .sd-navicon hover, focus and is-active colours keep working with no icon
 * stylesheet involved, and there is no font to download before the nav can
 * paint.
 *
 * The directory is named for the style it holds. Font Awesome ships the same
 * icon names across a dozen families, so a generic "icons" folder would give
 * no way to tell which one a file came from, and the next person copying an
 * icon in would have no way to match it.
 *
 * Only the icons the app actually uses are vendored into resources/icons.
 * The full Pro bundle is 1.1 GB of licensed third-party assets and is
 * gitignored — see .gitignore — so this directory is the app's supported
 * icon set, and adding a new icon means copying one file into it.
 */
class Icon
{
    /** Every Font Awesome icon is drawn on a 512-unit-tall canvas. */
    private const VIEWBOX_HEIGHT = 512;

    /**
     * Read once per request.
     *
     * A page renders the same icon several times — the nav, a menu, a button —
     * and each render would otherwise be its own filesystem read.
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    public static function inline(string $name, int $size, ?ComponentAttributeBag $attributes = null): HtmlString
    {
        $attributes ??= new ComponentAttributeBag;

        /**
         * Decorative unless the caller says otherwise.
         *
         * Every one of these sits inside a link or button that already carries
         * its own aria-label, so announcing the icon as well would read the
         * same thing twice. A caller that passes an aria-label means it.
         */
        if (! $attributes->has('aria-label')) {
            $attributes = $attributes->merge(['aria-hidden' => 'true']);
        }

        $svg = self::source($name);

        /**
         * Sized by height, with the width derived from the viewBox.
         *
         * Font Awesome icons are not square: every one is 512 units tall but
         * they run 448, 512, 576 or 640 wide. Forcing an N×N box makes the
         * default preserveAspectRatio letterbox them, so a 640-wide icon
         * renders visibly shorter than a 448-wide one at the same nominal
         * size — the Staff and Marketing icons next to Dashboard. Scaling from
         * the constant height instead is what keeps them optically level.
         */
        $width = (int) round($size * self::viewBoxWidth($svg) / self::VIEWBOX_HEIGHT);

        $open = '<svg width="'.$width.'" height="'.$size.'" '.$attributes->toHtml().' ';

        return new HtmlString(preg_replace('/^<svg /', $open, $svg, 1));
    }

    private static function viewBoxWidth(string $svg): int
    {
        // "0 0 640 512" -> 640. Falls back to square if a file ever arrives
        // without a viewBox, which is better than dividing by nothing.
        if (preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $m) === 1) {
            return (int) $m[1];
        }

        return self::VIEWBOX_HEIGHT;
    }

    private static function source(string $name): string
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        /**
         * The name is a filename, so it is validated rather than trusted.
         *
         * Nothing passes user input here today, but an icon name reaching this
         * from a database column or a query string later would otherwise be a
         * path traversal straight into resources/.
         */
        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid icon name [{$name}].");
        }

        $path = resource_path("icons/sharp-light/{$name}.svg");

        if (! is_file($path)) {
            throw new InvalidArgumentException(
                "Icon [{$name}] is not vendored. Copy svgs/sharp-light/{$name}.svg from the Font Awesome Pro bundle into resources/icons/sharp-light/."
            );
        }

        return self::$cache[$name] = trim(file_get_contents($path));
    }
}
