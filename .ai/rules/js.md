---
paths:
  - 'resources/js/{listing-filters.js,data-grid.js}'
---

# Js

## Listing search is live and debounced; never append to data-url
The search box on a listing (`/services`, `/resources`, and any page using `listing-filters.js`) narrows the grid as the reader types. No Enter, no Search button — the buttons were removed because the grid is JS-only anyway, so there is no no-JS path to preserve.

Rules:
- 300ms debounce, minimum 2 characters. A term under the minimum counts as NO term, both in the box and in `reload()` — otherwise a reload from a chip or a stat card would disagree with what the reader last saw. Clearing bypasses the debounce: "show me everything again" should not wait.
- `setData()` restarts at page 1, which is what a new search means.
- The spinner is driven by `styledesk:grid-loaded`, dispatched from BOTH `ajaxResponse` and `ajaxError` in data-grid.js. A timer in the search box would guess, and guess wrong on a slow request.
- `[data-search-clear]` is handled by a DELEGATED listener: there are two of them — the cross in the field and the one the grid draws in its placeholder — and the second is replaced on every load.
- The empty state is search-aware: a search that found nothing offers "Clear search" (keeps filters); filters that found nothing offer "Clear filters" (drops everything). They are different dead ends.

**Trap (fixed, do not reintroduce):** `data-url` on the grid is rendered as `route('…data', $filters)`, so it ALREADY carries a query string whenever the page loaded with filters. `reload()` must take `new URL(...).pathname` — appending `?${query}` produced `?search=mas?status=active`, and everything after the first `?` arrived as part of the search term.

Server side, `scopeMatching` on Service and Resource reaches past the name: description, category, and for services the assigned resources and price (typed as major units, e.g. 35 not 3500); for resources the code, location and availability. Status/availability words are matched against the TRANSLATED labels with `str_starts_with`, because the label is what is on the screen the reader is searching.
