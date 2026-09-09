---
paths:
  - 'app/{Http/Controllers/ClientMembershipController.php,Support/ClientMemberships.php},resources/views/clients/partials/_membership.blade.php'
---

# Clients Partials

## Cancelling a membership at end of cycle leaves it live; "cancelling" is derived, never stored
`ClientMembership::status()` derives three states the column never holds. Always call `status()`, never read `$membership->status` for display or logic:
- `ends_on` earlier than today → 'ended' (use `lt(today)`, NOT `isPast()` — a membership "ending on the 8th" is usable through the 8th, and isPast() calls it over at 00:01 that day)
- `cancelled_at` set while still running → 'cancelling'
- stored 'scheduled' whose start date arrived → 'active'

`isLive()` includes 'cancelling': the client paid for the cycle they are standing in, so cancelling at end of cycle must NOT take their credits away. Only an immediate cancellation writes `status = 'cancelled'`.

`scopeLive()` wraps its conditions in a single `where(fn ...)` group. It is used inside `whereIn(... ->select('id'))` subqueries for credits, and a bare `orWhere` there escapes the surrounding conditions — that is how a client's credit lookup starts returning every client's.

Cancellation dates are computed in `cancellationTakesEffect()`, never posted: notice period first (`today + notice_days`), then end-of-cycle takes the later of that and `next_billing_on - 1 day`. "Immediately" means when the notice runs out, or the notice setting would do nothing. Commitment is counted from `starts_on`, not the sale date.

Pause clears `next_billing_on`; resume recomputes it a cycle from TODAY, because the client did not pay for the paused months.

Test trap: jumping `Carbon::setTestNow` forward more than the idle-session window makes the next request redirect with `session_timed_out`. Call `$this->flushSession()` before acting again.
