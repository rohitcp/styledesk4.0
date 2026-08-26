<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BookingSettings;
use App\Models\BusinessType;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    /** Ordered step ids. The last one is a confirmation screen, not a form. */
    private const STEPS = ['business', 'location', 'services', 'team', 'booking', 'complete'];

    /**
     * Subdomains that must never become a tenant slug: they either already
     * resolve to something else or would be mistaken for infrastructure.
     */
    /** Trial length in days, per spec section 18. */
    private const TRIAL_DAYS = 14;

    private const RESERVED_SLUGS = [
        'www', 'app', 'admin', 'api', 'mail', 'billing', 'status',
        'support', 'help', 'blog', 'static', 'assets', 'cdn',
    ];

    // ---------------------------------------------------------------- step 1

    public function business(Request $request): View
    {
        return view('onboarding.business', [
            'tenant' => $request->user()->tenant,
            'businessTypes' => BusinessType::active()->get(),
            'progress' => $this->progress('business'),
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
            'business_phone' => ['required', 'string', 'max:32'],
            'business_phone_country' => ['nullable', 'string', 'size:2'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'website_scheme' => ['nullable', 'string', 'max:16'],
            'website' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ], [
            'business_type_ids.required' => 'Choose at least one business type.',
            'slug.regex' => 'Use lowercase letters, numbers and hyphens only.',
            'slug.not_in' => 'That address is reserved. Please choose another.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
        ]);

        $attributes = [
            'name' => $data['name'],
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name'], $tenantId),
            'business_phone' => $data['business_phone'] ?? null,
            'business_phone_country' => $data['business_phone_country'] ?? null,
            'business_email' => $data['business_email'] ?? null,
            'website' => $this->joinWebsite($data['website_scheme'] ?? null, $data['website'] ?? null),
        ];

        if ($request->hasFile('logo')) {
            $attributes['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        DB::transaction(function () use ($user, $tenantId, $attributes, $data) {
            if ($tenantId === null) {
                // First pass: this is what brings the tenant into existence and
                // gives the user something for tenancy to resolve from.
                //
                // Payment is never required to sign up, so the workspace opens
                // on a trial and the subscription module converts it later.
                $tenant = Tenant::create($attributes + [
                    'status' => 'trial',
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

            $this->onboardingFor($tenant)->update([
                'business_completed' => true,
                'current_step' => 'location',
            ]);
        });

        return redirect()->route('onboarding.location');
    }

    // ---------------------------------------------------------------- step 2

    public function location(Request $request): View
    {
        return view('onboarding.location', [
            'location' => $request->user()->tenant?->locations()->where('is_primary', true)->first(),
            'progress' => $this->progress('location'),
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
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['required', 'timezone'],
            'hours' => ['array'],
            'hours.*.is_open' => ['nullable'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($tenant, $data, $request) {
            $location = $tenant->locations()->updateOrCreate(
                ['is_primary' => true],
                collect($data)->except('hours')->all()
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

            // Never asked for during onboarding, so it is inferred once from
            // the location and stays editable in Settings.
            $tenant->update(['currency' => $this->currencyFor($data['country'])]);

            $this->onboardingFor($tenant)->update([
                'location_completed' => true,
                'current_step' => 'services',
            ]);
        });

        return redirect()->route('onboarding.services');
    }

    // ---------------------------------------------------------------- step 3

    public function services(Request $request): View
    {
        return view('onboarding.services', [
            'services' => $request->user()->tenant->services()->get(),
            'progress' => $this->progress('services'),
        ]);
    }

    public function storeServices(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'services' => ['array'],
            'services.*.name' => ['required', 'string', 'max:255'],
            'services.*.category' => ['nullable', 'string', 'max:255'],
            'services.*.duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($tenant, $data) {
            $tenant->services()->delete();

            foreach ($data['services'] ?? [] as $row) {
                Service::create([
                    'name' => $row['name'],
                    'category' => $row['category'] ?? null,
                    'duration_minutes' => $row['duration_minutes'],
                    // Minor units: round once here, never do float maths later.
                    'price_minor' => (int) round(((float) ($row['price'] ?? 0)) * 100),
                    'description' => $row['description'] ?? null,
                ]);
            }

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
            'owner' => $request->user(),
            'staff' => $tenant->staff()->with('services')->get(),
            'services' => $tenant->services()->get(),
            'progress' => $this->progress('team'),
        ]);
    }

    public function storeTeam(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $user = $request->user();

        $data = $request->validate([
            'owner_services' => ['array'],
            'owner_services.*' => ['integer', 'exists:services,id'],
            'members' => ['array'],
            'members.*.first_name' => ['required', 'string', 'max:100'],
            'members.*.last_name' => ['required', 'string', 'max:100'],
            'members.*.email' => ['nullable', 'email', 'max:255'],
            'members.*.phone' => ['nullable', 'string', 'max:32'],
            'members.*.role' => ['nullable', 'string', 'max:40'],
        ]);

        DB::transaction(function () use ($tenant, $user, $data) {
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
                ]
            );
            $owner->services()->sync($data['owner_services'] ?? []);

            $tenant->staff()->whereNull('user_id')->delete();

            foreach ($data['members'] ?? [] as $row) {
                Staff::create([
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'role' => $row['role'] ?? 'staff',
                ]);
            }

            $this->onboardingFor($tenant)->update([
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
            $tenant->update(['status' => 'active']);
        }

        return view('onboarding.complete', [
            'tenant' => $tenant,
            'progress' => $this->progress('complete'),
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

        $next = self::STEPS[array_search($step, self::STEPS, true) + 1];

        $this->onboardingFor($request->user()->tenant)->update(['current_step' => $next]);

        return redirect()->route('onboarding.'.$next);
    }

    // ---------------------------------------------------------------- helpers

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
            'done' => $onboarding !== null && $i < $currentIndex && $onboarding->hasCompleted($step),
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
    private function resolveSlug(?string $given, string $name, ?string $tenantId): string
    {
        $base = Str::slug($given ?: $name) ?: 'business';

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
        $host = trim((string) $host);

        return $host === '' ? null : ($scheme ?? 'https://').$host;
    }

    /**
     * Currency is never asked for, so it is inferred from the location's
     * country once and remains editable in Settings.
     */
    private function currencyFor(string $country): string
    {
        return [
            'US' => 'USD', 'CA' => 'CAD', 'GB' => 'GBP', 'AU' => 'AUD',
            'MX' => 'MXN', 'FR' => 'EUR', 'DE' => 'EUR', 'ES' => 'EUR',
        ][strtoupper($country)] ?? 'USD';
    }
}
