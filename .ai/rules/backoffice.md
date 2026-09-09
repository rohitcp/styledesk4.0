---
paths:
  - 'app/{Models/BackofficeAdmin.php,Http/Controllers/Backoffice/PasswordResetController.php},config/auth.php'
---

# Backoffice

## Backoffice password reset is separate down to the token table
The console's reset must not reuse anything from the salon app.

- BackofficeAdmin overrides `sendPasswordResetNotification()`. Do not remove it. The inherited version sends Laravel's ResetPassword notification, whose URL comes from `route('password.reset')` — the salon screen, backed by the wrong broker, where the token can never be redeemed. The override mails BackofficePasswordResetMail pointing at `backoffice.password.reset`.
- The `backoffice_admins` broker uses its own table, `backoffice_password_reset_tokens`. A separate broker is NOT enough: Laravel's DatabaseTokenRepository keys on the email address with no guard column, so one shared table means one row per address across both consoles — a salon owner and an administrator with the same address would hand each other working reset links.
- Sent, not queued, like the sign-in code: the link is short-lived (30 min) and a lagging worker turns it into a dead one.

Covered by tests/Feature/BackofficePasswordResetTest.php.
