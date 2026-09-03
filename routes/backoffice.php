<?php

declare(strict_types=1);

use App\Http\Controllers\Backoffice\ClientController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\LoginController;
use App\Http\Controllers\Backoffice\ModuleController;
use App\Http\Controllers\Backoffice\PasswordResetController;
use App\Http\Controllers\Backoffice\ProfileController;
use App\Http\Controllers\Backoffice\VerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The platform console
|--------------------------------------------------------------------------
|
| Mounted at /backoffice, on the web middleware group for sessions and CSRF
| and on nothing else. In particular no tenancy middleware: a StyleDesk
| administrator belongs to no salon, and initialising tenancy here would scope
| the console to whichever tenant happened to be resolved.
|
| Everything past the sign-in carries two middleware — `backoffice.auth` for
| who, `backoffice.can` for what. The second is on every route without
| exception, so a route added later that names no permission fails closed
| rather than quietly admitting everybody.
|
*/

/* Signed out. Throttled hard: these are the endpoints somebody who has found
   the address will point a script at. */
Route::middleware('guest:backoffice')->group(function (): void {
    Route::get('/', fn () => redirect()->route('backoffice.verify.email'));

    Route::get('verify', [VerificationController::class, 'email'])->name('verify.email');
    Route::post('verify', [VerificationController::class, 'send'])
        ->middleware('throttle:'.config('backoffice.verification.throttle'))
        ->name('verify.send');

    Route::get('verify/code', [VerificationController::class, 'code'])->name('verify.code');
    Route::post('verify/code', [VerificationController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('verify.check');
    Route::post('verify/resend', [VerificationController::class, 'resend'])
        ->middleware('throttle:'.config('backoffice.verification.throttle'))
        ->name('verify.resend');

    /* The password screen, behind the code. The middleware is what makes the
       second step a gate rather than a suggestion. */
    Route::middleware('backoffice.verified')->group(function (): void {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('login.store');
    });

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

/* Signed in. */
Route::middleware('backoffice.auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('backoffice.can:dashboard.view')
        ->name('dashboard');

    /* An administrator's own account needs no permission: it is theirs, and a
       role that could not reach it would be a role that could not change its
       own password. */
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    /*
     | The modules the navigation names. Real routes with real permission
     | gates from the first day — each is replaced by its own controller as
     | its phase lands, and the route names do not change.
     */
    Route::get('clients', [ClientController::class, 'index'])
        ->middleware('backoffice.can:clients.view')->name('clients.index');

    /* Bound on the slug, which is what Tenant::getRouteKeyName says — the id
       is a UUID and nobody reads one off a screen. */
    Route::get('clients/{tenant}', [ClientController::class, 'show'])
        ->middleware('backoffice.can:clients.view')->name('clients.show');

    /* Switching a business off is `clients.manage`, not `clients.view`: the
       roles that may read the list are not all the roles that may take a
       salon offline. */
    Route::post('clients/{tenant}/disable', [ClientController::class, 'disable'])
        ->middleware('backoffice.can:clients.manage')->name('clients.disable');

    Route::post('clients/{tenant}/enable', [ClientController::class, 'enable'])
        ->middleware('backoffice.can:clients.manage')->name('clients.enable');

    Route::get('plans', [ModuleController::class, 'plans'])
        ->middleware('backoffice.can:plans.view')->name('plans.index');

    Route::get('billing', [ModuleController::class, 'billing'])
        ->middleware('backoffice.can:billing.view')->name('billing.index');

    Route::get('settings', [ModuleController::class, 'settings'])
        ->middleware('backoffice.can:settings.view')->name('settings.index');
});
