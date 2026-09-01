<?php

use App\Contracts\TenantStorageContract;
use App\Http\Controllers\AccessCodeController;
use App\Http\Controllers\Account\NotificationController as AccountNotificationController;
use App\Http\Controllers\Account\PasswordController as AccountPasswordController;
use App\Http\Controllers\Account\PreferencesController as AccountPreferencesController;
use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingLeadController;
use App\Http\Controllers\BookingQuoteController;
use App\Http\Controllers\BookingStatusController;
use App\Http\Controllers\ClientBookingPreferenceController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GettingStartedController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentLinkController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceImageController;
use App\Http\Controllers\Settings\BrandingController;
use App\Http\Controllers\Settings\BusinessHoursController;
use App\Http\Controllers\Settings\BusinessSettingsController;
use App\Http\Controllers\Settings\ClientSettingsController;
use App\Http\Controllers\Settings\CurrencyController;
use App\Http\Controllers\Settings\LanguageController;
use App\Http\Controllers\Settings\LocationController;
use App\Http\Controllers\Settings\ReasonCodeController;
use App\Http\Controllers\Settings\ResourceCategoryController;
use App\Http\Controllers\Settings\RolePermissionController;
use App\Http\Controllers\Settings\ServiceCategoryController as SettingsServiceCategoryController;
use App\Http\Controllers\Settings\ShiftRuleController;
use App\Http\Controllers\Settings\StaffController;
use App\Http\Controllers\Settings\TipController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffScheduleBoardController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamInviteSignupController;
use App\Http\Controllers\VerificationEmailController;
use App\Http\Controllers\VerifyEmailLinkController;
use App\Http\Middleware\RequireAccessCode;
use App\Models\StoredFile;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

/*
| The front door.
|
| StyleDesk is invitation only for now, so the address itself lands on the
| access-code screen rather than on a marketing page — there is nothing to
| market to somebody who cannot get in, and a visitor with a code should not
| have to find the way to the form.
|
| Three answers, one hop each: somebody already signed in goes to their
| dashboard, somebody who has answered the gate on this machine goes to the
| login form, and everybody else is asked for a code.
|
| resources/views/welcome.blade.php is kept, not deleted. It is what this
| route goes back to serving on the day the product opens.
*/
Route::get('/', function (Request $request) {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route(
        RequireAccessCode::hasPassed($request) ? 'login' : 'access-code.show'
    );
})->name('home');

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
| Paying for an appointment, by link.
|
| Deliberately outside auth, like the booking site above: a client settling a
| deposit is not a StyleDesk user and never will be. The token in the URL is
| the whole credential, which is why it is 64 random characters and why the
| page shows one appointment and nothing else — no client record, no history,
| no way to reach anything the token does not name.
|
| Throttled by IP: an unauthenticated route that looks a token up is one
| somebody will try to guess their way through.
*/
Route::middleware(['throttle:30,1'])
    ->get('pay/{token}', [PaymentLinkController::class, 'show'])
    ->name('booking.pay-link');

/*
| The access-code gate.
|
| StyleDesk is not open to the public yet, so the sign-in and sign-up screens
| sit behind a code. This is the screen that asks for it; RequireAccessCode,
| appended to the web group, is what sends people here.
|
| Outside every auth and tenancy middleware on purpose: the visitor has no
| account, no session worth the name and no tenant to resolve from.
|
| Throttled because a short numeric code is guessable at network speed and
| nothing else limits the attempts.
*/
Route::middleware('throttle:10,1')
    ->controller(AccessCodeController::class)
    ->group(function () {
        Route::get('access', 'show')->name('access-code.show');
        Route::post('access', 'store')->name('access-code.store');
    });

/*
| The verification screen's own actions: resend, enter the code, correct the
| address. Behind auth but deliberately outside the `verified` gate — the
| whole point is that this user cannot verify yet.
|
| Throttled on top of the resend cooldown, which is a courtesy to the reader
| rather than a defence: the cooldown lives in the session and a client that
| discards its cookie discards it too. `code` is throttled because six digits
| are guessable at network speed and nothing else limits the attempts.
*/
Route::middleware(['auth', 'throttle:10,1'])
    ->controller(VerificationEmailController::class)
    ->group(function () {
        Route::patch('email/verify/update', 'update')->name('verification.email.update');
        Route::post('email/verify/resend', 'resend')->name('verification.resend');
        Route::post('email/verify/code', 'code')->name('verification.code');

        /* Fortify's own resend URL, pointed at the same action. Left to
           Fortify it would send an email without the cooldown — the same
           button by another address, and a way around the wait. */
        Route::post('email/verification-notification', 'resend')->name('verification.send');
    });

/*
| The link in the email.
|
| Registered here rather than left to Fortify's identical route so a reader
| whose mail client opened it in another browser is signed in rather than
| shown a login form, and so an expired link explains itself instead of
| answering 403. routes/web.php is registered before Fortify's routes, so this
| definition is the one that matches — VerifyEmailLinkController says what it
| does differently and why.
|
| No `signed` middleware: the signature is checked inside the controller, so
| the failure is a page with a resend button on it rather than an error page.
*/
Route::middleware('throttle:10,1')
    ->get('email/verify/{id}/{hash}', VerifyEmailLinkController::class)
    ->name('verification.verify');

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
| Service pictures.
|
| Outside the `onboarded` gate on purpose, and so above the services module:
| step 3 of the wizard is where a business adds its first service picture, and
| a business in the wizard has not finished setup by definition. The upload
| lands unattached and is claimed when the service is saved.
*/
Route::middleware(['auth', 'verified', 'tenant.user'])
    ->prefix('services/images')
    ->name('services.images.')
    ->controller(ServiceImageController::class)
    ->group(function () {
        Route::post('/', 'store')->name('store');
        Route::delete('{storedFile}', 'destroy')->name('destroy');
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
        /* Asked while the user is still typing their address. */
        Route::get('business/slug-availability', 'slugAvailability')->name('business.slug');

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
| My Account.
|
| The signed-in person's own settings. Deliberately not inside the settings
| group and deliberately without can-manage-settings: App Settings configures
| the business and is an administrator's screen, while everything here belongs
| to whoever is signed in, so a receptionist has exactly the same rights to it
| as the owner.
|
| No `onboarded` gate either. Somebody part-way through setting up a business
| still has a password to change and a language to choose, and locking them
| out of their own account until the wizard is finished would be a support
| ticket rather than a protection.
|
| Every route acts on $request->user(). There is no {user} parameter anywhere
| in this group, which is what makes "you may only change your own settings"
| a property of the URLs rather than a policy somebody has to remember.
*/
Route::middleware(['auth', 'verified', 'tenant.user'])
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::redirect('/', '/account/profile')->name('index');

        Route::controller(AccountProfileController::class)->group(function () {
            Route::get('profile', 'show')->name('profile');
            Route::patch('profile', 'update')->name('profile.update');

            /* Its own endpoints: a photo is chosen and applied on its own,
               so it is not lost when the rest of the form is refused. */
            Route::post('profile/photo', 'uploadPhoto')->name('photo.store');
            Route::delete('profile/photo', 'removePhoto')->name('photo.destroy');

            /* Throttled: each of these sends mail, and the address it goes to
               is chosen by whoever is asking. */
            Route::post('profile/email', 'requestEmailChange')->middleware('throttle:6,1')->name('email.request');
            Route::post('profile/email/resend', 'resendEmailChange')->middleware('throttle:6,1')->name('email.resend');
            Route::delete('profile/email', 'cancelEmailChange')->name('email.cancel');
        });

        Route::controller(AccountPreferencesController::class)->group(function () {
            Route::get('preferences', 'show')->name('preferences');
            Route::patch('preferences', 'update')->name('preferences.update');
            Route::post('preferences/reset', 'reset')->name('preferences.reset');
        });

        Route::controller(AccountPasswordController::class)->group(function () {
            Route::get('password', 'show')->name('password');
            Route::put('password', 'update')->name('password.update');
        });

        Route::controller(AccountNotificationController::class)->group(function () {
            Route::get('notifications', 'show')->name('notifications');
            Route::patch('notifications', 'update')->name('notifications.update');
            Route::post('notifications/reset', 'reset')->name('notifications.reset');
        });
    });

/*
| The link in the change-email message.
|
| Outside `auth`, like the verification link is and for the same reason: the
| mail client opens it in whichever browser it likes, and that browser is
| often not the one holding the session. The token is the credential and it is
| checked against a stored hash, so nothing is trusted from the URL but the id.
*/
Route::middleware('throttle:10,1')
    ->get('account/email/confirm/{user}/{token}', [AccountProfileController::class, 'confirmEmailChange'])
    ->name('account.email.confirm');

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
        | App Settings → Resources: the catalogue of resource categories.
        |
        | The kinds of thing a business books, decided once. The chairs and
        | rooms themselves are the Resources module's business, which is why
        | that one lives outside settings.
        */
        /*
        | App Settings → Services: the catalogue of service categories.
        |
        | How the price list is organised, decided once. The services
        | themselves are the Services module's business.
        */
        Route::controller(SettingsServiceCategoryController::class)
            ->prefix('services')
            ->name('services.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('reorder', 'reorder')->name('reorder');
                /* Asked while somebody is typing a name, so it is throttled:
                   the answer is cheap, and one request per keystroke is not. */
                Route::get('name-in-use', 'nameInUse')->middleware('throttle:60,1')->name('name-in-use');
                Route::patch('{serviceCategory}', 'update')->name('update');
                Route::patch('{serviceCategory}/status', 'toggle')->name('toggle');
                Route::delete('{serviceCategory}', 'destroy')->name('destroy');
            });

        /*
        | App Settings → Reasons: why things happened, as a list.
        |
        | Nine lists of reason codes the rest of StyleDesk picks from —
        | cancellations, reschedules, refunds and the rest. Reference data,
        | so a tenth list is a block added to config/reasons.php and nothing
        | here has to change.
        |
        | `{type}` is a key from that config, checked by the controller
        | rather than by a route constraint, so a new type does not need this
        | file edited to become reachable.
        */
        /*
        | Whether this business takes tips, and which of its services are
        | tipped. Inside the settings group, so the same permission that
        | guards every other module guards this one.
        */
        Route::controller(TipController::class)->prefix('tips')->name('tips.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::patch('/', 'update')->name('update');
            Route::patch('services/{service}', 'service')->name('service');
        });

        Route::controller(ReasonCodeController::class)
            ->prefix('reasons')
            ->name('reasons.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                /* Before {reason}: a literal segment declared after a
                   parameter is reached by matching it as an id. */
                Route::patch('code/{reason}', 'update')->name('update');
                Route::patch('code/{reason}/status', 'toggle')->name('toggle');
                Route::delete('code/{reason}', 'destroy')->name('destroy');
                Route::get('{type}', 'show')->name('show');
                Route::post('{type}', 'store')->name('store');
                Route::post('{type}/reorder', 'reorder')->name('reorder');
            });

        Route::controller(ResourceCategoryController::class)
            ->prefix('resources')
            ->name('resources.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('reorder', 'reorder')->name('reorder');
                /* Before {resourceCategory}: a literal segment declared after
                   a parameter is reached by matching "code-format" as an id. */
                Route::patch('code-format', 'updateCodeFormat')->name('code-format');
                Route::patch('{resourceCategory}', 'update')->name('update');
                Route::patch('{resourceCategory}/status', 'toggle')->name('toggle');
                Route::delete('{resourceCategory}', 'destroy')->name('destroy');
            });

        /*
        | Roles & permissions — read-only in Phase 1.
        |
        | Two GET routes and nothing else. Editing arrives in Phase 2 with the
        | screens that do it; adding write routes now would mean shipping an
        | endpoint whose only job is to refuse.
        */
        Route::controller(RolePermissionController::class)
            ->prefix('roles-permissions')
            ->name('roles.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{role}', 'show')->name('show');
            });

        /*
        | Staff members — the directory, per §3.
        |
        | Authorisation is per-action through StaffPolicy rather than a
        | blanket role check: a service provider may open the directory to see
        | their own record, and the policy is what makes "see their own" and
        | "see everyone" different answers to the same route.
        */
        Route::controller(StaffController::class)
            ->prefix('staff')
            ->name('staff.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::get('data', 'data')->name('data');
                Route::post('/', 'store')->name('store');
                Route::post('avatar', 'uploadAvatar')->name('avatar.upload');

                // Bound last: a literal segment must win over {staff}, or
                // /settings/staff/create would look up a member called
                // "create" and 404.
                Route::get('{staff}', 'show')->name('show');
                Route::get('{staff}/schedule', 'schedule')->name('schedule');
                Route::get('{staff}/services', 'services')->name('services');
                Route::get('{staff}/notes', 'notes')->name('notes');

                Route::post('{staff}/services', 'attachServices')->name('services.attach');
                Route::delete('{staff}/services/{service}', 'detachService')->name('services.detach');
                Route::patch('{staff}/shift-rule', 'assignShiftRule')->name('shift-rule');
                /* Its own page rather than a dialog: a fortnight is twenty-eight
                   time fields, and the range travels in the URL so the screen can
                   be linked to and the back button means what it says. */
                Route::get('{staff}/schedule/assign', 'assignScheduleForm')->name('schedule.assign.form');
                Route::post('{staff}/schedule', 'assignSchedule')->name('schedule.assign');
                Route::post('{staff}/schedule/draft', 'saveScheduleDraft')->name('schedule.draft');
                Route::post('{staff}/schedule/publish', 'publishSchedule')->name('schedule.publish');
                /* The listing grid's own rows, as JSON. */
                Route::get('{staff}/schedule/data', 'scheduleData')->name('schedule.data');
                Route::delete('{staff}/schedule/{date}', 'destroyScheduleDay')->name('schedule.day.destroy');

                Route::post('{staff}/notes', 'storeNote')->name('notes.store');
                Route::delete('{staff}/notes/{note}', 'destroyNote')->name('notes.destroy');

                Route::get('{staff}/edit', 'edit')->name('edit');
                Route::patch('{staff}', 'update')->name('update');
                Route::patch('{staff}/status', 'toggleStatus')->name('status');
                Route::delete('{staff}', 'destroy')->name('destroy');
            });

        /*
        | Shift Rules — reusable working patterns.
        |
        | Configuration rather than operations, which is why they live here:
        | a pattern is decided once and revisited rarely, where the rota it
        | generates is touched every week. Owner and Administrator only, from
        | the group's own can-manage-settings — a manager meets a shift rule
        | on the Staff Schedule screen, where they pick one, never here.
        */
        Route::controller(ShiftRuleController::class)
            ->prefix('shift-rules')
            ->name('shift-rules.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                /* Before {shiftRule}: a literal segment declared after a
                   parameter is reached by matching "create" as an id. */
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                /* Whether the business uses shift rules at all. Its own
                   address rather than a field on the rule form: it is a
                   decision about the feature, not about any one rule. */
                Route::patch('feature', 'setFeature')->name('feature');

                Route::get('{shiftRule}/edit', 'edit')->name('edit');
                Route::patch('{shiftRule}', 'update')->name('update');
                Route::post('{shiftRule}/duplicate', 'duplicate')->name('duplicate');
                Route::patch('{shiftRule}/status', 'setStatus')->name('status');
                Route::delete('{shiftRule}', 'destroy')->name('destroy');
            });

        /*
        | Locations — branches and how each one operates.
        |
        | Read-only show, separate create and edit addresses, matching Business
        | and Staff: the page someone lands on is never a form they did not ask
        | for, and both forms are linkable and back-button friendly.
        |
        | `create` is bound before `{location}`, or /settings/locations/create
        | would look up a branch called "create" and 404.
        */
        Route::controller(LocationController::class)
            ->prefix('locations')
            ->name('locations.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                /* Asked while somebody is typing a code, so it is throttled:
                   the answer is cheap, and one request per keystroke is not. */
                Route::get('code-in-use', 'codeInUse')->middleware('throttle:60,1')->name('code-in-use');
                Route::post('/', 'store')->name('store');

                Route::get('{location}', 'show')->name('show');
                Route::get('{location}/edit', 'edit')->name('edit');
                Route::patch('{location}', 'update')->name('update');

                // Retiring or reinstating a branch, per §12. Its own address
                // because it is its own decision, guarded by its own policy
                // method rather than by whoever may edit a phone number.
                Route::patch('{location}/status', 'setStatus')->name('status');
            });

        /*
        | Business hours — the weekly pattern, and the dates that override it.
        |
        | Separate from Locations on purpose. Editing one branch's week is part
        | of editing that branch; this is where the business sees every branch
        | at once, plans a schedule change ahead of time, and keeps the holiday
        | calendar. Both write through the same action.
        |
        | Closures hang off a location in the URL rather than standing alone,
        | so the branch is authorised before the entry is reached and a closure
        | id from another branch cannot be swapped in.
        */
        Route::controller(BusinessHoursController::class)
            ->prefix('business-hours')
            ->name('hours.')
            ->group(function () {
                Route::get('/', 'index')->name('index');

                Route::get('{location}', 'edit')->name('edit');
                Route::patch('{location}', 'update')->name('update');
                Route::delete('{location}/schedule', 'destroySchedule')->name('schedule.destroy');

                Route::post('{location}/closures', 'storeClosure')->name('closures.store');
                Route::patch('{location}/closures/{closure}', 'updateClosure')->name('closures.update');
                Route::delete('{location}/closures/{closure}', 'destroyClosure')->name('closures.destroy');
            });

        /*
        | Branding — the business's identity wherever a client sees it.
        |
        | Uploads are their own POST endpoints rather than fields on the form:
        | the file is stored when it is chosen, so the preview beside the field
        | is the real asset and a logo that will not upload says so before the
        | whole form is submitted.
        */
        Route::controller(BrandingController::class)
            ->prefix('branding')
            ->name('branding.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::patch('/', 'update')->name('update');
                Route::delete('/', 'reset')->name('reset');

                Route::post('logo', 'uploadLogo')->name('logo.upload');
                Route::post('favicon', 'uploadFavicon')->name('favicon.upload');
            });

        /*
        | Languages — what the interface is shown in.
        |
        | The business-level configuration only. Choosing your own language is
        | route `language.preference` below, outside this group: it changes
        | nothing but the chooser's own screen, so requiring an administrator
        | for it would make the feature useless to the people it is for.
        */
        Route::controller(LanguageController::class)
            ->prefix('languages')
            ->name('languages.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::get('edit', 'edit')->name('edit');
                Route::patch('/', 'update')->name('update');
            });

        /*
        | Currency — what the business prices in.
        |
        | The same shape as Languages, and for the same reason: one primary
        | that everything defaults to, plus the additional ones the business
        | supports. Unlike a language, this is not a personal choice — a price
        | belongs to the business, so there is no per-user equivalent.
        */
        Route::controller(CurrencyController::class)
            ->prefix('currency')
            ->name('currency.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::get('edit', 'edit')->name('edit');
                Route::patch('/', 'update')->name('update');
            });

        /*
        | Clients — global configuration for client records.
        |
        | Not the Clients module: this decides what a client record looks like
        | and how it behaves, while the profiles, history and day-to-day work
        | belong to the module that reads these. Owner/Administrator only, per
        | §18 — day-to-day permission over client records is a different
        | question and stays with Roles & Permissions.
        |
        | The preference and tag lists get their own routes because §4 and §5
        | ask for adding, editing, deactivating and reordering them, none of
        | which belongs in a form that saves forty other switches.
        */
        Route::controller(ClientSettingsController::class)
            ->prefix('clients')
            ->name('clients.')
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::patch('/', 'update')->name('update');

                Route::post('preferences', 'storePreference')->name('preferences.store');
                Route::patch('preferences/order', 'reorderPreferences')->name('preferences.reorder');
                Route::patch('preferences/{preference}', 'togglePreference')->name('preferences.toggle');

                Route::post('tags', 'storeTag')->name('tags.store');

                // Before {tag}, or the order route reads as a tag called
                // "order" and 404s on a business that has no such tag.
                Route::patch('tags/order', 'reorderTags')->name('tags.reorder');
                Route::patch('tags/{tag}', 'updateTag')->name('tags.update');
                Route::patch('tags/{tag}/toggle', 'toggleTag')->name('tags.toggle');

                /*
                | Refused for a tag any client carries — see destroyTag.
                | Deactivating is what "remove" means once a label is part of
                | someone's record.
                */
                Route::delete('tags/{tag}', 'destroyTag')->name('tags.destroy');

                /*
                | Behavioural tags: activate and deactivate, and nothing
                | else. They are defined by StyleDesk, and their keys are
                | what reporting joins on.
                */
                Route::patch('behavioral-tags/{behavioralTag}/toggle', 'toggleBehavioralTag')
                    ->name('behavioral.toggle');
            });

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

/*
| Keeps an idle session alive when someone answers the timeout warning.
|
| Deliberately trivial: the session middleware stamps the activity time on
| every request, so simply arriving here is the whole effect.
*/
Route::middleware('auth')->post('session/keep-alive', fn () => response()->json(['ok' => true]))
    ->name('session.keep-alive');

/*
| One person's own interface language, chosen from the header.
|
| `auth` only. A receptionist reading the app in Spanish changes nothing
| except their own screen, and gating it behind App Settings would put the
| feature out of reach of everyone it exists for.
*/
Route::middleware('auth')->patch('language', [LanguageController::class, 'preference'])
    ->name('language.preference');

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

    /*
    | Clients — the module, not its settings.
    |
    | Outside the App Settings group on purpose. Configuring what a client
    | record looks like is Owner/Admin work; working with clients is what a
    | receptionist does all day, and §Access Control asks for the two to stay
    | separate. The permission is checked in the controller, so every role
    | that holds clients.view can open it at whatever scope it holds.
    */
    /*
    | How a client likes to be booked, kept on the client.
    |
    | Added and removed one at a time as somebody mentions them at the desk,
    | which is why they are not part of the client form.
    */
    Route::controller(ClientBookingPreferenceController::class)
        ->prefix('clients/{client}/booking-preferences')
        ->name('clients.booking-preferences.')
        ->group(function () {
            Route::post('/', 'store')->name('store');
            Route::delete('{preference}', 'destroy')->name('destroy');
        });

    /*
    | Coupons and offers.
    |
    | Under clients rather than settings: a promotion is something a business
    | runs at people, and the people are here.
    |
    | Declared before the client routes below, or /clients/coupons-offers
    | would be read as a client whose id is "coupons-offers".
    */
    Route::controller(PromotionController::class)
        ->prefix('clients/coupons-offers')
        ->name('promotions.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            /* Before {promotion}: a literal segment declared after a
               parameter is reached by matching "create" as an id. */
            Route::get('create', 'create')->name('create');
            Route::get('data', 'data')->name('data');
            Route::post('/', 'store')->name('store');

            Route::get('{promotion}', 'show')->name('show');
            Route::get('{promotion}/edit', 'edit')->name('edit');
            Route::patch('{promotion}', 'update')->name('update');
            Route::get('{promotion}/duplicate', 'duplicate')->name('duplicate');
            Route::patch('{promotion}/status', 'toggle')->name('toggle');
        });

    Route::controller(ClientController::class)
        ->prefix('clients')
        ->name('clients.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');

            /*
            | The grid's rows, a page at a time.
            |
            | Bound before {client} so it is never read as a client called
            | "data", and behind the same permission as the page itself: an
            | endpoint that hands out client records is the page, whatever
            | shape it returns them in.
            */
            Route::get('data', 'data')->name('data');

            /*
            | "Do we already have this address?", asked while the form is
            | still being filled in. Before {client} for the same reason as
            | `data`, and throttled because it answers yes-or-no about an
            | address — which is a question worth asking slowly.
            */
            Route::get('email-in-use', 'emailInUse')
                ->middleware('throttle:60,1')
                ->name('email-in-use');

            Route::post('/', 'store')->name('store');

            // Bound last: a literal segment must win over {client}, or
            // /clients/create would look up a client called "create" and 404.
            Route::get('{client}', 'show')->name('show');
            /* The four figures at the top of the profile, asked for again
               when the tab comes back to the front — a payment taken at the
               till while this page sat open should not leave it showing
               yesterday's numbers. */
            Route::get('{client}/visit-summary', 'visitSummary')
                ->middleware('throttle:60,1')
                ->name('visit-summary');

            /*
            | The Services tab: what this client has booked, and what they
            | are known to want. Two lists answered together and never
            | merged — one is arithmetic over the diary, the other is a
            | statement somebody made at the desk.
            */
            Route::get('{client}/services', 'services')->name('services');
            Route::post('{client}/favorite-services', 'addFavoriteServices')->name('favorite-services.store');
            Route::delete('{client}/favorite-services/{service}', 'removeFavoriteService')
                ->name('favorite-services.destroy');
            Route::get('{client}/edit', 'edit')->name('edit');
            Route::patch('{client}', 'update')->name('update');

            /*
            | Archive, not delete. §11 keeps an archived client out of booking
            | search and in every appointment and report that names them, so
            | there is no destroy route to reach by accident.
            */
            Route::patch('{client}/archive', 'archive')->name('archive');

            /*
            | Active / inactive, on its own.
            |
            | Not the update route with one field filled in: that one builds
            | its rules from the business's whole field configuration, and a
            | menu item posting to it would either be refused or quietly
            | rewrite the rest of the record.
            */
            Route::patch('{client}/status', 'status')->name('status');

            /*
            | The client tags a client carries — the ones the team puts on by
            | hand. Posted as a whole set, because that is what the modal
            | asks: of your tags, which apply to this client.
            */
            Route::patch('{client}/tags', 'tags')->name('tags');

            /*
            | The behavioural tags a client carries. Posted as a whole set,
            | because that is what the modal asks: which of these apply.
            */
            Route::patch('{client}/behavioral-tags', 'behavioralTags')->name('behavioral');
        });

    /*
    | Notes on a client's record.
    |
    | Their own controller and their own permissions: reading a client is not
    | reading their notes, and `clients.view_notes` / `clients.add_notes`
    | exist precisely so a business can separate the two.
    */
    /*
    | Services — the work the business sells.
    |
    | An operational area rather than a settings screen: a price changes and a
    | treatment is added the week it launches. What belongs in App Settings is
    | the catalogue of categories; what lives here is the list itself.
    */
    Route::controller(ServiceController::class)
        ->prefix('services')
        ->name('services.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            /* Before the {service} routes: a literal segment declared after a
               parameter is reached by matching "create" as an id. */
            Route::get('create', 'create')->name('create');
            /* The rows the listing grid asks for, as JSON. */
            Route::get('data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::get('{service}/edit', 'edit')->name('edit');
            Route::get('{service}', 'show')->name('show');
            Route::delete('{service}', 'destroy')->name('destroy');
            Route::patch('{service}', 'update')->name('update');
            Route::patch('{service}/status', 'toggle')->name('toggle');
            Route::post('{service}/duplicate', 'duplicate')->name('duplicate');
        });

    /*
    | Shifts — working hours on a named date.
    |
    | Declared before the staff group, not inside it: /staff/{staff} would
    | otherwise match "shifts" and look for a member of staff by that name.
    |
    | Guarded by StaffPolicy through the controller, like the rest of the
    | module — a shift is a fact about a member of staff, and whoever may
    | change their record is whoever may say when they work.
    */
    Route::controller(ShiftController::class)
        ->prefix('staff/shifts')
        ->name('shifts.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            /* Before {shift}: a literal segment declared after a parameter is
               reached by matching "create" as an id. */
            Route::get('create', 'create')->name('create');
            Route::get('data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::get('{shift}/edit', 'edit')->name('edit');
            Route::patch('{shift}', 'update')->name('update');
            Route::patch('{shift}/cancel', 'cancel')->name('cancel');
            Route::delete('{shift}', 'destroy')->name('destroy');
        });

    /*
    | Staff — the people, run from the module rather than from settings.
    |
    | The same controller, views, form, validation and policy as
    | /settings/staff: §12 and §13 ask for one set of records reached from two
    | places, and two controllers would have made that a promise instead of a
    | fact. App\Support\StaffSection is the only thing that differs — which of
    | the two prefixes a link or a redirect belongs to.
    |
    | Not behind can-manage-settings. That is the point of the split: running
    | the rota is a daily job and configuring the business is not, so this
    | group is guarded by StaffPolicy, which the controller already asks.
    */
    /*
    | Bookings — the diary and the screen that adds to it.
    |
    | Its own controller rather than a corner of the calendar: taking an
    | appointment is a form with five decisions in it, and reading the day's
    | appointments is a listing. They share a table and nothing else.
    */
    /*
    | Bookings that were started and not finished.
    |
    | Declared before the booking routes below, or /bookings/leads would be
    | read as a booking with the id "leads". Its own controller: a lead holds
    | no slot and blocks no time — it is a call to return, not an appointment.
    */
    Route::controller(BookingLeadController::class)
        ->prefix('bookings/leads')
        ->name('bookings.leads')
        ->group(function () {
            Route::get('/', 'index')->name('');
            Route::get('data', 'data')->name('.data');
            /* One lead, for the drawer the listing opens over itself. */
            Route::get('{lead}', 'show')->name('.show');
            Route::post('{lead}/cancel', 'cancel')->name('.cancel');
            /* A note about the call, kept on the client and tagged with the
               lead it was written about. */
            Route::post('{lead}/notes', 'note')->name('.notes');
        });

    Route::controller(BookingController::class)
        ->prefix('bookings')
        ->name('bookings.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            /* Before {booking}: a literal segment declared after a parameter
               is reached by matching "create" as an id. */
            Route::get('create', 'create')->name('create');
            /* The rows the listing grid asks for, as JSON. */
            Route::get('data', 'data')->name('data');
            /* Which times could actually be booked, for what has been chosen
               so far. Asked again on every change to the location, services,
               staff member or date, so it is throttled generously rather than
               tightly: a receptionist adjusting a booking is meant to ask it
               often. */
            Route::get('availability', 'availability')->middleware('throttle:120,1')->name('availability');
            /* Which rooms one service could go in, and which are free.
               Asked on every change to the day, the time or the branch, so
               it is throttled like availability rather than tightly. */
            Route::get('resources', 'resources')->middleware('throttle:120,1')->name('resources');
            /* The client search behind the booking screen's first column,
               and the history panel that opens once one is chosen. */
            Route::get('clients', 'clients')->name('clients');
            Route::get('clients/{client}/context', 'context')->name('clients.context');
            /* Four fields' worth of client, added without leaving the
               booking that needs them. */
            Route::post('clients', 'storeClient')->name('clients.store');
            /* Whether the walk-in being typed is already on the book. Posted
               rather than asked in the query string: a phone number and an
               email address in a URL is personal data written into every
               access log on the way. Throttled like availability, because it
               is asked on a debounce as somebody types. */
            Route::post('clients/match', 'matchClient')->middleware('throttle:120,1')->name('clients.match');
            /* A booking somebody started, written the moment the services
               are settled so an abandoned call leaves a trace. */
            Route::post('leads', 'storeLead')->name('leads.store');
            /* The booking screen saving itself as it is filled in. Throttled
               like availability rather than tightly: it fires on a debounce
               as a receptionist works, which is many saves per booking and
               exactly the behaviour it is for. */
            Route::post('draft', 'autosave')->middleware('throttle:120,1')->name('draft');
            /* What the booking comes to, before it exists. Asked as the
               reader switches between card and cash, types a coupon or picks
               a tip — so throttled like availability rather than tightly. */
            Route::post('quote', BookingQuoteController::class)->middleware('throttle:120,1')->name('quote');
            Route::post('/', 'store')->name('store');

            /* One booking, and the two things done to it after it is taken:
               money written against it, and the client told about it. Both
               answer JSON, because the booking screen's third column moves
               from summary to payment to confirmation without leaving the
               page it is on. */
            /* What one client has booked, and one booking's own drawer —
               both read from the client profile without leaving it. */
            Route::get('for-client/{client}', 'forClient')->name('for-client');
            Route::get('{booking}', 'show')->name('show');
            Route::get('{booking}/drawer', 'drawer')->name('drawer');
            Route::patch('{booking}', 'update')->name('update');
            Route::get('{booking}/receipt', 'receipt')->name('receipt');
            Route::post('{booking}/payments', 'pay')->name('pay');
            Route::post('{booking}/confirmation', 'sendConfirmation')->name('confirmation');
        });

    /*
    | What happens to a booking after it has been taken.
    |
    | Its own controller: nobody came, it was called off, it was turned down
    | and it moved are four acts of one shape — a reason, a note, a status and
    | an entry in two histories — and the one thing they must never become is
    | four slightly different implementations of that.
    */
    Route::controller(BookingStatusController::class)
        ->prefix('bookings/{booking}')
        ->name('bookings.')
        ->group(function () {
            Route::post('check-in', 'checkIn')->name('check-in');
            Route::post('no-show', 'noShow')->name('no-show');
            Route::post('cancel', 'cancel')->name('cancel');
            Route::post('decline', 'decline')->name('decline');
            Route::post('reschedule', 'reschedule')->name('reschedule');
            /* Which times could be worked, asked as the dialog's date
               changes. Throttled like the booking screen's own availability:
               it is meant to be asked often. */
            Route::get('slots', 'slots')->middleware('throttle:120,1')->name('slots');
        });

    /*
    | The whole team's rota, a month at a time.
    |
    | Declared before the {staff} routes below, or /staff/schedules would be
    | read as a member of staff called "schedules". Its own controller: the
    | board is a different question from one person's week — whose month is
    | empty, rather than what this person is working — and it writes nothing.
    */
    Route::controller(StaffScheduleBoardController::class)
        ->prefix('staff/schedules')
        ->name('staff.schedules')
        ->group(function () {
            Route::get('/', 'index')->name('');
            /* The rows the board's grid asks for, as JSON. */
            Route::get('data', 'data')->name('.data');
            Route::get('start', 'start')->name('.start');
            /* One person's month, for the board's own modal. */
            Route::get('{staff}/month', 'month')->name('.month');
        });

    Route::controller(StaffController::class)
        ->prefix('staff')
        ->name('staff.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            /* Before the {staff} routes: a literal segment declared after a
               parameter is reached by matching "create" as an id. */
            Route::get('create', 'create')->name('create');
            /* The rows the listing grid asks for, as JSON. */
            Route::get('data', 'data')->name('data');
            Route::post('/', 'store')->name('store');
            Route::post('avatar', 'uploadAvatar')->name('avatar.upload');

            Route::get('{staff}', 'show')->name('show');
            /* The staff member's own workspace: one header, four tabs, each
               its own address so a schedule can be bookmarked and the back
               button means what it says. */
            Route::get('{staff}/schedule', 'schedule')->name('schedule');
            Route::get('{staff}/services', 'services')->name('services');
            Route::get('{staff}/notes', 'notes')->name('notes');

            Route::post('{staff}/services', 'attachServices')->name('services.attach');
            Route::delete('{staff}/services/{service}', 'detachService')->name('services.detach');
            Route::patch('{staff}/shift-rule', 'assignShiftRule')->name('shift-rule');
            /* Its own page rather than a dialog: a fortnight is twenty-eight
               time fields, and the range travels in the URL so the screen can
               be linked to and the back button means what it says. */
            Route::get('{staff}/schedule/assign', 'assignScheduleForm')->name('schedule.assign.form');
            Route::post('{staff}/schedule', 'assignSchedule')->name('schedule.assign');
            Route::post('{staff}/schedule/draft', 'saveScheduleDraft')->name('schedule.draft');
            Route::post('{staff}/schedule/publish', 'publishSchedule')->name('schedule.publish');
            /* The listing grid's own rows, as JSON. */
            Route::get('{staff}/schedule/data', 'scheduleData')->name('schedule.data');
            Route::delete('{staff}/schedule/{date}', 'destroyScheduleDay')->name('schedule.day.destroy');

            Route::post('{staff}/notes', 'storeNote')->name('notes.store');
            Route::delete('{staff}/notes/{note}', 'destroyNote')->name('notes.destroy');

            Route::patch('{staff}/status', 'toggleStatus')->name('status');
            Route::get('{staff}/edit', 'edit')->name('edit');
            Route::patch('{staff}', 'update')->name('update');
            Route::delete('{staff}', 'destroy')->name('destroy');
        });

    /*
    | Resources — the chairs, rooms and equipment a booking needs as well as
    | a person.
    |
    | An operational area rather than a settings screen: a room goes out for
    | repair on a Tuesday morning, which is not a decision anyone revisits
    | once a year. What belongs in App Settings is the defaults; what lives
    | here is the actual furniture.
    */
    Route::controller(ResourceController::class)
        ->prefix('resources')
        ->name('resources.')
        ->group(function () {
            Route::get('/', 'index')->name('index');
            /* Before the {resource} routes: a literal segment declared after
               a parameter is reached by matching "create" as an id. */
            Route::get('create', 'create')->name('create');
            Route::get('data', 'data')->name('data');
            /* Asked while somebody is typing a code, so it is throttled: the
               answer is cheap, and one request per keystroke is not. */
            Route::get('code-in-use', 'codeInUse')->middleware('throttle:60,1')->name('code-in-use');
            Route::get('{resource}/edit', 'edit')->name('edit');
            Route::get('{resource}', 'show')->name('show');
            Route::delete('{resource}', 'destroy')->name('destroy');
            Route::post('/', 'store')->name('store');
            Route::patch('{resource}', 'update')->name('update');
            Route::patch('{resource}/status', 'toggle')->name('toggle');
            Route::post('{resource}/blocks', 'block')->name('block');
            Route::delete('{resource}/blocks/{block}', 'unblock')->name('unblock');
        });

    /*
    | A stored file, handed over only to someone who may have it.
    |
    | Every private file is reached through here rather than by URL: a link
    | that works because it was guessed is not a permission check, and the
    | local disk cannot sign URLs at all.
    */
    Route::get('files/{storedFile}', function (StoredFile $storedFile, TenantStorageContract $storage) {
        return $storage->show($storedFile);
    })->name('files.show');

    Route::get('files/{storedFile}/download', function (StoredFile $storedFile, TenantStorageContract $storage) {
        return $storage->download($storedFile);
    })->name('files.download');

    Route::controller(ClientNoteController::class)
        ->prefix('clients/{client}/notes')
        ->name('clients.notes.')
        ->group(function () {
            Route::post('/', 'store')->name('store');
            Route::patch('{note}', 'update')->name('update');
            Route::delete('{note}', 'destroy')->name('destroy');

            /*
            | An image the editor is about to place in a note. Bound before
            | {note} so it is never read as a note called "images".
            */
            Route::post('images', 'image')->name('images');
        });
});
