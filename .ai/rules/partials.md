---
paths:
  - 'app/{Models/LoyaltySettings.php,Models/ClientLoyaltyPoint.php,Support/LoyaltyPoints.php,Http/Controllers/ClientLoyaltyController.php,Http/Controllers/Settings/LoyaltySettingsController.php},config/loyalty.php,resources/views/settings/loyalty/**,resources/views/clients/partials/_rewards.blade.php'
---

# Partials

## Loyalty points reconcile; they never accumulate
`LoyaltyPoints::settle()` never "adds the points for this booking". It computes what the booking *should* have earned (completed + net-paid, in proportion) minus what `client_loyalty_points` already holds for it, and writes only the difference. That one shape is why a partial refund, a full refund, a cancellation, a deposit and a payment arriving in two halves all work with no branch of their own — and why it is safe to call on every payment event. Do not "optimise" it into an accumulator.

It is called from `Booking::settlePaymentStatus()` (the one choke point every gateway, webhook and manual payment already passes through) and again from `BookingStatusController::complete()` and its cancel/decline/no-show path, because the visit finishing and the money landing can happen in either order. Whichever comes second writes the line. Like `ReviewRequests`, it swallows every error and logs: taking money must never fail over a points balance.

Two things are deliberately *not* rows. Pending points are computed from upcoming bookings (`pendingFor`) — a forecast written into a ledger is the row nobody deletes when the client cancels. Expiry is applied when the balance is summed (`ClientLoyaltyPoint::scopeLive`), so a business that sets a deadline gets one with no nightly sweep, and "lifetime earned" still counts points that have since expired.

One balance per client for the whole business (§15): `location_id` records where a line happened and never divides it. Refund rows are `abs()`d in `netPaidMinor()` because the gateways disagree on whether a refund's `amount_minor` is stored negative — don't make the balance hostage to that.
