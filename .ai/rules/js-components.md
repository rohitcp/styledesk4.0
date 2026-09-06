---
paths:
  - 'app/{Support/ResourceAvailability.php,Support/ResourceAllocator.php,Http/Controllers/ResourceAvailabilityController.php},resources/js/components/ResourceAvailability.vue'
  - 'app/{Support/ActivityStream.php,Http/Controllers/ActivityController.php},resources/js/components/ActivityPanel.vue'
---

# Js Components

## Resource availability: one engine, one clock, HTML frame
The availability board (`/resources/availability`) must never work availability out for itself. `ResourceAllocator::occupancyFor()` owns how long a room is held — appointment + preparation + cleaning — and `ResourceAvailability` reads it rather than restating it. A chart that draws a room free while the booking screen refuses to book it is invisible until a client has been promised the room.

`statusAt()` returns NULL on any day but today, and the UI must respect it: "Available now" / "In use" said about last Tuesday is wrong, not cautious. The Vue swaps the three snapshot cards, the row meta line and the detail panel's current/available-until rows for what a past day CAN answer (utilization, bookings) whenever `board.is_today` is false. The state filter track is hidden there for the same reason.

The day is modelled as an array of minutes (max 1440 × tens of resources), not intersected intervals: capacity falls out as a count, so a couple room with one client in it is still available. Blocks paint `capacity` weight — a room being repainted is not half available.

The chart's frame is HTML and only the bars are SVG. This is not a style choice: the resource names must stick to the left of the scroller and the hour header to its top, and SVG cannot stick to anything. Row height (ROW) and name width (NAME) are JS constants read by both the template and the scale — do not duplicate them into the stylesheet.

SVG text neither wraps nor clips; `clip()` cuts a block's label to its own width. Removing it puts service names over the next appointment and past the end of the chart.

## Business activity is read, never written
The activity panel has NO table and no write path. `ActivityStream` reads four records the app already keeps — `client_activities`, `booking_status_changes`, `audit_logs`, published `staff_shifts` — and normalises them. Do not add a fifth "activities" table: every controller would have to remember to write to it, and the day one forgets is the day the feed stops being true.

The sentence shown is the sentence that was stored. This class adds only the one-word type, the icon, the link, and the day grouping.

Trap (fixed): a check-in is recorded in TWO places — the client's timeline and the booking's status history — so the feed showed it twice. `dedupe()` keys on kind|booking reference|minute, and the sources are concatenated with `fromStatusChanges()` FIRST because `unique()` keeps the first occurrence and the status row is the better sentence (it names the booking and carries the reason). Reordering those concat calls silently reverts the fix.

Permission is applied per source, IN THE QUERY. `ownOnly()` returns a staff id for anybody without location-wide client access, and every source constrains on it; audit rows need `staff.view` at `location`. A filter applied after the query is one somebody can page past.

Unread is one `users.activity_seen_at` timestamp, not a row per item — the panel is a glance, not an inbox. First-ever visit counts only the last day, or a new user's badge reads 400.

Icons are chosen from data at runtime, so they cannot be the Blade `<x-icon>` component. `Icon::symbol()` builds a `<symbol>` and layouts/app.blade.php emits one sprite; the Vue references `#act-{kind}` with `<use>`.

Also: `__()` splits on dots, so a lang key that CONTAINS one ("booking.assigned") can never be reached as `__('...types.booking.assigned')` — it returns the key. Index the whole array instead. That bug printed raw keys down the notifications screen for months.
