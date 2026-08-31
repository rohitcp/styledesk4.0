<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\AccountPreferences;
use App\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * How this person wants the app to read.
 *
 * Every setting here has a counterpart in Business Settings, and that is the
 * relationship worth keeping in mind while changing this file: the business
 * decides what everybody sees by default, and a colleague may differ. So a
 * value is only ever stored when it differs, and "Reset to default" writes
 * nulls rather than copying today's business setting into the row — a
 * business that later switches to a 24-hour clock should carry along
 * everybody who never expressed a view.
 *
 * Nothing here touches tenant configuration. A stylist changing their own
 * date format gains no business permissions by doing so.
 */
class PreferencesController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.preferences', [
            'user' => $user,
            'languages' => Locale::enabledFor($user->tenant)
                ->mapWithKeys(fn (string $code) => [$code => Locale::nativeName($code)])
                ->all(),
            'timezones' => AccountPreferences::timezones(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            /* Only a language the business has switched on. Honouring one it
               has turned off would make the business setting a suggestion. */
            'locale' => ['nullable', Rule::in(Locale::enabledFor($user->tenant)->all())],
            'date_format' => ['nullable', Rule::in(array_keys(config('business_profile.date_formats')))],
            'time_format' => ['nullable', Rule::in(array_keys(config('business_profile.time_formats')))],
            'timezone' => ['nullable', Rule::in(timezone_identifiers_list())],
            'first_day_of_week' => ['nullable', Rule::in(array_keys(config('business_profile.first_day_of_week')))],
            'calendar_view' => ['nullable', Rule::in(AccountPreferences::CALENDAR_VIEWS)],
            'show_weekends' => ['boolean'],
            'show_cancelled' => ['boolean'],
            'show_resource_color' => ['boolean'],
            'show_staff_color' => ['boolean'],
        ]);

        /**
         * Read with a default rather than by key.
         *
         * A combo left on "use the business setting" posts nothing at all, so
         * an absent key is an answer — "I have no preference" — and not a
         * missing field. Indexing $validated directly would make the commonest
         * choice on the screen a 500.
         */
        $chosen = fn (string $key) => Arr::get($validated, $key) ?: null;

        /**
         * The language lives on `users`, not here.
         *
         * App\Support\Locale and the middleware that sets the request's
         * locale already read users.locale, and a second copy would be a
         * second answer to the same question.
         */
        $user->forceFill(['locale' => $chosen('locale')])->save();

        $user->preferencesRow()->update([
            'date_format' => $chosen('date_format'),
            'time_format' => $chosen('time_format'),
            'timezone' => $chosen('timezone'),
            'first_day_of_week' => $chosen('first_day_of_week') === null
                ? null
                : (int) $chosen('first_day_of_week'),
            'calendar_view' => $chosen('calendar_view'),
            /* Toggles always post a value — the switch component sends "0"
               when it is off — so these are answers, not silence. */
            'show_weekends' => (bool) Arr::get($validated, 'show_weekends', false),
            'show_cancelled' => (bool) Arr::get($validated, 'show_cancelled', false),
            'show_resource_color' => (bool) Arr::get($validated, 'show_resource_color', false),
            'show_staff_color' => (bool) Arr::get($validated, 'show_staff_color', false),
        ]);

        return redirect()->route('account.preferences')
            ->with('toast', __('account.preferences.saved'));
    }

    /**
     * Back to whatever the business says.
     *
     * Nulls rather than the business's current values, so somebody who resets
     * today keeps following the business tomorrow.
     */
    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill(['locale' => null])->save();

        $user->preferencesRow()->update([
            'date_format' => null,
            'time_format' => null,
            'timezone' => null,
            'first_day_of_week' => null,
            'calendar_view' => null,
            'show_weekends' => null,
            'show_cancelled' => null,
            'show_resource_color' => null,
            'show_staff_color' => null,
        ]);

        return redirect()->route('account.preferences')
            ->with('toast', __('account.preferences.reset'));
    }
}
