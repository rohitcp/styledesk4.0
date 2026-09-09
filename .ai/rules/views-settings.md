---
paths:
  - 'app/Http/Controllers/Settings/**,resources/views/settings/**'
---

# Views Settings

## Edit screens return to the page that opened them via ?return=
A settings form reachable from more than one place (Languages, Currency, Branding, Locations are all opened from their own module and from the Business summary) must not hard-code Back.

Linking side: `route('settings.languages.edit', \App\Support\ReturnTo::from(request()))` appends `?return=<current path>`.

Form side: the controller spreads `$this->returnTo($request, $fallbackUrl)` (on the base Controller) into the view, giving `$returnTo` (absolute URL for Back/Cancel) and `$returnPath` (for `<x-return-to :path="$returnPath" />` inside the form). `update()`/`store()` then redirect with `->to(ReturnTo::resolve($request, $fallback))`. Breadcrumbs keep pointing at the module — they are the trail, not an exit.

`ReturnTo::path()` accepts only a path on this app: `//host`, a backslash and anything with a scheme are refused, or every edit link becomes an open redirect. Never swap it for `url()->previous()` — the referer is gone once a rejected form redirects back to itself. Covered by tests/Feature/ReturnToTest.php.
