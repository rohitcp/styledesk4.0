---
paths:
  - 'resources/views/settings/**/index.blade.php'
---

# Resources Views Settings

## A settings enable switch carries EVERY validated field as a hidden input
Loyalty, Membership, Reviews and Tips all use the same shape: the enable toggle is its own form at the top, posting the module's single `update()` action and carrying every other setting as a hidden input, so switching on cannot reset what somebody configured. Nothing below the switch renders until it is on.

The trap: `update()` validates the WHOLE settings payload, and the fields that ask about those settings live in the second form — which is not on the page while the module is off. Add a `required` rule (or a new field) without adding its hidden pass-through and the toggle silently fails validation, `back()`s, and reloads unchanged. The module cannot be switched on at all, with no error visible.

This shipped broken in Loyalty: commit 3d141c0 added `enrollment_mode` and `welcome_points` as required and put the inputs only in the rules form. Fixed in resources/views/settings/loyalty/index.blade.php.

When you add a field to one of these screens, add it to the switch form's hidden block in the same change. Test it by rendering the page and posting the switch form's own inputs, not a hand-written payload — hand-written payloads are why the existing tests all passed. See LoyaltyRewardsTest::test_the_switch_on_its_own_turns_the_scheme_on.
