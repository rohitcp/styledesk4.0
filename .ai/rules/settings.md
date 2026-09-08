---
paths:
  - 'resources/views/settings/**/show.blade.php,resources/views/components/settings/card.blade.php'
---

# Settings

## One settings card, one owner — its Edit must open a page holding every field in it
`<x-settings.card>` takes an optional `edit` (URL) prop that renders the pencil Edit affordance in the card header. It is a link, never an inline toggle: a settings card summarises a module, so editing means going to that module.

Group the fields in a card by which page can change them, not by what reads nicely together. A card whose rows span two modules produces an Edit that opens a form missing half of them — that is what forced /settings/business to split "Regional settings" into Languages, Currency, Regional (formats only) and to move the logo under Branding and the timezone under the address.

If nothing can edit a card's fields (a read-only id, a module still marked coming-soon in config/app_settings.php), omit `edit` rather than pointing at the nearest page. Sections on a target edit form need an `id` so the anchor lands on the right fields.

BusinessSettingsTest::test_each_summary_card_carries_an_edit_that_opens_the_page_owning_it asserts the destinations and counts the editable cards; update it when cards are added.
