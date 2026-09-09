<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Payments\PaymentFailed;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A client's saved cards.
 *
 * Every route here handles a REFERENCE to a card, never a card. The number is
 * typed into the gateway's own component in the browser and goes straight to
 * the gateway; what reaches this controller is the token it handed back.
 *
 * That is why `store` takes an id and not a form. If a request to this
 * controller ever carries a card number, something upstream is broken and the
 * fix is upstream — not a sanitiser here.
 */
class ClientPaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * Begin adding a card, and hand the browser what it needs.
     *
     * The client secret returned is what mounts the gateway's secure
     * component. It authorises collecting one card for one client and nothing
     * else, which is why it is safe to put on the page.
     */
    public function setup(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'clients.edit');

        $vault = $this->gateways->vault($request->user()->tenant);

        /* No processor connected, so there is nowhere to keep a card. Said
           plainly rather than failing at the last step — the screen offers
           Card on File only when this can answer. */
        if ($vault === null) {
            return response()->json(['message' => __('payments.methods_list.no_vault')], 422);
        }

        try {
            return response()->json($vault->startCardSetup($client));
        } catch (PaymentFailed $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Record a card the gateway has already stored.
     *
     * Takes the id the browser was handed and nothing else. The brand, the
     * last four and the expiry are read back from the gateway inside
     * `rememberCard` rather than accepted here.
     */
    public function store(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'clients.edit');

        $data = $request->validate([
            'payment_method_id' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:255'],
            'make_default' => ['nullable', 'boolean'],
        ]);

        $vault = $this->gateways->vault($request->user()->tenant);

        if ($vault === null) {
            return response()->json(['message' => __('payments.methods_list.no_vault')], 422);
        }

        try {
            $card = $vault->rememberCard($client, $data['payment_method_id'], $data['customer_id']);
        } catch (PaymentFailed $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        /* The first card a client saves is the one renewals reach for.
           Nothing else would be, and leaving them with no default is a
           renewal that cannot choose. */
        if (($data['make_default'] ?? false) || ClientPaymentMethod::query()->forClient($client->id)->usable()->count() === 1) {
            $card->makeDefault();
        }

        return response()->json(['card' => $this->present($card->fresh())], 201);
    }

    public function makeDefault(Request $request, Client $client, ClientPaymentMethod $method): RedirectResponse
    {
        $this->allow($request, 'clients.edit');
        $this->belongsTo($method, $client);

        $method->makeDefault();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('payments.methods_list.default_set', ['card' => $method->label()]),
        ]);
    }

    /**
     * Take a card out of use.
     *
     * Refused while a membership is renewing on it. The alternative is a
     * subscription whose next payment silently fails, and the client finding
     * out when their credits stop — so the desk is asked to move the
     * membership first rather than told afterwards.
     */
    public function destroy(Request $request, Client $client, ClientPaymentMethod $method): RedirectResponse
    {
        $this->allow($request, 'clients.edit');
        $this->belongsTo($method, $client);

        $renewing = $method->memberships()
            ->where('auto_renew', true)
            ->with('plan')
            ->get()
            ->filter(fn ($membership) => $membership->isLive());

        if ($renewing->isNotEmpty()) {
            return back()->withErrors([
                'payment_method' => __('payments.methods_list.in_use', [
                    'name' => $renewing->map(fn ($membership) => $membership->plan?->name)->filter()->join(', '),
                ]),
            ]);
        }

        /* The gateway is told to let it go; StyleDesk keeps its own row,
           because a membership renewed on this card last month still points
           at it. */
        $this->gateways->vault($request->user()->tenant)?->forgetCard($method);

        $method->markRemoved();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('payments.methods_list.removed', ['card' => $method->label()]),
        ]);
    }

    /**
     * One card, as a screen reads it.
     *
     * Everything here is safe to render. There is nothing else to send.
     *
     * @return array<string, mixed>
     */
    public static function present(ClientPaymentMethod $card): array
    {
        return [
            'id' => $card->id,
            'label' => $card->label(),
            'brand' => $card->brandLabel(),
            'last4' => $card->last4,
            'expiry' => $card->expiryLabel(),
            'is_default' => (bool) $card->is_default,
            'expired' => $card->isExpired(),
            'expiring_soon' => $card->isExpiringSoon(),
            'chargeable' => $card->isChargeable(),
            'status' => $card->statusLabel(),
        ];
    }

    /** One client's card is not another's, whatever the URL says. */
    private function belongsTo(ClientPaymentMethod $method, Client $client): void
    {
        abort_unless($method->client_id === $client->id, 404);
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
