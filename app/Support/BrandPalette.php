<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;

/**
 * A business's colours, and the ones derived from them.
 *
 * A PHP port of the prototype's SDBR maths, and it has to be a port rather
 * than a call into it: the palette has to reach a receipt, a confirmation
 * email and a booking page rendered for someone who is not signed in. None of
 * those run JavaScript, and a brand that only exists in the browser of the
 * person who chose it is not a brand.
 *
 * Two rules carried over from the prototype, both worth restating:
 *
 * **Derived values are never fields.** The hover shade and the colour of text
 * on a button are arithmetic. Asking a salon owner for them produces two more
 * inputs and a lower chance of a legible result, so they are computed.
 *
 * **Colour is checked, not merely stored.** A screen that cheerfully saves
 * pale yellow as a button fill with white text on it has helped someone ship
 * an unreadable email to every client they have.
 */
class BrandPalette
{
    /** StyleDesk's own colours, and what "Reset to default" restores. */
    public const DEFAULTS = [
        'primary' => '#3d348b',
        'secondary' => '#0d9488',
        'accent' => '#b45309',
    ];

    /**
     * @param  array{primary: string, secondary: string, accent: string}  $colors
     */
    public function __construct(public readonly array $colors) {}

    public static function default(): self
    {
        return new self(self::DEFAULTS);
    }

    /**
     * The palette a business has saved, falling back per colour.
     *
     * Per colour rather than all-or-nothing: a tenant that set a primary and
     * never touched the accent should keep StyleDesk's accent, not lose its
     * own primary because the set is incomplete.
     */
    public static function forTenant(?Tenant $tenant): self
    {
        if ($tenant === null) {
            return self::default();
        }

        return new self([
            'primary' => self::normalise($tenant->brand_primary) ?? self::DEFAULTS['primary'],
            'secondary' => self::normalise($tenant->brand_secondary) ?? self::DEFAULTS['secondary'],
            'accent' => self::normalise($tenant->brand_accent) ?? self::DEFAULTS['accent'],
        ]);
    }

    /**
     * Every CSS custom property the app reads, keyed by its variable name.
     *
     * The full token set rather than the three chosen colours, because every
     * brand rule in the stylesheets is written as var(--sd-*) and a missing
     * one falls back to whatever the stylesheet last defined — which would be
     * the previous tenant's colour under a long-running worker.
     *
     * @return array<string, string>
     */
    public function tokens(): array
    {
        $primary = $this->colors['primary'];

        return [
            '--sd-brand' => $primary,
            '--sd-brand-dark' => self::hoverFor($primary),
            '--sd-banner' => self::bannerFor($primary),
            '--sd-link' => $primary,
            '--sd-accent' => $this->colors['accent'],
            '--sd-btn' => $primary,
            // Computed, never chosen. This is the calculation that stops
            // someone shipping white-on-yellow.
            '--sd-btn-ink' => self::inkFor($primary),
            '--sd-secondary' => $this->colors['secondary'],
        ];
    }

    /** The tokens as a `:root { … }` rule, ready to inline in a document head. */
    public function css(): string
    {
        $declarations = collect($this->tokens())
            ->map(fn (string $value, string $name) => $name.':'.$value)
            ->join(';');

        return ':root{'.$declarations.'}';
    }

    public function isDefault(): bool
    {
        return $this->colors === self::DEFAULTS;
    }

    // ------------------------------------------------------- colour maths

    /**
     * A hex string as #rrggbb, or null if it is not one.
     *
     * Accepts the three-digit form and a missing hash, because both are what
     * people paste. Returns null rather than a fallback so callers can tell
     * "not set" from "set to something invalid".
     */
    public static function normalise(?string $value): ?string
    {
        $hex = ltrim(trim((string) $value), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#'.mb_strtolower($hex) : null;
    }

    /** sRGB relative luminance, per WCAG. */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);

        $channel = function (int $value): float {
            $v = $value / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /** The WCAG contrast ratio between two colours, 1:1 to 21:1. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Black or white, whichever is legible on this fill. */
    public static function inkFor(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#0f0f10') ? '#ffffff' : '#0f0f10';
    }

    /**
     * The pressed/hover shade.
     *
     * Darker is the normal answer; a brand already near-black cannot get
     * usefully darker, so it lightens instead — otherwise "hover" on charcoal
     * is invisible. The threshold is deliberately low: at 0.12 it caught the
     * house purple and lightened it on hover, contradicting the brand-dark
     * the design system has always used.
     */
    public static function hoverFor(string $hex): string
    {
        return self::luminance($hex) < 0.02
            ? self::mix($hex, '#ffffff', 0.18)
            : self::mix($hex, '#000000', 0.20);
    }

    /**
     * The banner strip above the app bar, always the darker of the two.
     *
     * Same low threshold as hoverFor, for the same reason: higher and the
     * house purple came out lighter than the bar beneath it, inverting the
     * two-tone chrome the design depends on.
     */
    public static function bannerFor(string $hex): string
    {
        return self::luminance($hex) < 0.02
            ? self::mix($hex, '#ffffff', 0.22)
            : self::mix($hex, '#000000', 0.28);
    }

    public static function mix(string $hex, string $target, float $amount): string
    {
        [$ar, $ag, $ab] = self::channels($hex);
        [$br, $bg, $bb] = self::channels($target);

        $blend = fn (int $from, int $to): int => (int) round($from + ($to - $from) * $amount);

        return sprintf('#%02x%02x%02x', $blend($ar, $br), $blend($ag, $bg), $blend($ab, $bb));
    }

    /**
     * How a colour scores for normal-size text, as WCAG grades it.
     *
     * Named rather than left as bare numbers at the call site. Large text is
     * 18.66px bold or 24px regular; our buttons are 13px semibold, so they
     * are held to the normal-text bar.
     *
     * @return array{ratio: float, label: string, ok: bool}
     */
    public static function grade(string $background, string $text): array
    {
        $ratio = self::contrast($background, $text);

        return [
            'ratio' => round($ratio, 1),
            'label' => match (true) {
                $ratio >= 7 => 'AAA',
                $ratio >= 4.5 => 'AA',
                $ratio >= 3 => 'Large text only',
                default => 'Fails',
            },
            'ok' => $ratio >= 4.5,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function channels(string $hex): array
    {
        $hex = self::normalise($hex) ?? '#000000';

        return [
            (int) hexdec(mb_substr($hex, 1, 2)),
            (int) hexdec(mb_substr($hex, 3, 2)),
            (int) hexdec(mb_substr($hex, 5, 2)),
        ];
    }
}
