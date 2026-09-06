---
paths:
  - 'app/{Support/StaffUtilization.php,Support/ResourceUtilization.php,Http/Controllers/StaffUtilizationController.php},resources/js/{bubble-board.js,components/StaffUtilization.vue,components/ResourceUtilization.vue}'
---

# Controllers Js

## Utilization boards: one bubble layout, capacity is the claim
Both utilization boards (resources, staff) draw their cluster from `resources/js/bubble-board.js` — the force layout, the sqrt size scale, the type sizing and the SVG text fitting. Do not copy it into a third screen; pass `boost` for bigger circles (staff uses 1.12) and `tintOf` / `metaOf` for what differs. Bubble AREA carries the percentage; radius-scaling misleads.

Trap (fixed): the board clamps every circle inside its height on each tick, and that clamp beats the collision force — so a cluster needing more height than the cap silently rendered as overlapping discs. `layout()` now shrinks all radii by `height / needed` when the cap binds. Never remove that; raising the cap alone does not fix it, and overlapping bubbles draw one percentage over another.

Staff utilization's whole claim is the DENOMINATOR: scheduled − break − non-bookable shift types (training, on-call) = bookable capacity, from `staff_shifts` and never from location hours. Cancelled shifts are not capacity. Somebody with no roster gets `status: 'unscheduled'` and is excluded from the bubbles and from the team average — NOT drawn at 0%, which is both an accusation and a figure that drags the average down for a reason nobody can act on. `ResourceUtilization::percentage()` is the one place a percentage is capped and rounded; both boards call it.

Two authorities on that screen, both enforced server-side: `staff.view` SCOPE narrows the query (`own` → the reader's own staff record via user_id, `location` → their branch, `all` → everyone), and `sales.view_staff_revenue` decides whether revenue exists in the payload and whether the grid gets a Revenue column at all. `show()` re-checks the scope on the single record — without it, changing the id in the URL reads a colleague's day.
