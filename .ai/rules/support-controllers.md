---
paths:
  - 'app/{Support/ClientServiceHistory.php,Models/Client.php,Http/Controllers/ClientController.php}'
  - 'app/{Support/MembershipCredits.php,Models/MembershipCreditRedemption.php,Http/Controllers/BookingQuoteController.php}'
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
