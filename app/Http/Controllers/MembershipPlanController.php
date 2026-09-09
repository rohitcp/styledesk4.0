<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClientMembership;
use App\Models\Location;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanService;
use App\Models\MembershipSettings;
use App\Models\Service;
use App\Services\MembershipImageSync;
use App\Support\Currencies;
use App\Support\MembershipCode;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Clients → Membership.
 *
 * The memberships a business sells, built here and bought in the booking
 * screen. One listing for both kinds behind two tabs, because a recurring
 * membership and a package differ in one thing — whether they bill again —
 * and every screen that shows them shows them side by side.
 *
 * Under Clients rather than Settings: a membership is something a business
 * sells to people, and the people are here. What is in Settings is the terms
 * every membership is sold on, which is a different decision.
 */
class MembershipPlanController extends Controller
{
    /* Overview, then one tab per kind, then the members. The order the tabs
       render in and the order somebody works through them. */
    private const TABS = ['overview', 'plans', 'packages', 'members'];

    public function __construct(private readonly MembershipImageSync $images) {}

    public function index(Request $request): View
    {
        $this->allow($request, 'clients.view');

        $counts = $this->counts();

        return view('membership.index', [
            'tab' => 'overview',
            'tabs' => self::TABS,
            'counts' => $counts,
            'settings' => $this->settings(),
            'currency' => Currencies::resolve(),
            /* The three most recently touched, so the overview is a way back
               into work rather than only a set of numbers. */
            'recent' => MembershipPlan::query()
                ->with('planServices')
                ->latest('updated_at')
                ->limit(3)
                ->get(),
        ]);
    }

    /** The Membership Plans tab, and the Membership Packages tab. */
    public function plans(Request $request, string $type): View
    {
        $this->allow($request, 'clients.view');

        abort_unless(in_array($type, MembershipPlan::TYPES, true), 404);

        return view('membership.plans', [
            'tab' => $type === 'recurring' ? 'plans' : 'packages',
            'tabs' => self::TABS,
            'type' => $type,
            'counts' => $this->counts(),
            'filters' => $this->filters($request),
            'locations' => Location::query()->orderBy('name')->get(),
            'hasAny' => MembershipPlan::query()->ofType($type)->exists(),
        ]);
    }

    /**
     * The Members tab: everybody holding one, and what they have left.
     *
     * Ordered by who renews soonest, then by who joined most recently. The
     * first is the question this list is opened to answer — a renewal that
     * fails is a member who quietly stops being one — and the second is what
     * makes it useful on the day somebody signs up.
     */
    public function members(Request $request): View
    {
        $this->allow($request, 'clients.view');

        $memberships = ClientMembership::query()
            ->with(['client', 'plan', 'credits.service'])
            ->orderByRaw('next_billing_on is null, next_billing_on')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('membership.members', [
            'tab' => 'members',
            'tabs' => self::TABS,
            'counts' => $this->counts(),
            'memberships' => $memberships,
            'currency' => Currencies::resolve(),
        ]);
    }

    /** The rows the listing grid asks for, as JSON. */
    public function data(Request $request, string $type): JsonResponse
    {
        $this->allow($request, 'clients.view');

        abort_unless(in_array($type, MembershipPlan::TYPES, true), 404);

        $filters = $this->filters($request);
        $currency = Currencies::resolve();

        $plans = MembershipPlan::query()
            ->ofType($type)
            ->with(['planServices.service', 'locations'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $q) use ($filters) {
                $like = '%'.$filters['search'].'%';

                $q->where('name', 'like', $like)
                    ->orWhere('internal_code', 'like', $like)
                    /* By service too, because "which memberships include a
                       facial" is a question the desk asks. */
                    ->orWhereHas('services', fn (Builder $s) => $s->where('name', 'like', $like));
            }))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->ofStatus($filters['status']))
            ->when($filters['location'] !== '', fn (Builder $query) => $query->atLocation((int) $filters['location']))
            ->orderBy('name')
            ->paginate(
                perPage: min(100, max(1, (int) $request->query('size', 25))),
                page: max(1, (int) $request->query('page', 1)),
            );

        return response()->json([
            'last_page' => $plans->lastPage(),
            'last_row' => $plans->total(),
            'total' => $plans->total(),
            'data' => $plans->getCollection()->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'code' => $plan->internal_code ?: '—',
                'price' => $plan->priceLabel($currency),
                'includes' => $this->includesLabel($plan),
                'benefit' => $plan->discountLabel($currency) ?? '—',
                'saving' => $plan->savingMinor() > 0
                    ? Money::format($plan->savingMinor() / 100, $currency)
                    : '—',
                'locations' => $plan->location_mode === 'all'
                    ? __('membership.all_locations')
                    : $plan->locations->pluck('name')->join(', '),
                'status' => $plan->statusLabel(),
                'status_class' => $plan->statusClass(),
                'url' => route('membership.show', $plan),
                'menu' => $this->rowMenu($plan),
            ])->all(),
        ]);
    }

    /* ------------------------------------------------------------ making */

    /**
     * Step 1, then the rest.
     *
     * Choosing the kind is its own screen because it is the one answer that
     * changes what every later question means — a billing frequency and a
     * regular value are not two settings of one plan, they belong to
     * different products. Everything after it is one form: a plan is a
     * priced thing a client will be charged for, and a wizard that saved as
     * it went would leave half-built products in a list somebody sells from.
     */
    public function create(Request $request): View
    {
        $this->allow($request, 'clients.create');

        $type = (string) $request->query('type', '');

        if (! in_array($type, MembershipPlan::TYPES, true)) {
            return view('membership.type', [
                'types' => config('membership.types'),
            ]);
        }

        return view('membership.form', $this->formData(null, $type));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'clients.create');

        $data = $this->validated($request);

        $plan = MembershipPlan::create($this->columns($data) + [
            'tenant_id' => $request->user()->tenant->getTenantKey(),
            'created_by' => $request->user()->id,
            /* Given here, at the first save, and never again. A reference is
               only useful if it is unique and everybody spells it the same
               way, and neither survives being typed at the desk. */
            'internal_code' => MembershipCode::next($request->user()->tenant, $data['type']),
        ]);

        $this->syncRelations($plan, $data);

        return redirect()->route('membership.show', $plan)
            ->with('toast', ['type' => 'success', 'message' => __('membership.created')]);
    }

    public function show(Request $request, MembershipPlan $plan): View
    {
        $this->allow($request, 'clients.view');

        $plan->load(['planServices.service', 'locations', 'createdBy']);

        return view('membership.show', [
            'plan' => $plan,
            'settings' => $this->settings(),
            'rules' => $plan->creditRules($this->settings()),
            'currency' => Currencies::resolve(),
        ]);
    }

    public function edit(Request $request, MembershipPlan $plan): View
    {
        $this->allow($request, 'clients.edit');
        $this->guardOnSale($plan);

        return view('membership.form', $this->formData($plan, $plan->type));
    }

    public function update(Request $request, MembershipPlan $plan): RedirectResponse
    {
        $this->allow($request, 'clients.edit');
        $this->guardOnSale($plan);

        $data = $this->validated($request, $plan);

        $plan->update($this->columns($data));
        $this->syncRelations($plan, $data);

        return redirect()->route('membership.show', $plan)
            ->with('toast', ['type' => 'success', 'message' => __('membership.saved')]);
    }

    /**
     * The same plan again, as a draft.
     *
     * A draft rather than published: a copy that went on sale the moment it
     * was made would be a second priced product nobody had checked, sharing
     * a name with the first.
     */
    public function duplicate(Request $request, MembershipPlan $plan): RedirectResponse
    {
        $this->allow($request, 'clients.create');

        $copy = $plan->replicate(['created_at', 'updated_at', 'deleted_at']);
        $copy->name = __('membership.copy_of', ['name' => $plan->name]);
        /* A code names one row, so the copy cannot keep it — and it is not
           left blank either: every membership carries one, and a copy is a
           membership. */
        $copy->internal_code = MembershipCode::next($request->user()->tenant, $plan->type);
        /* Deliberately without the picture. A file belongs to one row, and
           two plans pointing at it would mean editing either one's picture
           silently changed the other's. */
        $copy->image_file_id = null;
        $copy->is_draft = true;
        $copy->created_by = $request->user()->id;
        $copy->save();

        foreach ($plan->planServices as $line) {
            MembershipPlanService::create([
                'membership_plan_id' => $copy->id,
                'service_id' => $line->service_id,
                'quantity' => $line->quantity,
                'credits' => $line->grantedCredits(),
                'position' => $line->position,
            ]);
        }

        $copy->locations()->sync($plan->locations->pluck('id'));

        return redirect()->route('membership.edit', $copy)
            ->with('toast', ['type' => 'success', 'message' => __('membership.duplicated')]);
    }

    /**
     * A membership on sale is not edited.
     *
     * Checked here rather than only in the screen: a disabled button is a
     * courtesy, and the rule has to hold for anybody who reaches the URL
     * another way. Taking it off sale first is one click, and it stops new
     * purchases without touching a single membership already bought.
     */
    private function guardOnSale(MembershipPlan $plan): void
    {
        abort_if($plan->isOnSale(), 403, __('membership.on_sale_locked'));
    }

    /**
     * Taken off sale, and put back on.
     *
     * Never deleted: the people who bought it keep pointing at it, and a
     * plan that vanished would leave their credits unexplained.
     */
    public function toggle(Request $request, MembershipPlan $plan): RedirectResponse
    {
        $this->allow($request, 'clients.edit');

        $plan->update(['is_disabled' => ! $plan->is_disabled]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $plan->is_disabled ? __('membership.disabled') : __('membership.enabled'),
        ]);
    }

    /* ----------------------------------------------------------- helpers */

    /** @return array<string, mixed> */
    private function formData(?MembershipPlan $plan, string $type): array
    {
        $plan?->load(['planServices.service', 'locations', 'prices']);

        return [
            'plan' => $plan,
            /* What the code will be, shown while adding. A preview rather
               than a reservation: the number is settled at the save, so two
               people adding at once cannot be handed the same one. */
            'suggestedCode' => $plan === null
                ? MembershipCode::next(request()->user()?->tenant, $type)
                : $plan->internal_code,
            'type' => $type,
            'settings' => $this->settings(),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'frequencies' => MembershipSettings::billingFrequencies(),
            'creditExpiries' => MembershipSettings::creditExpiries(),
            'currency' => Currencies::resolve(),
            'symbol' => Money::symbol(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MembershipPlan $plan = null): array
    {
        $tenantKey = $request->user()->tenant?->getTenantKey();
        $isRecurring = $request->input('type') === 'recurring';

        $enabled = Currencies::enabledFor($request->user()->tenant);
        $primaryCurrency = Currencies::primaryFor($request->user()->tenant);

        /* Only a currency this business actually prices in. Without this a
           request could add a price in a currency no screen would ever show
           and every total would quietly ignore. */
        $currencyKeys = function (string $attribute, mixed $value, callable $fail) use ($enabled) {
            foreach (array_keys((array) $value) as $code) {
                if (! $enabled->contains($code)) {
                    $fail(__('validation.in', ['attribute' => $attribute]));
                }
            }
        };

        $data = $request->validate([
            /* Not editable after the fact. A plan that changed kind would
               have to change what every credit already granted under it
               means, and there is no answer to that a member would accept. */
            'type' => [
                'required',
                Rule::in(MembershipPlan::TYPES),
                $plan === null ? 'string' : Rule::in([$plan->type]),
            ],

            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            /* The picture, as the id of a file already uploaded. Every
               check that an id in a form needs is in MembershipImageSync;
               this only says the shape. */
            'image_file_id' => ['nullable', 'integer'],

            /* One price per currency the business sells in, keyed by the
               code — the shape service prices already use. Nothing converts:
               $150 and C$205 are two decisions, not one and an exchange rate
               that moves overnight.

               The primary currency is required and the rest are not: a plan
               has to be sellable somewhere, and a business that prices in
               three currencies but only sells this membership in one is
               making a normal decision. */
            'price' => ['required', 'array', $currencyKeys],
            'price.'.$primaryCurrency => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            /* Free is not a membership. Somebody giving one away can price
               it at a penny; zero is a form that was not filled in. */
            'price.*' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],

            'billing_frequency' => [
                Rule::requiredIf($isRecurring), 'nullable',
                Rule::in(MembershipSettings::billingFrequencies()),
            ],
            'joining_fee' => ['array', $currencyKeys],
            'joining_fee.*' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'setup_fee' => ['array', $currencyKeys],
            'setup_fee.*' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            'regular_value' => ['array', $currencyKeys],
            'regular_value.*' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],

            /* At least one, and a real one — where this business's
               memberships include anything at all. With credits switched off
               a membership is its discount and its standing, and insisting on
               a service list would make that product unsellable. */
            'services' => [
                Rule::requiredIf(fn () => MembershipSettings::forTenant($request->user()->tenant)->grantsCredits()),
                'array',
            ],
            'services.*.service_id' => [
                'required',
                Rule::exists('services', 'id')->where('tenant_id', $tenantKey),
            ],
            'services.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            /* What can actually be redeemed. Optional on the wire and
               defaulted to the quantity, so a form or an integration that
               only knows about quantity still describes a coherent benefit
               rather than granting one of everything. */
            'services.*.credits' => ['nullable', 'integer', 'min:1', 'max:99'],

            'discount_type' => ['nullable', Rule::in(MembershipPlan::DISCOUNT_TYPES)],
            'discount_value' => ['nullable', 'numeric', 'min:0',
                $request->input('discount_type') === 'percent' ? 'max:100' : 'max:100000'],
            'priority_booking' => ['nullable', 'boolean'],

            /* Blank is "follow the business setting", which is why none of
               these is required and none has a default here. */
            'credit_expiry' => ['nullable', Rule::in(MembershipSettings::creditExpiries())],
            'allow_rollover' => ['nullable', 'in:,0,1'],
            'maximum_rollover' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'allow_service_substitution' => ['nullable', 'in:,0,1'],

            'location_mode' => ['required', Rule::in(['all', 'selected'])],
            'locations' => ['array', Rule::requiredIf(fn () => $request->input('location_mode') === 'selected')],
            'locations.*' => [Rule::exists('locations', 'id')->where('tenant_id', $tenantKey)],

            'sell_in_store' => ['nullable', 'boolean'],
            'sell_online' => ['nullable', 'boolean'],

            'is_draft' => ['nullable', 'boolean'],
        ]);

        /* One service named twice is two rows claiming to be the same
           entitlement, and the credit engine would have to pick one. Caught
           here rather than by the unique index, which would 500. */
        $ids = array_column($data['services'] ?? [], 'service_id');

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'services' => __('membership.form.duplicate_service'),
            ]);
        }

        /* A package that claims a saving must claim a real one — in every
           currency it claims it in. Checked per currency because the two
           numbers are only comparable within one: C$300 is not more than
           $150 in any sense a saving could be worked out from. */
        if (! $isRecurring) {
            foreach ((array) ($data['regular_value'] ?? []) as $code => $value) {
                $price = $data['price'][$code] ?? null;

                if ($value === null || $value === '' || $price === null || $price === '') {
                    continue;
                }

                if ((float) $value < (float) $price) {
                    throw ValidationException::withMessages([
                        'regular_value.'.$code => __('membership.form.value_below_price'),
                    ]);
                }
            }
        }

        return $data;
    }

    /**
     * The posted form as columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        $isRecurring = $data['type'] === 'recurring';
        $percent = ($data['discount_type'] ?? null) === 'percent';

        /* The money on the plan itself is the primary currency's copy. It is
           written here so a new plan is never momentarily priceless, and
           written again by syncPrices from the rows — one answer, two places
           that cannot disagree because the same save writes both. */
        $primary = Currencies::primaryFor(request()->user()->tenant);
        $minor = function (string $key) use ($data, $primary) {
            $amount = $data[$key][$primary] ?? null;

            return $amount === null || $amount === '' ? null : (int) round(((float) $amount) * 100);
        };

        return [
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            /* Not here: the code is assigned once when the plan is created
               and belongs to that row for good. Editing a membership must
               not be able to change what it is referred to by — on a
               receipt, in a report, or in somebody's notes. */

            'price_minor' => $minor('price') ?? 0,

            /* Only the columns this kind uses. A package carrying a billing
               frequency is a row that would eventually be read as one. */
            'billing_frequency' => $isRecurring ? ($data['billing_frequency'] ?? null) : null,
            'joining_fee_minor' => $isRecurring ? $minor('joining_fee') : null,
            'setup_fee_minor' => $isRecurring ? $minor('setup_fee') : null,
            'trial_days' => $isRecurring ? ($data['trial_days'] ?? null) : null,
            'regular_value_minor' => $isRecurring ? null : $minor('regular_value'),

            /* A value with no type is not a discount, and a type with no
               value is not one either — so neither is stored without the
               other. */
            'discount_type' => ($data['discount_type'] ?? null) !== null && (float) ($data['discount_value'] ?? 0) > 0
                ? $data['discount_type']
                : null,
            'discount_value' => (float) ($data['discount_value'] ?? 0) <= 0
                ? 0
                : ($percent
                    ? (int) round((float) $data['discount_value'])
                    : (int) round(((float) $data['discount_value']) * 100)),
            'priority_booking' => (bool) ($data['priority_booking'] ?? false),

            /* Empty string is the "follow the business setting" option, and
               has to become null rather than false. */
            'credit_expiry' => ($data['credit_expiry'] ?? '') === '' ? null : $data['credit_expiry'],
            'allow_rollover' => $this->override($data['allow_rollover'] ?? null),
            'maximum_rollover' => $data['maximum_rollover'] ?? null,
            'allow_service_substitution' => $this->override($data['allow_service_substitution'] ?? null),

            'location_mode' => $data['location_mode'],
            'sell_in_store' => (bool) ($data['sell_in_store'] ?? false),
            'sell_online' => (bool) ($data['sell_online'] ?? false),

            'is_draft' => (bool) ($data['is_draft'] ?? false),
        ];
    }

    /** Three-state: follow the business (null), yes, or no. */
    private function override(?string $value): ?bool
    {
        return $value === null || $value === '' ? null : (bool) (int) $value;
    }

    /** @param  array<string, mixed>  $data */
    private function syncRelations(MembershipPlan $plan, array $data): void
    {
        /* Rewritten rather than merged: the form posts the whole list every
           time, and a line the reader deleted has to actually go. */
        $plan->planServices()->delete();

        foreach (array_values($data['services'] ?? []) as $position => $line) {
            MembershipPlanService::create([
                'membership_plan_id' => $plan->id,
                'service_id' => (int) $line['service_id'],
                'quantity' => (int) $line['quantity'],
                'credits' => (int) ($line['credits'] ?? $line['quantity']),
                'position' => $position,
            ]);
        }

        /* Every currency the form posted, and only those: a currency the
           business stopped pricing this plan in has to actually go, or the
           sale screen keeps offering a price nobody maintains. */
        $plan->syncPrices(
            collect($data['price'] ?? [])
                ->mapWithKeys(fn ($price, string $code) => [$code => [
                    'price' => $price,
                    'regular_value' => $data['regular_value'][$code] ?? null,
                    'joining_fee' => $data['joining_fee'][$code] ?? null,
                    'setup_fee' => $data['setup_fee'][$code] ?? null,
                ]])
                ->all(),
            Currencies::primaryFor($plan->tenant),
        );

        $plan->locations()->sync($data['location_mode'] === 'selected' ? ($data['locations'] ?? []) : []);

        /* After the plan exists, because claiming a picture means pointing
           it at a row that has an id. */
        $this->images->sync($plan, $data['image_file_id'] ?? null);

        $plan->load('planServices.service');
    }

    /** "1 × Swedish Massage, 2 × Facial". */
    private function includesLabel(MembershipPlan $plan): string
    {
        return $plan->planServices
            ->map(fn (MembershipPlanService $line) => $line->quantity.' × '.($line->service?->name ?? '—'))
            ->join(', ') ?: '—';
    }

    /** @return array<int, array<string, mixed>> */
    private function rowMenu(MembershipPlan $plan): array
    {
        $canEdit = request()->user()?->hasPermission('clients.edit', 'own') ?? false;

        return array_values(array_filter([
            ['label' => __('membership.actions.view'), 'url' => route('membership.show', $plan)],
            /* Only where it would actually work: a row menu offering Edit
               on a plan that is on sale is an offer the next screen refuses.
               Take it off sale from its own page first. */
            $canEdit && $plan->isEditable()
                ? ['label' => __('membership.actions.edit'), 'url' => route('membership.edit', $plan)]
                : null,
            $canEdit ? ['label' => __('membership.actions.duplicate'), 'url' => route('membership.duplicate', $plan)] : null,
        ]));
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'plans' => MembershipPlan::query()->ofType('recurring')->sellable()->count(),
            'packages' => MembershipPlan::query()->ofType('package')->sellable()->count(),
            'drafts' => MembershipPlan::query()->ofStatus('draft')->count(),
            /* Everybody whose membership applies today. A cancelled one is
               not a member, and counting them would make the tile a running
               total of everybody who ever joined. */
            'members' => ClientMembership::query()->live()->count(),
        ];
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($status, MembershipPlan::STATUSES, true) ? $status : '',
            'location' => (string) $request->query('location', ''),
        ];
    }

    private function settings(): MembershipSettings
    {
        return MembershipSettings::forTenant(request()->user()?->tenant);
    }

    /**
     * The module is unreachable until the business switches it on.
     *
     * Checked here rather than only hidden from the menu: a bookmarked URL
     * is how somebody reaches a screen whose feature was turned off, and it
     * should say no rather than let them build a product that cannot be
     * sold.
     */
    private function allow(Request $request, string $permission): void
    {
        abort_unless($this->settings()->is_enabled, 404);
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
