<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Which currencies exist, and which ones a business prices in.
 *
 * The register is config/currencies.php; nothing else reads it directly, so a
 * currency is added by adding an entry and no feature module changes. That is
 * the reusable component the spec asks for: Money does the formatting,
 * this does the "which".
 *
 * Two answers, in order: the business's primary, then the currencies it has
 * additionally enabled. A business always has a primary — pricing with no
 * currency is not a state anything downstream can read.
 */
class Currencies
{
    /** What the app falls back to when no business has chosen. */
    public const FALLBACK = 'USD';

    /**
     * Every currency StyleDesk can price in.
     *
     * Only the active ones. A currency listed but switched off must not reach
     * a selector: a business choosing it would be pricing in something the
     * rest of the app has no formatting rules for.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function available(): Collection
    {
        return collect(config('currencies.currencies'))
            ->filter(fn (array $currency) => $currency['active'] ?? false);
    }

    public static function supports(?string $code): bool
    {
        return $code !== null && self::available()->has(mb_strtoupper($code));
    }

    /** A usable code, whatever was passed. */
    public static function resolve(?string $code = null): string
    {
        $code = $code === null ? null : mb_strtoupper($code);

        if (self::supports($code)) {
            return $code;
        }

        $primary = self::primaryFor(auth()->user()?->tenant);

        return self::supports($primary) ? $primary : self::FALLBACK;
    }

    /**
     * One currency's full definition, always complete.
     *
     * Never returns a partial array: Money reads seven keys off this and a
     * missing separator would render a price with nothing between the digits.
     *
     * @return array<string, mixed>
     */
    public static function get(?string $code = null): array
    {
        $code = self::resolve($code);

        return config('currencies.currencies.'.$code) ?? config('currencies.currencies.'.self::FALLBACK);
    }

    public static function name(string $code): string
    {
        return config('currencies.currencies.'.mb_strtoupper($code).'.name', $code);
    }

    /**
     * "USD — US Dollar ($)", the form the spec's dropdowns use.
     *
     * Code first, because the code is what identifies the money and the
     * symbol is only how it is drawn — and because seven of these share a
     * dollar sign.
     */
    public static function label(string $code): string
    {
        $code = mb_strtoupper($code);
        $currency = config('currencies.currencies.'.$code);

        if ($currency === null) {
            return $code;
        }

        return $code.' — '.$currency['name'].' ('.$currency['symbol'].')';
    }

    /**
     * Every currency, as dropdown options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::available()->keys()->mapWithKeys(fn (string $code) => [$code => self::label($code)])->all();
    }

    /** The business's default. */
    public static function primaryFor(?Tenant $tenant): string
    {
        $primary = $tenant?->currency_code;

        return self::supports($primary) ? mb_strtoupper($primary) : self::FALLBACK;
    }

    /**
     * Every currency this business prices in, primary first.
     *
     * The primary is always in the list whether or not it was also stored as
     * a secondary — it is enabled by being the primary, and a list that
     * omitted it would leave a pricing screen unable to offer the one
     * currency the business definitely uses.
     *
     * @return Collection<int, string>
     */
    public static function enabledFor(?Tenant $tenant): Collection
    {
        if ($tenant === null) {
            return collect([self::FALLBACK]);
        }

        return $tenant->currencies()
            ->orderBy('position')
            ->pluck('currency_code')
            ->prepend(self::primaryFor($tenant))
            ->map(fn (string $code) => mb_strtoupper($code))
            ->filter(fn (string $code) => self::supports($code))
            ->unique()
            ->values();
    }

    /**
     * Whether this business prices in more than one currency.
     *
     * What a pricing field asks before deciding between a single input and a
     * row per currency, per §Pricing Behavior.
     */
    public static function hasMultiple(?Tenant $tenant): bool
    {
        return self::enabledFor($tenant)->count() > 1;
    }
}
