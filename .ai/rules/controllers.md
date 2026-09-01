---
paths:
  - 'app/{Models/StaffShift.php,Support/SchedulePeriod.php,Http/Controllers/**}'
  - 'app/{Models/Booking.php,Models/BookingPayment.php,Support/BookingTotals.php,Http/Controllers/BookingController.php}'
  - 'app/{Models/Booking.php,Models/BookingLead.php,Support/BookingAvailability.php,Http/Controllers/BookingController.php,Http/Controllers/BookingLeadController.php}'
  - 'app/{Models/BookingPaymentLink.php,Http/Controllers/PaymentLinkController.php,Http/Controllers/BookingController.php}'
  - 'app/{Models/BookingStatusChange.php,Models/ReasonCode.php,Support/BookingStatusHistory.php,Http/Controllers/BookingStatusController.php}'
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

## A booking in progress is a lead, not a draft booking
The New Booking screen auto-saves into `booking_leads`, never into `bookings`. A booking being written is not an appointment: it holds no slot, tells nobody anything, and must not appear in the diary beside the ones actually taken.

- `POST bookings/draft` (BookingController@autosave) writes the lead as soon as a `client_id` or a `guest_name` exists, and refuses (422) before that. It is the ONLY writer on that screen — `saveStep()` posts nothing. Two writers would leave two rows per booking attempt, which is exactly what must not happen.
- The lead carries a `BK-YYYYMMDD-NNNNN` reference from `Booking::nextReference()`, issued once and never reissued. `store()` copies it onto the booking when the lead converts, so the number quoted on the phone is the number on the appointment. `nextReference()` scans BOTH tables — the same number lives in each, one after the other.
- Lead status flow: `draft` → `in-progress` (once past the first card) → `converted`. `draft` is in `BookingLead::CHASEABLE`, so an abandoned one ages into follow-up/abandoned via `bookings:age-leads` like any other.
- The lead holds the WHOLE booking (location, staff, starts_at, source, notes, client_note, payment_type, deposit, confirmation, and the priced services snapshot). Continue Booking = `bookings.create?lead={id}`; `create()` hands it all back and the Vue `resumeLead()` restores it, then re-seeds `autosaveSaved` on nextTick so restoring does not itself trigger a save.
- `storeLead` (`POST bookings/leads`, BL- references) is the older lead-from-a-call path. The booking screen no longer calls it; do not reintroduce it there.
- Availability: `dropBookedOver()` still excludes `draft` bookings (the separate "Save as draft" button). Leads are a different table, so they can never block a slot.
- `availability()` must CAST staff_id/service_ids/ignore — the `integer` rule validates query strings but does not convert them, and `BookingAvailability::for()` is typed `?int`.

## Booking money: payment type, collection method, and what to collect now
Three separate questions, three separate fields. Do not collapse them.

- **`payment_type`** (`none` | `deposit` | `full`) — how much is being collected. A deposit larger than the total is refused in `store()` and blocked in the browser.
- **`collection_method`** (`collect-now` | `desk` | `link` | `later` | `waive`) — how it is collected. Renamed from `deposit_action`; the old `now` value migrated to `desk`. Cleared to null when `payment_type` is `none`: nothing to collect means no method. ONLY `collect-now` opens the payment card after Confirm.
- **`collect_minor` / `collect_amount`** on `panel()` — what the till is asked for NOW, which is NOT `due_minor`. A deposit booking with nothing paid collects the deposit; after that it is simply the balance. Every amount field and CTA reads `collect_amount`, never `due_amount`. Charging the total on a deposit booking is the bug this exists to prevent — the panel header states Booking total / Amount to collect now / Remaining balance so the split is unmissable.

Waiving is a decision, not a mechanism: it needs `payments.apply_discount` (NOT the booking permission — taking an appointment and letting somebody off paying are different authorities), a mandatory reason, and it stamps `waived_by`/`waived_at`.

Payment links live in `booking_payment_links`, one row per ask. `sent`/`opened` are known from this side; `paid` is settled ONLY from `settleAgainst()` when money is recorded against the booking, because StyleDesk charges nothing — never let a link mark itself paid because somebody clicked it. `expired` is derived from the clock, so read `currentStatus()`, never the raw column. The public `/pay/{token}` route is outside auth and throttled; the token is the whole credential, so the page shows one appointment and the business's own payment handles, nothing else.

The confirmation screen is full-width at the TOP of the create page (`stage === 'done'` hides the three columns via v-show and scrolls to top). It must never sit under the payment card.

## Booking status changes: snapshot the reason's words, never just the id
`booking_status_changes` keeps `reason_code_id` AND `reason_label`. The label is what the timeline prints. A business renaming a reason next spring has renamed their list, not last March's no-show — never join to `reason_codes` to render history.

Rows are immutable: `UPDATED_AT = null`, and `booted()` returns false on updating/deleting. Do not add an edit path.

What can be done to a booking, from which statuses, and on which permission, lives in one place: `config('bookings.status_actions')`. `Booking::availableActions($user)` reads it for the header; `BookingStatusController::permit()` reads it again for the request. Add an action there, not in a blade `@if`. 403 = may not; 422 = allowed, but the booking is not in a state where the act means anything.
