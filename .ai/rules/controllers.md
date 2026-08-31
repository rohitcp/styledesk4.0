---
paths:
  - 'app/{Models/StaffShift.php,Support/SchedulePeriod.php,Http/Controllers/**}'
  - 'app/{Models/Booking.php,Models/BookingPayment.php,Support/BookingTotals.php,Http/Controllers/BookingController.php}'
---

# Controllers

## Draft vs published schedules live on the shift, not a period table
A staff schedule is published a range at a time but stored a shift at a time. There is no schedule-period table: `staff_shifts.publish_status` ('draft'|'published') plus `published_at` carry the state, and `App\Support\SchedulePeriod` derives the period's state from the rows in the range.

Rules:
- `publish_status` is NOT `status`. `status` is where the shift got to (scheduled/confirmed/cancelled); `publish_status` is whether the staff member was told. A cancelled shift can be published; a scheduled one can be an unseen draft.
- `published_at` survives a shift going back to draft on purpose. That is how `SchedulePeriod::CHANGES` ("published once, edited since") is told from `DRAFT` ("never sent"). Never null it on an edit.
- Editing a shift (`ShiftController@update`) sets `publish_status` back to draft so the change needs an explicit Publish Changes. Nothing else may send mail as a side effect.
- Email goes out ONLY from `StaffController@publishSchedule`, one per published period however many weeks it spans — never one per week. Assigning and Save Draft send nothing.
- Everything that quotes period numbers (page banner, publish dialog, email) must read them from `SchedulePeriod` so the three cannot disagree. Cancelled shifts count towards neither hours nor working days.

Trap: the schedule Blade passes label arrays into Vue `data-props`, which lands in the page HTML. Keep using `Arr::only`/`Arr::except` there — shipping all of `lang/*/schedule.php` puts the email body and validation copy in the DOM and breaks `assertDontSee` in tests.

## Booking money: stored totals, payments as rows, never a fake charge
The bill is written onto the booking when it is taken (subtotal_minor / discount_minor / tax_minor / total_minor) and every later screen reads those columns. Never recompute from the price list or the tax rate — a price edited in March would rewrite what somebody was charged in February. `App\Support\BookingTotals::of()` computes it once; `::for()` reads it back.

`payment_status` is NOT `status`. One is where the bill got to, the other where the appointment got to; a booking can be completed and unpaid, or paid for and months away. Payments are rows in `booking_payments` (a bill split across cash and a card is two rows), so what is paid is `paidMinor()`, and `settlePaymentStatus()` writes the derived status back for the listings to filter on.

StyleDesk charges nothing. Every method except a card on a connected provider is money that changed hands elsewhere and is recorded here — `manual: true` on the pay endpoint. `config('bookings.card_provider')` is null by default, and with none the card form is shown but inert and the panel records what the terminal took. Never store or log card numbers, expiry or CVV; `reference` holds what the machine printed.
