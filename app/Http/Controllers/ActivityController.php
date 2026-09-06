<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ActivityStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What has been happening across the business.
 *
 * Read-only, and deliberately so: the panel is a window onto records the
 * application already keeps, and nothing here writes history. The one thing
 * it does write is when this reader last looked, which is theirs alone.
 *
 * No permission of its own. Every source inside `ActivityStream` applies the
 * permission that governs the records it reads — a service provider is shown
 * their own appointments, a receptionist the desk's work, an owner the lot —
 * so an activity nobody may open is an activity nobody is shown.
 */
class ActivityController extends Controller
{
    /**
     * The activity page.
     *
     * A page of its own rather than a drawer over whatever the reader was
     * doing: this is a log somebody sits and reads — scrolled, filtered,
     * followed into records and come back from — and a panel that closes when
     * you click past it is the wrong shape for that. The app bar opens it in
     * a new tab, so the screen they were on is still there when they are
     * finished with it.
     */
    public function index(Request $request): View
    {
        return view('activity.index', [
            'unread' => ActivityStream::unread($request->user()),
        ]);
    }

    /** The rows themselves, for the page and for paging through them. */
    public function feed(Request $request): JsonResponse
    {
        $group = $request->string('group')->value();

        return response()->json(ActivityStream::feed(
            $request->user(),
            in_array($group, ActivityStream::GROUPS, true) ? $group : 'all',
            min(60, max(10, $request->integer('limit', 40))),
            $request->string('before')->value() ?: null,
        ));
    }

    /**
     * Everything up to now has been seen.
     *
     * A timestamp rather than a row per item: "since I last looked" is the
     * only question the badge answers.
     */
    public function read(Request $request): JsonResponse
    {
        $request->user()->forceFill(['activity_seen_at' => now()])->save();

        return response()->json(['unread' => 0]);
    }
}
