---
paths:
  - 'app/{Models/BackofficeAdmin.php,Models/BackofficeAuditLog.php,Models/BackofficeLoginCode.php,Http/Controllers/Backoffice/**,Http/Middleware/AuthenticateBackoffice.php,Support/BackofficeVerification.php},routes/backoffice.php,config/backoffice.php'
---

# Middleware

## The Backoffice is a separate guard, and must stay one
The platform console at /backoffice authenticates on its own `backoffice` guard against `backoffice_admins`, NOT on `web`/`users`. A StyleDesk administrator belongs to no tenant, so `BelongsToTenant` must never be added to BackofficeAdmin and no tenancy middleware may be attached to routes/backoffice.php — the routes are registered in bootstrap/app.php `then:` with the `web` group only.

Rules that are easy to break:
- Sign-in is three steps: email → one-time code → password. `backoffice.verified` middleware is what makes step 2 a gate; without it anyone can type straight to /backoffice/login. LoginController also pins the submitted address to the verified one, or the code is a formality (verify your own address, submit a colleague's).
- An unknown address must be indistinguishable from a known one: same redirect, same wording, a `backoffice_login_codes` row either way, and one refusal message for wrong-password / disabled / no-such-admin. Anything else turns the login page into a staff list.
- AuthenticateBackoffice re-reads `status` from the database every request. Trusting the session means an administrator disabled at 09:10 keeps the console until their cookie expires.
- EnforceSessionTimeout (the salon app's) skips `backoffice/*` on purpose. Both consoles share one session cookie, so without the skip an idle salon session signs the administrator out and drops them on the salon's login screen.
- Roles/permissions live in config/backoffice.php only — there is no role table. Every route names a permission via `backoffice.can:`; a route naming none fails closed.
- Permissions are checked with `$admin->can()`, which returns false for a disabled admin regardless of role.
- The Super Owner password is never in source. BackofficeSuperOwnerSeeder reads BACKOFFICE_SUPER_OWNER_PASSWORD or generates one and prints it once, and never resets an existing row.

Blade trap hit twice in this codebase: a directive argument containing a comma inside brackets — `@section('intro', __('k', ['x' => $y]))` or a multi-line `@json([...])` — does not parse; Blade counts brackets rather than reading PHP, and the tail prints on the page. Use the block form (`@section(...) ... @endsection`) or build the array in `@php` first.
