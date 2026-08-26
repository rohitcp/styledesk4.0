<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central routes — marketing site and authenticated application
|--------------------------------------------------------------------------
|
| These are served on the central domains listed in config/tenancy.php
| (CENTRAL_DOMAINS in .env). Public booking pages are not here; they are
| host-identified and live in routes/tenant.php.
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
| The authenticated staff application.
|
| `tenant.user` runs after `auth` and initializes tenancy from the signed-in
| user's `users.tenant_id`, so every tenant-scoped model inside this group is
| filtered by the BelongsToTenant global scope without a controller ever
| naming a tenant. Ordering matters: tenancy cannot be resolved before the
| session has identified the user.
|
| Feature routes (dashboard, bookings, clients, services, staff) get added
| to this group as each module is specified and built.
*/
Route::middleware(['auth', 'tenant.user'])->group(function () {
    //
});
