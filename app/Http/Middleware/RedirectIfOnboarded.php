<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror of EnsureOnboardingIsComplete: a finished account has no reason
 * to be back inside the wizard, so onboarding routes bounce them to the
 * dashboard. Without this the two middlewares would not form a closed loop and
 * a completed user could re-run setup by typing the URL.
 */
class RedirectIfOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only the owner ever runs setup, so anyone else who reaches a wizard
        // URL — usually a new member following a stale link — is sent to the
        // app rather than into someone else's configuration.
        if ($user?->isNotTenantOwner()) {
            return redirect()->route('dashboard');
        }

        $onboarding = $user?->tenant?->onboarding;

        if ($onboarding !== null && $onboarding->isComplete()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
