---
paths:
  - 'app/{Models/ClientActivity.php,Support/ClientActivityLog.php}'
  - 'app/{Models/ClientPaymentMethod.php,Payments/VaultsCards.php,Payments/StripeGateway.php,Payments/PaymentGatewayManager.php}'
  - 'app/{Models/TenantStripeAccount.php,Models/StripeWebhookEvent.php,Payments/StripeWebhook.php}'
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

## StyleDesk stores payment references, never cards — the vault contract
`client_payment_methods` holds a gateway token plus display-safe metadata (brand, last4, exp month/year) and NOTHING else. Never add a column for a card number, CVV/CVC, PIN or track data — one such migration moves the business inside PCI scope. `ClientPaymentMethodTest::test_the_table_cannot_hold_a_card` names the forbidden columns so that becomes a failing build.

Card capture goes browser → gateway's own secure component (Stripe Payment Element) → token → StyleDesk. No method on `VaultsCards` ever takes a card number, expiry or CVC as an argument; if one needs to, the design is wrong. `rememberCard()` re-reads brand/last4/expiry FROM the gateway rather than trusting what the browser posted — what a page claims a card is and what the gateway will charge must be the same thing.

`VaultsCards` is a SEPARATE contract from `PaymentGateway` because most gateways are not vaults: cash and terminal-card are recorders and cannot be charged again next month. Resolve with `PaymentGatewayManager::vault($tenant)`, which returns null when the business has no processor connected — every Card on File screen must handle null rather than failing at the last step.

Renewals use `chargeSavedCard()` with `off_session`, and the SetupIntent is created with `usage: off_session` so Stripe collects the extra authentication while the client is present rather than failing the first renewal. Anything but `succeeded` throws — a "processing" intent has not paid for the month, and issuing credits against it gives away a massage on a promise. Idempotency keys are mandatory on both charge paths.

A card expires at the END of its month (`expiresAfter()` = end of exp_month). Using the 1st would refuse a valid card for thirty days. `markRemoved()` never deletes: a membership renewed on that card still points at it. `client_memberships.payment_method_id` is nullOnDelete so losing a card makes the next renewal ask for a new one instead of taking the membership down.

## Sandbox is read off the Stripe key, never from a setting
Whether a Stripe connection moves real money is decided by the KEY: `sk_test_`/`rk_test_` cannot charge a real card and `sk_live_`/`rk_live_` cannot avoid it. `TenantStripeAccount::isLive()` derives it; `tenant_stripe_accounts.livemode` is only a CACHE so badges and lists need not decrypt a secret to render. Never add a user-facing sandbox toggle — a business believing it is testing while charging real cards is the worst failure this screen can produce. An unrecognised key prefix is treated as NOT live, because the safe direction for a wrong guess is a business told it is in sandbox when it is not.

`statusKey()` returns 'sandbox' ahead of 'connected' for a working test connection: "Connected" where no real money can move is the reading that gets a salon to open for business on test keys. It still returns 'needs_attention' when Stripe will not let the account charge — sandbox is not an excuse to claim connected.

Every webhook is written to `stripe_webhook_events` BEFORE it is acted on, keyed by Stripe's unique `event_id`. Stripe delivers at least once and retries on any non-2xx, so `begin()` recognises a repeat as the same row and increments `attempts`. Handlers return the tenant id they touched (or null); null means 'ignored', which is an OUTCOME not a failure — logging the events StyleDesk has no opinion about as errors buries the ones that matter. Exceptions are caught, logged to the row and reported, and the endpoint still answers 200: a non-2xx makes Stripe retry an event that will fail identically forever.

`api_key` is `encrypted` + `$hidden`. A Stripe secret can charge, refund and read every customer on the account — it is never rendered, logged or serialised, and `StripeSandboxTest` asserts it never reaches a screen.
