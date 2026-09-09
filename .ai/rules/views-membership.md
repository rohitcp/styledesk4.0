---
paths:
  - 'app/{Models/MembershipPlan.php,Models/MembershipPlanService.php,Http/Controllers/MembershipPlanController.php},resources/views/membership/**'
---

# Views Membership

## Membership plans: one table for both kinds, nullable credit overrides, no wizard saves
`membership_plans` holds recurring plans and packages in one table, the way `promotions` holds coupons and offers. The kind-specific columns are nullable and named for their kind (`billing_frequency`/fees = recurring, `regular_value_minor` = package); `columns()` nulls the other kind's columns on every save so a stale field from the form cannot survive onto the wrong product. Type is immutable after create — validation pins it to the existing row — because changing it would change what every credit already granted under it means.

Per-plan credit rules (`credit_expiry`, `allow_rollover`, `maximum_rollover`, `allow_service_substitution`) are NULLABLE overrides meaning "follow App Settings". Read them through `MembershipPlan::creditRules($settings)`, never off the columns. Never copy the business default onto the plan at create time — the copy silently stops following the setting the day it changes. The form posts these as three-state combos ('' / '0' / '1'); '' arrives as null via ConvertEmptyStringsToNull.

Step 1 (recurring vs package) is its own screen; steps 2–8 are one form with one atomic save. Do not turn it into a saved-per-step wizard: a half-built plan is a priced product in the list the desk sells from. Publish/draft is two submit buttons named `is_draft`, not a second request.

The whole module 404s when `membership_settings.is_enabled` is false (`MembershipPlanController::allow()`), and `Nav::visible()` hides the menu entry via a `feature` key in config/navigation.php. `Nav::features()` memoizes on the Request's attribute bag, never in a static — a static outlives the request and answers the next page with the previous business's answer (this broke a test).
