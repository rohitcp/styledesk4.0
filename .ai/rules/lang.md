---
paths:
  - 'lang/**'
---

# Lang

## A new key goes into every language, not just lang/en
Five languages ship: en (the source), es, zh, fr, de. Adding a key to lang/en alone leaves four languages silently falling back to English — the reader sees a half-translated screen and nothing errors.

When you add or rename a key, add it to all five files in the same change. Then check parity — flatten lang/en/<file>.php and each translation and diff the key sets; 0 missing and 0 extra in every language, every time.

Two tests enforce this: LanguageTest::test_the_add_screens_carry_a_translation_in_every_offered_language and ::test_the_add_screen_translations_are_complete, driven by the TRANSLATED_MODULES constant. Add your new lang file to that constant and both tests hold all four languages to it. They already caught one real gap (es was missing account.password.hidden).

The one exception is backoffice.php, deliberately absent from zh/fr/de: the platform console authenticates on the `backoffice` guard while SetApplicationLocale reads $request->user('web'), so it always renders in the fallback. Translating it is strings nobody can display. Fix the guard first. See LanguageTest::test_the_platform_console_is_english_by_construction.

Values that are not interface copy stay put: currency names, WCAG grades (AA), brand names (Stripe, PayPal), format patterns (DD/MM/YYYY), and cta_action keys. Anything a business typed itself — service names, client notes, tag labels — is never translated.
