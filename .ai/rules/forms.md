---
paths:
  - 'app/{Models/Form.php,Models/FormVersion.php,Models/FormSubmission.php,Casts/AnswersWhenFlagged.php,Http/Controllers/Settings/FormController.php},config/forms.php,resources/views/settings/forms/**'
---

# Forms

## Forms &amp; Waivers: versions are evidence, and answers state their own encryption
A form's questions live on `form_versions.schema`, never on `forms`. A completed form is evidence — a client signed a consent that said something specific — so editing a form that HAS submissions must open a new version rather than overwrite one. A form nobody has filled in is edited in place.

`form_submissions.form_version_id` is pinned at ASSIGNMENT, not read at submit. A client half-way through when the business publishes an edit must keep answering the questions they started on. It is `restrictOnDelete`: the version is what the answers mean.

Assignment and submission are ONE row, not two. The lifecycle is a single line (assigned → sent → viewed → started → completed → signed) and the status column says where on it a row is.

`form_submissions.answers_encrypted` is a column on the row, not read from `forms.contains_sensitive`. The row states how it stored its own answers, so toggling the form's flag later cannot make existing rows undecryptable in either direction. App\Casts\AnswersWhenFlagged reads it — and THROWS if `answers` is loaded without `answers_encrypted` (a partial select, or Eloquent's `value()`, which is `first([$column])`), because guessing there decodes ciphertext as json and silently hands back null. Empty answers on a signed medical history is the worst way to be wrong. To read the raw column in a test, use the QUERY builder (`DB::table(...)->value()`), never Eloquent's.

Expiry is applied when a submission is read (`hasExpired()`, `statusKey()`, `scopeValid()`), never by a nightly sweep — same shape as loyalty point expiry. `completed_at` still says when the form was actually filled in.

Vocabulary (types, statuses, validity, layouts, submission statuses, sources) lives in config/forms.php and is validated against via `Rule::in(config(...))`. Never a database enum, never an inline literal.
