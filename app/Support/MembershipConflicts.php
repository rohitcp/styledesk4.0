<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What this client already holds, before they are sold another one.
 *
 * Not a refusal. A client may perfectly well hold two memberships — a monthly
 * plan and a massage package are different things, and even two of the same
 * package is a decision somebody is allowed to make. What this exists to stop
 * is the *silent* one: a second subscription created because nobody at the
 * desk knew about the first, and a client who finds out at the next billing
 * run.
 *
 * So it answers a question rather than blocking anything: what is already
 * standing, and does any of it overlap what is about to be sold. The desk
 * decides.
 */
class MembershipConflicts
{
    /**
     * Everything this client still holds, said in the terms the warning needs.
     *
     * "Still holds" is wider than active: a membership starting next week and
     * one paused for the winter are both things the desk should know about
     * before selling a second. Only what has actually ended or been cancelled
     * outright is left out — those are over, and warning about them would be
     * a dialog nobody could act on.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forClient(Client $client, ?MembershipPlan $plan = null, ?string $currency = null): array
    {
        return self::standing($client)
            ->map(fn (ClientMembership $held) => self::describe($held, $plan, $currency))
            ->values()
            ->all();
    }

    /** Whether there is anything at all to warn about. */
    public static function exist(Client $client): bool
    {
        return self::standingQuery($client)->exists();
    }

    /**
     * @return Collection<int, ClientMembership>
     */
    private static function standing(Client $client): Collection
    {
        return self::standingQuery($client)
            ->with(['plan', 'credits.service'])
            ->orderByDesc('starts_on')
            ->get()
            /* status() is worked out rather than stored — an end date that
               has passed makes a row "ended" whatever the column says — so
               the last word on what is still standing is taken from it. */
            ->filter(fn (ClientMembership $held) => ! in_array($held->status(), ['ended', 'cancelled'], true));
    }

    private static function standingQuery(Client $client): Builder
    {
        $today = Carbon::today()->toDateString();

        return ClientMembership::query()
            ->where('client_id', $client->id)
            ->whereIn('status', ['active', 'scheduled', 'paused'])
            ->where(fn (Builder $q) => $q
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $today));
    }

    /**
     * One held membership, in the terms the warning needs.
     *
     * A package and a subscription are worth different facts: what is left of
     * a package decides whether another one is wanted at all, and when a
     * subscription bills next decides whether a second is affordable. Each
     * gets the ones that help and none of the ones that do not.
     *
     * @return array<string, mixed>
     */
    private static function describe(ClientMembership $held, ?MembershipPlan $plan, ?string $currency): array
    {
        $money = $held->currency_code ?: Currencies::resolve($currency);

        $row = [
            'id' => $held->id,
            'name' => $held->plan?->name ?? '—',
            'type' => $held->type,
            'type_label' => __('membership.types.'.$held->type),
            'status' => $held->status(),
            'status_label' => $held->statusLabel(),
            'status_class' => $held->statusClass(),
            'starts_on' => $held->starts_on?->translatedFormat('j M Y'),
            'price' => Money::format($held->price_minor / 100, $money),
            /* The one fact that changes the wording: buying a second of the
               same plan is a different mistake from buying a second of
               something else. */
            'same_plan' => $plan !== null && (int) $held->membership_plan_id === (int) $plan->id,
        ];

        if ($held->isRecurring()) {
            return $row + [
                'billing_frequency' => $held->billing_frequency,
                'billing_label' => $held->billing_frequency === null
                    ? null
                    : __('membership.billing_frequencies.'.$held->billing_frequency),
                'next_billing_on' => $held->next_billing_on?->translatedFormat('j M Y'),
            ];
        }

        /* What is left of the package, and when it runs out. Zero remaining
           is worth saying too — it is the case where a second package is
           exactly the right answer. */
        $credits = $held->credits->reject(fn ($credit) => $credit->isExpired());

        return $row + [
            'remaining' => (int) $credits->sum(fn ($credit) => $credit->remaining()),
            'granted' => (int) $credits->sum('quantity_granted'),
            'expires_on' => $credits->filter(fn ($credit) => $credit->expires_on !== null)
                ->min('expires_on')?->translatedFormat('j M Y'),
        ];
    }
}
