---
paths:
  - 'app/{Support/ClientServiceHistory.php,Models/Client.php,Http/Controllers/ClientController.php}'
---

# Support Controllers

## Client services: history is arithmetic, favourites are a statement
Two lists on the client Services tab, and they must never merge.

- **Service history** — `ClientServiceHistory::for()`, grouped from `booking_services` over bookings with status `confirmed`/`arrived`/`completed`. One row per service, not per appointment. Lines whose `service_id` is null (service deleted from the price list) are skipped rather than grouped by the copied name — two spellings of "Cut" is a count nobody can trust. Nothing here is editable; it is arithmetic.
- **Favourite services** — `client_favorite_services`, `Client::favoriteServices()`. Set ONLY by hand, via `clients.favorite-services.store` / `.destroy`. A service booked six times does NOT become a favourite on its own: a favourite nobody chose is one nobody can be asked about, and the receptionist who trusts the list stops being able to tell which is which. Favourites work for a client with no booking history at all ("this is what I always have").

Both endpoints return BOTH lists (`serviceTab()`), because starring a service changes a history row too — a screen patching two lists locally is one where they can disagree.

Booking integration: `bookings/create` accepts `?client=` OR `?client_id=`, carries only the id (a tab open since Tuesday must not bring Tuesday's phone number into today's booking), and the client payload includes `favorite_service_ids`, `preferred_staff_id`, `preferred_location_id`. The service selector shows a **Client favourites** row above the list — hidden while searching or filtering by category, where a favourite outside the current filter reads as a bug. `applyClientPreferences()` sets the branch and stylist only when nothing has been chosen yet; a receptionist's choice is never overruled by a months-old preference.
