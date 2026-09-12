<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The one form two numbers are compared in.
 *
 * Worth its own unit test rather than only being exercised through the walk-in
 * flow: everything that decides whether two records are the same person runs
 * through here, and a normaliser that is wrong about one country is a silent
 * duplicate for every client in it.
 */
class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function usFormats(): array
    {
        return [
            'brackets' => ['(973) 555-1234', '+19735551234'],
            'hyphens' => ['973-555-1234', '+19735551234'],
            'spaces' => ['973 555 1234', '+19735551234'],
            'bare' => ['9735551234', '+19735551234'],
            'dialling code, no plus' => ['1 973 555 1234', '+19735551234'],
            'e164' => ['+19735551234', '+19735551234'],
            'spaced e164' => ['+1 973 555 1234', '+19735551234'],
            'dots' => ['973.555.1234', '+19735551234'],
        ];
    }

    #[DataProvider('usFormats')]
    public function test_every_way_a_us_number_is_written_normalises_the_same(string $typed, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalise($typed, 'US'));
    }

    public function test_a_number_keeps_its_own_country_code(): void
    {
        // The trunk 0 is how the number is dialled inside the UK, not part of
        // it: +44 20 7946 0100, never +44 020 7946 0100.
        $this->assertSame('+442079460100', PhoneNumber::normalise('020 7946 0100', 'GB'));
        $this->assertSame('+442079460100', PhoneNumber::normalise('+44 20 7946 0100', 'US'));
        $this->assertSame('+442079460100', PhoneNumber::normalise('0044 20 7946 0100', 'US'));
        $this->assertSame('+919876543210', PhoneNumber::normalise('98765 43210', 'IN'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rejected(): array
    {
        return [
            'empty' => [''],
            'spaces only' => ['   '],
            'half typed' => ['973555'],
            'one digit' => ['9'],
            'letters' => ['555-CALL'],
            'vanity' => ['973 555 HAIR'],
            'punctuation only' => ['()- '],
            'far too long' => ['+1234567890123456789'],
        ];
    }

    #[DataProvider('rejected')]
    public function test_a_number_that_could_not_be_rung_is_refused(string $typed): void
    {
        $this->assertNull(PhoneNumber::normalise($typed, 'US'));
        $this->assertFalse(PhoneNumber::looksValid($typed, 'US'));
    }

    public function test_the_country_decides_what_a_bare_number_means(): void
    {
        // Ten digits is a whole US number and half a German one.
        $this->assertSame('+19735551234', PhoneNumber::normalise('9735551234', 'US'));
        $this->assertNull(PhoneNumber::normalise('973555', 'US'));
    }

    public function test_the_typed_form_is_what_the_desk_reads_back(): void
    {
        $this->assertSame('(973) 555-1234', PhoneNumber::display('(973) 555-1234', '+19735551234'));
        $this->assertSame('+19735551234', PhoneNumber::display('', '+19735551234'));
        $this->assertNull(PhoneNumber::display(null, null));
    }
}
