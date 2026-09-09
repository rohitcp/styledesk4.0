<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps half-set-up accounts out of the application.
 *
 * A user with no tenant has not finished step 1, so there is nothing for the
 * app to show them — every tenant-scoped query would come back empty. They are
 * sent to the step they left off at, which is read from the database rather
 * than from the browser.
 */
class EnsureOnboardingIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->tenant_id === null) {
            return redirect()->route('onboarding.business');
        }

        /**
         * Setup belongs to the owner.
         *
         * A colleague who accepted an invitation mid-setup has a tenant that
         * is not finished, but the wizard edits the business's addresses,
         * hours and services — pushing them into it would hand a new
         * receptionist the business's configuration on their first visit.
         */
        if ($user->isNotTenantOwner()) {
            return $next($request);
        }

        $onboarding = $user->tenant?->onboarding;

        // Minimum setup, not full completion: services, team and booking are
        // skippable by design, so a business with its location and hours in
        // place is allowed into the product even if it never finished the
        // wizard. The dashboard's getting-started checklist picks up the rest.
        if ($onboarding !== null && ! $onboarding->isComplete() && ! $onboarding->meetsMinimumSetup()) {
            return redirect()->route('onboarding.'.$onboarding->current_step);
        }

        return $next($request);
    }
}
