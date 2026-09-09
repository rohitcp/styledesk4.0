<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClientMembership;
use App\Models\MembershipSettings;
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
