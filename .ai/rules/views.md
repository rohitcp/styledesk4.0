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

## Never name a Blade directive inside ANY comment, JavaScript ones included
The existing rule about `{{-- --}}` Blade comments is narrower than the trap. Blade compiles directives anywhere in the file, so naming one inside a `/* */` JavaScript comment in a `<script>` block breaks it too.

Cost an afternoon on resources/views/components/toast.blade.php: a JS comment explaining why a value went through `@ json` compiled that mention into `json_encode(, 15, 512)` — an argument-less call — and the whole page 500'd with "syntax error, unexpected token ','". The directive on the line below was correct; the comment about it was the bug.

Write "encoded rather than interpolated" rather than naming the directive. If a comment must reference one, break the token or drop the `@`.

Pinned by tests/Feature/DashboardRolesTest.php::test_the_owner_dashboard_renders_its_panels, which renders the layout the toast component sits in.

## A new screen is translatable from its first commit
Never type user-facing English into a view. Every label, placeholder, aria-label, title, data-tip, button, empty state, toast and page title goes through `__()` against a lang file — and that key is added to all five languages in the same change (see the rule on lang/**).

This is not theoretical tidiness. Screens shipped with literals were English for every non-English reader while the frame around them was translated: the dashboard checklist, the Dismiss button, the app-bar language picker (in French and German, the control for changing language was itself in English), and the nav's section headings.

Dropdown *values* count too, not just their labels. A form with translated labels and English options is the same half-translated screen one level in.

Copy assembled in PHP is still copy on a screen. If a controller or support class builds a label — a checklist, a status line, a summary sentence — it must resolve `__()` and the wording must live in a lang file, not as a literal in the array.

Config files are the same: config/navigation.php entries carry a `key` so App\Support\Nav resolves navigation.<key>; an entry without one stays English in every language.
