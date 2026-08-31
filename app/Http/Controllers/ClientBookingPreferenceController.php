<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientBookingPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * How one client likes to be booked, kept on the client.
 *
 * Its own controller rather than more of the client form: these are added and
 * removed one at a time as somebody mentions them at the desk, and a
 * preference typed into a form that saves forty other fields is a preference
 * that waits for the rest of the form to be valid.
 *
 * Only what a person said is stored. What the diary noticed — that they come
 * every four weeks, that they always take an afternoon — is worked out when
 * it is read, so it cannot go stale and is never quoted back as though the
 * client had asked for it.
 */
class ClientBookingPreferenceController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->allow($request);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        $client->bookingPreferences()->create([
            'tenant_id' => $client->tenant_id,
            'label' => $data['label'],
            'source' => 'client',
            'position' => (int) $client->bookingPreferences()->max('position') + 1,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('clients.module.booking_preferences.added'),
        ]);
    }

    public function destroy(Request $request, Client $client, ClientBookingPreference $preference): RedirectResponse
    {
        $this->allow($request);

        /* Bound separately, so a preference id from another client's profile
           cannot be deleted through this one's URL. */
        abort_unless($preference->client_id === $client->id, 404);

        $preference->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('clients.module.booking_preferences.removed'),
        ]);
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('clients.edit', 'own'), 403);
    }
}
