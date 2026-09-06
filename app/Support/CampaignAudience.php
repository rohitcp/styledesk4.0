<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use App\Models\ClientTag;
use App\Models\EmailCampaign;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Who a campaign goes to, worked out from rules rather than from a list.
 *
 * The rules are the point. "Clients who have not visited in ninety days"
 * answers differently on the day a campaign sends than on the day it was
 * written, and the day it sends is the one that matters — a saved list would
 * quietly email people who came in last week.
 *
 * The estimate is deliberately three numbers and not one. "1,248 clients"
 * hides the two facts an owner needs before pressing send: how many people
 * have asked not to hear from them, and how many addresses are not addresses.
 * Both belong on the screen, because both are the difference between the list
 * they think they are writing to and the one that will actually receive it.
 *
 * Consent is not a rule anybody can switch off here. A client who has
 * unsubscribed is excluded from the eligible set whatever the rules say, and
 * that exclusion lives in this class rather than in the sender so that the
 * number on the screen and the number that receives the email are arrived at
 * the same way.
 */
class CampaignAudience
{
    /** The audiences the builder offers, in the order it offers them. */
    public const SCOPES = ['all', 'active'];

    /**
     * The lapsed-client windows the screen offers as buttons.
     *
     * A short list rather than a free number: every salon asks the same four
     * questions, and a text box invites "3 months", "90" and "ninety" into one
     * column.
     */
    public const LAPSED_DAYS = [30, 60, 90, 180];

    /**
     * Everybody the rules describe, before consent is considered.
     *
     * @param  array<string, mixed>  $rules
     * @return Builder<Client>
     */
    public static function query(array $rules): Builder
    {
        $query = Client::query();

        /* Archived clients are gone from the business's own lists; they are
           not an audience. Everything else is a choice the rules make. */
        $query->where('status', '!=', Client::STATUS_ARCHIVED);

        if (($rules['scope'] ?? 'all') === 'active') {
            $query->where('status', Client::STATUS_ACTIVE);
        }

        /*
         * "Clients at Riverside" means two things and an owner means both:
         * the people who said Riverside is their branch, and the people who
         * actually go there. A client has a preferred location and a history
         * of appointments, and matching only the first misses everybody who
         * never filled that field in.
         */
        if (filled($rules['locations'] ?? null)) {
            $ids = (array) $rules['locations'];

            $query->where(fn (Builder $inner) => $inner
                ->whereIn('preferred_location_id', $ids)
                ->orWhereHas('bookings', fn (Builder $booking) => $booking
                    ->whereIn('location_id', $ids)
                    ->whereNotIn('status', ['cancelled', 'declined', 'draft'])));
        }

        if (filled($rules['tags'] ?? null)) {
            $query->whereHas('tags', fn (Builder $tag) => $tag
                ->whereIn('client_tags.id', (array) $rules['tags']));
        }

        if (filled($rules['staff'] ?? null)) {
            $query->whereHas('bookings', fn (Builder $booking) => $booking
                ->whereIn('staff_id', (array) $rules['staff'])
                ->whereNotIn('status', ['cancelled', 'declined', 'draft']));
        }

        if (filled($rules['services'] ?? null)) {
            $query->whereHas('bookings.services', fn (Builder $line) => $line
                ->whereIn('booking_services.service_id', (array) $rules['services']));
        }

        /* Came in recently. Counted from appointments they actually attended:
           a booking somebody cancelled is not a visit. */
        if (filled($rules['visited_within_days'] ?? null)) {
            $since = now()->subDays((int) $rules['visited_within_days'])->toDateString();

            $query->whereHas('bookings', fn (Builder $booking) => self::attended($booking)
                ->whereDate('date', '>=', $since));
        }

        /*
         * Has NOT been in.
         *
         * Deliberately not the inverse of the rule above: somebody who has
         * never visited at all has also not visited in ninety days, and a
         * "we miss you" email to a person who has never been through the door
         * is the wrong message. They must have come at some point, and not
         * lately.
         */
        if (filled($rules['not_visited_days'] ?? null)) {
            $since = now()->subDays((int) $rules['not_visited_days'])->toDateString();

            $query
                ->whereHas('bookings', fn (Builder $booking) => self::attended($booking))
                ->whereDoesntHave('bookings', fn (Builder $booking) => self::attended($booking)
                    ->whereDate('date', '>=', $since));
        }

        if (array_key_exists('has_upcoming', $rules) && $rules['has_upcoming'] !== null) {
            $upcoming = fn (Builder $booking) => $booking
                ->whereDate('date', '>=', now()->toDateString())
                ->whereIn('status', ['pending', 'confirmed']);

            $rules['has_upcoming']
                ? $query->whereHas('bookings', $upcoming)
                : $query->whereDoesntHave('bookings', $upcoming);
        }

        return $query;
    }

    /**
     * What the rules come to, and what stands between that and the inbox.
     *
     * @param  array<string, mixed>  $rules
     * @return array{eligible: int, unsubscribed: int, invalid: int, total: int}
     */
    public static function estimate(array $rules): array
    {
        $base = self::query($rules);

        $total = (clone $base)->count();

        /* Asked not to hear from us. Their own answer, and it outranks every
           rule above. */
        $unsubscribed = (clone $base)->where('marketing_email', false)->count();

        /* No address, or nothing that could be one. Counted separately from
           unsubscribed because they are different problems: one is a decision
           the client made and the other is a field somebody never filled in. */
        $invalid = (clone $base)
            ->where('marketing_email', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('email')
                ->orWhere('email', '')
                ->orWhere('email', 'not like', '%@%.%'))
            ->count();

        return [
            'total' => $total,
            'unsubscribed' => $unsubscribed,
            'invalid' => $invalid,
            'eligible' => max(0, $total - $unsubscribed - $invalid),
        ];
    }

    /**
     * The clients who will actually be written to.
     *
     * What the sender iterates, and what the estimate's "eligible" counts —
     * one definition, so the number on the screen and the number that
     * receives the email cannot disagree.
     *
     * @param  array<string, mixed>  $rules
     * @return Builder<Client>
     */
    public static function eligible(array $rules): Builder
    {
        return self::query($rules)
            ->where('marketing_email', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('email', 'like', '%@%.%');
    }

    /**
     * The rules in words, for the listing's Audience column.
     *
     * @param  array<string, mixed>  $rules
     */
    public static function describe(?array $rules): string
    {
        $rules ??= [];
        $parts = [];

        $parts[] = __('marketing.audience.scopes.'.($rules['scope'] ?? 'all'));

        /* Counted, so they are read with trans_choice rather than __: "1
           location" and "3 locations" are different strings. */
        foreach (['locations', 'tags', 'staff', 'services'] as $key) {
            if (filled($rules[$key] ?? null)) {
                $count = count((array) $rules[$key]);

                $parts[] = trans_choice('marketing.audience.said.'.$key, $count, ['count' => $count]);
            }
        }

        if (filled($rules['not_visited_days'] ?? null)) {
            $parts[] = __('marketing.audience.said.lapsed', ['days' => (int) $rules['not_visited_days']]);
        }

        if (filled($rules['visited_within_days'] ?? null)) {
            $parts[] = __('marketing.audience.said.visited', ['days' => (int) $rules['visited_within_days']]);
        }

        if (($rules['has_upcoming'] ?? null) === true) {
            $parts[] = __('marketing.audience.said.has_upcoming');
        }

        if (($rules['has_upcoming'] ?? null) === false) {
            $parts[] = __('marketing.audience.said.no_upcoming');
        }

        return implode(' · ', $parts);
    }

    /**
     * The lists the audience step picks from.
     *
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        return [
            'scopes' => collect(self::SCOPES)
                ->mapWithKeys(fn (string $scope) => [$scope => __('marketing.audience.scopes.'.$scope)])
                ->all(),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'tags' => ClientTag::query()->where('is_active', true)->orderBy('label')->pluck('label', 'id')->all(),
            'staff' => Staff::query()->where('is_active', true)
                ->orderBy('first_name')->get()
                ->mapWithKeys(fn ($member) => [$member->id => $member->displayName()])->all(),
            'services' => Service::query()->where('is_active', true)
                ->orderBy('name')->pluck('name', 'id')->all(),
            'lapsed_days' => self::LAPSED_DAYS,
        ];
    }

    /**
     * The figures the dashboard's six widgets are drawn from.
     *
     * @return array<string, int>
     */
    public static function summary(): array
    {
        $campaigns = EmailCampaign::query();

        $sent = (clone $campaigns)->whereIn('status', ['sent', 'sending']);

        $totals = (clone $sent)->selectRaw(
            'coalesce(sum(sent_count),0) s, coalesce(sum(delivered_count),0) d,
             coalesce(sum(opened_count),0) o, coalesce(sum(clicked_count),0) c,
             coalesce(sum(unsubscribed_count),0) u'
        )->first();

        $delivered = (int) ($totals->d ?? 0);
        $emails = (int) ($totals->s ?? 0);

        return [
            'campaigns' => (clone $campaigns)->count(),
            'sent' => $emails,
            'delivery_rate' => $emails > 0 ? (int) round($delivered / $emails * 100) : 0,
            /* Opens and clicks over what was DELIVERED: an address that
               bounced was never given the chance to open anything. */
            'open_rate' => $delivered > 0 ? (int) round(((int) $totals->o) / $delivered * 100) : 0,
            'click_rate' => $delivered > 0 ? (int) round(((int) $totals->c) / $delivered * 100) : 0,
            'unsubscribed' => (int) ($totals->u ?? 0),
        ];
    }

    /** Appointments somebody actually turned up to. */
    private static function attended(Builder $booking): Builder
    {
        return $booking
            ->whereNotIn('status', ['cancelled', 'declined', 'no-show', 'draft'])
            ->whereDate('date', '<=', Carbon::today()->toDateString());
    }
}
