<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LocationClosure;
use App\Models\Role;
use App\Models\TeamInvitation;
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
                        'status_label' => $statuses[$status]['label'],
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
                        'haystack' => mb_strtolower(implode(' ', [
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

        $upcomingClosures = LocationClosure::query()
            ->whereIn('location_id', $tenant->locations()->select('id'))
            ->upcoming()
            ->count();

        return [
            'active_staff' => ['value' => $activeStaff, 'label' => Str::plural('active member', $activeStaff)],
            'pending_invites' => ['value' => $pendingInvites, 'label' => Str::plural('pending invite', $pendingInvites)],
            'roles' => ['value' => $roles, 'label' => Str::plural('role', $roles)],
            'active_locations' => ['value' => $activeLocations, 'label' => Str::plural('active location', $activeLocations)],
            'upcoming_closures' => ['value' => $upcomingClosures, 'label' => Str::plural('upcoming closure', $upcomingClosures)],
            'enabled_languages' => ['value' => $enabledLanguages, 'label' => Str::plural('language', $enabledLanguages)],
        ];
    }
}
