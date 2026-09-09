<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BookingLead;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Move leads nobody has touched along.
 *
 * A booking left half-taken is a call worth returning for about a day; after
 * three it is a call that was never returned. Both of those are facts about
 * time passing rather than decisions anybody made, so they are applied here
 * rather than waited for.
 *
 * Deliberately not derived on read. "Follow-up required" is the state the
 * whole leads queue is filtered and counted by, and a status that only exists
 * while a page is rendering is one no query can reach.
 */
class AgeBookingLeads extends Command
{
    protected $signature = 'bookings:age-leads';

    protected $description = 'Move untouched booking leads to follow-up, then to abandoned.';

    public function handle(): int
    {
        $chase = now()->subHours((int) config('bookings.lead_follow_up_hours'));
        $drop = now()->subHours((int) config('bookings.lead_abandon_hours'));

        /* Oldest first, so a lead that has passed both thresholds since the
           last run lands on abandoned rather than stopping at follow-up. */
        $abandoned = BookingLead::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [...BookingLead::CHASEABLE, 'follow-up'])
            ->where(fn (Builder $query) => $query
                ->where('last_activity_at', '<=', $drop)
                ->orWhere(fn (Builder $fallback) => $fallback
                    ->whereNull('last_activity_at')
                    ->where('created_at', '<=', $drop)))
            ->update(['status' => 'abandoned']);

        $chased = BookingLead::query()
            ->withoutGlobalScopes()
            ->whereIn('status', BookingLead::CHASEABLE)
            ->where(fn (Builder $query) => $query
                ->where('last_activity_at', '<=', $chase)
                ->orWhere(fn (Builder $fallback) => $fallback
                    ->whereNull('last_activity_at')
                    ->where('created_at', '<=', $chase)))
            ->update(['status' => 'follow-up']);

        $this->info("Leads moved to follow-up: {$chased}, to abandoned: {$abandoned}.");

        return self::SUCCESS;
    }
}
