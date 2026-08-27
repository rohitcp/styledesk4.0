<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClientSettings;
use App\Support\ClientOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The Clients module's home.
 *
 * Day-to-day client management, which is a different thing from App Settings →
 * Clients — that screen decides what a client record looks like, this one is
 * where the records themselves will live. §Access Control asks for exactly
 * that separation, and it is also why this route is open to every role with
 * `clients.view` rather than to Owner and Admin alone: a receptionist works
 * with clients all day and configures nothing.
 *
 * There are no client records yet. The page says so plainly and shows what has
 * already been configured, rather than an empty table implying the business
 * has lost its clients.
 */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        /**
         * Scope is not checked here, only the permission.
         *
         * Whether someone sees their own clients, their location's or all of
         * them is a question about records, and there are none yet. When the
         * list arrives the scope decides what it contains — it does not decide
         * whether the screen opens.
         */
        abort_unless($request->user()->hasPermission('clients.view', 'own'), 403);

        $tenant = $request->user()->tenant;
        $settings = ClientSettings::forTenant($tenant);

        return view('clients.index', [
            'settings' => $settings,
            /**
             * What the business has already set up, shown as evidence rather
             * than as a promise: a page that says "no clients yet" and
             * nothing else gives someone no reason to believe the module is
             * coming.
             */
            'preferenceCount' => $tenant->clientPreferences()->active()->count(),
            'tagCount' => $tenant->clientTags()->active()->count(),
            'requiredFields' => collect($settings->orderedFields())
                ->filter(fn (array $field) => $field['enabled'] && $field['required'])
                ->pluck('label')
                ->all(),
            'nameFormat' => ClientOptions::nameFormats()[$settings->name_format] ?? null,
            // The link to configuration is only shown to someone who can open
            // it — a card that bounces the reader to the dashboard is worse
            // than no card.
            'canConfigure' => $request->user()->canManageSettings(),
        ]);
    }
}
