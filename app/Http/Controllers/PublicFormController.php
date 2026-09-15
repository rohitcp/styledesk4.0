<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Storage\TenantStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A form, filled in by the person it was sent to.
 *
 * No account, no session worth the name: the token in the URL is the whole of
 * the authorisation, which is why it is random and why this controller is
 * throttled. Everything here runs on the tenant subdomain, so the business is
 * resolved from the host rather than from anything the visitor sends.
 *
 * The renderer is the same one the builder previews with. A public form built
 * from its own markup would be one that could agree with the builder and
 * still disagree with the client.
 *
 * Both actions read the token off the route BY NAME rather than taking it as
 * an argument, and that is not fussiness. The same controller answers on two
 * routes: the tenant subdomain, registered under
 * `Route::domain('{tenantSubdomain}.…')` and therefore carrying TWO route
 * parameters, and the application's own domain, carrying one. Laravel fills a
 * controller's non-class arguments from the route's parameters in ORDER, so
 * `(Request, string $token)` receives the SUBDOMAIN on the first route — it
 * then looks a form up by "vivlifelounge", finds nothing, and 404s a form
 * that is perfectly live. Reading by name is right on both.
 */
class PublicFormController extends Controller
{
    public function show(Request $request): View
    {
        $form = $this->formFor((string) $request->route('token'));

        return view('forms.public', [
            'form' => $form,
            'version' => $form->currentVersion,
            'tenant' => tenant(),
        ]);
    }

    /**
     * Their answers.
     *
     * Checked again here rather than trusted: the panel refuses an empty
     * required question, and a request that did not come from the panel is
     * exactly the one that would not have.
     */
    public function store(Request $request, TenantStorageService $storage): JsonResponse
    {
        $form = $this->formFor((string) $request->route('token'));
        $version = $form->currentVersion;

        $request->validate([
            'answers' => ['required', 'string', 'max:200000'],
        ]);

        $answers = json_decode((string) $request->input('answers'), true);

        if (! is_array($answers)) {
            throw ValidationException::withMessages(['answers' => __('forms.public.failed')]);
        }

        $fields = collect($version->fields())->keyBy('key');

        /* Only questions this version actually asks. An answer to a key that
           is not on the form is either a stale tab or somebody probing, and
           neither is something to store. */
        $answers = collect($answers)
            ->filter(fn ($value, $key) => $fields->has($key))
            ->all();

        $this->refuseMissing($fields, $answers, $request);

        $submission = DB::transaction(function () use ($form, $version, $answers, $fields, $request, $storage) {
            $signature = $this->signatureFrom($fields, $answers);

            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'form_version_id' => $version->id,
                /* Nobody yet. The link is the form's, not a client's, so who
                   this belongs to is something the desk decides afterwards. */
                'client_id' => null,
                'token' => FormSubmission::newToken(),
                'status' => $signature['signature'] !== null
                    ? FormSubmission::STATUS_SIGNED
                    : FormSubmission::STATUS_COMPLETED,
                'source' => 'client_link',
                /* The row records how it stored its own answers, so the
                   form's flag changing later cannot make it unreadable. */
                'answers_encrypted' => (bool) $form->contains_sensitive,
                'answers' => $answers,
                'signature' => $signature['signature'],
                'signature_type' => $signature['type'],
                'signed_name' => $signature['name'],
                'signed_ip' => $request->ip(),
                'signed_agent' => substr((string) $request->userAgent(), 0, 255),
                'assigned_at' => now(),
                'viewed_at' => now(),
                'started_at' => now(),
                'completed_at' => now(),
                'signed_at' => $signature['signature'] !== null ? now() : null,
                'expires_at' => $this->expiryFor($form),
            ]);

            /* The files are owned by the submission, so it has to exist
               before they can be stored — and what they were an answer TO
               goes back into the answers, which is the only place that knows
               about fields and sides. */
            $stored = $this->storeFiles($request, $fields, $submission, $storage);

            if ($stored !== []) {
                $submission->forceFill(['answers' => array_merge($answers, $stored)])->save();
            }

            return $submission;
        });

        return response()->json([
            'message' => __('forms.public.thanks'),
            'submission' => $submission->id,
        ]);
    }

    /**
     * The form behind this token, or nothing.
     *
     * 404 on anything short of a live form with questions — inactive,
     * archived, unpublished or simply wrong. The difference between "no such
     * form" and "that form is paused" is not something a stranger needs, and
     * telling them narrows the guessing.
     */
    private function formFor(string $token): Form
    {
        /*
         * Which business, and how we know.
         *
         * On the tenant subdomain the middleware has already resolved it from
         * the host, and the query stays tenant-scoped — so one business's
         * token cannot be opened on another's subdomain.
         *
         * On the application's own domain there is no tenant in the host, so
         * the token is the only thing that can say. It is globally unique and
         * random, which is what makes that safe; tenancy is then initialised
         * from the form so everything downstream — storage, scoping — behaves
         * exactly as it does on the subdomain.
         */
        if (tenant() === null) {
            $form = Form::withoutGlobalScopes()
                ->where('public_token', $token)
                ->with('currentVersion')
                ->first();

            if ($form !== null && $form->tenant !== null) {
                tenancy()->initialize($form->tenant);
            }
        } else {
            $form = Form::query()
                ->where('public_token', $token)
                ->with('currentVersion')
                ->first();
        }

        abort_if(
            $form === null
                || ! $form->isActive()
                || $form->currentVersion === null
                || ! $form->currentVersion->isPublished()
                || $form->currentVersion->fields() === [],
            404,
        );

        return $form;
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $answers
     */
    private function refuseMissing($fields, array $answers, Request $request): void
    {
        $missing = [];

        foreach ($fields as $key => $field) {
            /* Never asked, so never missing. */
            if (($field['hidden'] ?? false) || ($field['type'] ?? '') === 'hidden') {
                continue;
            }

            if (($field['type'] ?? '') === 'before_after') {
                foreach (['before', 'after'] as $side) {
                    if (! ($field[$side.'_required'] ?? false)) {
                        continue;
                    }

                    if ($request->file('files.'.$key.'.'.$side) === null) {
                        $missing[$key.'.'.$side] = $field['error_message']
                            ?? __('forms.builder.'.$side.'_missing', ['label' => $field[$side.'_label'] ?? $side]);
                    }
                }

                continue;
            }

            if (! ($field['required'] ?? false) || ($field['disabled'] ?? false)) {
                continue;
            }

            $value = $answers[$key] ?? null;

            if ($value === null || $value === '' || $value === [] || $value === false) {
                $missing[$key] = $field['error_message'] ?? __('forms.builder.required_error');
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    /**
     * The mark, if the form asked for one.
     *
     * Taken out of the answers and into the columns that exist for it, so
     * "who signed this and when" is a fact about the submission rather than
     * something to go looking for inside a json blob.
     *
     * @return array{signature: ?string, type: ?string, name: ?string}
     */
    private function signatureFrom($fields, array $answers): array
    {
        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? '';

            if ($type !== 'signature' && $type !== 'signature_name') {
                continue;
            }

            $given = $answers[$key] ?? null;

            if ($type === 'signature' && is_string($given) && $given !== '') {
                return ['signature' => $given, 'type' => 'draw', 'name' => null];
            }

            if ($type === 'signature_name' && is_array($given) && ($given['signature'] ?? '') !== '') {
                return [
                    'signature' => (string) $given['signature'],
                    'type' => 'draw',
                    'name' => $given['name'] ?? null,
                ];
            }
        }

        return ['signature' => null, 'type' => null, 'name' => null];
    }

    /** When this answer stops counting, from the rule the form carries. */
    private function expiryFor(Form $form): ?Carbon
    {
        $days = $form->validityDays();

        return $days === null ? null : now()->addDays($days);
    }

    /**
     * The files that came with it.
     *
     * Stored under the submission rather than under a client, because there
     * is no client yet. `form-upload` is not a category the path builder
     * knows, which it handles on purpose — a new module should be able to
     * store something before anybody has decided where it belongs.
     *
     * Which question and which side each file answered goes back into the
     * submission's answers. `stored_files` has no column for it, and the
     * answers are the only place that knows a before-and-after has two
     * halves — a photograph that cannot say which half it belongs to is one
     * nobody can use.
     *
     * @return array<string, array<string, list<int>>>
     */
    private function storeFiles(Request $request, $fields, FormSubmission $submission, TenantStorageService $storage): array
    {
        $recorded = [];

        foreach ($fields as $key => $field) {
            $type = $field['type'] ?? '';

            $sides = match ($type) {
                'before_after' => ['before', 'after'],
                'file_upload' => ['files'],
                default => [],
            };

            foreach ($sides as $side) {
                $files = $request->file('files.'.$key.'.'.$side) ?? [];

                foreach (is_array($files) ? $files : [$files] as $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $stored = $storage->upload($file, 'form-upload', 'form-submission', $submission->id);

                    $recorded[$key][$side][] = $stored->id;
                }
            }
        }

        return $recorded;
    }
}
