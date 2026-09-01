---
paths:
  - 'app/{Models/ClientActivity.php,Support/ClientActivityLog.php}'
---

# Models

## Client activity is an audit trail, written once and never edited
`client_activities` is the client's history. It is NOT derived — the old timeline reconstructed itself at render time from whatever still existed, which can only ever describe the present. These rows are written when things happen and outlive what they describe: a note added and later deleted leaves both entries.

- **Write through `ClientActivityLog` only.** One place knows the shape of every entry; two spellings of `booking.cancelled` is a filter that silently stops matching half the history. Every method swallows its own errors — a booking must not fail because its history could not be written.
- **Immutable.** `ClientActivity` has `UPDATED_AT = null` and `updating`/`deleting` model hooks that return false. There is no route to edit or delete one. Do not add `->update()` on it.
- **`type` and `category` are two columns on purpose.** `booking.rescheduled` is the event; `bookings` is the tab. Deriving the second from the first by string surgery is how a filter breaks.
- **Lang keys must not contain dots.** `title()` maps `booking.created` → `booking_created` before the lookup, because Laravel walks `events.booking.created` one segment at a time and can never reach a key that itself contains a dot — it returns the key string and prints it on the page. This bit once.
- **Note bodies are gated twice** (`readableDescription`): the notes permission, AND private notes are never shown in the timeline at all. Who may read a private note is decided per note by author and role, and this row outlives the note — for a deleted one there is nothing left to ask.
- **Reschedule is a change of START, not of duration.** Compare `date + startsAt()`, never `timeLabel()`: the label carries the end time, which moves whenever a service is added.
- **Client edits are one entry per save**, with a `changes` array of `{field, from, to}`. Ids are resolved to names — "3 → 7" tells a reader nothing about which branch. Contact details reach the diff via the denormalised `clients.mobile`/`email` columns that `syncPhones`/`syncEmails` maintain.

`user_id` null means StyleDesk itself, and the timeline says "StyleDesk System" rather than leaving a blank that reads as missing data. `bookingCancelled()` exists but has no call site yet — the cancel flow is not built.
