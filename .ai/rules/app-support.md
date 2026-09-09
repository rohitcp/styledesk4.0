---
paths:
  - 'app/{Support/PaymentCapabilities.php,Support/PaymentFees.php,Payments/StripeWebhook.php},config/payments.php'
---

# App Support

## A capability needs three yeses; a fee of null is not a fee of zero
`PaymentCapabilities::allows()` answers three questions and all must pass: is the feature BUILT (`available`), has the business switched it ON, and can its gateway actually DO it (`needs_processor` vs `PaymentGateway::processes()`). Skipping the third is how a Payment Link button appears on a salon taking cash — a button with nothing at the other end. Unknown capability keys return false, never true.

`tenants.payment_capabilities` null means "the defaults", so existing businesses do not wake up with everything off. An empty ARRAY is a real answer meaning "all off" — unlike `accepted_methods`, where empty falls back to the gateway. The settings screen posts a hidden field for any enabled capability whose processor is temporarily unreachable, or a disabled checkbox would silently switch it off.

Testing trap: gateway readiness depends on tenancy resolved by middleware, so `PaymentCapabilities::allows()` called bare in a test sees no processor. Assert processor-dependent capabilities through an HTTP request, not in isolation.

Payment fees are NULLABLE and null is the truth: Stripe settles the fee on a balance transaction minutes or hours after the charge, and writing 0 would be a claim the receipt cannot support. `processor_fee_minor` and `platform_fee_minor` stay apart — "what did Stripe cost me" and "what did StyleDesk charge me" are separate questions owners ask separately. `net_minor` includes the tip, because it settles on the same card and pays out in the same batch. `PaymentFees::record()` is idempotent.

Webhook handlers return the tenant id they touched, or null for "nothing to do" which logs as `ignored`, not `failed`. `charge.dispute.created` sets status `disputed` and NEVER reverses the payment — Stripe holds the money while the case runs; `dispute.closed` resolves to `chargeback` (lost) or back to `paid` (won). `payment_method.detached` marks the local card removed so renewals stop reaching for it.
