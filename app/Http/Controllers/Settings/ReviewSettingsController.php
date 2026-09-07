<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\ReviewSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App Settings → Reviews & Feedback.
 *
 * Four questions, and they are separate ones: whether to ask at all, how long
 * to wait, which way to ask, and where to send the clients who enjoyed it.
 *
 * Switching reviews off stops the asking and erases nothing. A salon that
 * turns it off for a difficult month should come back in the spring to find
 * their timing, their channel and their Google links exactly as they left
 * them — and every review already given still on the client records.
 */
class ReviewSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->permit($request);

        return view('settings.reviews.index', [
            'settings' => ReviewSettings::forTenant($request->user()->tenant),
            'delays' => ReviewSettings::delays(),
            /* Every channel including the ones nothing can deliver yet. The
               screen renders those disabled and labelled, so it tells the
               truth about what is planned rather than pretending SMS does not
               exist. */
            'channels' => config('reviews.channels'),
            /* Google links are per branch: a business with three shops has
               three listings, and pointing everybody at one would pile a
               suburb's reviews onto a high street.

               Still passed while the Google card is held back from the view,
               so bringing it back is one file rather than two. */
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'google_review_url']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->permit($request);

        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'delay' => ['required', Rule::in(ReviewSettings::delays())],
            /* Only a channel that can actually be delivered. Offering SMS and
               then accepting it would leave a business believing their
               clients were being texted. */
            'channel' => ['required', Rule::in(ReviewSettings::availableChannels())],
            'google_enabled' => ['nullable', 'boolean'],
            'google_urls' => ['nullable', 'array'],
            'google_urls.*' => ['nullable', 'url', 'max:500'],
        ]);

        ReviewSettings::updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            [
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'delay' => $data['delay'],
                'channel' => $data['channel'],
                'google_enabled' => (bool) ($data['google_enabled'] ?? false),
            ],
        );

        /* Written per branch, and only for the branches this business
           actually has: the keys arrive from a form and are ids until proven
           otherwise. */
        foreach ($data['google_urls'] ?? [] as $locationId => $url) {
            Location::query()
                ->whereKey($locationId)
                ->update(['google_review_url' => blank($url) ? null : $url]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('reviews.settings.saved'),
        ]);
    }

    /**
     * Configuring how the business speaks to its clients after a visit is its
     * own authority, on top of the settings group's own. §29.
     */
    private function permit(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('reviews.manage_settings', 'own'), 403);
    }
}
