<?php

use App\Http\Middleware\AuthenticateBackoffice;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureBusinessIsActive;
use App\Http\Middleware\EnsureCanManageSettings;
use App\Http\Middleware\EnsureOnboardingIsComplete;
use App\Http\Middleware\InitializeTenancyFromRoute;
use App\Http\Middleware\InitializeTenancyFromUser;
use App\Http\Middleware\RedirectIfOnboarded;
use App\Http\Middleware\RequireAccessCode;
use App\Http\Middleware\RequireBackofficePermission;
use App\Http\Middleware\RequireBackofficeVerification;
use App\Http\Middleware\ScopeSessionCookieToHost;
use App\Http\Middleware\SetApplicationLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        /*
         * The platform console, mounted under its own prefix and name.
         *
         * Registered here rather than included from web.php so it carries the
         * web group (sessions, CSRF) and nothing else — in particular no
         * tenancy middleware, because a StyleDesk administrator belongs to no
         * salon and scoping the console to one would be wrong in a way that
         * is hard to see.
         */
        then: function (): void {
            Route::middleware('web')
                ->prefix('backoffice')
                ->name('backoffice.')
                ->group(base_path('routes/backoffice.php'));
        },
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
        /**
         * Before StartSession, which reads session.domain when it builds the
         * cookie. Prepended rather than appended for that reason alone.
         */
        $middleware->web(prepend: [
            ScopeSessionCookieToHost::class,
        ]);

        /**
         * Behind a tunnel or a load balancer the scheme and host arrive in
         * forwarded headers. Without trusting them Laravel builds http:// URLs
         * for an https:// request, and an OAuth redirect_uri built that way
         * will not match what was registered.
         */
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            EnforceSessionTimeout::class,

            /**
             * A business the platform has switched off has no working
             * sessions. In the group rather than on routes because the three
             * ways in that skip the login form — an existing session, a
             * remember-me cookie, a passkey — are exactly the ones a route
             * list would miss.
             */
            EnsureBusinessIsActive::class,

            /**
             * The closed door in front of sign-in and sign-up.
             *
             * In the group rather than on the routes because Fortify registers
             * login and signup from inside the package; the middleware names
             * the routes it gates itself.
             */
            RequireAccessCode::class,

            /**
             * Applied to every web request, not to a group.
             *
             * A screen added next year is then translated by existing rather
             * than by somebody remembering to opt it in — and signed-out
             * pages settle on the fallback, which is what a login page in
             * English should do.
             *
             * After the session middleware, because the reader is resolved
             * from the authenticated user.
             */
            SetApplicationLocale::class,
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

            /* The platform console. `backoffice.auth` answers who — and, on
               every request, whether they are still allowed to be. */
            'backoffice.auth' => AuthenticateBackoffice::class,
            'backoffice.can' => RequireBackofficePermission::class,
            'backoffice.verified' => RequireBackofficeVerification::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /**
         * A sign-in that breaks says so on the form it broke on.
         *
         * A refused password is a ValidationException and already answers
         * itself. Everything else — a mail driver throwing while the
         * verification notice goes out, a database that has gone away
         * mid-attempt, a listener that fails — would otherwise render the
         * 500 page. From the login screen that reads as the form having
         * ignored the click: no message, nothing to act on, and no hint that
         * trying again is worth doing.
         *
         * Rendered, not reported: the handler still logs the exception with
         * its stack trace, which is where the technical detail belongs.
         *
         * Deliberately not keyed to a field. Nothing the reader typed is
         * wrong, so nothing they typed is marked — the message is the
         * layout's alert, and the address comes back with it.
         */
        $exceptions->render(function (Throwable $failure, Request $request) {
            /* The refusals that already carry their own answer: a wrong
               password (ValidationException), and the throttle, which the
               limiter hands back as a response wrapped in an exception. */
            $isOrdinaryRefusal = $failure instanceof ValidationException
                || $failure instanceof HttpResponseException
                || $failure instanceof HttpExceptionInterface;

            if ($isOrdinaryRefusal || ! $request->isMethod('POST') || ! $request->routeIs('login.store')) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => __('auth.unavailable')], 500);
            }

            return redirect()->route('login')
                ->withInput($request->only('email'))
                ->withErrors(['signin' => __('auth.unavailable')]);
        });
    })->create();
