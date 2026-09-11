<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Staff;
use App\Support\Calendar;
use App\Support\Currencies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The diary, drawn against the clock.
 *
 * The bookings listing answers "what has been taken"; this answers the
 * question asked over the counter — who is working, who is in the building,
 * which room they are in, and where the gaps are. It is the screen the front
 * desk keeps open all day, so it loads one day at a time and nothing else.
 *
 * Thin on purpose: the board itself is Calendar's business, and this only
 * decides which day, how far, whose, and whether the reader may see it.
 */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request);

        $location = $this->location($request);

        return view('calendar.index', [
            'date' => $this->date($request),
            'view' => $this->view($request),
            'location' => $location,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'staffOptions' => Staff::query()
                ->where('is_active', true)
                ->where('provides_services', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'preferred_name']),
            'resourceOptions' => Resource::query()->orderBy('name')->get(['id', 'name']),
            /* What is actually bookable. A service withdrawn from sale can
               still be on today's diary, but it is not a filter anybody is
               going to reach for, and a list of every service a business ever
               offered is a list nobody can search. */
            'serviceOptions' => Service::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'staffId' => $this->staffId($request),
            'interval' => (int) $request->integer('interval', Calendar::INTERVAL),
            /* Whether the staff filter is theirs to change. A provider who
               may only see their own diary is shown it, not offered a menu
               of everybody else's. */
            'lockedToOwnStaff' => $this->ownStaffOnly($request) !== null,
            'resourceId' => $request->integer('resource_id') ?: null,
            'serviceId' => $request->integer('service_id') ?: null,
        ]);
    }

    /**
     * The day itself, as JSON.
     *
     * Its own endpoint rather than a page reload, because every control on
     * the screen changes it — the date, the location, the staff filter — and
     * a receptionist who moves a day forward should not lose their place.
     */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request);

        $arguments = [
            $this->location($request),
            $this->date($request),
            $this->staffId($request),
            $request->integer('resource_id') ?: null,
            $request->integer('service_id') ?: null,
        ];

        $board = match ($this->view($request)) {
            'week' => Calendar::week(...$arguments),
            'month' => Calendar::month(...$arguments),
            /* Only the day has a ruler to draw, so only the day is asked
               how finely to draw it. */
            default => Calendar::day(...$arguments, interval: (int) $request->integer('interval', Calendar::INTERVAL)),
        };

        return response()->json($board + ['currency' => Currencies::resolve()]);
    }

    /**
     * Whose diary is being asked for.
     *
     * A service provider sees their own. Applied here rather than trusted
     * from the query string, because the query string is a thing a person can
     * edit — and answered the same way the activity stream answers it, so one
     * reader does not get two different ideas of what "own" means.
     */
    private function staffId(Request $request): ?int
    {
        return $this->ownStaffOnly($request) ?? ($request->integer('staff_id') ?: null);
    }

    /**
     * The staff id this reader is pinned to, or null for everybody.
     *
     * Nought where they are pinned but have no staff row of their own: a
     * calendar of nobody is the right answer to "show me my appointments"
     * from somebody who is not on the rota, and null would show them the
     * whole salon.
     */
    private function ownStaffOnly(Request $request): ?int
    {
        $user = $request->user();

        if ($user === null || $user->hasPermission('calendar.view_all_staff', 'own')) {
            return null;
        }

        return (int) (Staff::query()->where('user_id', $user->id)->value('id') ?? 0);
    }

    /**
     * How far the reader is looking.
     *
     * Anything else is the day, which is what the front desk wants nine times
     * out of ten and the only honest answer to a word nobody recognises.
     */
    private function view(Request $request): string
    {
        $asked = (string) $request->query('view', 'day');

        return in_array($asked, ['day', 'week', 'month'], true) ? $asked : 'day';
    }

    /**
     * Which day is being asked for.
     *
     * Anything unreadable is today rather than an error: the date arrives in
     * a query string that a person can edit, and a red page because somebody
     * mistyped a URL is the wrong answer to a question with an obvious one.
     */
    private function date(Request $request): string
    {
        $asked = (string) $request->query('date', '');

        try {
            return $asked === ''
                ? Carbon::today()->toDateString()
                : Carbon::parse($asked)->toDateString();
        } catch (\Throwable) {
            return Carbon::today()->toDateString();
        }
    }

    /**
     * Which branch.
     *
     * The primary one when nobody has said, because a calendar of every
     * location at once is a screen with two rooms called Room 1 on it. A
     * combined view is Phase 2.
     */
    private function location(Request $request): ?Location
    {
        /* Said explicitly rather than inferred from a missing parameter: a
           blank location_id is what a fresh page load looks like, and that
           should open on the branch the reader works at rather than on every
           branch at once. `all` is somebody asking. */
        if ($request->query('location_id') === 'all') {
            return null;
        }

        $asked = $request->integer('location_id');

        if ($asked) {
            return Location::query()->find($asked);
        }

        return Location::query()->where('is_primary', true)->first()
            ?? Location::query()->orderBy('name')->first();
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('calendar.view', 'own'), 403);
    }
}
