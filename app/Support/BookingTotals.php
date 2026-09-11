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
    /**
     * The bill as the till reads it: every line, including the empty ones.
     *
     * `lines()` above leaves out a line worth nothing on purpose — a discount
     * of $0.00 is a discount nobody gave, and a tax line on a business that
     * charges no tax is a question its receipts should not raise. The payment
     * summary is the opposite case: somebody is deciding whether money is owed
     * and why, and a line that is absent because it is nothing looks exactly
     * like a line the page failed to render.
     *
     * It lives here rather than in the controller because three readers need
     * the same answer — the Vue island, the no-JavaScript fallback, and
     * somebody without permission to take money — and three copies of this
     * arithmetic is two chances for the figures to disagree on one screen.
     *
     * @return array<int, array{key: string, label: string, value: string, strong?: bool, negative?: bool}>
     */
    public function breakdownFor(Booking $booking): array
    {
        $discount = (int) $booking->discount_minor;
        $paid = $booking->paidMinor();
        $tipPaid = (int) $booking->payments->sum('tip_minor');
        $credit = (int) $booking->membership_credit_minor;
        $tipDue = $booking->tipDueMinor();

        $lines = [[
            'key' => 'subtotal',
            'label' => __('bookings.summary.subtotal'),
            'value' => $this->money((int) ($booking->subtotal_minor ?: $booking->total_minor)),
        ], [
            /* Named "coupon" when one was actually applied, because that is
               what the reader is looking for; a discount given by hand has no
               coupon to name and stays "discount". */
            'key' => 'discount',
            'label' => $booking->promotion_id
                ? __('bookings.summary.coupon')
                : __('bookings.summary.discount'),
            'value' => $discount > 0 ? '−'.$this->money($discount) : $this->money(0),
            'negative' => $discount > 0,
        ], [
            /* Its own line and never folded into the discount. A coupon is
               the business giving money away and a credit is the client
               spending something they already bought; a summary that jumped
               from a subtotal to a nought total with nothing in between is
               one the desk cannot explain to the person in front of it. */
            'key' => 'membership_credit',
            'label' => __('bookings.credits.line'),
            'value' => $credit > 0 ? '−'.$this->money($credit) : $this->money(0),
            'negative' => $credit > 0,
        ], [
            'key' => 'tax',
            'label' => $this->taxIncludedLabel(),
            'value' => $this->money((int) $booking->tax_minor),
        ], [
            'key' => 'total',
            'label' => __('bookings.summary.total'),
            'value' => $this->money((int) $booking->total_minor),
            'strong' => true,
        ]];

        /* Only once there is one. A tip line reading nil on a bill nobody has
           tipped invites the desk to think a tip was refused rather than never
           offered — and the panel asks for one either way. */
        if ($tipPaid > 0) {
            $lines[] = [
                'key' => 'tip_paid',
                'label' => __('bookings.summary.tip_paid'),
                'value' => $this->money($tipPaid),
            ];
        }

        $lines[] = [
            'key' => 'paid',
            'label' => __('bookings.summary.paid'),
            'value' => $paid > 0 ? '−'.$this->money($paid) : $this->money(0),
            'negative' => $paid > 0,
        ];

        $lines[] = [
            'key' => 'due',
            'label' => __('bookings.summary.due_now'),
            'value' => $this->money($booking->dueMinor()),
            'strong' => true,
        ];

        /* The gratuity agreed when the booking was taken, and what the till
           should therefore collect. Added rather than folded into the
           balance: what is owed for the work and what was agreed as a tip
           are two different debts, and a business chasing one is not chasing
           the other. Shown only while there is one outstanding — on a bill
           whose tip has been taken these two lines would repeat the balance
           twice over. */
        if ($tipDue > 0) {
            $lines[] = [
                'key' => 'tip_due',
                'label' => __('bookings.summary.tip_due'),
                'value' => '+'.$this->money($tipDue),
            ];

            $lines[] = [
                'key' => 'amount_due',
                'label' => __('bookings.summary.amount_due'),
                'value' => $this->money($booking->amountDueMinor()),
                'strong' => true,
            ];
        }

        return $lines;
    }

    /**
     * How the tax line should be named, whether or not there is any tax.
     *
     * The pay card states every line including the empty ones, so it needs the
     * label even when the figure is nil — and the label is not a constant: a
     * business whose prices include tax says something different from one that
     * adds it on.
     */
    public function taxIncludedLabel(): string
    {
        return $this->taxIncluded
            ? __('bookings.summary.tax_included', ['rate' => $this->rateLabel()])
            : __('bookings.summary.tax', ['rate' => $this->rateLabel()]);
    }

    private function rateLabel(): string
    {
        return rtrim(rtrim(number_format($this->taxRate, 2), '0'), '.').'%';
    }
}
