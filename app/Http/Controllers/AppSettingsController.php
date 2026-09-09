<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LocationClosure;
use App\Models\Role;
use App\Models\TeamInvitation;
use App\Support\Currencies;
use App\Support\Icon;
use App\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The App Settings landing page.
 *
 * Navigation only: it lists the configuration modules and lets someone find
 * one. The fields themselves belong to each module's own page, so nothing is
 * configured here.
 *
 * Access is enforced by the `can-manage-settings` middleware on the route, not
 * in this class, so every future settings route inherits the same rule by
 * being placed in the same group.
 */
class AppSettingsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $statuses = config('app_settings.statuses');
        $counts = $this->counts($request);

        $groups = collect(config('app_settings.groups'))
            ->map(fn (array $group) => [
                ...$group,
                /**
                 * Headings and card copy resolve through translation keys.
                 *
                 * The config keeps its English literals as the last-resort
                 * fallback, so a module added without a translation still
                 * renders its own name — never `modules.foo.name`, which is
                 * the one thing a settings page must not print.
                 */
                'name' => self::text('modules.groups.'.self::slug($group['name']).'.name', $group['name']),
                'description' => self::text('modules.groups.'.self::slug($group['name']).'.description', $group['description']),
                'modules' => collect($group['modules'])->map(function (array $module) use ($statuses, $counts) {
                    /**
                     * Status defaults to coming-soon.
                     *
                     * Every module here is unbuilt today. Defaulting rather
                     * than repeating 'coming-soon' 36 times in the config means
                     * a module becomes live by gaining a route and a status,
                     * and forgetting to set one leaves it honestly marked
                     * rather than silently claiming to work.
                     */
                    $status = $module['status'] ?? 'coming-soon';

                    return [
                        ...$module,
                        'name' => self::text('modules.modules.'.$module['key'].'.name', $module['name']),
                        'description' => self::text('modules.modules.'.$module['key'].'.description', $module['description']),
                        /**
                         * The card's live figures, resolved here rather than
                         * in the view: a Blade template counting rows is a
                         * query nobody can see when the page gets slow.
                         */
                        'counts' => collect($module['counts'] ?? [])
                            ->map(fn (string $key) => $counts[$key] ?? null)
                            ->filter(fn ($count) => $count !== null && $count['value'] > 0)
                            ->values()
                            ->all(),
                        'status' => $status,
                        'status_label' => self::text('modules.statuses.'.$status, $statuses[$status]['label']),
                        'status_class' => $statuses[$status]['class'],
                        'url' => isset($module['route']) ? route($module['route']) : null,
                        /**
                         * The icon arrives as rendered markup.
                         *
                         * The island cannot read resources/icons itself, and
                         * shipping the icon set to the browser to pick 36 out
                         * of it would send far more than the page needs.
                         */
                        'icon_svg' => Icon::inline($module['icon'], 18)->toHtml(),
                        /**
                         * Everything the search box matches on, flattened once
                         * here rather than assembled in JavaScript — the client
                         * should not have to know that keywords exist.
                         */
                        /**
                         * Everything the search box matches on, flattened
                         * once here rather than assembled in JavaScript — the
                         * client should not have to know that keywords exist.
                         *
                         * Built from the translated name and description, not
                         * the config literals: a Spanish reader searching
                         * "ubicaciones" is searching for what is on their
                         * screen. The English is kept alongside so a
                         * bilingual team can find a card either way.
                         */
                        'haystack' => mb_strtolower(implode(' ', [
                            self::text('modules.modules.'.$module['key'].'.name', $module['name']),
                            self::text('modules.modules.'.$module['key'].'.description', $module['description']),
                            $module['name'],
                            $module['description'],
                            implode(' ', $module['keywords'] ?? []),
                            $group['name'] ?? '',
                        ])),
                    ];
                })->all(),
            ])
            ->all();

        return view('settings.index', ['groups' => $groups]);
    }

    /**
     * A translation, or the literal the config already carried.
     *
     * Locale::get() would humanise a missing key into "Name", which is right
     * for a label with nowhere else to turn and wrong here — the config holds
     * the real English, so that is the better fallback.
     */
    private static function text(string $key, string $fallback): string
    {
        return trans()->has($key) ? __($key) : $fallback;
    }

    /**
     * A group heading as a translation key segment.
     *
     * Derived from the English name rather than stored, because groups have
     * no key of their own in the config. Deriving it keeps the two in step:
     * renaming a group without adding its translation falls back to the new
     * name rather than to a stale one.
     */
    private static function slug(string $name): string
    {
        return Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    /**
     * Figures a card can display, per §2.
     *
     * Computed once for the page rather than per card, because two cards
     * asking the same question is two queries for one answer.
     *
     * @return array<string, array{value: int, label: string}>
     */
    private function counts(Request $request): array
    {
        $tenant = $request->user()->tenant;

        if ($tenant === null) {
            return [];
        }

        $activeStaff = $tenant->staff()->where('is_active', true)->count();

        $pendingInvites = $tenant->teamInvitations()
            ->where('status', TeamInvitation::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $roles = Role::withoutGlobalScopes()->where('tenant_id', $tenant->getTenantKey())->count();

        $activeLocations = $tenant->locations()->active()->count();

        $enabledLanguages = Locale::enabledFor($tenant)->count();
        $enabledCurrencies = Currencies::enabledFor($tenant)->count();

        $upcomingClosures = LocationClosure::query()
            ->whereIn('location_id', $tenant->locations()->select('id'))
            ->upcoming()
            ->count();

        /**
         * Pluralised by the translation file, not by Str::plural().
         *
         * That helper only knows English, so it would have produced
         * "2 ubicación activas" — a rule applied to a language it was never
         * written for. Laravel's choice syntax lets each language state its
         * own plural, and the whole phrase is one string so word order can
         * differ too.
         */
        $count = fn (string $key, int $value) => [
            'value' => $value,
            'label' => trans_choice('modules.counts.'.$key, $value, ['count' => $value]),
        ];

        return [
            'active_staff' => $count('active_staff', $activeStaff),
            'pending_invites' => $count('pending_invites', $pendingInvites),
            'roles' => $count('roles', $roles),
            'active_locations' => $count('active_locations', $activeLocations),
            'upcoming_closures' => $count('upcoming_closures', $upcomingClosures),
            'enabled_languages' => $count('enabled_languages', $enabledLanguages),
            'enabled_currencies' => $count('enabled_currencies', $enabledCurrencies),
        ];
    }
}
