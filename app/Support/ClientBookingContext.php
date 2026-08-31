<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What the booking screen shows about a client once one is chosen.
 *
 * A receptionist with somebody on the phone needs three things the client
 * record holds and the booking form does not: who they usually see, what they
 * had last time, and how they like to be booked. Fetching that as five
 * separate questions while the caller waits is the reason people keep paper
 * diaries, so it is assembled once, here.
 *
 * Two kinds of fact live side by side and are labelled apart on purpose.
 * "Asked for by name" is something the client said; "Booked most often" is
 * something the diary noticed. Flattening the two would have the desk quoting
 * the software back to people as though they had said it.
 */
class ClientBookingContext
{
    /** Visits worth reasoning from: kept, not cancelled, and in the past. */
    private const COUNTED = ['confirmed', 'arrived', 'completed'];

    public function __construct(private Client $client) {}

    public static function for(Client $client): self
    {
        return new self($client);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $history = $this->history();
        $byStaff = $this->byStaff($history);

        return [
            'client' => [
                'id' => $this->client->id,
                'name' => $this->client->displayName(),
                'initials' => $this->client->initials(),
                'ref' => $this->client->client_ref,
                'mobile' => $this->client->mobile,
                'email' => $this->client->email,
                'url' => route('clients.show', $this->client),
                'visits' => $history->count(),
            ],
            'preferred' => $this->preferred($byStaff),
            'also_seen' => $this->alsoSeen($byStaff),
            'last' => $this->lastBooking($history),
            'recent' => $this->recent($history),
            'rating' => $this->averageRating($history),
            'preferences' => $this->preferences($history),
        ];
    }

    /**
     * Every visit that counts, newest first.
     *
     * Drafts and cancellations are left out: one was never promised and the
     * other did not happen, and counting either would tell a receptionist
     * this client sees somebody they have never met.
     *
     * @return Collection<int, Booking>
     */
    private function history(): Collection
    {
        return Booking::query()
            ->with(['services', 'staff', 'review'])
            ->where('client_id', $this->client->id)
            ->whereIn('status', self::COUNTED)
            ->whereDate('date', '<=', now()->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('starts_at')
            ->limit(60)
            ->get();
    }

    /**
     * How many visits each staff member has had with this client.
     *
     * @param  Collection<int, Booking>  $history
     * @return Collection<int, array<string, mixed>>
     */
    private function byStaff(Collection $history): Collection
    {
        return $history
            ->filter(fn (Booking $booking) => $booking->staff_id !== null)
            ->groupBy('staff_id')
            ->map(fn (Collection $visits, $staffId) => [
                'id' => (int) $staffId,
                'staff' => $visits->first()->staff,
                'visits' => $visits->count(),
                'last' => $visits->first()->date,
            ])
            ->sortByDesc('visits')
            ->values();
    }

    /**
     * Who the client is most likely to want, and why.
     *
     * The person they asked for outranks the person they have seen most: one
     * is a request and the other is a coincidence of the diary, and the panel
     * says which is which.
     *
     * @param  Collection<int, array<string, mixed>>  $byStaff
     * @return array<int, array<string, mixed>>
     */
    private function preferred(Collection $byStaff): array
    {
        $preferred = $this->client->preferred_staff_id;
        $rows = [];

        if ($preferred !== null) {
            $seen = $byStaff->firstWhere('id', $preferred);
            $staff = $seen['staff'] ?? Staff::query()->find($preferred);

            if ($staff) {
                $rows[] = $this->staffRow($staff, $seen['visits'] ?? 0, 'asked_for');
            }
        }

        /* The one they see most, when that is somebody else: a client who
           asked for Nadia and has seen Luis eight times wants both names in
           front of the receptionist. */
        $most = $byStaff->first(fn (array $row) => $row['id'] !== $preferred);

        if ($most && $most['visits'] >= 2) {
            $rows[] = $this->staffRow($most['staff'], $most['visits'], 'most_booked');
        }

        return $rows;
    }

    /**
     * Everybody else they have seen, for the day the usual person is off.
     *
     * @param  Collection<int, array<string, mixed>>  $byStaff
     * @return array<int, array<string, mixed>>
     */
    private function alsoSeen(Collection $byStaff): array
    {
        $named = collect($this->preferred($byStaff))->pluck('id')->all();

        return $byStaff
            ->reject(fn (array $row) => in_array($row['id'], $named, true))
            ->take(4)
            ->map(fn (array $row) => $this->staffRow($row['staff'], $row['visits'], 'also_seen'))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function staffRow(Staff $staff, int $visits, string $kind): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->displayName(),
            'initials' => $staff->initials(),
            'role' => $staff->job_title ?: $staff->roleName(),
            'kind' => $kind,
            'why' => __('bookings.context.kinds.'.$kind),
            'visits' => $visits,
            'visits_label' => trans_choice('bookings.context.visits', $visits, ['count' => $visits]),
        ];
    }

    /**
     * The last thing they had, and enough of it to book it again.
     *
     * @param  Collection<int, Booking>  $history
     * @return array<string, mixed>|null
     */
    private function lastBooking(Collection $history): ?array
    {
        $last = $history->first();

        if (! $last) {
            return null;
        }

        return [
            'services' => $last->services->pluck('name')->implode(', '),
            'date' => $last->date->translatedFormat('j M Y'),
            'staff' => $last->staff?->displayName(),
            'total' => Money::format($last->total_minor / 100, $last->currency_code),
            /* What Book the same again fills in. Only services that still
               exist and are still offered: a discontinued one would prefill a
               booking nobody can take. */
            'again' => [
                'service_ids' => $last->services
                    ->pluck('service_id')
                    ->filter()
                    ->values()
                    ->all(),
                'staff_id' => $last->staff_id,
            ],
        ];
    }

    /**
     * The last three visits, with what the client thought where they said.
     *
     * @param  Collection<int, Booking>  $history
     * @return array<int, array<string, mixed>>
     */
    private function recent(Collection $history): array
    {
        return $history->take(3)->map(fn (Booking $booking) => [
            'date' => $booking->date->translatedFormat('j M Y'),
            'services' => $booking->services->pluck('name')->implode(', '),
            'staff' => $booking->staff?->displayName(),
            /* Only where a review exists. A blank row of stars reads as a
               bad review rather than as no review. */
            'rating' => $booking->review?->rating,
            'comment' => $booking->review?->comment,
        ])->values()->all();
    }

    /** @param  Collection<int, Booking>  $history */
    private function averageRating(Collection $history): ?string
    {
        $ratings = $history->map(fn (Booking $booking) => $booking->review?->rating)->filter();

        return $ratings->isEmpty() ? null : number_format($ratings->avg(), 1);
    }

    /**
     * How they like to be booked: what they said, then what the diary
     * noticed, each labelled with which it is.
     *
     * @param  Collection<int, Booking>  $history
     * @return array<int, array<string, mixed>>
     */
    private function preferences(Collection $history): array
    {
        $rows = $this->client->bookingPreferences
            ->map(fn ($preference) => [
                'label' => $preference->label,
                'source' => $preference->source,
            ])
            ->values()
            ->all();

        foreach ([$this->cadence($history), $this->timeOfDay($history)] as $noticed) {
            if ($noticed !== null) {
                $rows[] = ['label' => $noticed, 'source' => 'system'];
            }
        }

        return $rows;
    }

    /**
     * How often they come back, from the gaps between visits.
     *
     * The median rather than the mean: one appointment missed over Christmas
     * should not turn a four-week client into a seven-week one.
     *
     * @param  Collection<int, Booking>  $history
     */
    private function cadence(Collection $history): ?string
    {
        $dates = $history->pluck('date')->take(8)->values();

        if ($dates->count() < 3) {
            return null;
        }

        $gaps = $dates
            ->sliding(2)
            ->map(fn (Collection $pair) => CarbonImmutable::parse($pair->last())
                ->diffInDays(CarbonImmutable::parse($pair->first())))
            ->map(fn (float $days) => abs($days))
            ->sort()
            ->values();

        $median = $gaps->get((int) floor($gaps->count() / 2));
        $weeks = (int) round($median / 7);

        return $weeks >= 1 && $weeks <= 26
            ? trans_choice('bookings.context.cadence', $weeks, ['count' => $weeks])
            : null;
    }

    /**
     * Morning or afternoon, where they clearly favour one.
     *
     * Two thirds of at least three visits, or nothing: a client with four
     * mornings and three afternoons has no preference worth reporting.
     *
     * @param  Collection<int, Booking>  $history
     */
    private function timeOfDay(Collection $history): ?string
    {
        if ($history->count() < 3) {
            return null;
        }

        $windows = $history->countBy(function (Booking $booking) {
            $hour = (int) substr($booking->startsAt(), 0, 2);

            return match (true) {
                $hour < 12 => 'morning',
                $hour < 17 => 'afternoon',
                default => 'evening',
            };
        });

        $top = $windows->sortDesc()->keys()->first();

        return $windows[$top] / $history->count() >= 0.66
            ? __('bookings.context.windows.'.$top)
            : null;
    }
}
