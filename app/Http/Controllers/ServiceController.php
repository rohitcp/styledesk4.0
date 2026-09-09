<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\TipSettings;
use App\Services\ServiceImageSync;
use App\Support\Currencies;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The work a business sells.
 *
 * Day-to-day management rather than configuration: a price changes, a service
 * stops being offered on Sundays, a new treatment is added the week it is
 * launched. What belongs in App Settings is the catalogue of categories and
 * the rules that govern every service; what lives here is the list itself.
 */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeServices($request, 'services.view');

        return view('services.index', $this->formData() + [
            'filters' => $this->filters($request),
            /* Whether the business has any at all, which is a different
               question from whether this search found any: one is an empty
               module and the other is an empty result. */
            'total' => Service::query()->count(),
            'canCreate' => $request->user()->hasPermission('services.create'),
        ]);
    }

    /**
     * The rows, for the grid.
     *
     * Every value is rendered here rather than in the browser: the durations,
     * the prices and the counts are phrases in the reader's own language, and
     * a grid that assembled them would be a second place for the wording to
     * live.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeServices($request, 'services.view');

        $filters = $this->filters($request);
        $currency = Currencies::primaryFor($request->user()->tenant);

        $canEdit = $request->user()->hasPermission('services.edit', 'all');
        $canCreate = $request->user()->hasPermission('services.create');
        $canToggle = $request->user()->hasPermission('services.toggle_active', 'all');

        /**
         * The grid asks for its own page size and the server decides what it
         * is allowed to be: a page parameter that reached the query unchecked
         * would let anyone ask for every service in one request.
         */
        $size = min(200, max(1, (int) $request->query('size', 100)));

        $services = $this->query($filters);

        /*
         * Paged in memory, because two of the filters cannot be expressed as
         * a where clause: "offered here" includes every service with no
         * locations at all, and both are answered by reading the row. A
         * price list is hundreds of rows rather than hundreds of thousands,
         * so the whole set is a cheap thing to hold.
         */
        $page = (int) $request->query('page', 1);
        $rows = $services->forPage(max(1, $page), $size);

        return response()->json([
            /* Tabulator's own shape: the rows, and how many pages exist so
               it knows when to stop asking. */
            'last_page' => max(1, (int) ceil($services->count() / $size)),
            'last_row' => $services->count(),
            'total' => $services->count(),
            'data' => $rows->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'color' => $service->color,
                /* The category beside the name rather than only in its own
                   column, so the first column answers "which service" on its
                   own once the table narrows and columns start leaving. */
                'primary_badge' => $service->category?->name,
                'category' => $service->category?->name,
                'duration' => $service->durationLabel(),
                /* A column each, because they are two prices and a reader
                   comparing services down a column cannot do it when both
                   sit stacked in one cell. Cash repeats the card price
                   where a service charges the same either way: an empty
                   cell reads as "no cash price", which is a different
                   thing from "the same one". */
                'price' => $service->priceLabel($currency) ?: null,
                'cash_price' => $service->cashPriceLabel($currency) ?: null,
                /* Read from the price row rather than from the service's own
                   summary flag: "20% required" and "yes" are not the same
                   answer, and only one of them is what was configured. */
                'deposit' => $service->depositLabelIn($currency),
                'staff' => $this->summarise($service->staff->count(), $service->staff->first()?->first_name.' '.$service->staff->first()?->last_name, 'services.staff_count', __('services.anyone')),
                'resource' => $service->requires_resource ? __('services.resource_required') : __('services.resource_not_required'),
                /* No fallback word: a service now has to name where it is
                   offered, so an empty cell is a service saved before that
                   rule rather than a business-wide default. */
                'location' => $this->summarise($service->locations->count(), $service->locations->first()?->name, 'services.location_count', ''),

                'online' => $service->online_booking_enabled ? __('services.status.online_enabled') : __('services.status.online_disabled'),
                'online_class' => $service->online_booking_enabled ? 'styledesk_badge--active' : 'styledesk_badge--soon',
                'status' => $service->is_active ? __('services.status.active') : __('services.status.inactive'),
                'status_class' => $service->is_active ? 'styledesk_badge--active' : 'styledesk_badge--soon',

                'url' => route('services.show', $service),
                'menu' => $this->rowMenu($service, $canEdit, $canCreate, $canToggle),
            ])->values()->all(),
        ]);
    }

    /**
     * One name where there is one, a count where there are several.
     *
     * A row listing four stylists is a row twice the height of every other
     * one; a row saying "4 staff" is a row. What no one at all reads as is
     * the caller's to say: "anyone" for staff, nothing for locations.
     */
    private function summarise(int $count, ?string $first, string $key, string $none): string
    {
        return match (true) {
            $count === 0 => $none,
            $count === 1 => trim((string) $first),
            default => trans_choice($key, $count),
        };
    }

    /**
     * One row's actions.
     *
     * Decided here rather than in the browser: which entries a reader may see
     * is a permission question, and a grid that assembled the menu itself
     * would be a second place for that rule to live.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowMenu(Service $service, bool $canEdit, bool $canCreate, bool $canToggle): array
    {
        $menu = [
            ['label' => __('services.view'), 'url' => route('services.show', $service)],
        ];

        if ($canEdit) {
            $menu[] = ['label' => __('common.edit'), 'url' => route('services.edit', $service)];
        }

        if ($canCreate) {
            $menu[] = [
                'label' => __('services.duplicate'),
                'url' => route('services.duplicate', $service),
                'method' => 'POST',
                'confirm' => __('services.duplicate_confirm', ['name' => $service->name]),
                'confirm_title' => __('services.duplicate'),
                'confirm_label' => __('services.duplicate'),
                'tone' => 'brand',
            ];
        }

        if ($canToggle) {
            $label = $service->is_active ? __('services.retire') : __('services.restore');

            $menu[] = ['separator' => true];
            $menu[] = [
                'label' => $label,
                'url' => route('services.toggle', $service),
                'method' => 'PATCH',
                'danger' => $service->is_active,
                'confirm' => $service->is_active ? __('services.retire_confirm') : null,
                'confirm_title' => $service->is_active ? __('services.retire_title') : $label,
                'confirm_label' => $label,
                'tone' => $service->is_active ? 'danger' : 'brand',
            ];
        }

        return $menu;
    }

    /**
     * What the reader asked to see, from the query string.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')),
            'category' => $request->query('category'),
            'location' => (array) $request->query('location', []),
            'staff' => (array) $request->query('staff', []),
            'status' => $request->query('status'),
            'booking' => $request->query('booking'),
            'resource' => $request->query('resource'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Service>
     */
    private function query(array $filters): Collection
    {
        $services = Service::query()
            ->with(['category', 'staff', 'locations', 'prices'])
            ->matching($filters['search'])
            ->when($filters['category'], fn (Builder $q, $id) => $q->where('service_category_id', $id))
            ->when($filters['staff'], fn (Builder $q, array $ids) => $q->whereHas('staff', fn (Builder $s) => $s->whereIn('staff.id', $ids)))
            ->when($filters['status'] === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($filters['booking'] === 'online', fn (Builder $q) => $q->where('online_booking_enabled', true))
            ->when($filters['booking'] === 'internal', fn (Builder $q) => $q->where('online_booking_enabled', false))
            ->when($filters['resource'] === 'required', fn (Builder $q) => $q->where('requires_resource', true))
            ->when($filters['resource'] === 'not_required', fn (Builder $q) => $q->where('requires_resource', false))
            ->inOrder()
            ->get();

        /*
         * Filtered per row rather than in the query: "offered here" includes
         * every service with no locations at all, and a join cannot match
         * rows by the absence of the rows it is joining to without saying so
         * twice. Once the set is in memory the rule is one readable line.
         */
        if ($filters['location']) {
            $services = $services->filter(
                fn (Service $service) => collect($filters['location'])->contains(fn ($id) => $service->isOfferedAt($id))
            );
        }

        return $services->values();
    }

    /**
     * One service, read-only.
     *
     * The listing opens this rather than the form: reading what a service is
     * costs nothing and risks nothing, where landing on an editable form is
     * one stray keystroke away from changing a price. Editing is a step the
     * reader takes deliberately, from here.
     */
    public function show(Request $request, Service $service): View
    {
        $this->authorizeServices($request, 'services.view', $service);

        return view('services.show', [
            'service' => $service->load(['category', 'staff', 'locations', 'resources', 'prices']),
            'currencies' => Currencies::enabledFor($request->user()->tenant),
            'canEdit' => $request->user()->hasPermission('services.edit', 'all'),
        ]);
    }

    /**
     * The add form, on a page of its own.
     *
     * A page rather than a dialog: the form is five sections long, it has to
     * survive a failed validation with everything the user typed still in it,
     * and it is a place — one that can be linked to and returned to.
     */
    public function create(Request $request): View
    {
        $this->authorizeServices($request, 'services.create');

        return view('services.create', $this->formData());
    }

    public function edit(Request $request, Service $service): View
    {
        $this->authorizeServices($request, 'services.edit', $service);

        $service->load(['staff', 'locations', 'resources', 'prices']);

        return view('services.edit', $this->formData() + [
            'service' => $service,
            'canDelete' => $request->user()->hasPermission('services.delete', 'all'),
            /* Keyed by currency, which is the shape the price field reads:
               it renders one row per currency the business prices in. */
            'priceValues' => $service->prices
                ->mapWithKeys(fn ($price) => [$price->currency_code => $price->amount()])
                ->all(),
            /* Blank where a service has only ever had one price: blank means
               "the same as card", which is what it has always charged. */
            'cashPriceValues' => $service->prices
                ->mapWithKeys(fn ($price) => [$price->currency_code => $price->cashAmount()])
                ->all(),
            'deposits' => $service->prices
                ->mapWithKeys(fn ($price) => [$price->currency_code => [
                    'required' => $price->deposit_required,
                    'type' => $price->deposit_type,
                    'value' => $price->depositValue(),
                ]])
                ->all(),
        ]);
    }

    /**
     * The option lists both form pages need.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'categories' => ServiceCategory::query()->assignable()->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'staff' => Staff::query()->where('is_active', true)->orderBy('first_name')->get(),
            /* Retired resources are left out: a service cannot be mapped to a
               room that is no longer in use. One already mapped stays mapped
               — see the edit form, which adds it back to the list. */
            'resources' => Resource::query()->active()->inOrder()->get(),
            /* Whether to ask about tipping at all. A card asking how much to
               suggest, in a salon that has never tipped anybody, is a
               question with no answer. */
            'tips' => TipSettings::forTenant(request()->user()->tenant),
        ];
    }

    public function store(Request $request, ServiceImageSync $images): RedirectResponse
    {
        $this->authorizeServices($request, 'services.create');

        $data = $this->validated($request);

        $service = Service::create(
            $this->columns($data) + ['tenant_id' => $request->user()->tenant->getTenantKey()]
        );

        $this->syncRelations($service, $data, $images);

        /* To the listing, not back: "back" from a form page is the form
           again, which reads as a save that did not take. */
        return redirect()->route('services.index')
            ->with('toast', ['type' => 'success', 'message' => __('services.added')]);
    }

    public function update(Request $request, Service $service, ServiceImageSync $images): RedirectResponse
    {
        $this->authorizeServices($request, 'services.edit', $service);

        $data = $this->validated($request);

        $service->forceFill($this->columns($data))->save();

        $this->syncRelations($service, $data, $images);

        return redirect()->route('services.index')
            ->with('toast', ['type' => 'success', 'message' => __('services.updated')]);
    }

    /**
     * Copy a service, everything but its name and its availability.
     *
     * The copy starts retired. A price list is edited by starting from the
     * nearest thing already on it, and a duplicate that were bookable the
     * instant it appeared would be bookable at the original's price under
     * the original's name until somebody got round to it.
     */
    public function duplicate(Request $request, Service $service): RedirectResponse
    {
        $this->authorizeServices($request, 'services.create', $service);

        $copy = $service->replicate(['created_at', 'updated_at']);
        $copy->name = __('services.copy_of', ['name' => $service->name]);
        $copy->is_active = false;
        /* The pictures are not copied. They are stored_files rows pointed at
           the original, so a replicated image_file_id would leave the copy
           showing a picture its own gallery does not contain — and the copy
           opens straight into its form, where the reader adds its own. */
        $copy->image_file_id = null;
        $copy->save();

        $service->loadMissing(['staff', 'locations', 'resources', 'prices']);

        $copy->staff()->sync($service->staff->pluck('id'));
        $copy->locations()->sync($service->locations->pluck('id'));
        $copy->resources()->sync($service->resources->pluck('id'));

        foreach ($service->prices as $price) {
            $copy->prices()->create($price->only(['currency_code', 'price_minor']));
        }

        /* Straight into the copy's own form. A duplicate is the start of an
           edit — nobody copies a service to leave it exactly as it was — and
           returning to the listing would leave a near-identical row sitting
           under the original for the reader to find again. */
        return redirect()->route('services.edit', $copy)
            ->with('toast', ['type' => 'success', 'message' => __('services.duplicated')])
            ->with('duplicated_from', $service->name);
    }

    /**
     * Retire or bring back.
     *
     * Never a delete: a service that is gone still names the appointments it
     * was booked for, and removing the row would rewrite them.
     */
    public function toggle(Request $request, Service $service): RedirectResponse
    {
        $this->authorizeServices($request, 'services.toggle_active', $service);

        $service->forceFill(['is_active' => ! $service->is_active])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $service->is_active ? __('services.activated') : __('services.deactivated'),
        ]);
    }

    /**
     * Remove a service.
     *
     * Soft, always: a service names the appointments it was booked for, and a
     * hard delete would rewrite last year's takings. Gone from every list,
     * still resolvable from the history that refers to it.
     *
     * Distinct from retiring, which is a service the business still sells and
     * has merely stopped offering — this is one that should not have existed.
     */
    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $this->authorizeServices($request, 'services.delete', $service);

        $service->delete();

        return redirect()->route('services.index')
            ->with('toast', ['type' => 'success', 'message' => __('services.deleted')]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        /* deposit_required is a summary of the prices, written by syncPrices
           rather than posted: the form has a toggle per price and none for
           the service. It is not in the rules either — a field the save
           discards is a field the form should not be offering. */
        /* images and default_image_id are not columns either: the gallery is
           rows in stored_files, and which one leads is written by
           ServiceImageSync once the service has an id to attach them to. */
        return collect($data)
            ->except(['staff', 'locations', 'resources', 'price', 'cash_price', 'deposit', 'images', 'default_image_id'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Service $service, array $data, ServiceImageSync $images): void
    {
        $service->staff()->sync($data['staff'] ?? []);
        $service->locations()->sync($data['locations'] ?? []);

        /* The resources, keeping whatever preference each pairing already
           had. A plain sync writes the pivot's default over it, which would
           silently flatten "a chair first, a bed if the client would rather"
           into "any of these" — the ordering is not on this form, so editing
           a price should not be able to lose it. */
        $existing = $service->resources()->pluck('resource_service.priority', 'resources.id');

        $service->resources()->sync(collect($data['resources'] ?? [])
            ->mapWithKeys(fn ($id) => [(int) $id => ['priority' => (int) ($existing[$id] ?? 0)]])
            ->all());

        $service->load('prices');
        $service->syncPrices($data['price'] ?? [], $data['deposit'] ?? [], $data['cash_price'] ?? []);

        $images->sync($service, $data['images'] ?? [], $data['default_image_id'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $max = (int) config('service_options.max_ancillary_minutes');
        $enabled = Currencies::enabledFor($request->user()->tenant);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'service_category_id' => ['nullable', Rule::exists('service_categories', 'id')],
            'description' => ['nullable', 'string', 'max:1000'],

            'duration_minutes' => ['required', 'integer', 'min:1', 'max:'.config('service_options.max_duration_minutes')],
            'preparation_minutes' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'processing_minutes' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'cleanup_minutes' => ['nullable', 'integer', 'min:0', 'max:'.$max],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:'.$max],

            /* The palette is a shortlist, not the whole set: the last card
               in the form opens a colour picker, so any well-formed hex is a
               colour a business may have chosen. Still validated — an
               unchecked value here reaches a style attribute. */
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['boolean'],
            'online_booking_enabled' => ['boolean'],
            'requires_resource' => ['boolean'],

            /* Tenant-scoped by Rule::exists against the tenant's own rows:
               a hand-made request naming another business's staff id has to
               fail here, not merely be absent from the dropdown. */
            'staff' => ['array'],
            'staff.*' => [Rule::exists('staff', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*' => [Rule::exists('locations', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],

            /**
             * The rooms or chairs this service may be performed in.
             *
             * Required only while the switch is on. requiredIf rather than a
             * closure on the array: a closure rule is skipped when the
             * attribute is absent, and "no resources at all" is exactly the
             * case this has to catch. required also refuses an empty array,
             * so the two shapes of nothing are answered the same way.
             */
            'resources' => ['array', Rule::requiredIf(fn () => $request->boolean('requires_resource'))],

            /* Tipping. Every field optional, and blank on the two that can
               be blank means "whatever the business says" rather than a
               value of its own — so a service that never disagreed moves
               when the business changes its mind. */
            'accepts_tips' => ['nullable', 'boolean'],
            'tip_type' => ['nullable', Rule::in(TipSettings::TYPES)],
            'tip_value' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tip_required' => ['nullable', 'boolean'],
            'allow_no_tip' => ['nullable', 'boolean'],
            'resources.*' => [Rule::exists('resources', 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey())],

            /* Keyed by currency, and only by a currency this business
               actually prices in. Without the second half a request could
               add a price row in a currency the business has switched off,
               which no screen would ever show and every total would quietly
               include. */
            'price' => ['array', function (string $attribute, mixed $value, callable $fail) use ($enabled) {
                foreach (array_keys((array) $value) as $code) {
                    if (! $enabled->contains($code)) {
                        $fail(__('validation.in', ['attribute' => $attribute]));
                    }
                }
            }],
            'price.*' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            /* The cash price, keyed the same way. Deliberately not required
               to be lower than the card price, or equal to it: a business
               that charges the same either way, or more for cash, is not
               doing anything wrong and the form should not argue. */
            'cash_price' => ['array'],
            'cash_price.*' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            /* One deposit per price, keyed the same way. Checked as a whole
               rather than field by field: whether a value is required depends
               on that row's own toggle, which a per-field rule cannot see. */
            'deposit' => ['array', function (string $attribute, mixed $value, callable $fail) {
                foreach ((array) $value as $code => $deposit) {
                    if (! (bool) ($deposit['required'] ?? false)) {
                        continue;
                    }

                    $type = $deposit['type'] ?? null;
                    $amount = $deposit['value'] ?? null;

                    if (! in_array($type, ['fixed', 'percent'], true)) {
                        $fail(__('services.deposit_type_required'));

                        continue;
                    }

                    if ($amount === null || $amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
                        $fail(__('services.deposit_value_required'));

                        continue;
                    }

                    /* A percentage over 100 is a deposit larger than the
                       price, which is a typo rather than a policy. */
                    if ($type === 'percent' && (float) $amount > 100) {
                        $fail(__('services.deposit_percent_range'));
                    }
                }
            }],

            /**
             * Ids of pictures already uploaded, not files: the upload happens
             * as each one is chosen — see ServiceImageController. Ownership,
             * category and count are checked in ServiceImageSync rather than
             * here, so this form and the onboarding wizard cannot disagree
             * about what a service is allowed to carry.
             */
            'images' => ['nullable', 'array', 'max:'.ServiceImageSync::MAX_IMAGES],
            'images.*' => ['integer'],
            'default_image_id' => ['nullable', 'integer'],
        ], [
            'images.max' => __('services.images.too_many', ['max' => ServiceImageSync::MAX_IMAGES - 1]),
            'resources.required' => __('services.resources_required'),
        ]);
    }

    /**
     * Permission, then ownership.
     *
     * The category and staff lists are tenant-scoped by their models, but a
     * service reached by id is not — so the second check is what stops one
     * business editing another's price list by guessing a number.
     */
    private function authorizeServices(Request $request, string $permission, ?Service $service = null): void
    {
        abort_unless($request->user()->hasPermission($permission, 'all'), 403);

        if ($service !== null) {
            abort_unless(
                (string) $service->tenant_id === (string) $request->user()->tenant?->getTenantKey(),
                404,
            );
        }
    }
}
