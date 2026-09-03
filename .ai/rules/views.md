---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Never name a Blade directive inside a Blade comment
Blade compiles directives inside `{{-- --}}` comments. A comment that mentions `@php`, `@if`, `@foreach` etc. by name opens a real block, and the next genuine close tag ends it — silently deleting everything in between.

This cost an afternoon on the email template editor: a comment explaining a `@php` block sat directly above that block, so the comment's mention opened PHP, the real `@endphp` closed it, and the `@php` body AND the control after it vanished from the output. The page still returned 200 with the section simply empty — no error, nothing in the log.

Write "the props are built above" rather than naming the directive. If a comment must reference one, break the token (`@ php`) or use an HTML comment.

Related, already recorded on .ai/rules/middleware.md: a directive argument with a comma inside brackets — a multi-line `@json([...])` — does not parse either, because Blade counts brackets rather than reading PHP. Build the array in a preceding block and pass the variable.

Pinned by tests/Feature/EmailTemplateListTest.php::test_the_detail_row_and_service_pickers_both_render, which counts the rendered controls.
