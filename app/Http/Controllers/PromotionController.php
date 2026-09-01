<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Location;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Currencies;
use App\Support\Promotions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Coupons and offers.
 *
 * One screen for both, because they are one thing with one difference: a
 * coupon is typed in and an offer applies itself. Splitting them would mean
 * two listings, two forms and two places to fix a rule.
 */
class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request, 'clients.view');

        return view('promotions.index', [
            'filters' => $this->filters($request),
            'counts' => $this->counts(),
            'locations' => Location::query()->orderBy('name')->get(),
            'hasAny' => Promotion::query()->exists(),
        ]);
    }

    /** The rows the listing grid asks for, as JSON. */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request, 'clients.view');

        $filters = $this->filters($request);
        $currency = Currencies::resolve();

        $promotions = Promotion::query()
            ->withCount('redemptions')
            ->with(['services', 'serviceCategories'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $q) use ($filters) {
                $like = '%'.$filters['search'].'%';

                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    /* By service too, because "which offers apply to
                       facials" is a question the desk asks. */
                    ->orWhereHas('services', fn (Builder $s) => $s->where('name', 'like', $like));
            }))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->ofStatus($filters['status']))
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['location'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $q) => $q->where('location_mode', 'all')
                    ->orWhereHas('locations', fn (Builder $l) => $l->where('locations.id', $filters['location']))
            ))
            ->orderByDesc('starts_on')
            ->orderBy('name')
            ->paginate(
                perPage: min(100, max(1, (int) $request->query('size', 25))),
                page: max(1, (int) $request->query('page', 1)),
            );

        return response()->json([
            'last_page' => $promotions->lastPage(),
            'last_row' => $promotions->total(),
            'total' => $promotions->total(),
            'data' => $promotions->getCollection()->map(fn (Promotion $promotion) => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                /* An offer has no code, and says so rather than showing a
                   blank a reader would take for missing data. */
                'code' => $promotion->code ?: __('promotions.automatic'),
                'type' => __('promotions.types.'.$promotion->type),
                'discount' => $promotion->discountLabel($currency),
                'applies' => $this->appliesLabel($promotion),
                'starts' => $promotion->starts_on?->translatedFormat('j M Y'),
                'ends' => $promotion->ends_on?->translatedFormat('j M Y') ?? __('promotions.no_expiry_short'),
                'used' => $promotion->usageLabel(),
                'status' => $promotion->statusLabel(),
                'status_class' => $promotion->statusClass(),
                'url' => route('promotions.show', $promotion),
                'menu' => $this->rowMenu($promotion),
            ])->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->allow($request, 'clients.create');

        /* A template only fills the form in. It is not a kind of promotion —
           everything it sets is a field the reader can then change, which is
           what makes templates safe to offer. */
        $template = config('promotions.templates.'.$request->query('template'));

        return view('promotions.form', $this->formData(null, $template));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'clients.create');

        $data = $this->validated($request);

        $promotion = Promotion::create($this->columns($data) + [
            'tenant_id' => $request->user()->tenant->getTenantKey(),
            'created_by' => $request->user()->id,
        ]);

        $this->syncRelations($promotion, $data);

        return redirect()->route('promotions.show', $promotion)
            ->with('toast', ['type' => 'success', 'message' => __('promotions.created')]);
    }

    public function show(Request $request, Promotion $promotion): View
    {
        $this->allow($request, 'clients.view');

        $promotion->load(['services', 'serviceCategories', 'locations', 'clients', 'createdBy']);

        return view('promotions.show', [
            'promotion' => $promotion,
            'report' => Promotions::report($promotion),
            'currency' => Currencies::resolve(),
            'applies' => $this->appliesLabel($promotion),
        ]);
    }

    public function edit(Request $request, Promotion $promotion): View
    {
        $this->allow($request, 'clients.edit');

        return view('promotions.form', $this->formData($promotion));
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->allow($request, 'clients.edit');

        $data = $this->validated($request, $promotion);

        $promotion->update($this->columns($data));
        $this->syncRelations($promotion, $data);

        return redirect()->route('promotions.show', $promotion)
            ->with('toast', ['type' => 'success', 'message' => __('promotions.saved')]);
    }

    /**
     * The same promotion again, as a draft.
     *
     * A draft rather than live: a copy that started running the moment it
     * was made would be a second promotion nobody had checked, sharing a
     * name with the first.
     */
    public function duplicate(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->allow($request, 'clients.create');

        $copy = $promotion->replicate(['created_at', 'updated_at', 'deleted_at']);
        $copy->name = __('promotions.copy_of', ['name' => $promotion->name]);
        /* A code is unique per business, so the copy cannot keep it. */
        $copy->code = $promotion->code === null ? null : $this->freeCode($promotion->code);
        $copy->is_draft = true;
        $copy->created_by = $request->user()->id;
        $copy->save();

        foreach (['services', 'serviceCategories', 'locations', 'clients'] as $relation) {
            $copy->{$relation}()->sync($promotion->{$relation}->pluck('id'));
        }

        return redirect()->route('promotions.edit', $copy)
            ->with('toast', ['type' => 'success', 'message' => __('promotions.duplicated')]);
    }

    /**
     * Switched off, and switched back on.
     *
     * Never deleted: the bookings it discounted keep pointing at it, and a
     * promotion that vanished would leave last month's takings unexplained.
     */
    public function toggle(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->allow($request, 'clients.edit');

        $promotion->update(['is_disabled' => ! $promotion->is_disabled]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $promotion->is_disabled ? __('promotions.disabled') : __('promotions.enabled'),
        ]);
    }

    /* ------------------------------------------------------------ helpers */

    /** @return array<string, mixed> */
    private function formData(?Promotion $promotion, ?array $template = null): array
    {
        return [
            'promotion' => $promotion,
            'template' => $template,
            'templates' => config('promotions.templates'),
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => ServiceCategory::query()->assignable()->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'clients' => Client::query()->orderBy('first_name')->limit(500)->get(),
            'currency' => Currencies::resolve(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Promotion $promotion = null): array
    {
        $isCoupon = $request->input('type') === 'coupon';

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(Promotion::TYPES)],

            /* Only a coupon has one, and it has to be free. Letters, digits
               and hyphens: a code with a space in it is a code somebody
               reads out wrongly. */
            'code' => [
                Rule::requiredIf($isCoupon), 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('promotions', 'code')
                    ->where('tenant_id', $request->user()->tenant?->getTenantKey())
                    ->whereNull('deleted_at')
                    ->ignore($promotion?->id),
            ],

            'discount_type' => ['required', Rule::in(Promotion::DISCOUNT_TYPES)],
            'discount_value' => ['required', 'numeric', 'min:0',
                $request->input('discount_type') === 'percent' ? 'max:100' : 'max:100000'],

            'applies_to' => ['required', Rule::in(Promotion::APPLIES_TO)],
            'services' => ['array', Rule::requiredIf(fn () => $request->input('applies_to') === 'services')],
            'services.*' => [Rule::exists('services', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],
            'categories' => ['array', Rule::requiredIf(fn () => $request->input('applies_to') === 'categories')],
            'categories.*' => [Rule::exists('service_categories', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],

            'location_mode' => ['required', Rule::in(['all', 'selected'])],
            'locations' => ['array', Rule::requiredIf(fn () => $request->input('location_mode') === 'selected')],
            'locations.*' => [Rule::exists('locations', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],

            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'no_expiry' => ['nullable', 'boolean'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:0,6'],

            'eligibility' => ['required', Rule::in(Promotion::ELIGIBILITY)],
            'clients' => ['array', Rule::requiredIf(fn () => $request->input('eligibility') === 'selected')],
            'clients.*' => [Rule::exists('clients', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],

            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'total_limit' => ['nullable', 'integer', 'min:1'],
            'per_client_limit' => ['nullable', 'integer', 'min:1'],

            'allow_online' => ['nullable', 'boolean'],
            'combinable' => ['nullable', 'boolean'],
            'is_draft' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * The posted form as columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        $percent = $data['discount_type'] === 'percent';

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            /* Upper case, always: a coupon read off a poster is not
               proof-read, and WELCOME20 and welcome20 are one code. */
            'code' => $data['type'] === 'coupon' ? mb_strtoupper(trim((string) $data['code'])) : null,
            'discount_type' => $data['discount_type'],
            /* Percentages are whole points; fixed amounts are minor units. */
            'discount_value' => $percent
                ? (int) round((float) $data['discount_value'])
                : (int) round(((float) $data['discount_value']) * 100),
            'applies_to' => $data['applies_to'],
            'location_mode' => $data['location_mode'],
            'eligibility' => $data['eligibility'],
            'starts_on' => $data['starts_on'],
            /* "No expiry" wins over whatever is in the date box, so a
               standing offer cannot be given an end date by a stale field. */
            'ends_on' => ($data['no_expiry'] ?? false) ? null : ($data['ends_on'] ?? null),
            /* Every day is stored as null rather than as all seven: the two
               mean the same thing and one of them survives a week being
               added to the calendar. */
            'days' => count($data['days'] ?? []) === 7 ? null : ($data['days'] ?? null),
            'min_spend_minor' => ($data['min_spend'] ?? null) === null
                ? null
                : (int) round(((float) $data['min_spend']) * 100),
            'total_limit' => $data['total_limit'] ?? null,
            'per_client_limit' => $data['per_client_limit'] ?? null,
            'allow_online' => (bool) ($data['allow_online'] ?? false),
            'combinable' => (bool) ($data['combinable'] ?? false),
            'is_draft' => (bool) ($data['is_draft'] ?? false),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function syncRelations(Promotion $promotion, array $data): void
    {
        $promotion->services()->sync($data['applies_to'] === 'services' ? ($data['services'] ?? []) : []);
        $promotion->serviceCategories()->sync($data['applies_to'] === 'categories' ? ($data['categories'] ?? []) : []);
        $promotion->locations()->sync($data['location_mode'] === 'selected' ? ($data['locations'] ?? []) : []);
        $promotion->clients()->sync($data['eligibility'] === 'selected' ? ($data['clients'] ?? []) : []);
    }

    /** "All services", or the four it actually covers. */
    private function appliesLabel(Promotion $promotion): string
    {
        return match ($promotion->applies_to) {
            'services' => $promotion->services->pluck('name')->join(', ') ?: __('promotions.applies.services'),
            'categories' => $promotion->serviceCategories->pluck('name')->join(', ') ?: __('promotions.applies.categories'),
            default => __('promotions.applies.'.$promotion->applies_to),
        };
    }

    /** A code the business is not already using. */
    private function freeCode(string $code): string
    {
        $base = Str::limit($code, 34, '');

        for ($suffix = 2; $suffix < 100; $suffix++) {
            $candidate = mb_strtoupper($base.'-'.$suffix);

            if (! Promotion::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return mb_strtoupper(Str::random(10));
    }

    /** @return array<int, array<string, mixed>> */
    private function rowMenu(Promotion $promotion): array
    {
        $canEdit = request()->user()?->hasPermission('clients.edit', 'own') ?? false;

        return array_values(array_filter([
            ['label' => __('promotions.actions.view'), 'url' => route('promotions.show', $promotion)],
            $canEdit ? ['label' => __('promotions.actions.edit'), 'url' => route('promotions.edit', $promotion)] : null,
            $canEdit ? ['label' => __('promotions.actions.duplicate'), 'url' => route('promotions.duplicate', $promotion)] : null,
        ]));
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'active' => Promotion::query()->ofStatus('active')->count(),
            'scheduled' => Promotion::query()->ofStatus('scheduled')->count(),
            'expired' => Promotion::query()->ofStatus('expired')->count(),
            'redemptions' => PromotionRedemption::query()->count(),
        ];
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($status, Promotion::STATUSES, true) ? $status : '',
            'type' => in_array($type, Promotion::TYPES, true) ? $type : '',
            'location' => (string) $request->query('location', ''),
        ];
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission, 'own'), 403);
    }
}
