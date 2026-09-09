<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipCredit;
use App\Models\MembershipCreditRedemption;
use App\Models\MembershipPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What one client's memberships come to, for the tab on their profile.
 *
 * Read here rather than in the view so the tab, the tab strip and the
 * permission that decides whether either exists all read one answer — the
 * same arrangement the rewards tab uses.
 *
 * The history is assembled rather than stored. Three tables already say what
 * happened — money taken, credits spent, and the dates on the membership
 * itself — and a fourth that repeated them would be the one that drifts.
 */
class ClientMemberships
{
    /**
     * Everything this client holds or has held, the live ones first.
     *
     * Not only the active one: "what did I pay for last year" is a question
     * the desk has to answer, and a list that hid everything finished would
     * make the profile look like the client had never been a member.
     *
     * @return Collection<int, ClientMembership>
     */
    public static function held(Client $client): Collection
    {
        return ClientMembership::query()
            ->where('client_id', $client->id)
            ->with(['plan', 'credits.service', 'location'])
            ->get()
            /* Live first, then by when they started. Sorted here rather than
               in SQL because "live" is worked out from dates as well as the
               column — see ClientMembership::status(). */
            ->sortBy(fn (ClientMembership $membership) => [
                $membership->isLive() ? 0 : 1,
                -$membership->starts_on->timestamp,
            ])
            ->values();
    }

    /**
     * What this client can spend today, across every membership they hold.
     *
     * Summed per service rather than per membership: somebody with a massage
     * package and a monthly membership has two sources for one service, and
     * the desk asks "how many massages have they got", not "how many from
     * which".
     *
     * @return Collection<int, array{service: string, remaining: int, expires_on: ?string}>
     */
    public static function creditsFor(Client $client): Collection
    {
        return MembershipCredit::query()
            ->spendable()
            ->whereIn('client_membership_id', ClientMembership::query()
                ->where('client_id', $client->id)
                ->live()
                ->select('id'))
            ->with('service')
            ->get()
            ->groupBy('service_id')
            ->map(fn (Collection $credits) => [
                'service' => $credits->first()->service?->name ?? '—',
                'remaining' => (int) $credits->sum(fn (MembershipCredit $credit) => $credit->remaining()),
                /* The soonest deadline among them, because that is the one
                   the client needs telling about. */
                'expires_on' => $credits
                    ->pluck('expires_on')
                    ->filter()
                    ->sort()
                    ->first()?->toDateString(),
            ])
            ->sortBy('service')
            ->values();
    }

    /**
     * Everything that has happened to this client's memberships, newest first.
     *
     * Three sources, one list. A receptionist reading this is asking what
     * happened to this person, not what happened in which table — so a
     * payment, a credit spent and a cancellation sit in one column of dates.
     *
     * @return Collection<int, array{at: Carbon, type: string, title: string, detail: ?string, amount: ?string}>
     */
    public static function historyFor(Client $client): Collection
    {
        $memberships = ClientMembership::query()
            ->where('client_id', $client->id)
            ->with('plan')
            ->get();

        if ($memberships->isEmpty()) {
            return collect();
        }

        $ids = $memberships->pluck('id');
        $names = $memberships->mapWithKeys(fn (ClientMembership $m) => [$m->id => $m->plan?->name ?? '']);

        $entries = collect();

        /* Bought, and every renewal since. */
        MembershipPayment::query()
            ->whereIn('client_membership_id', $ids)
            ->get()
            ->each(function (MembershipPayment $payment) use ($entries, $names) {
                $entries->push([
                    'at' => $payment->paid_at ?? $payment->created_at,
                    'type' => 'payment',
                    'title' => __('membership.member.history.payment'),
                    'detail' => $names[$payment->client_membership_id] ?? null,
                    'amount' => Money::format($payment->amount_minor / 100, $payment->currency_code),
                ]);
            });

        /* Every credit spent, and every one handed back. */
        MembershipCreditRedemption::query()
            ->whereIn('client_membership_id', $ids)
            ->with('service')
            ->get()
            ->each(function (MembershipCreditRedemption $redemption) use ($entries) {
                $entries->push([
                    'at' => $redemption->created_at,
                    'type' => 'redeemed',
                    'title' => __('membership.member.history.redeemed'),
                    'detail' => $redemption->service?->name,
                    'amount' => null,
                ]);

                if ($redemption->released_at !== null) {
                    $entries->push([
                        'at' => $redemption->released_at,
                        'type' => 'released',
                        'title' => __('membership.member.history.released'),
                        'detail' => $redemption->service?->name,
                        'amount' => null,
                    ]);
                }
            });

        /* And what was done to the membership itself. Read off its own dates
           rather than from a log: the columns are the record, and a second
           one would be the copy that disagrees. */
        $memberships->each(function (ClientMembership $membership) use ($entries) {
            $entries->push([
                'at' => $membership->created_at,
                'type' => 'started',
                'title' => __('membership.member.history.started'),
                'detail' => $membership->plan?->name,
                'amount' => null,
            ]);

            if ($membership->paused_at !== null) {
                $entries->push([
                    'at' => $membership->paused_at,
                    'type' => 'paused',
                    'title' => __('membership.member.history.paused'),
                    'detail' => $membership->plan?->name,
                    'amount' => null,
                ]);
            }

            if ($membership->cancelled_at !== null) {
                $entries->push([
                    'at' => $membership->cancelled_at,
                    'type' => 'cancelled',
                    'title' => __('membership.member.history.cancelled'),
                    'detail' => $membership->ends_on === null
                        ? $membership->plan?->name
                        : __('membership.member.history.ends_on', [
                            'date' => $membership->ends_on->translatedFormat('j M Y'),
                        ]),
                    'amount' => null,
                ]);
            }
        });

        return $entries
            ->filter(fn (array $entry) => $entry['at'] !== null)
            ->sortByDesc(fn (array $entry) => $entry['at']->timestamp)
            ->values();
    }
}
