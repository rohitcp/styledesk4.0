<?php

use App\Contracts\TenantStorageContract;
use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GettingStartedController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\Settings\BrandingController;
use App\Http\Controllers\Settings\BusinessHoursController;
use App\Http\Controllers\Settings\BusinessSettingsController;
use App\Http\Controllers\Settings\ClientSettingsController;
use App\Http\Controllers\Settings\CurrencyController;
use App\Http\Controllers\Settings\LanguageController;
use App\Http\Controllers\Settings\LocationController;
use App\Http\Controllers\Settings\ResourceCategoryController;
use App\Http\Controllers\Settings\RolePermissionController;
use App\Http\Controllers\Settings\ServiceCategoryController as SettingsServiceCategoryController;
use App\Http\Controllers\Settings\StaffController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamInviteSignupController;
use App\Http\Controllers\VerificationEmailController;
use App\Models\StoredFile;
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
                Route::patch('{serviceCategory}', 'update')->name('update');
                Route::patch('{serviceCategory}/status', 'toggle')->name('toggle');
                Route::delete('{serviceCategory}', 'destroy')->name('destroy');
            });

        Route::controller(ResourceCategoryController::class)
            ->prefix('resources')
            ->name('resources.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('reorder', 'reorder')->name('reorder');
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
                Route::post('/', 'store')->name('store');
                Route::post('avatar', 'uploadAvatar')->name('avatar.upload');

                // Bound last: a literal segment must win over {staff}, or
                // /settings/staff/create would look up a member called
                // "create" and 404.
                Route::get('{staff}', 'show')->name('show');
                Route::get('{staff}/edit', 'edit')->name('edit');
                Route::patch('{staff}', 'update')->name('update');
                Route::delete('{staff}', 'destroy')->name('destroy');
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
            Route::post('/', 'store')->name('store');

            // Bound last: a literal segment must win over {client}, or
            // /clients/create would look up a client called "create" and 404.
            Route::get('{client}', 'show')->name('show');
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
