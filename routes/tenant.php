<?php

declare(strict_types=1);

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
    });
