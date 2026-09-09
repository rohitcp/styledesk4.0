---
paths:
  - 'app/{Http/Controllers/MembershipSaleController.php,Http/Controllers/ClientPaymentMethodController.php,Support/MembershipPurchase.php}'
---

# Controllers Controllers

## auto_renew is who takes the money, not whether it is owed
`client_memberships.auto_renew` decides whether StyleDesk charges the stored card automatically. It does NOT decide whether the subscription renews: `next_billing_on` is set for every recurring membership either way, because a business with no processor connected still has money to collect and needs a date to chase. Unticking the box means "collect each renewal at the desk", never "this is a one-off". A package has no `next_billing_on` at all — a question that does not apply.

The server never defaults `auto_renew` to true (`$request->boolean('auto_renew')`). The SCREEN ticks the box for a recurring plan, and only when `cardVault.available` — a subscription that starts charging a card because a field was missing is the one mistake this default cannot make, and defaulting it on would have made recurring memberships unsellable for businesses with no Stripe.

`auto_renew` true requires a chargeable card, refused at the point of sale (`guardCard`) rather than discovered on the first billing date. `cardFor()` checks both that the card is THIS client's — an id in a form is a number a reader can change — and that it is chargeable, so a sale cannot be booked against an expired or removed card.

`ClientPaymentMethodController::store` takes a token id and a customer id, never card fields. Its `present()` is the only shape a card reaches any screen in. `destroy` refuses while a live `auto_renew` membership points at the card: the alternative is a subscription whose next payment silently fails and a client who finds out when their credits stop.
