<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Icon;
use Illuminate\Contracts\View\View;

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
    public function __invoke(): View
    {
        $statuses = config('app_settings.statuses');

        $groups = collect(config('app_settings.groups'))
            ->map(fn (array $group) => [
                ...$group,
                'modules' => collect($group['modules'])->map(function (array $module) use ($statuses) {
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
}
