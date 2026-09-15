<?php

declare(strict_types=1);

use App\Http\Controllers\PublicFormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant routes — public booking
|--------------------------------------------------------------------------
|
| Everything reachable on a tenant subdomain (acme.styledesk.app) lives here.
| These are the public, unauthenticated pages a client uses to book: the
| business's landing page, service list, availability and checkout.
|
| The tenant is identified from the host, so `tenant.subdomain` must be the
| first tenancy middleware. `tenant.central-only` then rejects the same routes
| when they are reached on a central domain, where no tenant is in scope.
|
| The Route::domain() constraint is load-bearing, not decoration. Laravel's
| RouteCollection is keyed by domain + URI, so an unconstrained tenant `/`
| and the central `/` in routes/web.php produce the same key and the one
| registered last silently replaces the other. The constraint keeps them
| distinct and confines these routes to real tenant subdomains.
|
| The authenticated staff application does NOT live here — it is a single
| hostname shared by all businesses and resolves its tenant from the signed-in
| user instead. See routes/web.php.
|
*/

Route::domain('{tenantSubdomain}.'.config('tenancy.tenant_domain_suffix'))
    ->middleware([
        'web',
        'tenant.subdomain',
        'tenant.central-only',
    ])
    ->group(function () {
        // {tenantSubdomain} exists to make the domain pattern concrete; the
        // tenant itself is resolved by the middleware, so routes read it from
        // tenant() rather than from this parameter.
        Route::get('/', function () {
            return 'Public booking site for '.tenant('name').' ('.tenant('id').')';
        })->name('booking.home');

        /*
        | A form, filled in by the person it was sent to.
        |
        | The token in the URL is the whole of the authorisation — there is no
        | account and no session worth the name — so these are throttled by
        | IP. An unauthenticated route that looks a token up is one somebody
        | will try to guess their way through, the same reasoning as the
        | review links in routes/web.php.
        */
        Route::middleware(['throttle:60,1'])
            ->controller(PublicFormController::class)
            ->group(function () {
                Route::get('form/{token}', 'show')->name('public.form.show');
                Route::post('form/{token}', 'store')->name('public.form.store');
            });
    });
