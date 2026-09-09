<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use App\Payments\StripeGateway;
use App\Support\PaymentCapabilities;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Payments.
 *
 * Three questions, in the order a business answers them: are we taking
 * payments at all, who processes them, and what will we accept.
 *
 * The screen never names a processor's API. It shows which are available on
 * this deployment and which this business has connected — everything else is
 * App\Payments' business, which is what lets Square arrive later without this
 * page changing.
 */
class PaymentSettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('settings.payments.edit', $this->state($request->user()->tenant));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $methods = array_keys(config('payments.methods'));

        $data = $request->validate([
            'payments_enabled' => ['required', 'boolean'],

            /* Only a gateway this deployment can actually use. Choosing one
               that is not built would leave the business switched on and
               unable to take anything. */
            'payment_gateway' => ['nullable', Rule::in($this->availableGateways())],

            'accepted_methods' => ['nullable', 'array'],
            'accepted_methods.*' => [Rule::in($methods)],

            /* Only a capability that exists and is built. Accepting
               "tap_to_pay" would leave a business believing its readers work
               and nothing would ever take a payment on one. */
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => [Rule::in(PaymentCapabilities::selectable())],

            'default_deposit_type' => ['nullable', Rule::in(['none', 'fixed', 'percent'])],
            'default_deposit_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $type = $data['default_deposit_type'] ?? 'none';

        $tenant->forceFill([
            'payments_enabled' => (bool) $data['payments_enabled'],
            'payment_gateway' => $data['payment_gateway'] ?? null,
            /* An empty list is stored as null, which means "whatever the
               gateway can take". Storing [] would switch the till off, and
               nobody unticking every box means that. */
            'accepted_methods' => filled($data['accepted_methods'] ?? []) ? array_values($data['accepted_methods']) : null,
            /* An empty list here IS an answer, unlike accepted_methods:
               a business that switched every feature off means it, and there
               is no gateway default to fall back to. Stored as [] rather than
               null so "off" and "never asked" stay different facts. */
            'payment_capabilities' => array_values($data['capabilities'] ?? []),
            'default_deposit_type' => $type === 'none' ? null : $type,
            'default_deposit_value' => $type === 'none' ? null : $this->depositValue($type, $data['default_deposit_value'] ?? null),
        ])->save();

        return back()->with('status', __('payments.saved'));
    }

    /**
     * A percentage is a percentage; an amount is minor units.
     *
     * One column holds both, so the conversion happens here rather than being
     * re-guessed by everything that reads it.
     */
    private function depositValue(string $type, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $type === 'percent'
            ? (int) min(100, max(0, (int) round((float) $value)))
            : (int) round(((float) $value) * 100);
    }

    /** @return array<int, string> */
    private function availableGateways(): array
    {
        return collect(app(PaymentGatewayManager::class)->all())
            ->filter(fn (PaymentGateway $gateway) => $gateway->isReady())
            ->map(fn (PaymentGateway $gateway) => $gateway->key())
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function state(Tenant $tenant): array
    {
        $manager = app(PaymentGatewayManager::class);
        $active = $manager->for($tenant);

        return [
            'tenant' => $tenant,
            'active' => $active,
            /* Every gateway the product knows about, connected or not. One
               that cannot be used yet says so, where hiding it leaves an
               owner hunting for Square. */
            'gateways' => collect(config('payments.gateways'))
                ->map(fn (array $config, string $key) => [
                    'key' => $key,

                    /*
                     * Three different questions, and collapsing them is how
                     * the bring-your-own-keys form ended up behind a gate that
                     * required keys.
                     *
                     *   supported  StyleDesk has built this processor at all.
                     *   usable     It can be reached from this deployment —
                     *              platform keys, or the business's own.
                     *   ready      This business can take a card through it
                     *              right now.
                     */
                    'supported' => $config['driver'] !== null,
                    'usable' => $manager->make($key) !== null,
                    'ready' => $manager->make($key)?->isReady() ?? false,
                    'processes' => $manager->make($key)?->processes() ?? false,
                ])
                ->values()
                ->all(),
            'methods' => config('payments.methods'),
            'accepted' => $tenant->accepted_methods ?? $active->methods(),
            /* What this business's payments may do, with the reason beside
               anything that cannot be switched on. */
            'capabilities' => PaymentCapabilities::grouped($tenant),
            'takeable' => $active->methods(),
            /* The connected account, when there is one. Null covers both
               "never connected" and "this deployment has no Stripe", which
               the card tells apart by whether the gateway is built at all. */
            'stripe' => StripeGateway::accountFor($tenant),
            /* Whether the platform route is on offer at all. Without keys of
               its own StyleDesk cannot onboard anybody, but a business can
               still bring its own — so the two halves of the card are shown
               independently. */
            'platformStripe' => filled(config('services.stripe.secret')),
            'depositType' => $tenant->default_deposit_type ?? 'none',
            'depositValue' => $tenant->default_deposit_type === 'percent'
                ? (string) $tenant->default_deposit_value
                : number_format(((int) $tenant->default_deposit_value) / 100, 2, '.', ''),
        ];
    }
}
