---
paths:
  - 'app/Http/Controllers/BookingQuoteController.php,resources/js/components/{BookingBuilder.vue,PaymentPanel.vue}'
---

# Http Controllers Js Components

## The tip default needs tip_chosen; "no tip" and "not asked yet" are different answers
The booking quote applies the business's configured default tip (`tip_settings.default_tip_type` / `default_tip_value`) until somebody answers. It can only do that because the screen sends `tip_chosen`: without that flag, "the client chose No Tip" and "nobody has been asked" both arrive as an absent `tip_percent`, and the server either loses the default or overwrites a deliberate zero on every recalculation. Keep sending it from anything that calls the quote endpoint.

Client side, `tipChosen` flips true in `chooseTipPercent()`, `applyCustomTip()` and `chooseCustomTip()`. While it is false the quote response is mirrored back into `tipPercent`/`customTip` so the right chip lights; once true those refs are the answer and the quote is downstream of them. `chooseCustomTip()` exists precisely so pressing Custom counts as answering — otherwise the next quote reapplies the default over the empty box.

The custom-amount input renders on `tipChosen && tipPercent === null`, not on `tipPercent === null` alone — the latter was also the screen's initial state, which is why every booking used to open with an empty amount box and no chip lit.

A fixed (non-percentage) default has no chip to light: it comes back as `default_tip_percent: null` with `default_tip_minor` set, and lands in the custom box.

Deposit percentages are taken on `depositBaseMinor` (payable minus tip), never on the tipped total. A tip is a gratuity decided at the till, not part of what is owed for the appointment, and basing deposits on it would move every deposit the day the business changed its default tip.

`PaymentPanel` emits `draft {amount, tip}`; `BookingBuilder` binds `@draft` into `tillTipMinor`, and `summaryTipMinor`/`summaryTipPercent`/`summaryTotalMinor` prefer it once `booking` exists so the summary card and the till can never show different amounts. The percentage shown is matched against `booking.tips.suggested`, never divided out of the amount — division rounds 18% into 17% on bills that do not divide evenly.
