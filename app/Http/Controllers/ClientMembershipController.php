<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClientMembership;
use App\Models\MembershipSettings;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Ending, pausing and restarting a membership somebody holds.
 *
 * Three acts on one row, and every one of them is a promise about money the
 * client has already agreed to. The terms they are checked against are the
 * business's own, set once in App Settings — a commitment period, a notice
 * period, and whether cancelling takes effect now or at the end of the cycle.
 *
 * None of these delete anything. A membership that ended is a thing that
 * happened: its credits, its payments and its dates stay readable, because a
 * client asking "what did I pay for last year" is asking a question the desk
 * has to be able to answer.
 */
class ClientMembershipController extends Controller
{
    /**
     * One membership, in the drawer the bookings use.
     *
     * The same renderer and the same panel: a receptionist checking what a
     * client holds while somebody is on hold should not lose the profile,
     * the tab or their place — and a second panel that described a
     * membership its own way would be a second thing to trust.
     *
     * Everything here is read-only. Ending or holding a membership is its own
     * act with its own confirmation; this only answers "what have they got".
     */
    public function drawer(Request $request, ClientMembership $membership): JsonResponse
    {
        $this->allow($request);

        $membership->load(['plan', 'client', 'credits.service', 'payments', 'paymentMethod']);

        $settings = MembershipSettings::forTenant($request->user()->tenant);
        $money = fn (?int $minor) => Money::format((int) $minor / 100, $membership->currency_code);

        return response()->json([
            'name' => $membership->plan?->name ?? '—',
            /* The membership's own number, not the plan's code: the panel is
               about the thing this client holds. */
            'reference' => $membership->reference ?? '',
            'status' => $membership->statusLabel(),
            'status_class' => $membership->statusClass(),
            'sections' => array_values(array_filter([
                $this->detailSection($membership),
                $this->moneySection($membership, $money),
                $this->creditsSection($membership),
                $this->cycleSection($membership, $settings),
            ])),
            /* What the footer button says. The panel is shared with the
               bookings, and "View full booking" under a membership is the
               wrong sentence. */
            'cta' => __('membership.member.drawer.view_plan'),
            'urls' => ['show' => route('membership.show', $membership->plan)],
        ]);
    }

    /**
     * What it is and where it stands.
     *
     * @return array<string, mixed>
     */
    private function detailSection(ClientMembership $membership): array
    {
        $rows = [
            __('membership.member.drawer.type') => __('membership.types.'.$membership->type),
            __('membership.member.drawer.status') => $membership->statusLabel(),
            __('membership.member.drawer.purchased') => $membership->created_at?->translatedFormat('j M Y') ?? '—',
            __('membership.member.drawer.started') => $membership->starts_on->translatedFormat('j M Y'),
        ];

        if ($membership->reference) {
            $rows[__('membership.member.drawer.number')] = $membership->reference;
        }

        /* The plan's code as well: one identifies what was sold, the other
           identifies the sale, and a desk reconciling either has the one it
           is holding. */
        if ($membership->plan?->internal_code) {
            $rows[__('membership.form.internal_code')] = $membership->plan->internal_code;
        }

        /* Either the day it stops or the day it bills again, never both: a
           membership that is ending has no next billing date. */
        if ($membership->ends_on) {
            $rows[__('membership.member.drawer.ends')] = $membership->ends_on->translatedFormat('j M Y');
        } elseif ($membership->isRecurring()) {
            $rows[__('membership.member.drawer.billing')] = $membership->billing_frequency === null
                ? '—'
                : __('membership.billing_frequencies.'.$membership->billing_frequency);
            $rows[__('membership.member.next_billing')] = $membership->next_billing_on?->translatedFormat('j M Y')
                ?? __('membership.member.no_billing');
        }

        return ['title' => __('membership.member.drawer.membership'), 'rows' => $rows];
    }

    /**
     * What it cost, and what has actually been taken.
     *
     * @return array<string, mixed>
     */
    private function moneySection(ClientMembership $membership, callable $money): array
    {
        $paid = $membership->paidMinor();
        $due = $membership->dueTodayMinor();

        $rows = [
            __('membership.member.drawer.price') => $money($membership->price_minor).' · '.$membership->currency_code,
            __('membership.member.drawer.paid') => $money($paid),
            __('membership.member.drawer.payment_status') => $paid >= $due
                ? __('membership.member.drawer.paid_in_full')
                : __('membership.member.drawer.part_paid'),
        ];

        /* How it was taken, where anything was. The methods are named the
           same way the booking screens name them. */
        $methods = $membership->payments
            ->map(fn ($payment) => __('bookings.methods.'.$payment->method.'.name'))
            ->unique()
            ->join(', ');

        if ($methods !== '') {
            $rows[__('membership.member.drawer.method')] = $methods;
        }

        /* The card renewals will reach for. Only for something that renews —
           a package has nothing to charge again. */
        if ($membership->isRecurring() && $membership->paymentMethod) {
            $rows[__('membership.member.drawer.card')] = $membership->paymentMethod->label();
        }

        return ['title' => __('membership.member.drawer.payment'), 'rows' => $rows];
    }

    /**
     * What is included, and what is left of it.
     *
     * Three numbers per service rather than one: "2 left" answers today's
     * question, and "4 included, 2 used" answers the one the client asks
     * next.
     *
     * @return array<string, mixed>|null
     */
    private function creditsSection(ClientMembership $membership): ?array
    {
        if ($membership->credits->isEmpty()) {
            return null;
        }

        $rows = [];

        foreach ($membership->credits as $credit) {
            $rows[$credit->service?->name ?? '—'] = __('membership.member.drawer.credit_line', [
                'included' => (int) $credit->quantity_granted,
                'used' => (int) $credit->quantity_used,
                'remaining' => $credit->remaining(),
            ]);
        }

        $rows[__('membership.member.drawer.total')] = __('membership.member.drawer.credit_line', [
            'included' => (int) $membership->credits->sum('quantity_granted'),
            'used' => (int) $membership->credits->sum('quantity_used'),
            'remaining' => (int) $membership->credits->sum(fn ($credit) => $credit->remaining()),
        ]);

        return ['title' => __('membership.member.drawer.included'), 'rows' => $rows];
    }

    /**
     * When this lot of credits runs out, and what happens to what is left.
     *
     * A package expires; a subscription's credits are reissued. They are
     * different facts and only one of them applies.
     *
     * @return array<string, mixed>|null
     */
    private function cycleSection(ClientMembership $membership, MembershipSettings $settings): ?array
    {
        $credit = $membership->credits->first();

        if ($credit === null) {
            return null;
        }

        $rules = $membership->plan?->creditRules($settings) ?? [];
        $rows = [];

        if ($credit->period_start && $credit->period_end) {
            $rows[__('membership.member.drawer.cycle')] = $credit->period_start->translatedFormat('j M')
                .' – '.$credit->period_end->translatedFormat('j M Y');
        }

        if ($membership->isRecurring() && $membership->next_billing_on) {
            $rows[__('membership.member.drawer.renews')] = $membership->next_billing_on->translatedFormat('j M Y');
        }

        if ($credit->expires_on) {
            $rows[__('membership.member.drawer.expires')] = $credit->expires_on->translatedFormat('j M Y');
        }

        $rows[__('membership.member.drawer.rollover')] = ($rules['rollover'] ?? false)
            ? __('common.yes')
            : __('common.no');

        return $rows === [] ? null : ['title' => __('membership.member.drawer.cycle_title'), 'rows' => $rows];
    }

    /**
     * End it.
     *
     * The date is worked out here rather than asked for. Which cycle a
     * cancellation lands in is the business's standing decision and not a
     * choice the desk makes per client — a receptionist who could pick the
     * date is one who can be talked into picking today.
     */
    public function cancel(Request $request, ClientMembership $membership): RedirectResponse
    {
        $this->allow($request);

        $settings = $this->settings($request);

        abort_unless($settings->allow_cancellation, 403);

        if ($membership->isCancelled()) {
            throw ValidationException::withMessages([
                'membership' => __('membership.member.already_cancelled'),
            ]);
        }

        /* A commitment the client agreed to is a commitment. Refused rather
           than warned about: the desk cannot wave it, and a screen that let
           them try and then said no is a screen that wasted the
           conversation. */
        $commitmentEnds = $membership->commitmentEndsOn($settings);

        if ($commitmentEnds !== null && $commitmentEnds->isFuture()) {
            throw ValidationException::withMessages([
                'membership' => __('membership.member.in_commitment', [
                    'date' => $commitmentEnds->translatedFormat('j F Y'),
                ]),
            ]);
        }

        $endsOn = $membership->cancellationTakesEffect($settings);

        $membership->forceFill([
            'cancelled_at' => now(),
            'ends_on' => $endsOn->toDateString(),
            /* It will not renew. Cleared rather than left pointing at a
               charge nobody is going to take. */
            'next_billing_on' => null,
            /* Only where it stops today. One ending at the end of the cycle
               is still running, and a column saying "cancelled" would take
               the client's remaining credits with it. */
            'status' => $endsOn->isToday() || $endsOn->isPast() ? 'cancelled' : $membership->status,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $endsOn->isFuture()
                ? __('membership.member.cancelled_on', ['date' => $endsOn->translatedFormat('j F Y')])
                : __('membership.member.cancelled_now'),
        ]);
    }

    /**
     * Hold it, without losing it.
     *
     * Billing stops and the credits stop being spendable, which is the whole
     * bargain: a client who is not paying this month is not having this
     * month's massage either.
     */
    public function pause(Request $request, ClientMembership $membership): RedirectResponse
    {
        $this->allow($request);

        abort_unless($this->settings($request)->allow_pause, 403);

        if (! $membership->isLive()) {
            throw ValidationException::withMessages([
                'membership' => __('membership.member.cannot_pause'),
            ]);
        }

        $membership->forceFill([
            'status' => 'paused',
            'paused_at' => now(),
            /* Nothing is taken while it is held. The date is worked out
               again when it restarts, from the day it restarts. */
            'next_billing_on' => null,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('membership.member.paused'),
        ]);
    }

    /**
     * Start it again.
     *
     * The next payment is a cycle from today rather than from wherever it
     * had got to: the client did not pay for the months they were paused, so
     * charging them as though they had is the wrong direction of generous.
     */
    public function resume(Request $request, ClientMembership $membership): RedirectResponse
    {
        $this->allow($request);

        if (! $membership->isPaused()) {
            throw ValidationException::withMessages([
                'membership' => __('membership.member.not_paused'),
            ]);
        }

        $membership->forceFill([
            'status' => 'active',
            'paused_at' => null,
            'next_billing_on' => $membership->billingDateAfter(Carbon::today())?->toDateString(),
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('membership.member.resumed'),
        ]);
    }

    private function settings(Request $request): MembershipSettings
    {
        return MembershipSettings::forTenant($request->user()->tenant);
    }

    /**
     * Who may end or hold somebody's membership.
     *
     * Its own permission rather than `clients.edit`: correcting a phone
     * number and stopping a subscription somebody is paying for are not the
     * same authority, and the second one costs the business money either way
     * it goes wrong.
     */
    private function allow(Request $request): void
    {
        abort_unless($this->settings($request)->is_enabled, 404);
        abort_unless($request->user()?->hasPermission('membership.manage_members', 'own'), 403);
    }
}
