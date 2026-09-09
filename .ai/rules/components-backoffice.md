---
paths:
  - 'app/Http/Controllers/Backoffice/ClientController.php,resources/views/backoffice/**,resources/views/components/backoffice/**'
---

# Components Backoffice

## Backoffice tables are server-rendered; the client record's tabs are URLs
The platform console does NOT use the salon app's Tabulator grid (resources/js/data-grid.js). It renders tables on the server so the console still lists a customer when a script fails to load. Use the shared `x-backoffice.data-table` component for any new console listing — it renders the toolbar (search, filters, rows-per-page), the sortable head, the empty state and the footer; you supply a `columns` array and the rows as the slot. It hides the paginator's built-in "Showing x to y" copy because the component prints its own, which is the one that survives on a single page.

The client record's six tabs (/backoffice/clients/{tenant}/{tab?}) are route segments, not JS panels: a tab must be bookmarkable and reloadable with its search and page intact. The route constrains {tab} with whereIn against ClientController::TABS, so an unknown tab 404s rather than quietly showing the Overview. ClientController::show loads only the open tab's rows.

Two derived-status traps: do not add a SQL status filter or ORDER BY for Staff — Staff::status() is a precedence over four columns and a WHERE would be a second definition of it (the team tab filters by location instead). Tenant has one off-switch (status=disabled) with a required reason; "suspend" and "deactivate" are the same mechanism, so only Deactivate/Activate are offered.

Quick actions are data, built in ClientController::quickActions() — add an entry there rather than adding a button to the view. Actions with no implementation yet (send email, resend welcome email) carry 'disabled' => true and render as "Coming soon" rather than being hidden.
