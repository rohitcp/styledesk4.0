<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TenantStripeAccount;
use App\Payments\PaymentFailed;
use App\Payments\StripeAccountKeys;
use App\Payments\StripeConnect;
use App\Payments\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Connecting, managing and disconnecting a business's Stripe account.
 *
 * Three steps and no jargon on any of them: press Connect, answer Stripe, come
 * back to a card showing the account. The words API key, capability and
 * webhook appear nowhere the owner can see them.
 */
class StripeConnectController extends Controller
{
    /**
     * Resolved after the guard, not injected.
     *
     * Constructor injection builds the Stripe client — which refuses an empty
     * key by throwing — before `allow()` can answer 404, so a deployment with
     * no Stripe secret got a 500 where it should have got "no such page".
     */
    private function stripe(): StripeConnect
    {
        return app(StripeConnect::class);
    }

    /** Off to Stripe to create or finish the account. */
    public function connect(Request $request): RedirectResponse
    {
        $this->allow($request);

        $tenant = $request->user()->tenant;

        try {
            $account = $this->stripe()->account($tenant, $request->user()->id);

            $url = $this->stripe()->onboardingUrl(
                $account,
                returnUrl: route('settings.payments.stripe.return'),
                /* Stripe sends the owner here if the link went stale before
                   they finished. Pointing it back at connect starts a new one
                   rather than showing them an expired page. */
                refreshUrl: route('settings.payments.stripe.connect'),
            );
        } catch (PaymentFailed $e) {
            return $this->failed($e->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Back from Stripe.
     *
     * Returning does not mean finished — Stripe sends the owner back whether
     * they completed the form or abandoned it, so the account is asked rather
     * than assumed. That is why this syncs instead of celebrating.
     */
    public function return(Request $request): RedirectResponse
    {
        $this->allow($request);

        $account = StripeGateway::accountFor($request->user()->tenant);

        if ($account === null) {
            return $this->failed(__('payments.stripe.no_account'));
        }

        try {
            $account = $this->stripe()->sync($account);
        } catch (PaymentFailed $e) {
            return $this->failed($e->getMessage());
        }

        /* Connecting is choosing. An owner who has just connected Stripe and
           finds StyleDesk still only recording payments would call that
           broken. */
        if ($account->canCharge()) {
            $request->user()->tenant->forceFill(['payment_gateway' => 'stripe'])->save();
        }

        return redirect()->route('settings.payments.show')->with(
            'status',
            $account->canCharge()
                ? __('payments.stripe.connected')
                : __('payments.stripe.still_needed'),
        );
    }

    /** Stripe's own dashboard: payouts, bank details, disputes. */
    public function dashboard(Request $request): RedirectResponse
    {
        $this->allow($request);

        $account = StripeGateway::accountFor($request->user()->tenant);

        if ($account === null) {
            return $this->failed(__('payments.stripe.no_account'));
        }

        try {
            return redirect()->away($this->stripe()->dashboardUrl($account));
        } catch (PaymentFailed $e) {
            return $this->failed($e->getMessage());
        }
    }

    /**
     * Stop taking card payments through Stripe.
     *
     * The row goes; the Stripe account does not. StyleDesk did not create the
     * business's relationship with Stripe and has no business closing it —
     * and the salon's own money, payouts and history stay where they are.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $this->allow($request);

        $tenant = $request->user()->tenant;

        StripeGateway::accountFor($tenant)?->delete();

        /* Fall back rather than leave the business pointed at a processor it
           no longer has: disconnecting must not stop the desk taking cash. */
        if ($tenant->payment_gateway === 'stripe') {
            $tenant->forceFill(['payment_gateway' => null])->save();
        }

        return redirect()->route('settings.payments.show')
            ->with('status', __('payments.stripe.disconnected'));
    }

    /**
     * The business's own Stripe account, on its own key.
     *
     * For a salon that already has Stripe and would rather keep StyleDesk out
     * of the arrangement entirely: the key is theirs, the account is theirs,
     * and StyleDesk simply uses it.
     *
     * The key is verified before it is trusted. Saving one that does not work
     * would leave a business believing it can take payments until the first
     * client tries.
     */
    public function saveKeys(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payments.process', 'own'), 403);

        $data = $request->validate([
            /* Shape-checked before it is sent anywhere: a publishable key
               pasted into the secret box is the likeliest mistake, and it
               would otherwise be stored and fail at the till. */
            'api_key' => ['required', 'string', 'starts_with:sk_,rk_', 'max:255'],
            'publishable_key' => ['nullable', 'string', 'starts_with:pk_', 'max:255'],
        ]);

        $tenant = $request->user()->tenant;

        try {
            /* Asked what the key can do, rather than assumed. Stripe's own
               answer is also what fills in the business name and payout
               account on the card. */
            $account = app(StripeAccountKeys::class)->verify($data['api_key']);
        } catch (PaymentFailed $e) {
            return back()->withErrors(['api_key' => __('payments.stripe.key_rejected', ['reason' => $e->getMessage()])]);
        }

        $stored = TenantStripeAccount::updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            [
                'mode' => TenantStripeAccount::MODE_OWN,
                /* Their account, not one under StyleDesk's platform. */
                'stripe_account_id' => data_get($account, 'id'),
                'api_key' => $data['api_key'],
                'publishable_key' => $data['publishable_key'] ?? null,
                'connected_by' => $request->user()->id,
                'connected_at' => now(),
            ],
        );

        app(StripeConnect::class)->apply($stored, $account);

        if ($stored->fresh()->canCharge()) {
            $tenant->forceFill(['payment_gateway' => 'stripe'])->save();
        }

        return redirect()->route('settings.payments.show')
            ->with('status', __('payments.stripe.keys_saved'));
    }

    private function failed(string $reason): RedirectResponse
    {
        return redirect()->route('settings.payments.show')
            ->withErrors(['stripe' => __('payments.stripe.failed', ['reason' => $reason])]);
    }

    private function allow(Request $request): void
    {
        /* Onboarding under the platform needs the platform's own key. A
           deployment without one still offers the bring-your-own route, which
           does not come through here. */
        abort_unless(filled(config('services.stripe.secret')), 404);
        abort_unless($request->user()?->hasPermission('payments.process', 'own'), 403);
    }
}
