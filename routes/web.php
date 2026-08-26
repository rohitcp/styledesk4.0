<?php

use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GettingStartedController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\Settings\BusinessSettingsController;
use App\Http\Controllers\Settings\StaffController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamInviteSignupController;
use App\Http\Controllers\VerificationEmailController;
use App\Models\Tenant;
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
| Public booking, by path.
|
| The spec's canonical booking URL is /book/{slug} on the central domain. The
| tenant subdomain (routes/tenant.php) stays available as an alias, and both
| resolve the same tenant, so a business can share whichever address it
| prefers. Deliberately outside auth: clients booking are not StyleDesk users.
*/
Route::middleware(['tenant.route'])->get('book/{tenant}', function (Tenant $tenant) {
    return 'Public booking site for '.$tenant->name.' ('.$tenant->getTenantKey().')';
})->name('booking.path');

/*
| Correcting a mistyped sign-up address. Sits behind auth but deliberately
| outside the `verified` gate — the whole point is that this user cannot
| verify yet.
*/
Route::middleware('auth')->patch('email/verify/update', [VerificationEmailController::class, 'update'])
    ->name('verification.email.update');

/*
| Team invitations — the invited person's side.
|
| Outside auth and outside every tenancy middleware, deliberately: the visitor
| has no account and no tenant to resolve from, and the token is the only thing
| that says who they are and which business invited them. The tenant is read
| off the invitation, never off the request, which is what stops a modified URL
| reaching another business.
|
| Throttled because these routes are reachable by anyone with a link: without
| it the show route is an oracle for guessing tokens at network speed.
*/
Route::middleware('throttle:20,1')
    ->prefix('invite/team')
    ->name('team-invite.')
    ->controller(TeamInviteSignupController::class)
    ->group(function () {
        Route::get('{token}', 'show')->name('show');
        Route::post('{token}/register', 'register')->name('register');
        Route::post('{token}/login', 'login')->name('login');
        Route::post('{token}/accept', 'accept')->middleware('auth')->name('accept');
    });

/*
| Team invitations — the inviting business's side.
|
| Inside auth + tenant.user but outside the `onboarded` gate, because step 4 of
| onboarding sends invitations before setup is finished. Authorisation is
| per-action through TeamInvitationPolicy.
*/
Route::middleware(['auth', 'verified', 'tenant.user'])
    ->prefix('team/invitations')
    ->name('team-invitations.')
    ->controller(TeamInvitationController::class)
    ->group(function () {
        Route::post('/', 'store')->name('store');
        Route::post('{invitation}/resend', 'resend')->name('resend');
        Route::delete('{invitation}', 'revoke')->name('revoke');
    });

/*
| Service categories.
|
| Inside the authenticated, tenant-resolved group but outside the `onboarded`
| gate, because the service step of onboarding needs them before setup is
| finished. Authorisation is per-action in the controller.
*/
Route::middleware(['auth', 'verified', 'tenant.user'])
    ->prefix('service-categories')
    ->name('service-categories.')
    ->controller(ServiceCategoryController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('reorder', 'reorder')->name('reorder');
        Route::patch('{serviceCategory}', 'update')->name('update');
        Route::delete('{serviceCategory}', 'destroy')->name('destroy');
    });

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
    ->controller(OnboardingController::class)
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

        Route::post('logo', 'uploadLogo')->name('logo.upload');

        Route::post('skip/{step}', 'skip')->name('skip');
    });

/*
| App Settings.
|
| An administrative module: Owner and Administrator only, enforced here rather
| than by hiding the nav icon. Every future settings route belongs in this
| group, so the permission comes with the address instead of having to be
| remembered per controller.
*/
Route::middleware(['auth', 'verified', 'tenant.user', 'onboarded', 'can-manage-settings'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::get('/', AppSettingsController::class)->name('index');

        /*
        | Staff members — the directory, per §3.
        |
        | Authorisation is per-action through StaffPolicy rather than a
        | blanket role check: a service provider may open the directory to see
        | their own record, and the policy is what makes "see their own" and
        | "see everyone" different answers to the same route.
        */
        Route::get('staff', [StaffController::class, 'index'])
            ->name('staff.index');

        /*
        | Business — company-level information.
        |
        | Read-only by default with a separate /edit address, so the page a
        | person lands on is never a form they did not ask for, and the edit
        | screen is linkable and back-button friendly.
        */
        Route::controller(BusinessSettingsController::class)
            ->prefix('business')
            ->name('business.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::get('edit', 'edit')->name('edit');
                Route::patch('/', 'update')->name('update');
            });
    });

Route::middleware(['auth', 'verified', 'tenant.user', 'onboarded'])->group(function () {
    /**
     * Placeholder landing page, and the end-to-end check that the stack is
     * wired: it renders only if auth, tenant-from-user resolution, the Blade
     * layout, Vite and the Vue island mount are all working.
     *
     * Replace with the real dashboard once that module is specified.
     */
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::delete('getting-started', [GettingStartedController::class, 'destroy'])
        ->name('getting-started.dismiss');
});
