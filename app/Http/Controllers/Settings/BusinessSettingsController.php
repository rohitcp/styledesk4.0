<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BusinessType;
use App\Models\Tenant;
use App\Support\EmailAddress;
use App\Support\InputCase;
use App\Support\WebsiteAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

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

            /**
             * Held to what the browser holds them to.
             *
             * Laravel's `email` rule on its own accepts "nadia@salon" — legal
             * on a local network, reaching nobody a salon's clients live on —
             * while the live-validation module has always required a dot in
             * the domain. The same address was refused as it was typed on the
             * sign-up form and accepted here. App\Support\EmailAddress is the
             * one answer both ends now read.
             */
            'business_email' => EmailAddress::rules(required: true),
            'support_email' => EmailAddress::rules(),
            'booking_email' => EmailAddress::rules(),

            'business_phone' => ['nullable', 'string', 'max:32'],
            /* The dialling code, chosen beside the number. The column has
               always existed; the form simply never sent it, so every number
               saved here was a number with no country against it. */
            'business_phone_country' => ['nullable', 'string', 'size:2'],

            /**
             * The scheme is chosen from a list and only the host is typed —
             * "https//" with the colon missing is one of the two commonest
             * things anybody types into a website field, and the other is
             * nothing at all.
             *
             * Checked as the whole address rather than as the typed fragment:
             * "hello world" passes a string rule on its own and is then
             * stored as "https://hello world".
             */
            'website_scheme' => ['nullable', Rule::in(WebsiteAddress::schemes())],
            'website' => ['nullable', 'string', 'max:255', WebsiteAddress::rule($request->input('website_scheme'))],

            /**
             * A URL, not merely a string containing a dot.
             *
             * These become links on the public booking profile later, so a
             * value that is not a URL is a broken link on a page clients see
             * rather than a cosmetic problem in settings.
             */
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
            /* A percentage, not a fraction: 8.5 is how a rate is published,
               and a field that quietly wanted 0.085 would be filled in wrong
               by everybody who read its label. */
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            /* Where money is asked for when it is not handed over at the
               desk. Free text, because a Zelle is an email or a phone number
               and a Venmo is a handle, and validating one shape would reject
               three of the four. */
            'paypal_handle' => ['nullable', 'string', 'max:255'],
            'zelle_handle' => ['nullable', 'string', 'max:255'],
            'cash_app_handle' => ['nullable', 'string', 'max:255'],
            'venmo_handle' => ['nullable', 'string', 'max:255'],
            'default_staff_assignment' => ['nullable', Rule::in(array_keys(config('business_profile.staff_assignment')))],

            /* Only a window the dropdown offers. Null means "follow the
               StyleDesk default", which is what a business that has never
               opened this setting has. */
            'session_timeout_minutes' => ['nullable', Rule::in(array_keys(config('business_profile.session_timeouts')))],
        ], [
            /**
             * Written as instructions rather than as descriptions of a rule.
             *
             * "The business email field must be a valid email address" names
             * the validator; "Enter a valid email address" names what to do,
             * and it is read inches from the box it is about.
             *
             * Translated, because a Spanish form that validates in English
             * hands its reader the one sentence on the page they most need to
             * understand in the one language they did not choose.
             */
            'name.required' => __('business.validation.name_required'),
            'business_email.required' => __('business.validation.email_required'),
            /* Both the rule and the pattern say the same sentence: which of
               the two refused an address is not a distinction the reader can
               act on, and "the format is invalid" is the validator talking
               about itself. */
            'business_email.email' => __('business.validation.email_invalid'),
            'business_email.regex' => __('business.validation.email_invalid'),
            'support_email.email' => __('business.validation.email_invalid'),
            'support_email.regex' => __('business.validation.email_invalid'),
            'booking_email.email' => __('business.validation.email_invalid'),
            'booking_email.regex' => __('business.validation.email_invalid'),
            'instagram_url.url' => __('business.validation.instagram_invalid'),
            'facebook_url.url' => __('business.validation.facebook_invalid'),
            'tiktok_url.url' => __('business.validation.tiktok_invalid'),
            'google_business_url.url' => __('business.validation.google_invalid'),
            'status.required' => __('business.validation.status_required'),
            'status.in' => __('business.validation.status_required'),
            'location_id.exists' => __('business.validation.location_invalid'),
        ]);

        // The project capitalisation rule. Emails and URLs are deliberately
        // excluded: case there is not the business's to choose.
        $data = InputCase::apply($data, ['name', 'legal_name', 'business_category', 'description']);

        /* The address is stored whole, because that is what a link needs. The
           two controls are how it is edited, not how it is kept — the same
           join onboarding does, from the same class. */
        $data['website'] = WebsiteAddress::join($data['website_scheme'] ?? null, $data['website'] ?? null);
        unset($data['website_scheme']);

        $typeIds = $data['business_type_ids'] ?? [];
        unset($data['business_type_ids']);

        try {
            /**
             * Both writes in one transaction.
             *
             * The tenant row and the business-type pivot are one change as far
             * as the user is concerned. Committing the first and failing the
             * second would leave the screen showing a business whose types no
             * longer match what was saved, with nothing to indicate it.
             */
            DB::transaction(function () use ($tenant, $data, $typeIds) {
                $tenant->forceFill($data);

                if ($tenant->save() !== true) {
                    // save() returning false means the write did not happen.
                    // Success must never be reported on the strength of having
                    // reached this line.
                    throw new RuntimeException('The business record reported an unsuccessful save.');
                }

                $tenant->businessTypes()->sync($typeIds);
            });
        } catch (Throwable $e) {
            /**
             * The reason is logged, never shown.
             *
             * A driver message can carry table names, credentials and the
             * shape of the schema; the person who pressed Save can act on none
             * of it and should not be handed it.
             */
            Log::error('Business settings could not be saved.', [
                'tenant_id' => $tenant->getTenantKey(),
                'user_id' => $request->user()->id,
                'exception' => $e->getMessage(),
            ]);

            $message = __('business.save_failed');

            // Stay on the edit page. Redirecting away would discard everything
            // typed, for a failure that was not the user's doing.
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return back()->withInput()->with('toast', ['type' => 'danger', 'message' => $message]);
        }

        /**
         * Re-read before reporting success.
         *
         * The confirmation the user sees should rest on what the database now
         * holds, not on the request having been accepted — and the view that
         * renders next reads this same instance.
         */
        $tenant->refresh();

        $request->session()->flash('toast', [
            'type' => 'success',
            'message' => __('business.saved'),
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
