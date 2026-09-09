<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dismisses the first-run checklist (spec section 17).
 *
 * Owner-only: the checklist is the owner's setup to-do list, and one staff
 * member hiding it for the whole business would be surprising.
 */
class GettingStartedController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        abort_unless($tenant->owner_user_id === $request->user()->id, 403);

        $tenant->onboarding?->update(['getting_started_dismissed_at' => now()]);

        return back();
    }
}
