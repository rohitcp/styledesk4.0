---
paths:
  - app/Support/ClientVisitSummary.php
---

# Support

## Client summary cards: four figures, four different counts
`ClientVisitSummary::for()` feeds the four cards at the top of a client profile. Each counts something different and, more importantly, refuses something different — do not fold them into one query.

- **Last visit / Total visits** count `status = 'completed'` ONLY (`self::VISITED`). Not confirmed-but-future, not cancelled, not no-show, never draft leads. A booking in the diary for Tuesday is not a visit.
- **Next appointment** is `confirmed` with `date >= today`. Not `arrived` — that is "where are they now", not "when are they next in" — and today counts all day, because a 2pm appointment is still the answer at 2:30.
- **Lifetime spend** comes from `booking_payments` (`paid` + `refunded`), NEVER from `bookings.total_minor`. A bill somebody was quoted and never settled is not spend. Refunds are negative rows, so the sum is what the business still holds.

The cards are a Vue island (`ClientVisitSummary.vue`) refreshed from `clients.visit-summary` on `focus`/`visibilitychange` — not polling. A payment taken at the till in another tab should not leave this page showing yesterday's numbers, but a page asking every ten seconds asks a hundred questions nobody was in the room for. A `<noscript>` block renders the same figures.

Values longer than 12 characters get `styledesk_metric__value--compact` (14px, nowrap): "4 Sep 2026 · 10:30 AM" is one sentence and must not break after the date, or the stray "AM" underneath reads as a second answer. Counts and totals stay at 17px.
