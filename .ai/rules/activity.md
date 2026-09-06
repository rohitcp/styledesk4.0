---
paths:
  - 'app/{Support/ActivityStream.php,Http/Controllers/ActivityController.php},resources/js/components/ActivityFeed.vue,resources/views/activity/index.blade.php'
---

# Activity

## Business activity is a page, and is read never written
Supersedes the earlier "Business activity is read, never written" note on the drawer: the panel is gone. Activity is a PAGE at `/activity` (`activity.index` renders the view; `activity.feed` is the JSON the page pages through; `activity.read` stamps `users.activity_seen_at`). The app bar carries a plain `<a target="_blank" rel="noopener">` with a server-rendered badge — not a Vue island — so whatever the reader was working on survives them reading the log. Do not turn it back into a drawer: it is scrolled, filtered, followed into records and come back from, and a panel that shuts on an outside click fights all four.

The icon sprite (`Icon::symbol()` → `#act-{kind}`) lives on the activity page only, not in the layout. Adding it back to layouts/app.blade.php puts 24 SVG symbols on every page in the app for one screen that uses them.

Everything else from the earlier rule still stands: no activities table (four existing sources are read and normalised), `dedupe()` keys on kind|reference|minute with `fromStatusChanges()` concatenated FIRST so its better sentence wins, permission is applied per source in the query via `ownOnly()`, and unread is one timestamp capped at 99+.
