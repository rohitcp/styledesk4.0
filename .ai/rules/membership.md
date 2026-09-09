---
paths:
  - 'app/{Models/MembershipSettings.php,Http/Controllers/Settings/MembershipSettingsController.php},config/membership.php,resources/views/settings/membership/**'
---

# Membership

## Membership rollover and reset are one stored answer, not two
`membership_settings` holds both `allow_rollover` and `reset_credits_on_cycle`, but the screen only ever asks about rollover. The controller writes `reset_credits_on_cycle = ! allow_rollover` and nulls `maximum_rollover` whenever rollover is off. Storing them independently produces credits that both survive the cycle and are wiped by it, and a rollover cap that reappears unexplained the day somebody turns rollover on. Do not add a second toggle for reset.

Same shape as Loyalty/Reviews/Tips: `forTenant()` returns an unsaved model so reading the screen never writes a row; the enable switch is its own form at the top carrying every other answer as hidden fields, so switching off keeps the terms and every existing member's plan, credits and history. Nothing below the switch renders until it is on.

`config/membership.php` is the vocabulary (types, billing frequencies, activation, credit expiry, cancellation timing, channels) — validate against it via the model's static accessors, never against inline literals. A channel with `available => false` renders disabled and is refused by validation.
