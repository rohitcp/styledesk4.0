<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientLoyaltyPoint;
use App\Models\LoyaltySettings;

/**
 * Points spent against a bill.
 *
 * One place decides how many points a client may put towards a booking,
 * because the question is asked twice and the two answers have to agree: the
 * quote endpoint asks it to show the desk a figure, and `store()` asks it
 * again to write one. A screen that offered £10 off and a booking that
 * recorded £5 is the kind of disagreement a client notices at the counter.
 *
 * Whole rewards only, which is the same rule the profile states: 500 points
 * is £5 and 900 points is still £5. The remainder stays on the balance rather
 * than being spent as change nobody agreed to.
 *
 * Nothing here writes. Redeeming is `LoyaltyPoints::redeem()`, inside the
 * booking's own transaction — this only ever answers "how much".
 */
class LoyaltyRedemption
{
    /**
     * The most this client could put towards a bill of this size.
     *
     * Four ceilings, and the lowest wins: what they have, what the business
     * lets anybody spend at once, what a whole number of rewards comes to,
     * and the bill itself. The last is the one that is easy to forget and the
     * only one that would have the salon paying the client to attend.
     */
    public static function maxPointsFor(?Client $client, int $payableMinor, ?LoyaltySettings $settings = null): int
    {
        if ($client === null || $payableMinor <= 0) {
            return 0;
        }

        $settings ??= LoyaltySettings::forTenant($client->tenant);

        if (! self::isOpenTo($client, $settings)) {
            return 0;
        }

        $balance = LoyaltyPoints::balanceFor($client);
        $required = max(1, (int) $settings->points_required);
        $value = max(1, (int) $settings->reward_value_minor);

        /* How many whole rewards the bill itself can absorb, and how many the
           balance can pay for. Counted in rewards rather than points because
           a reward is the unit the business set — points between two of them
           buy nothing and must not be taken. */
        $affordable = intdiv($balance, $required);
        $useful = intdiv($payableMinor, $value);

        $rewards = min($affordable, $useful);

        if ($settings->maximum_reward_minor !== null) {
            $rewards = min($rewards, intdiv((int) $settings->maximum_reward_minor, $value));
        }

        $points = $rewards * $required;

        /* Below the floor the business set is not a redemption at all. A
           minimum of 500 against a balance of 400 means nothing to spend,
           rather than 400 spent. */
        return $points >= (int) $settings->minimum_redemption ? $points : 0;
    }

    /**
     * What a number of points actually takes off this bill.
     *
     * Clamped to the bill as well as to the rate: a request for more than the
     * client has, or more than the appointment costs, is answered with what
     * they may legitimately spend rather than refused. The screen is asking
     * on somebody's behalf, and a till that errored at a receptionist typing
     * a round number would be a till they stop using.
     *
     * `capped` says the answer is smaller than what was asked for. The caller
     * needs it because silently giving somebody less than they typed is the
     * one outcome they cannot see: the total moves by an amount they did not
     * choose, and nothing on the screen says why.
     *
     * @return array{points: int, minor: int, capped: bool, max_points: int, max_minor: int}
     */
    public static function resolve(
        ?Client $client,
        int $requestedPoints,
        int $payableMinor,
        ?LoyaltySettings $settings = null,
    ): array {
        $none = ['points' => 0, 'minor' => 0, 'capped' => false, 'max_points' => 0, 'max_minor' => 0];

        if ($client === null || $requestedPoints <= 0) {
            return $none;
        }

        $settings ??= LoyaltySettings::forTenant($client->tenant);

        $ceiling = self::maxPointsFor($client, $payableMinor, $settings);
        $ceilingMinor = $ceiling > 0 ? $settings->rewardMinorFor($ceiling) : 0;

        if ($ceiling <= 0) {
            /* Nothing may be spent here at all, which is still worth saying
               when somebody asked: the box they typed into is about to come
               back empty. */
            /* array_merge, not `+`: the union operator keeps the LEFT side on
               a duplicate key, so `capped` would stay false here and the
               screen would silently show nothing applied. */
            return array_merge($none, ['capped' => $requestedPoints > 0]);
        }

        $required = max(1, (int) $settings->points_required);

        /* Down to the whole reward below what was asked for, then down again
           to the ceiling. Rounding down twice rather than once because the
           request and the ceiling are both arbitrary numbers and only the
           multiple of a reward is spendable. */
        $points = min(intdiv(max(0, $requestedPoints), $required) * $required, $ceiling);

        /* Asked for more than the ceiling allows. Measured against the
           ceiling rather than against the rounding, so asking for 900 where
           1,000 is allowed is not reported as having been cut short — it was
           rounded to a whole reward, which is the rule rather than a limit. */
        $capped = $requestedPoints > $ceiling;

        return [
            'points' => $points,
            'minor' => $points > 0 ? $settings->rewardMinorFor($points) : 0,
            'capped' => $capped,
            'max_points' => $ceiling,
            'max_minor' => $ceilingMinor,
        ];
    }

    /**
     * Whether this client may spend points at all.
     *
     * A paused or suspended membership keeps its balance and stops spending
     * it: that is the difference between pausing somebody and unenrolling
     * them, and a till that ignored it would make the setting meaningless.
     *
     * A client who never formally joined is deliberately allowed. They have a
     * balance because they have been coming here, and refusing to let them
     * spend it would be a rule invented by the enrolment screen and applied
     * retrospectively to everybody who predates it.
     */
    public static function isOpenTo(Client $client, LoyaltySettings $settings): bool
    {
        if (! $settings->is_enabled) {
            return false;
        }

        return ! $client->isEnrolledInLoyalty() || $client->loyaltyIsActive();
    }

    /**
     * Whether this booking has already had its points taken.
     *
     * The guard against redeeming twice. `store()` can be reached more than
     * once for one booking — a retried request, a confirm pressed twice — and
     * points are the one thing here that cannot be reconciled back, because
     * nothing says which of two identical deductions was the duplicate.
     */
    public static function alreadyRedeemed(Booking $booking): bool
    {
        return ClientLoyaltyPoint::query()
            ->where('booking_id', $booking->id)
            ->where('type', 'redeemed')
            ->exists();
    }
}
