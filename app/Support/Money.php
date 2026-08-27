<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The only place in StyleDesk that turns a number into money.
 *
 * The spec is explicit that individual screens must not implement their own
 * currency formatting, and the reason is visible in the config: seven of the
 * supported currencies use a dollar sign, the euro puts its symbol after the
 * number, the Swiss franc groups with an apostrophe, and the Indian rupee
 * groups in twos after the first three digits. Any screen that wrote its own
 * `number_format($amount, 2)` would be right for the dollar and wrong for four
 * of the others — and nobody would notice until a business in Mumbai read a
 * receipt.
 *
 * Amounts are identified by their currency *code*, never by the symbol alone.
 * "$100" is ambiguous across seven currencies; "$100 USD" is not.
 */
class Money
{
    /**
     * An amount as a person would read it, in the given currency.
     *
     * The amount is a plain number rather than minor units, because that is
     * what the pricing fields collect and what the database stores. Rounding
     * happens once, here, so the same price cannot render as 74.99 on one
     * screen and 75.00 on another.
     */
    public static function format(int|float|string|null $amount, ?string $code = null): string
    {
        $currency = Currencies::get($code);
        $value = (float) ($amount ?? 0);

        /**
         * The sign is placed on the whole thing, not on the number inside it.
         *
         * "$-75.00" is what you get from formatting a negative and then adding
         * a symbol; "-$75.00" is what a refund actually looks like.
         */
        $negative = $value < 0;
        $number = self::number(abs($value), $currency);

        /**
         * A word-like symbol gets a space; a glyph does not.
         *
         * "$75.00" is right and "CHF75.00" is not — CHF, RM and R are read as
         * words, and running them into the digits makes the amount look like
         * a product code. The space is non-breaking either way, so a price
         * never wraps between its symbol and its number.
         */
        $gap = preg_match('/\p{L}$/u', $currency['symbol']) ? "\u{00A0}" : '';

        $formatted = $currency['position'] === 'after'
            ? $number."\u{00A0}".$currency['symbol']
            : $currency['symbol'].$gap.$number;

        return $negative ? '-'.$formatted : $formatted;
    }

    /**
     * The unambiguous form: the amount and its code.
     *
     * What receipts, invoices, exports and anything a business might compare
     * across currencies should use. §Currency Display exists because "$100"
     * read on its own could be four different amounts of money.
     */
    public static function formatWithCode(int|float|string|null $amount, ?string $code = null): string
    {
        $code = Currencies::resolve($code);

        return self::format($amount, $code).' '.$code;
    }

    /** Just the symbol, for a field prefix where the code is already stated. */
    public static function symbol(?string $code = null): string
    {
        return Currencies::get($code)['symbol'];
    }

    public static function decimals(?string $code = null): int
    {
        return Currencies::get($code)['decimals'];
    }

    /**
     * Zero, written out.
     *
     * Named rather than left to callers passing 0, because "free" and "not
     * priced yet" are different things and a screen should be able to say
     * which it means.
     */
    public static function zero(?string $code = null): string
    {
        return self::format(0, $code);
    }

    /**
     * The digits, grouped and separated as the currency is written.
     *
     * @param  array<string, mixed>  $currency
     */
    private static function number(float $value, array $currency): string
    {
        $decimals = $currency['decimals'];
        $rounded = round($value, $decimals);

        $whole = (string) (int) floor($rounded);
        $fraction = $decimals > 0
            ? str_pad((string) (int) round(($rounded - floor($rounded)) * (10 ** $decimals)), $decimals, '0', STR_PAD_LEFT)
            : '';

        $grouped = $currency['grouping'] === 'indian'
            ? self::groupIndian($whole, $currency['thousands'])
            : self::groupWestern($whole, $currency['thousands']);

        return $decimals > 0 ? $grouped.$currency['decimal'].$fraction : $grouped;
    }

    private static function groupWestern(string $whole, string $separator): string
    {
        return strrev(implode($separator, str_split(strrev($whole), 3)));
    }

    /**
     * The Indian system: the last three digits, then twos.
     *
     * 123456.78 is written 1,23,456.78 rather than 123,456.78. Not a
     * curiosity — it is how prices are written for a fifth of the world, and
     * getting it wrong makes a receipt look like a typo.
     */
    private static function groupIndian(string $whole, string $separator): string
    {
        if (mb_strlen($whole) <= 3) {
            return $whole;
        }

        $last = mb_substr($whole, -3);
        $rest = mb_substr($whole, 0, -3);

        $pairs = strrev(implode($separator, str_split(strrev($rest), 2)));

        return $pairs.$separator.$last;
    }

    /**
     * Parse what someone typed back into a number.
     *
     * A price field shows "1.234,56 €" to a business pricing in euros, and
     * that string has to survive a round trip. Reading it with (float) would
     * give 1.234 — the separators mean the opposite of what PHP assumes.
     */
    public static function parse(?string $input, ?string $code = null): ?float
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $currency = Currencies::get($code);

        $clean = Str::of($input)
            ->replace($currency['symbol'], '')
            ->replace($currency['thousands'], '')
            ->replace("\u{00A0}", '')
            ->replace($currency['decimal'], '.')
            ->replaceMatches('/[^0-9.\-]/', '')
            ->toString();

        return is_numeric($clean) ? (float) $clean : null;
    }
}
