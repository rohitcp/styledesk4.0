<?php

declare(strict_types=1);

namespace App\Messaging;

/**
 * What a message will be charged as.
 *
 * A text is billed by the segment, not by the message. GSM-7 fits 160
 * characters in one, and 153 in each of a concatenated set because the rest
 * of the space carries the instructions for reassembling them. One character
 * outside that alphabet — a curly apostrophe pasted from a word processor, an
 * emoji in a birthday message — moves the whole message to UCS-2, where the
 * numbers fall to 70 and 67.
 *
 * That cliff is why the template editor counts out loud: a greeting somebody
 * "just tidied up" can triple a month's bill without a word changing.
 */
class SmsSegments
{
    /** Characters that fit in GSM-7 but take two of its slots. */
    private const GSM_EXTENDED = '^{}\\[~]|€';

    private const GSM_BASE = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        .'¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    /**
     * How many segments this body needs.
     *
     * @return array{segments: int, characters: int, encoding: string}
     */
    public static function measure(string $body): array
    {
        $unicode = ! self::isGsm($body);
        $characters = $unicode ? mb_strlen($body) : self::gsmLength($body);

        $single = $unicode ? 70 : 160;
        $multi = $unicode ? 67 : 153;

        return [
            'segments' => $characters <= $single ? max(1, (int) ceil($characters / $single)) : (int) ceil($characters / $multi),
            'characters' => $characters,
            'encoding' => $unicode ? 'unicode' : 'gsm',
        ];
    }

    /** Just the number, for a record that only needs the one figure. */
    public static function count(string $body): int
    {
        return self::measure($body)['segments'];
    }

    /** Every character available in the seven-bit alphabet? */
    private static function isGsm(string $body): bool
    {
        foreach (mb_str_split($body) as $character) {
            if (! str_contains(self::GSM_BASE, $character) && ! str_contains(self::GSM_EXTENDED, $character)) {
                return false;
            }
        }

        return true;
    }

    /** Length in GSM slots, where the extended characters take two. */
    private static function gsmLength(string $body): int
    {
        $length = 0;

        foreach (mb_str_split($body) as $character) {
            $length += str_contains(self::GSM_EXTENDED, $character) ? 2 : 1;
        }

        return $length;
    }
}
