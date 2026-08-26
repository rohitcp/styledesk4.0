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
/*
| Correcting a mistyped sign-up address. Sits behind auth but deliberately
| outside the `verified` gate — the whole point is that this user cannot
| verify yet.
*/
Route::middleware('auth')->patch('email/verify/update', [App\Http\Controllers\VerificationEmailController::class, 'update'])
    ->name('verification.email.update');

/*
| Onboarding.
|
| Sits inside auth + tenant.user but outside the `onboarded` gate, which is
| what stops it redirecting to itself. tenant.user lets a tenant-less user
| through precisely so step 1 stays reachable before a tenant exists.
*/
Route::middleware(['auth', 'verified', 'tenant.user', 'not-onboarded'])
    ->prefix('onboarding')
    ->name('onboarding.')
    ->controller(App\Http\Controllers\OnboardingController::class)
    ->group(function () {
        Route::get('business', 'business')->name('business');
        Route::post('business', 'storeBusiness')->name('business.store');

        Route::get('location', 'location')->name('location');
        Route::post('location', 'storeLocation')->name('location.store');

        Route::get('services', 'services')->name('services');
        Route::post('services', 'storeServices')->name('services.store');

        Route::get('team', 'team')->name('team');
        Route::post('team', 'storeTeam')->name('team.store');

        Route::get('booking', 'booking')->name('booking');
        Route::post('booking', 'storeBooking')->name('booking.store');

        Route::get('complete', 'complete')->name('complete');

        Route::post('skip/{step}', 'skip')->name('skip');
    });

Route::middleware(['auth', 'verified', 'tenant.user', 'onboarded'])->group(function () {
    /**
     * Placeholder landing page, and the end-to-end check that the stack is
     * wired: it renders only if auth, tenant-from-user resolution, the Blade
     * layout, Vite and the Vue island mount are all working.
     *
     * Replace with the real dashboard once that module is specified.
     */
    Route::view('/dashboard', 'dashboard')->name('dashboard');
});
