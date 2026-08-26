<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BusinessType;
use App\Models\Tenant;
use App\Support\InputCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Business settings module: company-level information only.
 *
 * Locations, hours, currencies, languages, booking rules and branding are
 * summarised here but configured in their own modules. Without that line the
 * Business screen becomes the place every setting eventually lands.
 *
 * Access is Owner/Administrator, enforced by the `can-manage-settings`
 * middleware on the route group — this controller does not re-check it,
 * because a second copy of the rule is a second thing to keep in step.
 */
class BusinessSettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('settings.business.show', $this->profile($request->user()->tenant));
    }

    public function edit(Request $request): View
    {
        $tenant = $request->user()->tenant;

        return view('settings.business.edit', [
            ...$this->profile($tenant),
            'businessTypes' => BusinessType::where('is_active', true)->orderBy('sort_order')->get(),
            'selectedTypes' => $tenant->businessTypes->pluck('id')->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'business_category' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'business_type_ids' => ['nullable', 'array'],
            'business_type_ids.*' => ['integer', Rule::exists('business_types', 'id')],
            'status' => ['required', Rule::in(['active', 'inactive'])],

            'business_email' => ['required', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:32'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'booking_email' => ['nullable', 'email', 'max:255'],
            /**
             * A URL, not merely a string containing a dot.
             *
             * These become links on the public booking profile later, so a
             * value that is not a URL is a broken link on a page clients see
             * rather than a cosmetic problem in settings.
             */
            'website' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'google_business_url' => ['nullable', 'url', 'max:255'],

            'date_format' => ['nullable', Rule::in(array_keys(config('business_profile.date_formats')))],
            'time_format' => ['nullable', Rule::in(array_keys(config('business_profile.time_formats')))],
            'first_day_of_week' => ['nullable', Rule::in(array_keys(config('business_profile.first_day_of_week')))],

            'default_booking_duration' => ['nullable', Rule::in(array_keys(config('business_profile.booking_durations')))],
            'default_appointment_interval' => ['nullable', Rule::in(array_keys(config('business_profile.appointment_intervals')))],
            'default_tax_behavior' => ['nullable', Rule::in(array_keys(config('business_profile.tax_behaviors')))],
            'default_staff_assignment' => ['nullable', Rule::in(array_keys(config('business_profile.staff_assignment')))],
        ]);

        // The project capitalisation rule. Emails and URLs are deliberately
        // excluded: case there is not the business's to choose.
        $data = InputCase::apply($data, ['name', 'legal_name', 'business_category', 'description']);

        $typeIds = $data['business_type_ids'] ?? [];
        unset($data['business_type_ids']);

        $tenant->forceFill($data)->save();
        $tenant->businessTypes()->sync($typeIds);

        /**
         * The toast is flashed either way, and the read-only view renders it.
         *
         * The form posts over fetch so that validation errors appear without
         * losing what was typed; on success it follows `redirect` here. Both
         * paths therefore end on the same page with the same confirmation, and
         * the form still works with no JavaScript at all.
         */
        $request->session()->flash('toast', [
            'type' => 'success',
            'message' => 'Business settings updated successfully.',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('settings.business.show')]);
        }

        return redirect()->route('settings.business.show');
    }

    /**
     * Everything both the view and the edit screen need.
     *
     * Assembled once so the two cannot disagree about where a value comes
     * from — the read-only screen showing a location's address while the form
     * edits the tenant's would stay invisible until someone compared them.
     *
     * @return array<string, mixed>
     */
    private function profile(Tenant $tenant): array
    {
        $tenant->loadMissing(['businessTypes', 'currencies', 'languages']);

        return [
            'tenant' => $tenant,
            // The primary address is a Location, not a tenant field. Shown
            // read-only here with a link out, per the spec.
            'primaryLocation' => $tenant->locations()->orderByDesc('is_primary')->first(),
            'locationCount' => $tenant->locations()->count(),
            'bookingSettings' => $tenant->bookingSettings,
            'currencies' => $tenant->currencies->pluck('currency_code')->all(),
            'languages' => $tenant->languages->pluck('language_code')->all(),
        ];
    }
}
