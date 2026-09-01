---
paths:
  - 'tests/**'
---

# Tests

## Never run two test suites at once
Every suite shares one MySQL database (`styledesk_v2_testing`) and RefreshDatabase drops and recreates its tables. A second `php artisan test` started while one is running makes both fail with deadlocks and "table doesn't exist" — hundreds of failures that look like real regressions and are not.

Before starting a run, check `ps aux | grep "[a]rtisan test"`. The full suite takes ~16 minutes; wait for it rather than starting a filtered run alongside it.
