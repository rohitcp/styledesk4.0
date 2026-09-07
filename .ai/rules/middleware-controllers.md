---
paths:
  - 'app/{Models/Tenant.php,Http/Middleware/EnsureBusinessIsActive.php,Providers/FortifyServiceProvider.php,Http/Controllers/OnboardingController.php}'
---

# Middleware Controllers

## Tenant status is access only; trial counts as active
`tenants.status` answers "may anybody sign in?" and only `STATUS_DISABLED` says no. `STATUS_TRIAL` is a stage of setup: written when the Business step creates the workspace, cleared to `STATUS_ACTIVE` when the wizard completes, and `isActive()` accepts it.

Whether a trial has run out is a billing question — that lives in `subscription_status` (`trialing`/`past_due`/`canceled`), which is what `displayStatus()` reads. Never add a status value that `isActive()` does not classify: `isDisabled()` is `! isActive()`, so an unclassified value silently means "switched off" to the login form (FortifyServiceProvider) and to EnsureBusinessIsActive, which runs on every web request. That is exactly how `trial` logged every new owner out on the redirect after the Business step.
