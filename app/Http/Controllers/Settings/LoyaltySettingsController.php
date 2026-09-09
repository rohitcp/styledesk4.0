<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\LoyaltySettings;
use App\Support\Currencies;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Loyalty & Rewards.
 *
 * Two rules and their boundaries: what a pound spent is worth in points, and
 * what a point is worth back. Everything else on this screen is a limit on one
 * of the two — which purchases count, how few points may be spent at once, how
 * much may be spent at once, and how long a point lives.
 *
 * Switching the scheme off stops the earning and the redeeming and erases
 * nothing. A salon that pauses rewards for a difficult quarter comes back to
 * find its rate, its rule and every client's balance exactly as they left
 * them: clients were never told their points were forfeited, and a settings
 * screen must not be the thing that tells them.
 */
class LoyaltySettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->permit($request, 'loyalty.view_settings');

        $settings = LoyaltySettings::forTenant($request->user()->tenant);

        return view('settings.loyalty.index', [
            'settings' => $settings,
            /* Every purchase type including the ones nothing can sell yet.
               The screen renders those disabled and labelled, so it tells the
               truth about what is planned rather than offering a switch that
               would silently award nothing. */
            'purchases' => config('loyalty.purchases'),
            'expiries' => LoyaltySettings::expiries(),
            'notifications' => config('loyalty.notifications'),
            'currency' => Currencies::resolve(),
            'symbol' => Money::symbol(),
            /* Whether this reader may change any of it. The settings group
               lets them look; changing how the business rewards its clients
               is its own authority. */
            'canManage' => (bool) $request->user()?->hasPermission('loyalty.manage_settings', 'own'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->permit($request, 'loyalty.manage_settings');

        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'program_name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],

            /* At least one of each, because zero is not a rule. "$0 spent
               earns 1 point" is an infinite loop wearing a settings form. */
            'spend_amount' => ['required', 'integer', 'min:1', 'max:100000'],
            'points_earned' => ['required', 'integer', 'min:1', 'max:100000'],

            /* Only a purchase type this business can actually sell. Accepting
               "gift_cards" would leave a business believing gift cards earn,
               and nothing would ever award for one. */
            'eligible_purchases' => ['nullable', 'array'],
            'eligible_purchases.*' => [Rule::in(LoyaltySettings::availablePurchases())],

            'points_required' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reward_value' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'minimum_redemption' => ['required', 'integer', 'min:0', 'max:1000000'],
            'maximum_reward' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],

            'expiry' => ['required', Rule::in(LoyaltySettings::expiries())],
        ]);

        LoyaltySettings::updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            [
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'program_name' => $data['program_name'],
                'description' => $data['description'] ?? null,
                'spend_amount' => (int) $data['spend_amount'],
                'points_earned' => (int) $data['points_earned'],
                /* Services always earn. It is the only thing this business
                   sells, and a scheme where nothing earns is a scheme that is
                   switched on and does nothing — which reads as a bug rather
                   than as a choice. */
                'eligible_purchases' => array_values(array_unique(
                    array_merge(['services'], $data['eligible_purchases'] ?? []),
                )),
                'points_required' => (int) $data['points_required'],
                'reward_value_minor' => (int) round(((float) $data['reward_value']) * 100),
                'minimum_redemption' => (int) $data['minimum_redemption'],
                'maximum_reward_minor' => isset($data['maximum_reward'])
                    ? (int) round(((float) $data['maximum_reward']) * 100)
                    : null,
                'expiry' => $data['expiry'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('loyalty.settings.saved'),
        ]);
    }

    /**
     * Reading how the scheme works and changing it are two authorities, on
     * top of the settings group's own. Somebody who may configure the salon
     * is not automatically somebody who decides what its clients' points are
     * worth.
     */
    private function permit(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
