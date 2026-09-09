---
paths:
  - 'resources/js/components/{MultiSelect.vue,SingleSelect.vue},resources/css/styledesk.css'
---

# Css

## The portalled dropdown panel's z-index lives in CSS, never inline
MultiSelect's panel is `Teleport`ed to `<body>`, so it is a SIBLING of whatever opened it, not a child. Stacking is therefore global: `.styledesk_timepicker__panel` is z-index 160, above every layer it can open inside (modal 90, composer/sheet 120, picker 140) and below the tooltip at 200.

Trap (fixed, do not reintroduce): `placePanel()` used to set `zIndex: 60` inline, chosen when the tallest thing on the page was the app bar. An inline value beats the stylesheet, so every dropdown inside a dialog rendered BEHIND the dialog — reported on the client Files upload modal (Category/Service/Booking). Do not put a z-index back in `panelStyle`; raise the stylesheet value if a taller layer is ever added.

No stacking context on the control can fix this, because the control is no longer the panel's parent.

Use `SingleSelect.vue` for a scalar v-model rather than repeating the `[value]` / `values[0]` adapter — MultiSelect's model is always an array, including in `single` mode.
