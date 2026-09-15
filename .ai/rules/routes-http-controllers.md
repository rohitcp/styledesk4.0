---
paths:
  - 'routes/tenant.php,app/Http/Controllers/PublicFormController.php'
---

# Routes Http Controllers

## A tenant-route controller takes the subdomain as its first argument
Every route in routes/tenant.php is registered under `Route::domain('{tenantSubdomain}.'.config('tenancy.tenant_domain_suffix'))`, so it carries TWO route parameters. Laravel fills a controller's non-class arguments from the route's parameters in ORDER, not by name.

So `show(Request $request, string $token)` receives the SUBDOMAIN in `$token`. The lookup then finds nothing and 404s a record that is perfectly live — and it looks identical to a missing route, which is why it cost an afternoon on the public form. Write `show(Request $request, string $subdomain, string $token)` and leave `$subdomain` unused; the tenant itself comes from `tenant()`, resolved by the middleware.

Two related traps when working on tenant routes:
- `config('tenancy.central_domains')` and `tenancy.tenant_domain_suffix` must agree. stancl's subdomain middleware refuses any host that does not END with a central domain ("Hostname x does not include a subdomain"). phpunit.xml pins both so the suite does not depend on a developer's local hostname.
- Testing one locally without a wildcard vhost: `php artisan serve` plus `curl -H "Host: <slug>.<suffix>" http://127.0.0.1:<port>/…`. Apache/Herd will otherwise answer with their own 404 and the request never reaches Laravel — a StyleDesk-styled 404 means you got through, a plain server one means you did not.
