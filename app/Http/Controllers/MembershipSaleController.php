<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPaymentMethod;
use App\Models\Location;
use App\Models\MembershipPlan;
use App\Models\MembershipSettings;
use App\Support\Currencies;
use App\Support\MembershipConflicts;
use App\Support\MembershipPurchase;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Selling a membership from the booking screen.
 *
 * Its own controller rather than a branch inside BookingController: a
 * membership sale takes no slot, needs no staff member and has no duration,
 * so almost nothing the booking store does applies to it. What it shares is
 * the screen it is sold from and the client on it, which is the point of the
 * Select Type accordion.
 */
class MembershipSaleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'appointments.create');

        $settings = MembershipSettings::forTenant($request->user()->tenant);

        /* The module being off is not a validation error — there is nothing
           the reader could type to fix it. It is a screen that should never
           have offered this. */
        abort_unless($settings->is_enabled, 404);

        $data = $request->validate([
            'membership_plan_id' => [
                'required',
                Rule::exists('membership_plans', 'id')
                    ->where('tenant_id', $request->user()->tenant?->getTenantKey())
                    ->whereNull('deleted_at'),
            ],
            /* A membership is always attached to somebody. There is no guest
               version: the whole thing is a standing relationship, and one
               with nobody's name on it could never be redeemed. */
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey()),
            ],
            'location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey()),
            ],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'payment_method' => ['required', Rule::in(array_keys(config('bookings.methods')))],

            /* Whether it renews, and the card it renews on. The card is an
               id into this business's own vault — the number itself never
               reaches StyleDesk, and this is a reference to a token the
               gateway is holding. */
            'auto_renew' => ['nullable', 'boolean'],
            /* Somebody has seen what this client already holds and said sell
               it anyway. Not a preference — the answer to a question the
               screen asked, and the server's proof that a second membership
               was a decision rather than an accident. */
            'acknowledge_existing' => ['nullable', 'boolean'],
            'card_id' => [
                'nullable',
                Rule::exists('client_payment_methods', 'id')
                    ->where('tenant_id', $request->user()->tenant?->getTenantKey()),
            ],
        ]);

        $plan = MembershipPlan::query()
            ->with('planServices.service')
            ->findOrFail($data['membership_plan_id']);

        $client = Client::query()->findOrFail($data['client_id']);
        $startsOn = Carbon::parse($data['starts_on'])->startOfDay();

        $this->guardPlan($plan, $settings);
        $this->guardStartDate($settings, $startsOn);
        $this->guardMethod($plan, $data['payment_method']);
        $this->guardExisting($client, $plan, $request->boolean('acknowledge_existing'));

        /* Renewing is an explicit answer, never an assumption.
         *
         * The screen ticks the box for a recurring plan — that is what the
         * client is being sold — but the server does not fill it in for a
         * request that did not say. A subscription that starts charging a
         * card every month because a field was missing is the one mistake
         * this direction of default cannot make.
         *
         * A package never renews, whatever arrived. */
        $autoRenew = $plan->isRecurring() && $request->boolean('auto_renew');
        $card = $this->cardFor($data['card_id'] ?? null, $client);

        $this->guardCard($autoRenew, $card);

        /* The money this sale is taken in — the same one the booking screen
           priced it in, and the one the receipt will carry. */
        $currency = Currencies::resolve();

        $membership = MembershipPurchase::sell(
            plan: $plan,
            client: $client,
            startsOn: $startsOn,
            location: $data['location_id'] === null ? null : Location::query()->find($data['location_id']),
            payment: [
                /* What it costs is the server's answer, never the form's. A
                   price posted from a page is a price somebody can edit. */
                'method' => $data['payment_method'],
                'amount_minor' => MembershipPurchase::dueTodayMinor($plan, $currency),
            ],
            seller: $request->user(),
            card: $card,
            autoRenew: $autoRenew,
            currency: $currency,
        );

        return redirect()->route('membership.sales.show', $membership);
    }

    /**
     * Membership Activated.
     *
     * Its own page rather than the booking confirmation: what a client wants
     * to see after buying a membership is what they now hold, and the booking
     * confirmation answers "when should I turn up", which has no answer here.
     */
    public function show(Request $request, ClientMembership $membership): View
    {
        $this->allow($request, 'appointments.view');

        $membership->load(['client', 'plan', 'credits.service', 'payments', 'location', 'soldBy']);

        return view('membership.sold', [
            'membership' => $membership,
            'currency' => Currencies::resolve(),
            'paid' => Money::format($membership->paidMinor() / 100, $membership->currency_code),
        ]);
    }

    /**
     * What this client already holds, for the screen to warn about.
     *
     * Asked before the sale rather than answered after it: the desk needs to
     * know while there is still a decision to make.
     */
    public function check(Request $request): JsonResponse
    {
        $this->allow($request, 'appointments.create');

        $data = $request->validate([
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey()),
            ],
            'membership_plan_id' => [
                'nullable',
                Rule::exists('membership_plans', 'id')
                    ->where('tenant_id', $request->user()->tenant?->getTenantKey()),
            ],
        ]);

        $client = Client::query()->findOrFail($data['client_id']);

        $plan = ($data['membership_plan_id'] ?? null) === null
            ? null
            : MembershipPlan::query()->find($data['membership_plan_id']);

        $held = MembershipConflicts::forClient($client, $plan, Currencies::resolve());

        return response()->json([
            'held' => $held,
            /* The one fact that changes the wording, lifted out so the screen
               does not have to work it out from the list. */
            'same_plan' => collect($held)->contains('same_plan', true),
            'plan' => $plan?->name,
        ]);
    }

    /* ----------------------------------------------------------- guards */

    /**
     * A second membership is a decision, never an accident.
     *
     * Never a refusal — a client may hold a monthly plan and a massage
     * package, and even two of the same package is somebody's call to make.
     * What this stops is the silent one: a second subscription created
     * because nobody at the desk knew about the first, and a client who finds
     * out at the next billing run.
     *
     * Checked here as well as on the screen, because a dialog is a courtesy
     * and this is the rule.
     */
    private function guardExisting(Client $client, MembershipPlan $plan, bool $acknowledged): void
    {
        if ($acknowledged || ! MembershipConflicts::exist($client)) {
            return;
        }

        $held = MembershipConflicts::forClient($client, $plan);
        $samePlan = collect($held)->contains('same_plan', true);

        throw ValidationException::withMessages([
            'membership_plan_id' => $samePlan
                ? __('membership.sale.already_has_this', ['client' => $client->displayName()])
                : __('membership.sale.already_has_one', ['client' => $client->displayName()]),
        ]);
    }

    /**
     * Whether this plan is one the desk may sell at all.
     *
     * Everything the booking screen already filtered on, asked again: the
     * list it offered came from the server, but the id that came back is a
     * number in a form.
     */
    private function guardPlan(MembershipPlan $plan, MembershipSettings $settings): void
    {
        if ($plan->is_draft || $plan->is_disabled) {
            throw ValidationException::withMessages([
                'membership_plan_id' => __('membership.sale.not_on_sale'),
            ]);
        }

        /* The business's channel switch is a ceiling over the plan's: a plan
           marked sellable at the desk in a business that has closed its
           in-store channel is not for sale. */
        if (! $settings->allow_purchase_in_store || ! $plan->sell_in_store) {
            throw ValidationException::withMessages([
                'membership_plan_id' => __('membership.sale.channel_closed'),
            ]);
        }
    }

    /** A future start date only where the business allows one to be chosen. */
    private function guardStartDate(MembershipSettings $settings, Carbon $startsOn): void
    {
        if (! $settings->allow_start_date_selection && $startsOn->isFuture()) {
            throw ValidationException::withMessages([
                'starts_on' => __('membership.sale.no_future_start'),
            ]);
        }
    }

    /**
     * A recurring membership needs a method that can be charged again.
     *
     * Cash buys a package; it does not renew a subscription. Stated on the
     * settings screen as a rule of the app, and enforced here rather than
     * left for the first failed renewal to discover.
     */
    private function guardMethod(MembershipPlan $plan, string $method): void
    {
        if (! $plan->isRecurring()) {
            return;
        }

        if (in_array($method, config('membership.repeatable_methods'), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'payment_method' => __('membership.sale.method_not_repeatable'),
        ]);
    }

    /**
     * The card named, if it is this client's and can actually be charged.
     *
     * Both checks matter. The first stops one client's membership renewing on
     * another's card — an id in a form is a number a reader can change. The
     * second stops a membership being sold against a card that has expired or
     * been removed, which is a renewal that fails on the day it matters.
     */
    private function cardFor(int|string|null $cardId, Client $client): ?ClientPaymentMethod
    {
        if ($cardId === null) {
            return null;
        }

        $card = ClientPaymentMethod::query()->find($cardId);

        if ($card === null || $card->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'card_id' => __('membership.sale.card_not_theirs'),
            ]);
        }

        if (! $card->isChargeable()) {
            throw ValidationException::withMessages([
                'card_id' => __('membership.sale.card_unusable'),
            ]);
        }

        return $card;
    }

    /**
     * A membership that renews needs something to renew on.
     *
     * Refused at the point of sale rather than discovered on the first
     * billing date: a subscription with no card is one the client believes
     * they have and the business cannot charge for.
     */
    private function guardCard(bool $autoRenew, ?ClientPaymentMethod $card): void
    {
        if ($autoRenew && $card === null) {
            throw ValidationException::withMessages([
                'card_id' => __('membership.sale.card_required'),
            ]);
        }
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
