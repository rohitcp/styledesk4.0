# 0002 — Onboarding: requirement, schema and acceptance criteria

Date: 2026-08-26
Status: **Proposed — awaiting approval. No code generated.**

Written per the project workflow rule: requirement, DB fields and acceptance
criteria are agreed before generation. Every field below is derived from an
actual input in the prototype's onboarding pages, not invented.

## Requirement

A six-step wizard a new account completes once, between registering and
reaching the dashboard. It is what creates the tenant and populates
`users.tenant_id`, which is what the central app resolves tenancy from.

| # | Step | Prototype page | Required? |
|---|---|---|---|
| 1 | Business | `onboarding-business.html` | Yes |
| 2 | Location | `onboarding-location.html` | Yes |
| 3 | Services | `onboarding-services.html` | Skippable |
| 4 | Team | `onboarding-team.html` | Skippable |
| 5 | Booking | `onboarding-booking.html` | Skippable |
| 6 | Complete | `onboarding-complete.html` | — |

Steps 3–5 each carry an explicit "I'll do this later" link in the prototype,
so skipping is a designed path, not an edge case.

### Server owns progress

`styledesk.js` states this in its own header: the localStorage draft is a
prototype store, and onboarding completion must never be determined from
front-end state. Progress therefore lives in the database, and each step
re-reads it. The prototype's `SD.STEPS` flags map one-to-one onto columns.

## Proposed schema

### 1. `tenants` — extend (table exists)

Already has `id`, `name`, `slug`, `status`, `data`.

| Column | Type | Source |
|---|---|---|
| `business_phone` | string(32) null | `bizPhone` |
| `business_phone_country` | char(2) null | phone widget ISO |
| `business_email` | string null | `bizEmail` |
| `website` | string null | `bizScheme` + `bizSite`, stored joined |
| `logo_path` | string null | `logo` — JPG/PNG/WEBP, max 2 MB |

`slug` is already unique and becomes the booking subdomain.

### 2. `tenant_onboarding` — new, one row per tenant

| Column | Type |
|---|---|
| `tenant_id` | FK unique |
| `current_step` | string(20), default `business` |
| `business_completed` / `location_completed` / `services_completed` / `team_completed` / `booking_completed` | boolean default false |
| `completed_at` | timestamp null |

Separate table rather than columns on `tenants`: this is transient setup
state, read constantly during onboarding and never again afterwards.

### 3. `locations` — new

`tenant_id`, `name` (default "Main Location"), `address_line1`,
`address_line2` null, `city`, `state`, `postal_code`, `country` (char 2),
`phone` null, `phone_country` null, `timezone`, `is_primary` bool.

### 4. `location_hours` — new

`location_id`, `day_of_week` (0–6), `is_open` bool, `opens_at` time null,
`closes_at` time null. Unique on (`location_id`, `day_of_week`).

### 5. `services` — new

`tenant_id`, `name`, `category` null, `duration_minutes` int,
`price_minor` int (minor units — integers avoid float rounding on money),
`currency` char(3), `description` null, `is_active` bool.

### 6. `staff` — new

`tenant_id`, `user_id` null FK, `first_name`, `last_name`, `email` null,
`phone` null, `role`, `is_active` bool.

`user_id` is nullable because the team step invites people who have no
account yet. The owner is seeded as the first staff row, linked to their user.

### 7. `service_staff` — new pivot

`service_id`, `staff_id`. Unique pair. From `owner-services` in the team step.

### 8. `booking_settings` — new, one row per tenant

`tenant_id`, `is_enabled` bool, `allow_new_clients` bool,
`allow_existing_clients` bool, `min_notice_minutes` int,
`max_advance_days` int, `cancellation_window_hours` int,
`require_card` bool, `require_email` bool, `require_phone` bool.

Public booking URL is derived from `tenants.slug`, not stored.

## Acceptance criteria

1. A user with `tenant_id = null` visiting any app route lands on step 1.
2. Completing step 1 creates the tenant, sets `users.tenant_id`, seeds
   `tenant_onboarding`, and marks `business_completed`.
3. `slug` is validated unique and reserved words (`www`, `app`, `admin`,
   `api`, `mail`) are rejected — it becomes a tenant subdomain.
4. Steps 3–5 can be skipped; skipping advances `current_step` without setting
   the completed flag.
5. Re-entering a completed step shows the saved values, not a blank form.
6. Progress is read from the database. Clearing localStorage does not change
   which step the user is on.
7. Finishing sets `completed_at`; the dashboard no longer shows "No business yet".
8. A completed user visiting an onboarding route is redirected to the dashboard.
9. Every write is scoped by `tenant_id` via `BelongsToTenant`; a request cannot
   write to another tenant.
10. Logo upload rejects files over 2 MB and non-JPG/PNG/WEBP.

## Open questions

1. **Currency and timezone.** The business step never asks for either, but
   services need a currency and locations a timezone. Infer from country, or
   add fields to step 1?
2. **Owner as staff.** Seed the owner into `staff` automatically at step 4, or
   only when they add themselves?
3. **`business_types`.** The abandoned Aug 24 database had this table with 13
   rows, but the current prototype's business step does not ask for one. Drop
   the idea, or is it coming back?
