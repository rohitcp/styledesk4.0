<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Support\BookingAvailability;
use App\Support\BookingStatusHistory;
use App\Support\ClientActivityLog;
use App\Support\ResourceAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * What happens to a booking after it has been taken.
 *
 * Four acts: nobody came, it was called off, it was turned down, it moved.
 * Its own controller rather than four more methods on the booking screen's,
 * because all four are the same shape — a reason, a note, a status, an entry
 * in two histories — and the one thing they must never be is four slightly
 * different implementations of that.
 *
 * Every one of them is refused unless the booking is in a state where the act
 * means something. The status rules live in config/bookings.php beside the
 * statuses themselves, so the buttons the header offers and the acts this
 * controller will accept are read from one list rather than two that drift.
 */
class BookingStatusController extends Controller
{
    /** Nobody came. */
    public function noShow(Request $request, Booking $booking): RedirectResponse
    {
        return $this->settle($request, $booking, 'no-show');
    }

    /** Called off. */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        return $this->settle($request, $booking, 'cancelled');
    }

    /** A request the business turned down. */
    public function decline(Request $request, Booking $booking): RedirectResponse
    {
        return $this->settle($request, $booking, 'declined');
    }

    /**
     * The client is here.
     *
     * No reason asked for: arriving for an appointment does not need to be
     * explained, and a required dropdown at the front desk while somebody
     * stands at it waiting is the wrong shape entirely.
     */
    public function checkIn(Request $request, Booking $booking): RedirectResponse
    {
        $this->permit($request, $booking, 'check-in');

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        /* Written as a condition on the update rather than checked first.

           Two receptionists pressing Check In at the same moment both pass
           the status check a moment apart, and the loser of that race would
           otherwise write a second arrival for a client who only arrived
           once. Whoever's update matches the row is the one that happened. */
        $claimed = Booking::query()
            ->whereKey($booking->id)
            ->where('status', 'confirmed')
            ->update(['status' => 'arrived']);

        if ($claimed === 0) {
            /* Somebody got there first, or the booking moved on. Not an
               error worth a red page: the desk wanted this client checked
               in, and they are. */
            return back()->with('toast', [
                'type' => 'success',
                'message' => __('bookings.status.done.check-in'),
            ]);
        }

        $booking->setAttribute('status', 'arrived');

        BookingStatusHistory::record(
            $booking, 'arrived', null, $data['note'] ?? null, null,
            $request->user()->id,
            ['from_status' => 'confirmed'],
        );

        ClientActivityLog::bookingCheckedIn($booking, $data['note'] ?? null, $request->user()->id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('bookings.status.done.check-in'),
        ]);
    }

    /**
     * The three that end a booking.
     *
     * One method, because they differ only in the word they write and the
     * list they ask from — and the moment they are three methods is the
     * moment one of them forgets to write its history.
     */
    private function settle(Request $request, Booking $booking, string $status): RedirectResponse
    {
        $this->permit($request, $booking, $status);

        $action = config('bookings.status_actions.'.$status);
        $data = $this->validated($request, $action['reason']);
        $reason = $this->reason($request, $data['reason_code_id'], $action['reason']);

        DB::transaction(function () use ($booking, $status, $reason, $data, $request) {
            /* Recorded before the update, so `from_status` is read from the
               row rather than from what it has just become. */
            $from = $booking->status;
            $booking->update(['status' => $status]);
            $booking->setAttribute('status', $status);

            BookingStatusHistory::record(
                $booking, $status, $reason,
                $data['note'] ?? null, $data['details'] ?? null,
                $request->user()->id,
                ['from_status' => $from],
            );
        });

        /* The client's own timeline as well as the booking's. A receptionist
           reading a profile is asking what happened to this person, not what
           happened to a reference number. */
        $label = $this->reasonLine($reason, $data['details'] ?? null);

        match ($status) {
            'no-show' => ClientActivityLog::bookingNoShow($booking, $label, $data['note'] ?? null, $request->user()->id),
            'declined' => ClientActivityLog::bookingDeclined($booking, $label, $data['note'] ?? null, $request->user()->id),
            default => ClientActivityLog::bookingCancelled($booking, $label, $request->user()->id, $data['note'] ?? null),
        };

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('bookings.status.done.'.$status),
        ]);
    }

    /**
     * The appointment moves; the booking does not become a different one.
     *
     * Same row, same reference, same client and same bill — a reschedule that
     * cancelled one booking and wrote another would leave the desk chasing a
     * deposit against a reference the client was never given.
     */
    public function reschedule(Request $request, Booking $booking): RedirectResponse
    {
        $this->permit($request, $booking, 'reschedule');

        $data = $this->validated($request, 'booking-reschedule', [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->toDateString()],
            'starts_at' => ['required', 'date_format:H:i'],
            'staff_id' => ['nullable', 'integer', $this->ownRow('staff', $request)],
            'location_id' => ['nullable', 'integer', $this->ownRow('locations', $request)],
        ]);

        $reason = $this->reason($request, $data['reason_code_id'], 'booking-reschedule');

        /* Whether the new slot can actually be worked, asked the same way the
           booking screen asks it. Ignoring this booking's own time, or an
           appointment moved by ten minutes would be found to clash with
           itself. */
        $staffId = isset($data['staff_id']) ? (int) $data['staff_id'] : $booking->staff_id;
        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : $booking->location_id;

        $availability = BookingAvailability::for(
            $locationId === null ? null : Location::query()->find($locationId),
            $data['date'],
            $staffId,
            $booking->services->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->all(),
            $booking->id,
        );

        if (! in_array($data['starts_at'], $availability['slots'], true)) {
            throw ValidationException::withMessages([
                'starts_at' => __('bookings.status.slot_taken'),
            ]);
        }

        /* Read before the row moves. "Rescheduled" without the previous slot
           answers half the question, and the half it drops is the one
           somebody is usually looking for. */
        $was = [
            'date' => $booking->date?->isoFormat('D MMM Y'),
            'time' => $booking->timeLabel(),
            'on' => $booking->date?->toDateString(),
            'starts_at' => $booking->startsAt(),
            'staff_id' => $booking->staff_id,
            'location_id' => $booking->location_id,
        ];

        $starts = CarbonImmutable::parse($data['date'].' '.$data['starts_at']);

        /* Somewhere to be at the new time. The old room may well be taken
           then, and an appointment that moved but kept a room it no longer
           has is worse than one with no room recorded at all. Ignoring
           itself, or it would be found holding the slot it is leaving. */
        $resource = ResourceAllocator::assign(
            $booking->services->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->all(),
            $data['date'],
            $starts->format('H:i'),
            (int) $booking->minutes,
            $booking->id,
        );

        DB::transaction(function () use ($booking, $data, $starts, $staffId, $locationId, $resource, $reason, $was, $request) {
            $booking->update([
                'date' => $data['date'],
                'starts_at' => $starts->format('H:i'),
                'ends_at' => $starts->addMinutes((int) $booking->minutes)->format('H:i'),
                'staff_id' => $staffId,
                'location_id' => $locationId,
                'resource_id' => $resource?->id ?? $booking->resource_id,
            ]);

            BookingStatusHistory::record(
                $booking, $booking->status, $reason,
                $data['note'] ?? null, $data['details'] ?? null,
                $request->user()->id,
                [
                    'from_status' => $booking->status,
                    'from_date' => $was['on'],
                    'from_starts_at' => $was['starts_at'],
                    'to_date' => $data['date'],
                    'to_starts_at' => $starts->format('H:i'),
                    'from_staff_id' => $was['staff_id'],
                    'to_staff_id' => $staffId,
                    'from_location_id' => $was['location_id'],
                    'to_location_id' => $locationId,
                ],
            );
        });

        ClientActivityLog::bookingRescheduled(
            $booking->fresh(), $was, $request->user()->id,
            $this->reasonLine($reason, $data['details'] ?? null),
            $data['note'] ?? null,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('bookings.status.done.reschedule'),
        ]);
    }

    /**
     * Which times could be worked on a given day, for the reschedule dialog.
     *
     * The booking screen's own question, asked about a booking that already
     * exists — so it ignores this appointment's current slot, or moving one
     * by ten minutes would be found to clash with itself.
     */
    public function slots(Request $request, Booking $booking): JsonResponse
    {
        $this->permit($request, $booking, 'reschedule');

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'staff_id' => ['nullable', 'integer', $this->ownRow('staff', $request)],
            'location_id' => ['nullable', 'integer', $this->ownRow('locations', $request)],
        ]);

        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : $booking->location_id;

        $availability = BookingAvailability::for(
            $locationId === null ? null : Location::query()->find($locationId),
            $data['date'],
            isset($data['staff_id']) ? (int) $data['staff_id'] : $booking->staff_id,
            $booking->services->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->all(),
            $booking->id,
        );

        return response()->json($availability + [
            'message' => $availability['reason'] === null ? null : __('bookings.when.'.$availability['reason']),
        ]);
    }

    /**
     * May this reader do this to this booking, right now.
     *
     * Both halves are checked here rather than trusted from the header: the
     * buttons are a courtesy, and a form posted from a stale page is the
     * ordinary case rather than the suspicious one.
     */
    private function permit(Request $request, Booking $booking, string $action): void
    {
        $config = config('bookings.status_actions.'.$action);

        abort_if($config === null, 404);
        abort_unless($request->user()?->hasPermission($config['permission'], 'own'), 403);
        /* 422 rather than 403: they are allowed to do this, to a booking that
           is not in a state where it means anything. */
        abort_unless(in_array($booking->status, $config['from'], true), 422);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $type, array $extra = []): array
    {
        return $request->validate($extra + [
            'reason_code_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * The reason chosen, and only from the list this act asks from.
     *
     * Checked rather than trusted: a reason id from another type, another
     * business, or one that was switched off last week would otherwise be
     * written into a history nobody can correct.
     */
    private function reason(Request $request, mixed $id, string $type): ReasonCode
    {
        /* Cast, not trusted. It arrives as form text and the `integer` rule
           only checks that it looks like a number — it does not turn "218"
           into 218, and everything downstream of here is typed. */
        $reason = ReasonCode::query()->ofType($type)->whereKey((int) $id)->first();

        if ($reason === null || ! $reason->is_active) {
            throw ValidationException::withMessages([
                'reason_code_id' => __('bookings.status.reason_unavailable'),
            ]);
        }

        /* "Other" is not an answer on its own, and which reason asks for one
           is the business's own setting rather than a name this code knows. */
        if ($reason->requires_details && blank($request->input('details'))) {
            throw ValidationException::withMessages([
                'details' => __('bookings.status.details_required'),
            ]);
        }

        return $reason;
    }

    /** "Other — client emigrated", where a reason asked for the rest of it. */
    private function reasonLine(ReasonCode $reason, ?string $details): string
    {
        return collect([$reason->name, $details])->filter()->join(' — ');
    }

    /** A row belonging to the business making the request, and no other. */
    private function ownRow(string $table, Request $request): Exists
    {
        return Rule::exists($table, 'id')->where('tenant_id', $request->user()->tenant?->getTenantKey());
    }
}
