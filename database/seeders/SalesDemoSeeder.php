<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Money against the bookings that already exist, so the Sales page has
 * something to show.
 *
 *   php artisan db:seed --class=SalesDemoSeeder
 *
 * Demonstration data, not fixtures: it exists so a developer can look at the
 * Sales screen and judge whether the figures read correctly, which needs a
 * spread rather than a hundred identical rows. Every state the page can draw
 * appears at least once — paid in full, deposit then balance, part paid,
 * unpaid, tipped, refunded, and a failed attempt.
 *
 * Safe to run more than once: a booking that already has a payment is left
 * alone, so it tops up what is missing rather than doubling what is there.
 */
class SalesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstWhere('slug', 'smile') ?? Tenant::query()->first();

        if ($tenant === null) {
            $this->command?->warn('No tenant to seed against.');

            return;
        }

        $staff = User::query()->where('tenant_id', $tenant->getTenantKey())->value('id');

        $bookings = Booking::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getTenantKey())
            ->whereBetween('date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->whereDoesntHave('payments')
            ->where('total_minor', '>', 0)
            ->orderBy('date')
            ->get();

        if ($bookings->isEmpty()) {
            $this->command?->info('Every booking this month already has a payment.');

            return;
        }

        $methods = ['card', 'card', 'card', 'cash', 'cash', 'venmo', 'zelle', 'paypal', 'cash-app'];
        $made = 0;

        foreach ($bookings as $index => $booking) {
            $total = (int) $booking->total_minor;

            /* A fixed rotation rather than random: the same command twice
               gives the same screen, which is what makes a change to the page
               comparable against the last time somebody looked at it. */
            $shape = $index % 10;

            /* Money moves on the day of the appointment, or a little before
               it for a deposit — never in the future, or the widgets would
               count sales that have not happened. */
            $when = Carbon::parse($booking->date)->setTime(
                (int) substr((string) $booking->starts_at, 0, 2) ?: 10,
                (int) substr((string) $booking->starts_at, 3, 2) ?: 0,
            );

            if ($when->isFuture()) {
                $when = now()->subHours($index % 48);
            }

            $method = $methods[$index % count($methods)];

            match (true) {
                /* Paid in full, sometimes with a tip. */
                $shape <= 3 => $this->pay($booking, $total, $method, $when, $staff, tip: $shape === 1 ? (int) round($total * 0.18) : 0),

                /* A deposit on booking, the balance on the day. */
                $shape <= 5 => $this->deposit($booking, $total, $method, $when, $staff),

                /* Part paid and still owing — what the Outstanding widget is
                   for. */
                $shape === 6 => $this->pay($booking, (int) round($total * 0.4), $method, $when, $staff),

                /* Refunded after the fact. */
                $shape === 7 => $this->refund($booking, $total, $method, $when, $staff),

                /* A card that did not go through. */
                $shape === 8 => $this->pay($booking, $total, 'card', $when, $staff, status: 'failed'),

                /* Nothing at all: an unpaid booking has no payment row, and
                   the page has to handle that rather than assume one. */
                default => null,
            };

            $booking->load('payments');
            $booking->settlePaymentStatus();

            $made += $booking->payments->count();
        }

        $this->command?->info("Seeded {$made} transactions across {$bookings->count()} bookings.");
    }

    private function pay(
        Booking $booking,
        int $minor,
        string $method,
        Carbon $when,
        ?int $staff,
        int $tip = 0,
        string $status = 'paid',
    ): void {
        BookingPayment::withoutGlobalScopes()->create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'method' => $method,
            'status' => $status,
            'amount_minor' => $minor,
            'tip_minor' => $tip,
            'currency_code' => $booking->currency_code ?: 'USD',
            /* Cash is tendered and change is given; a card is not. */
            'received_minor' => $method === 'cash' ? (int) (ceil($minor / 1000) * 1000) : null,
            'change_minor' => $method === 'cash' ? (int) (ceil($minor / 1000) * 1000) - $minor : null,
            'reference' => $method === 'card' ? 'ch_'.str()->random(14) : null,
            'paid_at' => $when,
            'recorded_by' => $staff,
        ]);
    }

    /** A deposit when the booking was taken, the rest on the day. */
    private function deposit(Booking $booking, int $total, string $method, Carbon $when, ?int $staff): void
    {
        $deposit = (int) round($total * 0.3);

        $this->pay($booking, $deposit, 'card', $when->copy()->subDays(3), $staff);
        $this->pay($booking, $total - $deposit, $method, $when, $staff);
    }

    /** Taken, then given back. Both rows stay: the history is what happened. */
    private function refund(Booking $booking, int $total, string $method, Carbon $when, ?int $staff): void
    {
        $this->pay($booking, $total, $method, $when->copy()->subDay(), $staff);

        BookingPayment::withoutGlobalScopes()->create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'method' => $method,
            'status' => 'refunded',
            'amount_minor' => $total,
            'currency_code' => $booking->currency_code ?: 'USD',
            'note' => 'Client cancelled within the notice period.',
            'paid_at' => $when,
            'recorded_by' => $staff,
        ]);
    }
}
