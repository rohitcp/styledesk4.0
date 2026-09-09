<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ResourceAllocator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A month of appointments for Smile Spa.
 *
 *   php artisan db:seed --class=SmileSpaBookingsSeeder
 *
 * Eighty bookings across the current month: ten today, twenty-two over the
 * following three days, and the rest spread over what is left of it.
 *
 * The point of seeding a diary rather than eighty rows is that a diary has to
 * be possible. Every appointment here is given a therapist who is free, who
 * actually performs that treatment, and a room or chair that is free — picked
 * through the same allocator the booking screen uses, so a couple room is
 * only ever taken by a single client when every single room is full. A month
 * of overlapping bookings would look fine in a listing and be nonsense the
 * moment anybody opened the calendar.
 *
 * Sundays are skipped: the branch is closed, and an appointment on a day the
 * doors are locked is the kind of test data that teaches people to distrust
 * the seeder.
 *
 * Idempotent by month. It counts what is already there for the month and
 * tops it up, so running it twice does not produce a hundred and sixty.
 */
class SmileSpaBookingsSeeder extends Seeder
{
    private const OWNER_EMAIL = 'emma.martin3@example.com';

    /** Ten today, twenty-two over the next three days, eighty in the month. */
    private const TODAY = 10;

    private const NEXT_THREE_DAYS = 22;

    private const MONTH = 80;

    private Tenant $tenant;

    private Location $location;

    private User $owner;

    /** @var Collection<int, Service> */
    private Collection $services;

    /** @var Collection<int, Staff> */
    private Collection $team;

    /** @var Collection<int, Client> */
    private Collection $clients;

    public function run(): void
    {
        $owner = User::where('email', self::OWNER_EMAIL)->first();

        if ($owner?->tenant === null) {
            $this->command?->error('No business found for '.self::OWNER_EMAIL.'.');

            return;
        }

        $this->owner = $owner;
        $this->tenant = $owner->tenant;

        $location = Location::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('is_primary', true)
            ->first();

        $this->services = Service::withoutGlobalScopes()
            ->with('prices')
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('is_active', true)
            ->get();

        $this->team = Staff::withoutGlobalScopes()
            ->with('services')
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('is_active', true)
            ->where('provides_services', true)
            ->get();

        if ($location === null || $this->services->isEmpty() || $this->team->isEmpty()) {
            $this->command?->error('Run SmileSpaSeeder first — there are no services, staff or location to book.');

            return;
        }

        $this->location = $location;
        $this->clients = $this->clients();

        /* Fixed, so two runs of the seeder produce the same diary. A month of
           test data that reshuffles itself is a month nobody can point at. */
        mt_srand(20260901);

        $today = Carbon::today();
        $made = 0;

        $made += $this->fill($today, self::TODAY);

        foreach ([1, 2, 3] as $offset) {
            /* Twenty-two across three days, and the last of them takes the
               remainder so the total is exact rather than nearly. */
            $share = $offset === 3
                ? self::NEXT_THREE_DAYS - 2 * intdiv(self::NEXT_THREE_DAYS, 3)
                : intdiv(self::NEXT_THREE_DAYS, 3);

            $made += $this->fill($today->copy()->addDays($offset), $share);
        }

        /* Whatever is left of the eighty, spread over the rest of the month
           rather than piled onto one afternoon. */
        $remaining = self::MONTH - $this->countThisMonth();
        $days = $this->remainingDays($today);

        while ($remaining > 0 && $days->isNotEmpty()) {
            foreach ($days as $day) {
                if ($remaining <= 0) {
                    break;
                }

                $made += $taken = $this->fill($day, min(3, $remaining));
                $remaining -= $taken;

                /* A day that cannot take another appointment is done with;
                   without this the loop would circle a full month forever. */
                if ($taken === 0) {
                    $days = $days->reject(fn (Carbon $d) => $d->isSameDay($day));
                }
            }
        }

        $this->command?->info(sprintf(
            'Seeded %d bookings for Smile Spa — %d today, %d in the month.',
            $made,
            Booking::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey())
                ->whereDate('date', $today)->count(),
            $this->countThisMonth(),
        ));
    }

    /** People to book in. Twenty-five is enough for some to be regulars. */
    private function clients(): Collection
    {
        $names = [
            ['Olivia', 'Bennett'], ['Liam', 'Fitzgerald'], ['Priya', 'Anand'], ['Noah', 'Kim'],
            ['Sofia', 'Marchetti'], ['Ethan', 'Brooks'], ['Amara', 'Diallo'], ['Lucas', 'Moreau'],
            ['Hannah', 'Weiss'], ['Mateo', 'Silva'], ['Zara', 'Hussain'], ['Owen', 'Gallagher'],
            ['Ines', 'Ferreira'], ['Caleb', 'Nakamura'], ['Freya', 'Lindqvist'], ['Jonah', 'Adeyemi'],
            ['Nadia', 'Petrov'], ['Elias', 'Haddad'], ['Maya', 'Chandra'], ['Theo', 'Vandenberg'],
            ['Rosa', 'Delgado'], ['Ilya', 'Sokolov'], ['Aiko', 'Yamada'], ['Declan', 'O\'Rourke'],
            ['Yara', 'Karim'],
        ];

        return collect($names)->map(function (array $name) {
            [$first, $last] = $name;
            $email = mb_strtolower($first.'.'.str_replace("'", '', $last)).'@example.test';

            $client = Client::withoutGlobalScopes()->firstOrNew([
                'tenant_id' => $this->tenant->getTenantKey(),
                'email' => $email,
            ]);

            if (! $client->exists) {
                $client->fill([
                    'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
                    'first_name' => $first,
                    'last_name' => $last,
                    'status' => 'active',
                    'preferred_location_id' => $this->location->id,
                ])->save();

                /* Through the sync methods, not by filling the columns.
                 *
                 * `clients.mobile` and `clients.email` are a cache of the
                 * primary contact; the record is client_phones and
                 * client_emails. Writing the columns directly produced
                 * clients whose number showed in the listing — which reads
                 * the cache — and nowhere on their profile, which reads the
                 * record. */
                $client->syncPhones([[
                    'number' => '+1 201 555 '.str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'type' => 'mobile',
                    'is_primary' => true,
                ]]);

                $client->syncEmails([[
                    'email' => $email,
                    'type' => 'personal',
                    'is_primary' => true,
                ]]);
            }

            return $client;
        });
    }

    /**
     * Put up to `$wanted` appointments in one day, and say how many landed.
     *
     * Fewer is a real answer: a Saturday where every room is full by three
     * o'clock cannot take a fifth booking, and forcing one in would produce
     * exactly the double-booked diary this seeder exists to avoid.
     */
    private function fill(Carbon $day, int $wanted): int
    {
        if ($wanted <= 0 || $this->isClosed($day)) {
            return 0;
        }

        $made = 0;

        /* Start times on the half hour through the working day, shuffled so
           the morning does not fill up before the afternoon is touched. */
        $slots = collect(range(9 * 60, 20 * 60, 30))
            ->map(fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60))
            ->shuffle();

        foreach ($slots as $slot) {
            if ($made >= $wanted) {
                break;
            }

            if ($this->book($day, $slot)) {
                $made++;
            }
        }

        return $made;
    }

    /**
     * One appointment, if the building can carry it.
     *
     * Everything has to line up: a treatment, somebody who performs it and is
     * free, and a room or chair that is free. Any of those missing and the
     * slot is simply left alone.
     */
    private function book(Carbon $day, string $startsAt): bool
    {
        $service = $this->services->random();
        $minutes = (int) $service->duration_minutes;

        /* Not past closing. The last appointment of the day has to finish
           inside it, which is a different question from starting inside it. */
        if ($this->toMinutes($startsAt) + $minutes > $this->closesAt($day)) {
            return false;
        }

        $staff = $this->freeStaff($service, $day, $startsAt, $minutes);

        if ($staff === null) {
            return false;
        }

        /* The same allocator the booking screen uses, so the diary that comes
           out obeys the same preference rules — a couple room is only taken
           by a single client when every single room is full. */
        $resource = ResourceAllocator::assign([$service->id], $day->toDateString(), $startsAt, $minutes);

        if ($resource === null) {
            return false;
        }

        $price = (int) ($service->prices->firstWhere('currency_code', 'USD')?->price_minor ?? 0);
        $ends = $this->toMinutes($startsAt) + $minutes;
        $past = $day->copy()->setTimeFromTimeString($startsAt)->isPast();

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'reference' => Booking::nextReference(),
            'client_id' => $this->clients->random()->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'resource_id' => $resource->id,
            'date' => $day->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => sprintf('%02d:%02d', intdiv($ends, 60), $ends % 60),
            'minutes' => $minutes,
            /* What has already happened is done; what has not is booked in. */
            'status' => $past ? 'completed' : 'confirmed',
            'source' => collect(['front-desk', 'phone', 'online', 'walk-in'])->random(),
            'is_walk_in' => false,
            'subtotal_minor' => $price,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $price,
            'currency_code' => 'USD',
            'payment_type' => 'none',
            'collection_method' => 'later',
            'payment_status' => $past ? 'paid' : 'unpaid',
            'paid_minor' => $past ? $price : 0,
            'confirmation' => 'none',
            'confirmed_at' => now(),
            'created_by' => $this->owner->id,
        ]);

        $booking->services()->create([
            'service_id' => $service->id,
            'name' => $service->name,
            'minutes' => $minutes,
            'price_minor' => $price,
            'sort_order' => 0,
        ]);

        return true;
    }

    /**
     * Somebody who performs this treatment and is not already with a client.
     *
     * Checked against the diary rather than assumed: a therapist in two rooms
     * at once is the first thing anybody notices in seeded data.
     */
    private function freeStaff(Service $service, Carbon $day, string $startsAt, int $minutes): ?Staff
    {
        $able = $this->team
            ->filter(fn (Staff $member) => $member->services->contains('id', $service->id))
            ->shuffle();

        if ($able->isEmpty()) {
            return null;
        }

        $busy = Booking::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->whereIn('staff_id', $able->pluck('id'))
            ->whereDate('date', $day->toDateString())
            ->get(['staff_id', 'starts_at', 'ends_at'])
            ->filter(fn (Booking $booking) => $this->overlaps(
                $startsAt, $minutes, $booking->startsAt(), substr((string) $booking->ends_at, 0, 5)
            ))
            ->pluck('staff_id');

        return $able->first(fn (Staff $member) => ! $busy->contains($member->id));
    }

    /** The days left in the month that the branch is actually open. */
    private function remainingDays(Carbon $today): Collection
    {
        return collect(range(4, $today->daysInMonth - $today->day))
            ->map(fn (int $offset) => $today->copy()->addDays($offset))
            ->reject(fn (Carbon $day) => $this->isClosed($day))
            ->values();
    }

    private function countThisMonth(): int
    {
        $today = Carbon::today();

        return Booking::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->whereBetween('date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->count();
    }

    private function isClosed(Carbon $day): bool
    {
        return ! $this->location->allHours()
            ->where('day_of_week', $day->dayOfWeek)
            ->where('is_open', true)
            ->exists();
    }

    private function closesAt(Carbon $day): int
    {
        $hours = $this->location->allHours()
            ->where('day_of_week', $day->dayOfWeek)
            ->where('is_open', true)
            ->first();

        return $this->toMinutes(substr((string) $hours?->closes_at, 0, 5)) ?? 18 * 60;
    }

    private function overlaps(string $slot, int $minutes, ?string $from, ?string $until): bool
    {
        $start = $this->toMinutes($slot);
        $otherStart = $this->toMinutes($from);
        $otherEnd = $this->toMinutes($until);

        if ($start === null || $otherStart === null || $otherEnd === null) {
            return false;
        }

        return $start < $otherEnd && $otherStart < $start + $minutes;
    }

    private function toMinutes(?string $time): ?int
    {
        if ($time === null || ! str_contains($time, ':')) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
