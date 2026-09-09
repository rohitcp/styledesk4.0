---
paths:
  - 'resources/views/settings/index.blade.php,resources/css/styledesk.css'
---

# Settings Css

## The settings directory accordions render open and are closed by script
Each group on /settings is a `.styledesk_accordion` disclosure: a `<button data-settings-toggle>` header inside the `<h2>`, and a panel that animates on `grid-template-rows: 0fr -> 1fr` (not max-height — a guessed max-height either pauses on short groups or clips tall ones).

The markup ships every group with `data-open="true"` and `aria-expanded="true"`. The script collapses all but the first on arrival. Closed-by-default markup would leave a reader without JavaScript looking at seven headings and no way to open one. Only one group is open at a time.

Searching overrides that: while the box has a query, every group holding a match opens; clearing it restores first-open. A filter that leaves its own hits collapsed is the bug this guards against.

Counting headers in a test: match on `aria-controls="settings-group-"`, not `data-settings-toggle` — the script's own selector string is in the page too. Pinned by AppSettingsTest::test_each_group_renders_as_an_expanded_accordion.
