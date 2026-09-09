<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MembershipSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Membership.
 *
 * The terms every membership this business sells is sold on: where one may be
 * bought, when it starts working, what happens to a credit nobody used, and
 * what cancelling means. What a particular membership costs and includes is
 * not decided here — that is a plan, and plans are built under Clients.
 *
 * Switching membership off stops the selling and hides the module. It erases
 * nothing: existing members keep their plans, their credits and their history,
 * because a business that paused sales has not told the people who already
 * paid that their month is void.
 */
class MembershipSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->permit($request, 'membership.view_settings');

        return view('settings.membership.index', [
            'settings' => MembershipSettings::forTenant($request->user()->tenant),
            /* Every channel including the ones nothing can sell through yet.
               The screen renders those disabled and labelled, so it tells the
               truth about what is planned rather than offering a switch that
               would sell nothing. */
            'channels' => config('membership.channels'),
            'activations' => MembershipSettings::activations(),
            'creditExpiries' => MembershipSettings::creditExpiries(),
            'cancellations' => MembershipSettings::cancellationTimings(),
            /* Whether this reader may change any of it. The settings group
               lets them look; deciding what a client is committing to is its
               own authority. */
            'canManage' => (bool) $request->user()?->hasPermission('membership.manage_settings', 'own'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->permit($request, 'membership.manage_settings');

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],

            /* Only a channel this business can actually sell through.
               Accepting "online" would leave a business believing its website
               sells memberships, and nothing would ever be sold. */
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(MembershipSettings::availableChannels())],

            'allow_staff_to_sell' => ['nullable', 'boolean'],
            'allow_start_date_selection' => ['nullable', 'boolean'],
            'default_activation' => ['required', Rule::in(MembershipSettings::activations())],

            /* The master switch above every other credit question. */
            'credits_enabled' => ['nullable', 'boolean'],

            'reset_credits_on_cycle' => ['nullable', 'boolean'],
            'allow_rollover' => ['nullable', 'boolean'],
            /* Null is "as many as they accrue", which is different from a cap
               of zero — that would be rollover switched on and doing nothing. */
            'maximum_rollover' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'credit_expiry' => ['required', Rule::in(MembershipSettings::creditExpiries())],
            'allow_credits_across_locations' => ['nullable', 'boolean'],
            'allow_service_substitution' => ['nullable', 'boolean'],

            'allow_cancellation' => ['nullable', 'boolean'],
            'allow_pause' => ['nullable', 'boolean'],
            /* Two years and ninety days are the outer edges of a term a salon
               can defend. Beyond them it is not a membership, it is a loan. */
            'minimum_commitment_months' => ['required', 'integer', 'min:0', 'max:24'],
            'cancellation_notice_days' => ['required', 'integer', 'min:0', 'max:90'],
            'cancellation_effective' => ['required', Rule::in(MembershipSettings::cancellationTimings())],
        ]);

        $channels = $data['channels'] ?? [];
        $rollsOver = (bool) ($data['allow_rollover'] ?? false);

        MembershipSettings::updateOrCreate(
            ['tenant_id' => $request->user()->tenant->getTenantKey()],
            [
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),

                'allow_purchase_in_store' => in_array('in_store', $channels, true),
                'allow_purchase_online' => in_array('online', $channels, true),
                'allow_staff_to_sell' => (bool) ($data['allow_staff_to_sell'] ?? false),
                'allow_start_date_selection' => (bool) ($data['allow_start_date_selection'] ?? false),
                'default_activation' => $data['default_activation'],

                'credits_enabled' => (bool) ($data['credits_enabled'] ?? false),

                /* Rollover and reset are one question asked from both ends.
                   Storing them independently is how a business ends up with
                   credits that both survive the cycle and are wiped by it —
                   so the answer is taken once, from rollover, and reset is
                   its opposite. */
                'allow_rollover' => $rollsOver,
                'reset_credits_on_cycle' => ! $rollsOver,
                /* A cap on a rollover that does not happen is a number that
                   reappears, unexplained, the day somebody turns rollover on. */
                'maximum_rollover' => $rollsOver ? ($data['maximum_rollover'] ?? null) : null,

                'credit_expiry' => $data['credit_expiry'],
                'allow_credits_across_locations' => (bool) ($data['allow_credits_across_locations'] ?? false),
                'allow_service_substitution' => (bool) ($data['allow_service_substitution'] ?? false),

                'allow_cancellation' => (bool) ($data['allow_cancellation'] ?? false),
                'allow_pause' => (bool) ($data['allow_pause'] ?? false),
                'minimum_commitment_months' => (int) $data['minimum_commitment_months'],
                'cancellation_notice_days' => (int) $data['cancellation_notice_days'],
                'cancellation_effective' => $data['cancellation_effective'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('membership.settings.saved'),
        ]);
    }

    /**
     * Reading the terms and setting them are two authorities, on top of the
     * settings group's own. Somebody who may configure the salon is not
     * automatically somebody who decides what its clients are committing to.
     */
    private function permit(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
