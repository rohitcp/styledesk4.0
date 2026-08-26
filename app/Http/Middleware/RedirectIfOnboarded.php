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
        $onboarding = $request->user()?->tenant?->onboarding;

        if ($onboarding !== null && $onboarding->isComplete()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
