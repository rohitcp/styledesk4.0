<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Locale;
use App\Support\ReturnTo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The Languages module: what the StyleDesk interface is shown in.
 *
 * Two decisions live here and they belong to different people. The business
 * decides which languages its team may use — that is Owner/Administrator, and
 * it is what this controller's show/edit/update do. Each person then picks
 * their own from the header, which is `preference()` and is open to anybody
 * signed in, because it changes nothing but their own screen.
 *
 * Scope, restated because it is the thing most easily got wrong: this is the
 * interface only. Service names, client records, email and SMS templates,
 * receipts and anything else the business has typed are its own words, and no
 * language setting rewrites them.
 */
class LanguageController extends Controller
{
    public function show(Request $request): View
    {
        return view('settings.languages.show', $this->languageState($request->user()->tenant));
    }

    public function edit(Request $request): View
    {
        return view('settings.languages.edit', [
            ...$this->languageState($request->user()->tenant),
            ...$this->returnTo($request, route('settings.languages.show')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $codes = Locale::available()->keys()->all();

        $data = $request->validate([
            'primary' => ['required', Rule::in($codes)],
            'secondary' => ['nullable', 'array'],
            'secondary.*' => [Rule::in($codes)],
        ], [
            'primary.required' => __('languages.primary_required'),
            'primary.in' => __('languages.unsupported'),
            'secondary.*.in' => __('languages.unsupported'),
        ]);

        /**
         * The primary is removed from the secondaries rather than refused.
         *
         * Ticking your own primary is a reasonable mistake to make, and it
         * means nothing harmful — the language is enabled either way. An error
         * message would stop a save that was already expressing what the user
         * wanted.
         */
        $secondary = collect($data['secondary'] ?? [])
            ->reject(fn (string $code) => $code === $data['primary'])
            ->unique()
            ->values();

        DB::transaction(function () use ($tenant, $data, $secondary) {
            $tenant->forceFill(['default_language' => $data['primary']])->save();

            /**
             * Rewritten rather than reconciled, and position is the order the
             * selector lists them in. The primary is not stored here: it is
             * enabled by being the primary, and a second copy of that fact is
             * a second thing that can disagree.
             */
            $tenant->languages()->delete();

            $secondary->each(fn (string $code, int $index) => $tenant->languages()->create([
                'language_code' => $code,
                'position' => $index + 1,
            ]));
        });

        /**
         * Anyone left on a language the business just switched off falls back
         * on their next request — Locale::forUser() only honours a personal
         * choice while it is still enabled. Their stored preference is left
         * alone rather than cleared, so turning the language back on restores
         * what they had chosen.
         */
        return redirect()
            ->to(ReturnTo::resolve($request, route('settings.languages.show')))
            ->with('toast', ['type' => 'success', 'message' => __('languages.saved')]);
    }

    /**
     * One person's own choice, from the header.
     *
     * Deliberately not behind `can-manage-settings`: a receptionist choosing
     * to read the app in Spanish changes nothing except their own screen, and
     * requiring an administrator for that would make the feature useless to
     * the people it is for.
     */
    public function preference(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locale::enabledFor($user->tenant)->all())],
        ], [
            'locale.in' => __('languages.unsupported'),
        ]);

        $user->forceFill(['locale' => $data['locale']])->save();

        /**
         * Back to the page they were on, in the new language.
         *
         * A redirect rather than a client-side swap: every screen's controls
         * and Vue islands bind on load, so replacing the DOM underneath them
         * would leave a page that looked translated and no longer worked. The
         * user does not reload anything or sign out — which is what the
         * requirement is protecting — they press a menu item and the app comes
         * back in their language.
         */
        return back()->with('toast', [
            'type' => 'success',
            'message' => __('languages.preference_saved', [], $data['locale']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function languageState(Tenant $tenant): array
    {
        $enabled = Locale::enabledFor($tenant);
        $primary = Locale::primaryFor($tenant);

        return [
            'tenant' => $tenant,
            'available' => Locale::available(),
            'primary' => $primary,
            'secondary' => $enabled->reject(fn (string $code) => $code === $primary)->values(),
            'enabled' => $enabled,
        ];
    }
}
