<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\NotificationCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Which messages this person wants, and where.
 *
 * The screen posts the whole grid every time, and this writes the whole grid
 * back: a switch that was on and is now off has to be storable, and a partial
 * post cannot tell "turned off" from "not on the page". Critical types are
 * simply never written — see NotificationCatalog — so a crafted request
 * cannot silence a security alert by naming it.
 */
class NotificationController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.notifications', [
            'groups' => NotificationCatalog::grid($request->user()),
            'channels' => NotificationCatalog::channels(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'notifications' => ['array'],
            'notifications.*' => ['array'],
            'notifications.*.*' => ['in:0,1'],
        ]);

        $user = $request->user();
        $submitted = (array) $request->input('notifications', []);
        $rows = [];

        foreach (NotificationCatalog::types() as $key => $type) {
            /* Never stored, so never storable: a request naming a security
               alert is answered by leaving it alone rather than by an error,
               because there is nothing the sender could have meant that this
               screen should honour. */
            if ($type['critical']) {
                continue;
            }

            foreach ($type['channels'] as $channel) {
                if (! in_array($channel, NotificationCatalog::availableChannels(), true)) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $user->id,
                    'type_key' => $key,
                    'channel' => $channel,
                    'is_enabled' => (bool) ($submitted[$key][$channel] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        /* One statement rather than a save per switch: the grid is around a
           hundred rows, and a hundred round trips to record one click on
           "Enable all" is the kind of thing that makes a settings screen feel
           broken. */
        $user->notificationPreferences()->upsert($rows, ['user_id', 'type_key', 'channel'], ['is_enabled', 'updated_at']);

        $user->unsetRelation('notificationPreferences');

        return redirect()->route('account.notifications')
            ->with('toast', __('account.notifications.saved'));
    }

    /**
     * Back to the catalogue's defaults.
     *
     * Deleting the rows rather than writing the defaults into them, because
     * "no row" is what a default is: somebody who resets today should follow
     * the product when a notification's default changes tomorrow.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->user()->notificationPreferences()->delete();

        return redirect()->route('account.notifications')
            ->with('toast', __('account.notifications.reset'));
    }
}
