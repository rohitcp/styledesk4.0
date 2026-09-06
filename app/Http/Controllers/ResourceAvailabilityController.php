<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Location;
use App\Models\ResourceCategory;
use App\Support\ResourceAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * What the rooms and chairs are doing today.
 *
 * The resource control screen: one day, every room, drawn along a clock. It
 * exists to answer three questions a receptionist asks with somebody in
 * front of them — what is free now, when does the room they want come back,
 * and where does the next booking go — and none of the three is a
 * percentage.
 *
 * Its own controller rather than another method on the utilization one:
 * that reads a range and asks how the week went, this reads one day and asks
 * what is happening in it. Same permission, because both are readings of the
 * same list — somebody who may see the rooms may see what is in them.
 */
class ResourceAvailabilityController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request);

        $day = $this->day($request);
        $locationId = $request->integer('location') ?: null;

        return view('resources.availability', [
            'day' => $day->toDateString(),
            'today' => now()->toDateString(),
            'locationId' => $locationId,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'categories' => ResourceCategory::query()->orderBy('name')->get(['id', 'name']),
            'availability' => ResourceAvailability::forDay($day, $locationId),
        ]);
    }

    /**
     * The same day again, for a date or branch change.
     *
     * Asked on every arrow press, so it is throttled generously: stepping
     * through a week a day at a time is what this screen is for.
     */
    public function data(Request $request): JsonResponse
    {
        $this->allow($request);

        return response()->json(ResourceAvailability::forDay(
            $this->day($request),
            $request->integer('location') ?: null,
        ));
    }

    /**
     * One appointment, for the panel that opens over the chart.
     *
     * Deliberately not the booking page. Somebody who clicked a block wants
     * to know who is in the room and until when; sending them to another
     * screen loses the chart they were reading. The link to the full record
     * is in the panel for when they do want it.
     *
     * Its own permission: seeing that a room is busy is the resource list's
     * business, and seeing whose appointment it is is the diary's.
     */
    public function booking(Request $request, Booking $booking): JsonResponse
    {
        $this->allow($request);

        abort_unless($request->user()?->hasPermission('calendar.view', 'location'), 403);

        $booking->loadMissing(['client', 'staff', 'location', 'services.resource']);

        return response()->json([
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status,
            'status_label' => $booking->statusLabel(),
            'client' => $booking->clientName(),
            'client_url' => $booking->client ? route('clients.show', $booking->client) : null,
            'staff' => $booking->staff?->displayName() ?? __('bookings.any_staff'),
            'location' => $booking->location?->name,
            'date' => $booking->date->translatedFormat('D, j M Y'),
            'time' => $booking->timeLabel(),
            'minutes' => (int) $booking->minutes,
            'url' => route('bookings.show', $booking),
            'services' => $booking->services->map(fn ($line) => [
                'name' => $line->name,
                'minutes' => (int) $line->minutes,
                'resource' => $line->resource?->name,
            ])->values()->all(),
            'notes' => $booking->notes,
        ]);
    }

    /**
     * The day being asked about.
     *
     * Today where nothing was asked for, and today where something
     * unparseable was: a screen that 500s because a URL was hand-edited is
     * worse than one that shows this morning.
     */
    private function day(Request $request): Carbon
    {
        $date = $request->string('date')->value();

        try {
            return $date === '' ? now()->startOfDay() : Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('resources.view', 'location'), 403);
    }
}
