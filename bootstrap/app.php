<?php

use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureCanManageSettings;
use App\Http\Middleware\EnsureOnboardingIsComplete;
use App\Http\Middleware\InitializeTenancyFromRoute;
use App\Http\Middleware\InitializeTenancyFromUser;
use App\Http\Middleware\RedirectIfOnboarded;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * StyleDesk resolves the tenant two different ways.
         *
         *   tenant.subdomain  public booking on *.styledesk.app, from the host
         *   tenant.user       central app on styledesk.app, from users.tenant_id
         *
         * `tenant.central-only` guards routes that must never be reachable on
         * a tenant subdomain (billing, account deletion, the admin console).
         */
        /**
         * Tenancy must resolve after the user is authenticated but before
         * route models are bound.
         *
         * Before Authenticate, $request->user() is null and the middleware
         * passes through without initialising tenancy — every tenant-scoped
         * create then fails because BelongsToTenant has no tenant to stamp.
         * After SubstituteBindings, route models are resolved with no tenant
         * scope, so {serviceCategory} binds another tenant's row.
         *
         * appendToPriorityList pins it between the two.
         */
        $middleware->appendToPriorityList(
            // The priority list names the CONTRACT, not the Authenticate
            // class. Passing the class matches nothing and silently appends to
            // the end of the list — behind SubstituteBindings, which is the
            // opposite of what is wanted.
            AuthenticatesRequests::class,
            InitializeTenancyFromUser::class,
        );

        /**
         * Idle sessions end on the server, not on the browser's promise to
         * drop a cookie. Appended to the web group so it covers every page a
         * signed-in person can reach.
         */
        $middleware->web(append: [
            EnforceSessionTimeout::class,
        ]);

        $middleware->alias([
            'tenant.subdomain' => InitializeTenancyBySubdomain::class,
            'tenant.user' => InitializeTenancyFromUser::class,
            'tenant.central-only' => PreventAccessFromCentralDomains::class,
            'tenant.route' => InitializeTenancyFromRoute::class,

            // The two halves of the onboarding gate. Together they form a
            // closed loop: an unfinished account cannot reach the app, and a
            // finished one cannot re-enter the wizard by typing the URL.
            // App Settings is Owner/Administrator only, enforced on the route
            // rather than by hiding the nav icon.
            'can-manage-settings' => EnsureCanManageSettings::class,

            'onboarded' => EnsureOnboardingIsComplete::class,
            'not-onboarded' => RedirectIfOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
