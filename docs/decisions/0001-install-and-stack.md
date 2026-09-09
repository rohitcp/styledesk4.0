# 0001 — Initial install and stack decisions

Date: 2026-08-25
Status: Accepted

Record of the choices made while installing the StyleDesk application skeleton,
and the reasoning behind each. Written to satisfy the project's AI coding
workflow rule: document the planning and reasoning, not just the result.

## Context

The repository already held `html/` — a ~40-page static prototype with a real
design system (291 `sd-*` BEM classes, `--sd-*` runtime brand tokens, a custom
Tailwind config). No Laravel application existed yet.

## Decisions

### Laravel 13 (v13.29.0)

Requires PHP `^8.3`; the MAMP PHP is 8.3.30, so it qualifies. `stancl/tenancy`
v3.10.1 declares `illuminate/support: ^13.0`, so the tenancy package — the
highest-risk dependency — supports it. Laravel 12.68 was the fallback had that
not held.

### Install at the project root, `html/` left in place

Chosen by the project owner. Laravel serves from `public/`, so the prototype in
`html/` does not collide with anything. A collision check was run before moving
the skeleton in: zero conflicts.

### Single-database multi-tenancy

All tenants share one schema; rows are separated by a `tenant_id` column and the
`BelongsToTenant` global scope.

Two changes were required, because `stancl/tenancy` ships configured for
*multi*-database tenancy:

1. `DatabaseTenancyBootstrapper` removed from `config/tenancy.php`. It is the
   bootstrapper that swaps the connection to a per-tenant database.
2. The `CreateDatabase` / `MigrateDatabase` / `DeleteDatabase` job pipelines
   removed from `TenancyServiceProvider`. There is no per-tenant database to
   create, migrate or drop.

`tests/Feature/TenancyResolutionTest` asserts that initializing a tenant does
not change the connection, so re-enabling either fails loudly.

### Hybrid tenant identification

| Surface | Host | How the tenant is resolved |
|---|---|---|
| Public booking | `acme.styledesk.app` | `InitializeTenancyBySubdomain` (alias `tenant.subdomain`) |
| Central app | `styledesk.app` | `InitializeTenancyFromUser` (alias `tenant.user`) |

The central app is one hostname shared by every business, so the host header
carries no tenant — the signed-in user does. Resolving from `users.tenant_id`
rather than a URL segment means a request cannot be aimed at a tenant the user
does not belong to.

### One tenant per user

`users.tenant_id` foreign key, no pivot, no tenant switcher. Chosen by the
project owner. A person working at two businesses needs two accounts.

`tenant_id` is deliberately **not** in the User model's `#[Fillable]`: it decides
which business's data a user can see and must never come from request input.

### Fortify for auth (headless)

The prototype already contains `login.html`, `signup.html` and
`verify-email.html`. Fortify supplies the backend (2FA, verification, resets)
with no views to fight or delete.

### Tests run against MySQL, not SQLite

`phpunit.xml` was changed from in-memory SQLite to `styledesk_v2_testing`.
Tenant isolation leans on real foreign keys, unique indexes and MySQL JSON
semantics; SQLite would pass tests that MySQL fails.

## Problems found and fixed during install

### The `styledesk` database already held an abandoned schema

`CREATE DATABASE IF NOT EXISTS styledesk` silently reused a database from an
Aug 24 build. Its `migrations` table already listed `create_users_table`,
`create_tenants_table` and `create_domains_table`, so this install **skipped**
them and inherited the old tables — `users.first_name`/`last_name` where
Laravel 13 and Fortify expect `name`, plus a `tenant_memberships` pivot
contradicting the one-tenant-per-user decision.

Resolved by the project owner's choice: a fresh `styledesk_v2` /
`styledesk_v2_testing` pair, leaving the old databases untouched. Both were
dumped to `storage/app/backups/` beforehand.

### `TenancyServiceProvider` was never registered

`php artisan tenancy:install` appends the provider to the Laravel 10-style
`config/app.php` providers array, which Laravel 11+ no longer reads. The
provider never booted, so tenancy events, bootstrappers and `routes/tenant.php`
were all silently inert — tenancy appeared to work only because *no*
bootstrapper was running. Added to `bootstrap/providers.php` by hand.

### Central `/` silently replaced the tenant `/`

Laravel's `RouteCollection` is keyed by domain + URI. An unconstrained tenant
`/` and the central `/` produce the same key, so the one registered last
replaced the other and every request to a tenant subdomain hit the central
welcome page. Fixed by constraining tenant routes with
`Route::domain('{tenantSubdomain}.'.config('tenancy.tenant_domain_suffix'))`.
Registration *order* was investigated first and was not the cause.

### Reverb vs. Laravel 13 dependency conflict

`laravel/reverb` requires `guzzlehttp/psr7 ^2.6`; the Laravel 13 skeleton locked
3.1.0. Resolved with `composer require -W`. `symfony/postmark-mailer` was pinned
to `^7.4` because 8.x requires PHP >= 8.4.

## Deferred, not built

Per the project's workflow rule, no feature code was written. Specifically not
done, and awaiting a specification:

- Roles and permissions (`roles.html` exists in the prototype;
  `spatie/laravel-permission` was **not** installed as it was not requested).
- The Sendivo SMS notification channel. Sendivo has no first-party Laravel
  package; `config/services.php` holds the credentials, but the channel class
  itself needs writing.
- Porting the prototype's 291 `sd-*` classes into the `styledesk_` layer.
