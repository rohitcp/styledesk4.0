<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Collection;

/**
 * What a booking comes to.
 *
 * Every screen that shows a bill — the summary beside the booking form, the
 * payment panel, the confirmation, the receipt — reads it from here, so the
 * four cannot disagree by a cent. The arithmetic is in minor units the whole
 * way and rounds exactly once, at the tax line.
 *
 * Tax follows the two settings the business already keeps: the behaviour
 * (added at checkout, included in the price, or not charged) and the rate. An
 * inclusive price is not "no tax" — the tax is in there, and a receipt has to
 * be able to say how much of it was, which is why it is worked out and shown
 * either way.
 */
class BookingTotals
{
    private function __construct(
        public readonly int $subtotalMinor,
        public readonly int $discountMinor,
        public readonly int $taxMinor,
        public readonly int $totalMinor,
        public readonly bool $taxIncluded,
        public readonly float $taxRate,
        public readonly string $currency,
    ) {}

    /**
     * The bill for a set of prices, in minor units.
     *
     * @param  Collection<int, int>|array<int, int>  $prices
     */
    public static function of(Collection|array $prices, ?string $currency = null, int $discountMinor = 0): self
    {
        $currency ??= Currencies::resolve();
        $subtotal = (int) collect($prices)->sum();
        $discount = min($discountMinor, $subtotal);
        $net = $subtotal - $discount;

        $tenant = tenant();
        $rate = (float) ($tenant?->default_tax_rate ?? 0);
        $behavior = (string) ($tenant?->default_tax_behavior ?? 'none');

        if ($rate <= 0 || $behavior === 'none') {
            return new self($subtotal, $discount, 0, $net, false, 0.0, $currency);
        }

        /* Included: the price already holds the tax, so the tax is the part
           of it that is tax — not a line added on top of what was quoted. */
        if ($behavior === 'inclusive') {
            $tax = (int) round($net - ($net / (1 + ($rate / 100))));

            return new self($subtotal, $discount, $tax, $net, true, $rate, $currency);
        }

        $tax = (int) round($net * ($rate / 100));

        return new self($subtotal, $discount, $tax, $net + $tax, false, $rate, $currency);
    }

    /**
     * The bill as it was when the booking was taken.
     *
     * Read back from the booking rather than recomputed, so a price list or a
     * tax rate edited in March cannot rewrite what somebody was charged in
     * February.
     */
    public static function for(Booking $booking): self
    {
        $subtotal = (int) ($booking->subtotal_minor ?: $booking->total_minor);
        $tax = (int) $booking->tax_minor;

        return new self(
            $subtotal,
            (int) $booking->discount_minor,
            $tax,
            (int) $booking->total_minor,
            $tax > 0 && $subtotal - (int) $booking->discount_minor === (int) $booking->total_minor,
            (float) (tenant()?->default_tax_rate ?? 0),
            (string) ($booking->currency_code ?: Currencies::resolve()),
        );
    }

    /**
     * The lines a bill is read as, ready for a summary panel.
     *
     * Zero lines are left out rather than shown as nothing: a discount of
     * $0.00 is a discount nobody gave, and a tax line on a business that
     * charges no tax is a question its receipts should not raise.
     *
     * @return array<int, array{key: string, label: string, value: string, strong?: bool}>
     */
    public function lines(): array
    {
        $lines = [[
            'key' => 'subtotal',
            'label' => __('bookings.summary.subtotal'),
            'value' => $this->money($this->subtotalMinor),
        ]];

        if ($this->discountMinor > 0) {
            $lines[] = [
                'key' => 'discount',
                'label' => __('bookings.summary.discount'),
                'value' => '−'.$this->money($this->discountMinor),
            ];
        }

        if ($this->taxMinor > 0) {
            $lines[] = [
                'key' => 'tax',
                'label' => $this->taxIncluded
                    ? __('bookings.summary.tax_included', ['rate' => $this->rateLabel()])
                    : __('bookings.summary.tax', ['rate' => $this->rateLabel()]),
                'value' => $this->money($this->taxMinor),
            ];
        }

        $lines[] = [
            'key' => 'total',
            'label' => __('bookings.summary.total'),
            'value' => $this->money($this->totalMinor),
            'strong' => true,
        ];

        return $lines;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subtotal_minor' => $this->subtotalMinor,
            'discount_minor' => $this->discountMinor,
            'tax_minor' => $this->taxMinor,
            'total_minor' => $this->totalMinor,
            'total' => $this->money($this->totalMinor),
            'currency' => $this->currency,
            'lines' => $this->lines(),
        ];
    }

    public function money(int $minor): string
    {
        return Money::format($minor / 100, $this->currency);
    }

    /** "8%" rather than "8.00%", which reads like a precision nobody set. */
    private function rateLabel(): string
    {
        return rtrim(rtrim(number_format($this->taxRate, 2), '0'), '.').'%';
    }
}
