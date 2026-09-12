---
paths:
  - 'app/{Support/ClientServiceHistory.php,Models/Client.php,Http/Controllers/ClientController.php}'
  - 'app/{Support/MembershipCredits.php,Models/MembershipCreditRedemption.php,Http/Controllers/BookingQuoteController.php}'
  - 'app/{Support/WalkInClients.php,Support/PhoneNumber.php,Models/Client.php,Http/Controllers/BookingController.php},config/phone.php'
  - 'app/{Support/BusinessClock.php,Support/BookingAvailability.php,Support/Calendar.php,Http/Controllers/BookingController.php}'
---

# Support Controllers

## Client services: history is arithmetic, favourites are a statement
Two lists on the client Services tab, and they must never merge.

- **Service history** — `ClientServiceHistory::for()`, grouped from `booking_services` over bookings with status `confirmed`/`arrived`/`completed`. One row per service, not per appointment. Lines whose `service_id` is null (service deleted from the price list) are skipped rather than grouped by the copied name — two spellings of "Cut" is a count nobody can trust. Nothing here is editable; it is arithmetic.
- **Favourite services** — `client_favorite_services`, `Client::favoriteServices()`. Set ONLY by hand, via `clients.favorite-services.store` / `.destroy`. A service booked six times does NOT become a favourite on its own: a favourite nobody chose is one nobody can be asked about, and the receptionist who trusts the list stops being able to tell which is which. Favourites work for a client with no booking history at all ("this is what I always have").

Both endpoints return BOTH lists (`serviceTab()`), because starring a service changes a history row too — a screen patching two lists locally is one where they can disagree.

Booking integration: `bookings/create` accepts `?client=` OR `?client_id=`, carries only the id (a tab open since Tuesday must not bring Tuesday's phone number into today's booking), and the client payload includes `favorite_service_ids`, `preferred_staff_id`, `preferred_location_id`. The service selector shows a **Client favourites** row above the list — hidden while searching or filtering by category, where a favourite outside the current filter reads as a bug. `applyClientPreferences()` sets the branch and stylist only when nothing has been chosen yet; a receptionist's choice is never overruled by a months-old preference.

## Membership credits come off before the coupon, and are released on cancellation
A credit pays for one WHOLE service — never part of one. A covered line leaves the payable set entirely, so the coupon is computed against `$payableServices`/`$payablePrices`, not the full booking. Get this order wrong and a percentage is taken off work the client is not paying for. Both `BookingQuoteController` and `BookingController::store()` implement the same order; change them together.

`bookings.membership_credit_minor` is its own column, never folded into `discount_minor`: a coupon is the business giving money away, a credit is the client spending something they already bought, and one column makes "what did we discount" and "what did members spend" the same unanswerable number. Both are passed to `BookingTotals::of()` summed, because both reduce what is owed.

`membership_credits` posted from the screen is a REQUEST, not an instruction. `MembershipCredits::coverable()` re-answers it against credits that exist now; anything spent in between is silently charged rather than refusing a booking with the client at the desk. `apply()` runs inside the booking's own transaction and is skipped for drafts — a credit held against an abandoned draft is a massage the client cannot book.

`MembershipCredits::release($booking)` is called from `BookingStatusController::settle()` for cancelled/declined/no-show and is idempotent (only `held()` redemptions release), because those handlers can fire more than once. Redemptions are marked `released_at`, never deleted.

Test traps: `service_prices` has one row per currency with `price_minor` + `cash_price_minor` columns (no `method` column); the booking screen deduplicates services so the same service cannot appear twice on one booking; the cancel reason type is `booking-cancellation` and `reason_codes` uses `key`/`name`, not `label`.

## Phone numbers: typed for reading, E.164 for comparing
`client_phones.number` keeps what the receptionist typed — that is what they read back down the phone. `client_phones.number_e164` is the canonical form, written by `Client::syncPhones()` (never by callers) and used by `Client::possibleDuplicates()` for an exact indexed match. The old digits-only LIKE comparison stays behind it for rows that predate the column and numbers too odd to normalise. `App\Support\PhoneNumber` is the only normaliser; its country list is `config/phone.php`, which mirrors the phone field's JS list in resources/js/phone.js + prototype/styledesk.js. Do not add libphonenumber for this.

## A walk-in becomes a client only when somebody presses a button
The auto-save briefly did this and it was a bug: `autosave` fires on a debounce while a receptionist types, a phone number is half-typed for most of the time it is being entered, and the client list filled with unreachable strangers while the duplicate lookup attached bookings to whoever the first digits matched.

Two writers, both explicit:
- `BookingController@saveWalkInClient` (`POST bookings/walk-in-client`, "Save walk-in details") — validates strictly, resolves, writes `booking_leads.client_id`.
- `store()` on Confirm — reads `$lead?->client_id` FIRST, resolving only when there is no lead. Resolving twice makes a second record whenever a detail changed between the two moments.

`autosave` must NEVER touch the client list. Its `guest_phone`/`guest_email` rules are deliberately loose (`string|max:`) — strict rules there refuse every address on the way to a whole one and take the rest of the booking's auto-save down with them. The lead is a scratchpad; what is typed into it is held to its shape when it is used. `matchClient` is the read-only lookup that runs while typing — keep it read-only.

`resolve()` returns a `WalkInClient`, never a `?Client`: "the address is one person and the number is another" has no client to return, is not a failure, and must reach the screen with both records so a human chooses. It never merges, and only ever fills a blank on a record it matched.

## "Now" for a booking is the branch's clock, via BusinessClock
`app.timezone` is UTC and the desk is not. Every question about "now" or "today" that a booking depends on goes through `App\Support\BusinessClock`, which resolves location timezone → tenant timezone → `app.timezone`. Never `now()`, `today()`, or `Carbon::now()` bare in availability, slot filtering, or booking validation — a salon in Austin is still on the previous date for five hours of the server's morning.

Past slots are dropped in `BookingAvailability::dropPast()`, before the three questions that cost a query each. `reason` is `day_over` (open, but the day is spent) — distinct from `nothing_free` (taken or nobody on shift). `store()` re-checks with `BusinessClock::hasPassed()` because the screen is not the rule: a tab open since morning still holds morning's list.

Scope, deliberately: ONLY today is narrowed. A date wholly in the past comes back untouched, because writing up yesterday's walk-in is legitimate and refusing it makes the books unfixable. The check is on the START time (`<=` now, so the slot that just began is gone), not the end. It is on `store()` only — `validateBooking()` is shared with editing, and blocking that would stop anyone correcting a past appointment's details.
