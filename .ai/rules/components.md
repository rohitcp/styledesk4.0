---
paths:
  - 'resources/js/components/{BookingBuilder.vue,MultiSelect.vue}'
---

# Components

## Booking screen: the service selector, and the MultiSelect panel trap
The New Booking screen is one Vue island holding unsaved state (client, draft lead, chosen time). Two things follow from that:

- **Choosing services is a full-page overlay, not a route.** `sheetOpen` renders a `Teleport to="body"` fixed panel: header (back/title/search/count), categories 30% `hidden lg:flex` + services 70%, sticky footer (Cancel | Save & Close). A real navigation would have to serialise and restore the whole booking to come back to it. Below `lg` the category column is replaced by a horizontal chip strip (`lg:hidden`) — never two narrow columns on a phone.
- **The selection is drafted.** `sheetPicked` (ids) is seeded from `chosen` on open and only written back on Save & Close. That is what makes Cancel mean anything, and it stops every tick re-asking the server which times are free. `chosen` is the single source: setting it recalculates duration, bill, resources and availability, so nothing else needs telling.
- The Service card shows only the summary (`serviceCountLabel` · `durationLabel` · money) plus the chosen rows and the Add/Assign button. Do not put the catalogue back inside it — a 260px scroller inside a form is what this replaced.

**MultiSelect trap:** `.styledesk_timepicker__panel` still carries `min-width: 100%` from when the panel lived inside the control. It is now portalled to `<body>`, so that 100% is the width of the PAGE and beats any width set on the panel — every list opened as wide as the window. `placePanel()` therefore sets `minWidth` inline alongside `width`. Do not remove it.

Every dropdown on this screen is MultiSelect in `single` mode (booking source, deposit action). Do not add a bare `<select>` back — a native dropdown beside an `sd-input` is the one control that looks like it belongs to a different form.
