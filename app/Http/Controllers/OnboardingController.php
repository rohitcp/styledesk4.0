<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookingSettings;
use App\Models\BusinessType;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Services\ServiceImageSync;
use App\Support\InputCase;
use App\Support\LocationOptions;
use App\Support\Subdomain;
use App\Support\WebsiteAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The six-step setup wizard.
 *
 * Progress is read from and written to `tenant_onboarding`, never inferred
 * from the request or from browser storage — the prototype's own module says
 * completion must not be decided front-end side.
 *
 * Steps 3-5 are skippable by design; each has an "I'll do this later" link in
 * the prototype. Skipping advances current_step without setting the step's
 * completed flag, so it stays distinguishable from having actually done it.
 */
class OnboardingController extends Controller
{
    /**
     * Ordered step ids for the progress indicator.
     *
     * 'account' is shown and always complete: creating the account and
     * verifying the address is what got the user here, and the spec's
     * indicator names it. It is not a route, so it is excluded when working
     * out where to send someone next.
     */
    private const STEPS = ['account', 'business', 'location', 'services', 'team', 'booking', 'complete'];

    /** The steps that are actually routes in the wizard. */
    private const FORM_STEPS = ['business', 'location', 'services', 'team', 'booking', 'complete'];

    /**
     * Subdomains that must never become a tenant slug: they either already
     * resolve to something else or would be mistaken for infrastructure.
     */
    /** Trial length in days, per spec section 18. */
    private const TRIAL_DAYS = 14;

    /** Section 13. Roles are permissions; job title is what clients see. */
    public const ROLES = ['owner', 'administrator', 'manager', 'front-desk', 'service-provider'];

    /**
     * Reserved subdomains, kept with the rest of the address rules.
     *
     * Held here as an alias so existing references keep working, but there is
     * one list: the browser's live check and the server's validation have to
     * agree, and two copies is a form that says "Available" and then refuses
     * to save.
     */
    private const RESERVED_SLUGS = Subdomain::RESERVED;

    // ---------------------------------------------------------------- step 1

    public function business(Request $request): View
    {
        return view('onboarding.business', [
            'tenant' => $request->user()->tenant,
            'businessTypes' => BusinessType::active()->get(),
            'countries' => LocationOptions::operatingCountries(),
            'currencies' => $this->currencyOptions(),
            'countryCurrencies' => config('currencies.country_currencies'),
            'languages' => config('currencies.languages'),
            'selectedCountries' => $request->user()->tenant?->countries->pluck('country_code')->all() ?? [],
            'selectedCurrencies' => $request->user()->tenant?->currencies->pluck('currency_code')->all() ?? [],
            'selectedLanguages' => $request->user()->tenant?->languages->pluck('language_code')->all() ?? [],
            'progress' => $this->progress('business'),
            'previousStep' => $this->previousStep('business'),
        ]);
    }

    public function storeBusiness(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_type_ids' => ['required', 'array', 'min:1'],
            'business_type_ids.*' => ['integer', 'exists:business_types,id'],
            // Optional: generated from the name when left blank, but still
            // validated when the user overrides the suggestion.
            'slug' => [
                'nullable', 'string', 'max:60',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(self::RESERVED_SLUGS),
                Rule::unique('tenants', 'slug')->ignore($tenantId, 'id'),
            ],
            // Arrays, first entry primary. min:1 rather than required alone,
            // so an empty array is refused as clearly as a missing field.
            'country_codes' => ['required', 'array', 'min:1'],
            /* The markets we sell into, not every country we can store an
               address in — see config/locations.php. */
            'country_codes.*' => [Rule::in(config('locations.operating_countries'))],
            /**
             * Primary and secondary are separate fields, then composed into
             * one ordered list. Validating them apart is what lets "the
             * primary is required" and "a secondary may not repeat it" both be
             * stated plainly.
             */
            'currency_code' => ['required', Rule::in(array_keys(config('currencies.currencies')))],
            'secondary_currency_codes' => ['nullable', 'array'],
            'secondary_currency_codes.*' => [
                Rule::in(array_keys(config('currencies.currencies'))),
                'different:currency_code',
            ],
            /* Every language StyleDesk offers, whichever countries were
               chosen: where a business operates and what it serves clients in
               are separate questions. */
            'default_language' => ['required', 'string', Rule::in(array_keys(config('currencies.languages')))],
            'secondary_language_codes' => ['nullable', 'array'],
            'secondary_language_codes.*' => [
                Rule::in(array_keys(config('currencies.languages'))),
                'different:default_language',
            ],
            'business_phone' => ['required', 'string', 'max:32'],
            'business_phone_country' => ['nullable', 'string', 'size:2'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'website_scheme' => ['nullable', Rule::in(WebsiteAddress::schemes())],
            /*
             * Checked as the whole address, not as the fragment that was
             * typed: the scheme lives in the dropdown beside it, so "hello
             * world" used to pass a string rule and be stored as
             * "https://hello world".
             *
             * Shared with Business Settings, which asks the same question
             * about the same field — two copies of this would answer it
             * differently the moment either was touched.
             */
            'website' => ['nullable', 'string', 'max:255', WebsiteAddress::rule($request->input('website_scheme'))],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ], [
            'business_type_ids.required' => 'Choose at least one business type.',
            'slug.regex' => 'Use lowercase letters, numbers and hyphens only.',
            'slug.not_in' => 'That address is reserved. Please choose another.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
        ]);

        $attributes = [
            'name' => InputCase::sentence($data['name']),
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name'], $tenantId),
            'business_phone' => $data['business_phone'] ?? null,
            'business_phone_country' => $data['business_phone_country'] ?? null,
            'business_email' => $data['business_email'] ?? null,
            'website' => $this->joinWebsite($data['website_scheme'] ?? null, $data['website'] ?? null),
            'default_language' => $data['default_language'],
        ];

        if ($request->hasFile('logo')) {
            // No-JavaScript path: the file rides along with the form.
            $attributes['logo_path'] = $request->file('logo')->store('logos', 'brand');
        } elseif ($uploaded = $request->session()->pull('onboarding.logo_path')) {
            // Already uploaded asynchronously by the progress-bar flow.
            $attributes['logo_path'] = $uploaded;
        }

        DB::transaction(function () use ($user, $tenantId, $attributes, $data) {
            if ($tenantId === null) {
                // First pass: this is what brings the tenant into existence and
                // gives the user something for tenancy to resolve from.
                //
                // Payment is never required to sign up, so the workspace opens
                // on a trial and the subscription module converts it later.
                $tenant = Tenant::create($attributes + [
                    'status' => Tenant::STATUS_TRIAL,
                    'owner_user_id' => $user->id,
                    'subscription_status' => 'trialing',
                    'trial_started_at' => now(),
                    'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                ]);
                $tenant->domains()->create(['domain' => $tenant->slug]);

                $user->tenant_id = $tenant->getTenantKey();
                $user->save();
            } else {
                $tenant = Tenant::findOrFail($tenantId);
                $tenant->update($attributes);

                // Keep the booking subdomain in step with a renamed slug.
                $tenant->domains()->delete();
                $tenant->domains()->create(['domain' => $tenant->slug]);
            }

            $tenant->businessTypes()->sync($data['business_type_ids']);

            // Both write their own mirrored primary, so country_code and
            // currency_code are set here rather than in $attributes.
            $tenant->syncCountries($data['country_codes']);

            // Primary first, then the rest. array_unique guards the case where
            // a stale form posts the primary among the secondaries.
            $tenant->syncCurrencies(array_merge(
                [$data['currency_code']],
                $data['secondary_currency_codes'] ?? []
            ));

            $tenant->syncLanguages(array_merge(
                [$data['default_language']],
                $data['secondary_language_codes'] ?? []
            ));

            $this->onboardingFor($tenant)->update([
                'business_completed' => true,
                'current_step' => 'location',
            ]);
        });

        return redirect()->route('onboarding.location');
    }

    /**
     * Asynchronous logo upload, so the progress row shows real progress.
     *
     * The prototype fakes this with FileReader events and says so; a real
     * upload is the only way the percentage means anything. It also has to
     * work before the tenant exists — this is step 1 — so the stored path is
     * parked in the session and picked up when the business is saved.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ], [
            'logo.max' => 'The logo must be 2 MB or smaller.',
        ]);

        $path = $request->file('logo')->store('logos', 'brand');

        $request->session()->put('onboarding.logo_path', $path);

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('brand')->url($path),
        ]);
    }

    // ---------------------------------------------------------------- step 2

    public function location(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('onboarding.location', [
            'location' => $tenant?->locations()->where('is_primary', true)->first(),
            /**
             * Carried from the business step rather than asked again. The spec
             * requires the country chosen there to drive the country-specific
             * options on every later screen, so offering a second country
             * field here would let the two disagree.
             */
            'countryCode' => $tenant?->countryCode() ?? 'US',
            'countryName' => config('locations.countries.'.($tenant?->countryCode() ?? 'US')),
            'regions' => config('locations.regions'),
            'regionTimezones' => config('locations.region_timezones'),
            'countryTimezones' => config('locations.country_timezones'),
            'timezones' => $this->timezoneOptions(),
            'progress' => $this->progress('location'),
            'previousStep' => $this->previousStep('location'),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            // Not accepted from the request: the country belongs to the
            // tenant, and taking it from input would let a crafted form set a
            // location in a country the business never chose.
            'country' => ['nullable'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['required', 'timezone'],
            'hours' => ['array'],
            'hours.*.is_open' => ['nullable'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ]);

        $data = InputCase::apply($data, ['name', 'city', 'state']);

        DB::transaction(function () use ($tenant, $data) {
            $location = $tenant->locations()->updateOrCreate(
                ['is_primary' => true],
                collect($data)->except('hours')->merge(['country' => $tenant->countryCode()])->all()
            );

            foreach (range(0, 6) as $day) {
                $row = $data['hours'][$day] ?? [];
                $isOpen = (bool) ($row['is_open'] ?? false);

                $location->hours()->updateOrCreate(['day_of_week' => $day], [
                    'is_open' => $isOpen,
                    'opens_at' => $isOpen ? ($row['opens_at'] ?? '09:00') : null,
                    'closes_at' => $isOpen ? ($row['closes_at'] ?? '17:00') : null,
                ]);
            }

            $this->onboardingFor($tenant)->update([
                'location_completed' => true,
                // Hours are captured on the same screen, so they complete
                // together; they stay separate flags because the setup
                // checklist reports them as separate items.
                'hours_completed' => true,
                'current_step' => 'services',
            ]);
        });

        return redirect()->route('onboarding.services');
    }

    // ---------------------------------------------------------------- step 3

    public function services(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('onboarding.services', [
            'services' => $tenant->services()->with(['category', 'prices'])->get(),
            'maxOtherImages' => ServiceImageSync::MAX_IMAGES - 1,
            /**
             * One price field per configured currency, primary first. Read
             * from the tenant rather than chosen here — the service screen must
             * not be able to decide which currency is primary.
             */
            'tenantCurrencies' => $tenant->currencies->map(fn ($c) => [
                'code' => $c->currency_code,
                'label' => config('currencies.currencies.'.$c->currency_code.'.name', $c->currency_code),
                'symbol' => config('currencies.currencies.'.$c->currency_code.'.symbol', ''),
                'primary' => $c->currency_code === $tenant->currency_code,
            ])->values()->all(),
            'categories' => ServiceCategory::assignable()->get(['id', 'name']),
            'canCreateCategory' => $request->user()->can('create', ServiceCategory::class),
            'progress' => $this->progress('services'),
            'previousStep' => $this->previousStep('services'),
        ]);
    }

    public function storeServices(Request $request, ServiceImageSync $images): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'services' => ['array'],
            'services.*.name' => ['required', 'string', 'max:255'],
            /**
             * Validated against the tenant-scoped model, not a bare exists
             * rule: an id belonging to another tenant must fail here rather
             * than link a service across the boundary.
             */
            'services.*.service_category_id' => [
                'nullable', 'integer',
                function (string $attribute, $value, \Closure $fail) {
                    if ($value !== null && ! ServiceCategory::whereKey($value)->exists()) {
                        $fail('That service category is not available.');
                    }
                },
            ],
            'services.*.duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'services.*.prices' => ['nullable', 'array'],
            // Keys are validated against the tenant's own currencies, so a
            // crafted form cannot price a service in a currency the business
            // has not enabled.
            'services.*.prices.*' => ['nullable', 'numeric', 'min:0'],
            'services.*.description' => ['nullable', 'string', 'max:2000'],
            'services.*.online_booking_enabled' => ['nullable'],
            'services.*.taxable' => ['nullable'],
            'services.*.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            /**
             * Ids of pictures already uploaded, not files. The upload happened
             * as the reader chose each one — see ServiceImageController — so
             * what arrives here is a list of stored_files ids.
             *
             * Not checked against the database by an `exists` rule: ownership,
             * category and count are all enforced in ServiceImageSync, which
             * is the one place that has to get it right for both this step and
             * the Services module.
             */
            'services.*.images' => ['nullable', 'array', 'max:'.ServiceImageSync::MAX_IMAGES],
            'services.*.images.*' => ['integer'],
            'services.*.default_image_id' => ['nullable', 'integer'],
        ], [
            'services.*.images.max' => __('services.images.too_many', ['max' => ServiceImageSync::MAX_IMAGES - 1]),
        ]);

        $data = InputCase::apply($data, ['services.*.name']);

        DB::transaction(function () use ($tenant, $data, $images) {
            $tenant->services()->delete();

            $allowedCurrencies = $tenant->currencies->pluck('currency_code')->all();

            /** @var list<int> ids of every picture a row laid claim to */
            $claimed = [];

            foreach ($data['services'] ?? [] as $row) {
                $service = Service::create([
                    'name' => $row['name'],
                    'service_category_id' => $row['service_category_id'] ?? null,
                    'duration_minutes' => $row['duration_minutes'],
                    'description' => $row['description'] ?? null,
                    'online_booking_enabled' => (bool) ($row['online_booking_enabled'] ?? false),
                    'taxable' => (bool) ($row['taxable'] ?? false),
                    'color' => $row['color'] ?? null,
                ]);

                $service->syncPrices(
                    collect($row['prices'] ?? [])
                        ->only($allowedCurrencies)
                        ->all()
                );

                $claimed = array_merge($claimed, $images->sync(
                    $service,
                    $row['images'] ?? [],
                    $row['default_image_id'] ?? null,
                ));
            }

            /**
             * This step rewrites the whole price list — every service is
             * deleted and recreated — so a picture the reader took off a row
             * is now attached to a service that no longer exists. Nothing else
             * would ever collect it.
             */
            $images->pruneUnclaimed($claimed);

            $this->onboardingFor($tenant)->update([
                'services_completed' => true,
                'current_step' => 'team',
            ]);
        });

        return redirect()->route('onboarding.team');
    }

    // ---------------------------------------------------------------- step 4

    public function team(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('onboarding.team', [
            'tenant' => $tenant,
            'owner' => $request->user(),
            'staff' => $tenant->staff()->with('services')->get(),
            'services' => $tenant->services()->get(),
            /**
             * Invitations already sent, newest last so the list reads in the
             * order they were added. Rendered server-side as well as through
             * the island, so a reload after sending shows the same list.
             */
            'invitations' => $tenant->teamInvitations()->with(['location', 'services'])->orderBy('id')->get(),
            /**
             * Offered only when there is a choice to make. A single-location
             * business has nothing to assign, and a select with one option is
             * a question with one answer.
             */
            'locations' => $tenant->locations()->count() > 1
                ? $tenant->locations()->orderByDesc('is_primary')->get(['id', 'name'])
                : collect(),
            'progress' => $this->progress('team'),
            'previousStep' => $this->previousStep('team'),
        ]);
    }

    public function storeTeam(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $user = $request->user();

        $data = $request->validate([
            'provides_services' => ['required', 'boolean'],
            'owner_services' => ['array'],
            'owner_services.*' => ['integer', 'exists:services,id'],
        ]);

        DB::transaction(function () use ($user, $data) {
            // The owner is always staff member one — the prototype's team step
            // renders them pre-populated rather than asking them to add
            // themselves.
            $owner = Staff::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => 'owner',
                    'provides_services' => $data['provides_services'],
                ]
            );

            // Only a provider has services. Answering "no" clears any earlier
            // selection rather than leaving orphaned rows behind.
            $owner->services()->sync($data['provides_services'] ? ($data['owner_services'] ?? []) : []);

            $this->onboardingFor($user->tenant)->update([
                'team_completed' => true,
                'current_step' => 'booking',
            ]);
        });

        return redirect()->route('onboarding.booking');
    }

    // ---------------------------------------------------------------- step 5

    public function booking(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('onboarding.booking', [
            'tenant' => $tenant,
            'settings' => $tenant->bookingSettings,
            'progress' => $this->progress('booking'),
            'previousStep' => $this->previousStep('booking'),
        ]);
    }

    public function storeBooking(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'is_enabled' => ['nullable'],
            'allow_new_clients' => ['nullable'],
            'allow_existing_clients' => ['nullable'],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:730'],
            'cancellation_window_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'require_card' => ['nullable'],
            'require_email' => ['nullable'],
            'require_phone' => ['nullable'],
        ]);

        DB::transaction(function () use ($tenant, $data, $request) {
            BookingSettings::updateOrCreate(['tenant_id' => $tenant->getTenantKey()], [
                'is_enabled' => $request->boolean('is_enabled'),
                'allow_new_clients' => $request->boolean('allow_new_clients'),
                'allow_existing_clients' => $request->boolean('allow_existing_clients'),
                'min_notice_minutes' => $data['min_notice_minutes'],
                'max_advance_days' => $data['max_advance_days'],
                'cancellation_window_hours' => $data['cancellation_window_hours'],
                'require_card' => $request->boolean('require_card'),
                'require_email' => $request->boolean('require_email'),
                'require_phone' => $request->boolean('require_phone'),
            ]);

            $this->onboardingFor($tenant)->update([
                'booking_completed' => true,
                'current_step' => 'complete',
            ]);
        });

        return redirect()->route('onboarding.complete');
    }

    // ---------------------------------------------------------------- step 6

    public function complete(Request $request): View
    {
        $tenant = $request->user()->tenant;
        $onboarding = $this->onboardingFor($tenant);

        if (! $onboarding->isComplete()) {
            $onboarding->update(['completed_at' => now()]);
            $tenant->update(['status' => Tenant::STATUS_ACTIVE]);
        }

        return view('onboarding.complete', [
            'tenant' => $tenant,
            'progress' => $this->progress('complete'),
            /**
             * Section 16. Skipped items are reported differently from done
             * ones — telling someone "Services ✓" when they pressed "I'll do
             * this later" is simply untrue, and hides work they still owe.
             */
            'checklist' => [
                ['label' => 'Account created', 'done' => true],
                ['label' => 'Email verified', 'done' => $request->user()->hasVerifiedEmail()],
                ['label' => 'Business created', 'done' => $onboarding->business_completed],
                ['label' => 'Location created', 'done' => $onboarding->location_completed],
                ['label' => 'Business hours configured', 'done' => $onboarding->hours_completed],
                ['label' => 'Services configured', 'done' => $onboarding->services_completed],
                ['label' => 'Team configured', 'done' => $onboarding->team_completed],
                ['label' => 'Online booking configured', 'done' => $onboarding->booking_completed],
            ],
        ]);
    }

    // ---------------------------------------------------------------- skip

    /**
     * "I'll do this later". Advances position without claiming the step was
     * done, so a skipped step and a finished one stay distinguishable.
     */
    public function skip(Request $request, string $step): RedirectResponse
    {
        abort_unless(in_array($step, ['services', 'team', 'booking'], true), 404);

        $next = self::FORM_STEPS[array_search($step, self::FORM_STEPS, true) + 1];

        $this->onboardingFor($request->user()->tenant)->update(['current_step' => $next]);

        return redirect()->route('onboarding.'.$next);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The step before this one, or null on the first.
     *
     * Going back must never look like progress: it does not touch
     * current_step, so leaving a step to re-read an earlier one cannot rewind
     * what the wizard believes has been completed.
     */
    /**
     * Timezones with their current UTC offset.
     *
     * The offset is computed now rather than stored, so the label follows
     * daylight saving instead of drifting half the year.
     *
     * @return array<string, string>
     */
    private function timezoneOptions(): array
    {
        $options = [];

        foreach (config('locations.timezones') as $identifier => $label) {
            $offset = (new \DateTimeZone($identifier))->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));
            $sign = $offset < 0 ? '−' : '+';
            $offset = abs($offset);

            $options[$identifier] = sprintf(
                '(GMT%s%02d:%02d) %s',
                $sign,
                intdiv($offset, 3600),
                intdiv($offset % 3600, 60),
                $label
            );
        }

        return $options;
    }

    private function previousStep(string $current): ?string
    {
        $index = array_search($current, self::FORM_STEPS, true);

        return $index > 0 ? self::FORM_STEPS[$index - 1] : null;
    }

    private function onboardingFor(Tenant $tenant): TenantOnboarding
    {
        return TenantOnboarding::firstOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            ['current_step' => 'business']
        );
    }

    /**
     * Step list decorated for the progress bar.
     *
     * @return array<int, array{id: string, label: string, current: bool, done: bool}>
     */
    private function progress(string $current): array
    {
        $onboarding = request()->user()?->tenant?->onboarding;
        $currentIndex = array_search($current, self::STEPS, true);

        return collect(self::STEPS)->map(fn (string $step, int $i) => [
            'id' => $step,
            'label' => Str::headline($step),
            'current' => $step === $current,
            // 'account' has no flag of its own: reaching the wizard at all
            // means it is done.
            'done' => $step === 'account'
                || ($onboarding !== null && $i < $currentIndex && $onboarding->hasCompleted($step)),
        ])->all();
    }

    /**
     * Slug for the booking URL.
     *
     * Generated from the business name when the user has not overridden it,
     * with a numeric suffix on collision — "bella-beauty-studio" becomes
     * "bella-beauty-studio-2" rather than failing validation and making the
     * user invent one.
     */
    /**
     * Whether an address is free, asked while the user is still typing.
     *
     * The same three questions the validator asks, in the same order, so the
     * answer here and the answer on submit cannot disagree: is it a legal
     * shape, is it ours to give, and has somebody taken it.
     */
    public function slugAvailability(Request $request): JsonResponse
    {
        $slug = Subdomain::normalise($request->query('slug'));
        $tenantId = $request->user()->tenant_id;

        if ($slug === '') {
            return response()->json(['status' => 'empty', 'slug' => $slug]);
        }

        if (! Subdomain::isValid($slug)) {
            return response()->json(['status' => 'invalid', 'slug' => $slug]);
        }

        if (Subdomain::isReserved($slug)) {
            return response()->json(['status' => 'reserved', 'slug' => $slug]);
        }

        $taken = Tenant::where('slug', $slug)
            ->when($tenantId, fn ($query) => $query->where('id', '!=', $tenantId))
            ->exists();

        return response()->json(['status' => $taken ? 'taken' : 'available', 'slug' => $slug]);
    }

    private function resolveSlug(?string $given, string $name, ?string $tenantId): string
    {
        /* The user's own value is normalised rather than re-slugged, so a
           hyphen they typed on purpose survives; a name is reduced to letters
           and digits, so "Bell Body" becomes "bellbody" rather than
           "bell-body". */
        $base = ($given !== null && $given !== '')
            ? Subdomain::normalise($given)
            : Subdomain::fromName($name);

        $base = $base !== '' ? $base : 'business';

        if (in_array($base, self::RESERVED_SLUGS, true)) {
            $base .= '-business';
        }

        $slug = $base;
        $suffix = 1;

        while (Tenant::where('slug', $slug)->when($tenantId, fn ($q) => $q->where('id', '!=', $tenantId))->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function joinWebsite(?string $scheme, ?string $host): ?string
    {
        return WebsiteAddress::join($scheme, $host);
    }

    /**
     * Currencies labelled the way the specification shows them.
     *
     * @return array<string, string>
     */
    private function currencyOptions(): array
    {
        $options = [];

        foreach (config('currencies.currencies') as $code => $currency) {
            $options[$code] = sprintf('%s — %s (%s)', $code, $currency['name'], $currency['symbol']);
        }

        return $options;
    }
}
