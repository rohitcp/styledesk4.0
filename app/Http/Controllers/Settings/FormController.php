<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * App Settings → Forms & Waivers.
 *
 * The administrative half of the module: what forms exist, what kind of thing
 * each one is, and whether it is live. Asking a client to fill one in happens
 * on their profile and at the desk, which is a different job held by different
 * people — hence `forms.view` and `forms.create` rather than one key for the
 * module.
 *
 * A form is created here as a name and a kind, and its questions are added in
 * the builder. Creating the row and creating its first version happen in one
 * transaction: a form with no version has no questions and nothing downstream
 * can read it.
 */
class FormController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow($request, 'forms.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(config('forms.types'))],
            'status' => ['nullable', Rule::in(config('forms.statuses'))],
            'signature' => ['nullable', 'in:1,0'],
        ]);

        $forms = Form::query()
            ->with('category')
            /* Real ones only. A business's own test of its intake form is
               not a submission of it. */
            ->withCount(['submissions' => fn ($query) => $query->real()])
            ->when(
                ($filters['search'] ?? '') !== '',
                fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%'),
            )
            ->when(isset($filters['category']), fn ($query) => $query->where('category_id', $filters['category']))
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            /* Archived on request only. They are kept for the submissions
               hanging off them, not to be read past every day. */
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
                fn ($query) => $query->visible(),
            )
            ->when(
                isset($filters['signature']),
                fn ($query) => $query->where('signature_required', $filters['signature'] === '1'),
            )
            ->orderByDesc('updated_at')
            ->get();

        return view('settings.forms.index', [
            'forms' => $forms,
            'categories' => FormCategory::query()->inOrder()->get(),
            'types' => config('forms.types'),
            'statuses' => config('forms.statuses'),
            /* The create dialog lives on this screen, so its vocabulary
               travels with the list. */
            'layouts' => config('forms.layouts'),
            'filters' => $filters,
            /* Whether this reader may change any of it. The module's own
               permissions, so a receptionist who sends forms all day does not
               get a Create button that would refuse them. */
            'canCreate' => $this->holds($request, 'forms.create'),
            'canEdit' => $this->holds($request, 'forms.edit'),
            'canArchive' => $this->holds($request, 'forms.archive'),
        ]);
    }

    /**
     * A new form: what it is called, what kind it is, and how it reads.
     *
     * Deliberately not the whole of §23 — the rest of a form's settings are
     * asked for beside the questions they apply to. This is the smallest
     * thing that produces a row worth opening.
     *
     * Status is not a field. A form is created as a draft and becomes active
     * by being published, because an active form with no questions is a link
     * to an empty page in somebody's inbox.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'forms.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.config('forms.limits.name')],
            'type' => ['required', Rule::in(config('forms.types'))],
            'category_id' => ['nullable', $this->ownCategory($request)],
            'internal_description' => ['nullable', 'string', 'max:'.config('forms.limits.internal_description')],
            'layout' => ['required', Rule::in(config('forms.layouts'))],
        ]);

        $form = $this->createForm($request, $data);

        /* Straight into the form rather than back to the list: somebody who
           has just named a form is there to write its questions, and a list
           with one more row on it is a screen they would immediately leave. */
        return redirect()->route('settings.forms.edit', $form)
            ->with('toast', [
                'type' => 'success',
                'message' => __('forms.created_toast'),
                'hint' => __('forms.created_toast_hint'),
            ]);
    }

    /**
     * One form, and everything about it that is not a question.
     *
     * The questions themselves are the builder's, and this screen is where
     * they will appear. Until then it is the form's settings — live, saved
     * and used, rather than a placeholder standing in for them.
     */
    public function edit(Request $request, Form $form): View
    {
        $this->allow($request, 'forms.view');

        return view('settings.forms.edit', [
            'form' => $form->load('currentVersion'),
            'categories' => FormCategory::query()->inOrder()->get(),
            'types' => config('forms.types'),
            'layouts' => config('forms.layouts'),
            'submissionCount' => $form->submissions()->real()->count(),
            'canEdit' => $this->holds($request, 'forms.edit'),
            /*
             * Whether the status is a field or a fact.
             *
             * It becomes changeable once the form has questions to send and
             * the reader holds the authority to send them — going live is
             * `forms.publish`, not `forms.edit`, and a status dropdown on the
             * settings screen must not be a way around that.
             */
            'canSetStatus' => $this->holds($request, 'forms.publish') && $form->isPublishable(),
            'publicUrl' => $form->publicUrl(),
        ]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $this->allow($request, 'forms.edit');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.config('forms.limits.name')],
            'type' => ['required', Rule::in(config('forms.types'))],
            'category_id' => ['nullable', $this->ownCategory($request)],
            'internal_description' => ['nullable', 'string', 'max:'.config('forms.limits.internal_description')],
            'layout' => ['required', Rule::in(config('forms.layouts'))],
            /*
             * Live or paused, and only those two.
             *
             * Draft is what a form is before it is published and archived is
             * its own action, so neither is something to choose here. Applied
             * below only where the reader may publish and the form has
             * questions — the rule that keeps an empty form off the wire.
             */
            'status' => ['nullable', Rule::in([Form::STATUS_ACTIVE, Form::STATUS_INACTIVE])],
        ]);

        $status = $data['status'] ?? null;
        unset($data['status']);

        $form->update($data);

        if ($status !== null
            && $this->holds($request, 'forms.publish')
            && $form->isPublishable()
            && ! $form->isArchived()) {
            $form->forceFill(['status' => $status])->save();
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('forms.edit.saved')]);
    }

    /**
     * The builder.
     *
     * Its own window and its own chrome: building a form is a sitting, and
     * the application's navigation around it is an invitation to abandon it
     * half-finished. Opened from the list in a new tab, so the list is still
     * there when the work is done.
     *
     * Everything the panel needs travels as props — the field vocabulary and
     * every string in it — because Vue has no translator and a builder with
     * English field names inside a translated frame is the half-translated
     * screen the lang rule exists to prevent.
     */
    public function build(Request $request, Form $form): View
    {
        $this->allow($request, 'forms.view');

        abort_if($form->isArchived(), 404);

        /*
         * Read, never written.
         *
         * `draftVersionFor()` opens the next version when the latest one has
         * submissions against it, and calling it here would mean merely
         * OPENING the builder — or a reader who may only look doing so —
         * forked a version nobody asked for. The fork belongs to the first
         * save, which is where the edit actually happens.
         */
        $version = $form->versions()->orderByDesc('version')->first()
            ?? $this->firstVersionFor($form, (int) ($request->user()->id));

        return view('settings.forms.build', [
            'form' => $form,
            'version' => $version,
            'canEdit' => $this->holds($request, 'forms.edit'),
            'canPublish' => $this->holds($request, 'forms.publish'),
        ]);
    }

    /**
     * The questions, saved.
     *
     * Answers a fetch rather than a form post: the builder saves itself as it
     * is worked on, and a redirect would take the panel apart mid-sentence.
     *
     * Writes to the form's DRAFT version. Which row that is depends on
     * whether anybody has answered the published one — see
     * `draftVersionFor()`, which is where the versioning rule lives.
     */
    public function saveSchema(Request $request, Form $form): JsonResponse
    {
        $this->allow($request, 'forms.edit');

        abort_if($form->isArchived(), 404);

        $theme = config('forms.theme');
        $version = $this->draftVersionFor($form);

        $data = $request->validate([
            /*
             * Rows, or a flat list of questions.
             *
             * The builder sends rows. `fields` is still accepted because a
             * tab left open before rows existed would otherwise lose the
             * work in it on the next autosave — and because it is six lines
             * to keep honest.
             */
            'rows' => ['sometimes', 'array', 'max:'.config('forms.limits.rows')],
            'rows.*.key' => ['required', 'string', 'max:64'],
            'rows.*.layout' => ['required', Rule::in($theme['row_layouts'])],
            'rows.*.split' => ['nullable', Rule::in(array_keys($theme['column_splits']))],
            'rows.*.spacing' => ['nullable', Rule::in(array_keys($theme['row_spacings']))],
            'rows.*.spacing_px' => ['nullable', 'integer', 'min:0', 'max:200'],
            'rows.*.fields' => ['required', 'array', 'min:1', 'max:'.config('forms.limits.row_fields')],

            'fields' => ['sometimes', 'array', 'max:'.config('forms.limits.fields')],
        ] + $this->fieldRules('rows.*.fields.*') + $this->fieldRules('fields.*'));

        /* One or the other, and a request with neither is a request to empty
           the form — which is not something the builder ever means to say. */
        if (! isset($data['rows']) && ! isset($data['fields'])) {
            return response()->json(['message' => __('validation.required', ['attribute' => 'rows'])], 422);
        }

        $rows = isset($data['rows'])
            ? array_values($data['rows'])
            : array_values(array_map(
                fn (array $field, int $index) => [
                    'key' => 'row_'.($index + 1),
                    'layout' => 'single',
                    'fields' => [$field],
                ],
                $data['fields'],
                array_keys($data['fields']),
            ));

        /* Held to the shipped vocabulary rather than trusted: these values
           become inline styles on a page a client opens. */
        $keep = array_keys($theme['defaults']);

        $version->forceFill(['schema' => [
            'rows' => $rows,
            'theme' => array_intersect_key(
                $this->validatedTheme($request),
                array_flip($keep),
            ),
        ]])->save();

        return response()->json([
            'saved_at' => $version->updated_at->toIso8601String(),
            'version' => $version->version,
        ]);
    }

    /**
     * Live, from this version onwards.
     *
     * Publishing is what makes a version assignable, and it is its own
     * permission: an edit is a draft, and starting to send it to clients is
     * a different decision.
     */
    public function publish(Request $request, Form $form): JsonResponse
    {
        $this->allow($request, 'forms.publish');

        abort_if($form->isArchived(), 404);

        $version = $this->draftVersionFor($form);

        /* Nothing to publish. An active form with no questions is a link to
           an empty page in somebody's inbox. */
        if ($version->fields() === []) {
            return response()->json(['message' => __('forms.builder.publish_empty')], 422);
        }

        DB::transaction(function () use ($form, $version): void {
            $version->forceFill(['published_at' => now()])->save();

            $form->forceFill([
                'current_version_id' => $version->id,
                'status' => Form::STATUS_ACTIVE,
                /* Minted once and kept. Re-rolling it on every publish would
                   break every link the business has already handed out. */
                'public_token' => $form->public_token ?? Form::newPublicToken(),
            ])->save();
        });

        return response()->json([
            'status' => $form->status,
            'status_label' => $form->statusLabel(),
            'version' => $version->version,
            'url' => $form->fresh()->publicUrl(),
        ]);
    }

    /**
     * Gone, and only where that is allowed.
     *
     * A form anybody has completed is evidence, and the archive is what that
     * gets instead. Refused rather than silently archived: somebody who
     * pressed Delete should be told the form is being kept and why.
     */
    public function destroy(Request $request, Form $form): RedirectResponse
    {
        $this->allow($request, 'forms.archive');

        if ($form->submissions()->real()->exists()) {
            return back()->withErrors(['form' => __('forms.errors.has_submissions')]);
        }

        $name = $form->name;

        /* Versions go with it, and nothing else points at them — a submission
           would have been caught above. */
        DB::transaction(function () use ($form): void {
            $form->forceFill(['current_version_id' => null])->save();
            $form->versions()->delete();
            $form->submissions()->delete();
            $form->delete();
        });

        return redirect()->route('settings.forms.index')
            ->with('toast', ['type' => 'success', 'message' => __('forms.deleted', ['name' => $name])]);
    }

    /**
     * The same form again, as a draft.
     *
     * Fields, logic, design, settings and service mappings travel; submissions
     * and client assignments do not. A copy of a form is a new question to ask
     * somebody, never a copy of what anybody answered.
     */
    public function duplicate(Request $request, Form $form): RedirectResponse
    {
        $this->allow($request, 'forms.create');

        $copy = DB::transaction(function () use ($request, $form): Form {
            $copy = $form->replicate([
                'current_version_id',
                'status',
                'archived_at',
                'created_by',
            ]);

            $copy->fill([
                'name' => __('forms.copy_of', ['name' => $form->name]),
                'status' => Form::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ])->save();

            /* The questions come too, as this copy's version 1 and unpublished
               — a duplicate is somewhere to start, not something already live.
               Read from the original's current version rather than its latest:
               a half-finished edit of the original is not what was asked for. */
            $version = $this->firstVersionFor($copy, $request->user()->id);
            $version->forceFill(['schema' => $form->currentVersion?->schema])->save();

            $copy->forceFill(['current_version_id' => $version->id])->save();

            return $copy;
        });

        return redirect()->route('settings.forms.index')
            ->with('toast', ['type' => 'success', 'message' => __('forms.duplicated', ['name' => $copy->name])]);
    }

    /**
     * Live, or paused.
     *
     * Pausing keeps the questions and every submission: a business that stops
     * asking for a waiver has not withdrawn the ones already signed.
     */
    public function toggle(Request $request, Form $form): RedirectResponse
    {
        $this->allow($request, 'forms.publish');

        abort_if($form->isArchived(), 404);

        /* Nothing to switch on. A form with no published version has no
           questions, and an active one with none is a link to an empty page
           in somebody's inbox. */
        if (! $form->isActive() && ! $form->isPublishable()) {
            return back()->withErrors(['form' => __('forms.errors.nothing_to_publish')]);
        }

        $form->forceFill([
            'status' => $form->isActive() ? Form::STATUS_INACTIVE : Form::STATUS_ACTIVE,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $form->isActive()
                ? __('forms.activated', ['name' => $form->name])
                : __('forms.deactivated', ['name' => $form->name]),
        ]);
    }

    /**
     * Out of the way, never gone.
     *
     * Archiving is the only removal this screen offers. A form with
     * submissions against it is what a client signed, and a Delete that took
     * those with it is the one action on this module that cannot be undone.
     */
    public function archive(Request $request, Form $form): RedirectResponse
    {
        $this->allow($request, 'forms.archive');

        $form->forceFill([
            'status' => Form::STATUS_ARCHIVED,
            'archived_at' => now(),
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('forms.archived', ['name' => $form->name]),
        ]);
    }

    public function restore(Request $request, Form $form): RedirectResponse
    {
        $this->allow($request, 'forms.archive');

        /* Back as a draft rather than to whatever it was. A form that has
           been out of use is one somebody should look at before it starts
           reaching clients again. */
        $form->forceFill([
            'status' => Form::STATUS_DRAFT,
            'archived_at' => null,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('forms.restored', ['name' => $form->name]),
        ]);
    }

    // ------------------------------------------------------------- helpers

    /**
     * @param  array{name: string, type: string, category_id?: int|null}  $data
     */
    private function createForm(Request $request, array $data): Form
    {
        return DB::transaction(function () use ($request, $data): Form {
            $form = Form::create($data + [
                'status' => Form::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);

            $version = $this->firstVersionFor($form, $request->user()->id);

            $form->forceFill(['current_version_id' => $version->id])->save();

            return $form;
        });
    }

    /**
     * The version an edit writes to.
     *
     * The rule the whole module turns on: a version nobody has answered is
     * edited in place, and a version with submissions against it is never
     * touched again — editing that one opens the next.
     *
     * So a business still building its first intake form does not accumulate
     * nine versions before anybody has seen it, and a business that rewords
     * a consent after a hundred people signed it leaves those hundred
     * signatures attached to what they actually agreed to.
     */
    private function draftVersionFor(Form $form): FormVersion
    {
        $latest = $form->versions()->orderByDesc('version')->first();

        if ($latest === null) {
            return $this->firstVersionFor($form, (int) $form->created_by);
        }

        if (! $latest->hasSubmissions()) {
            return $latest;
        }

        return DB::transaction(function () use ($form, $latest): FormVersion {
            $next = FormVersion::create([
                'form_id' => $form->id,
                'version' => $latest->version + 1,
                /* Starts as a copy of what is live, because an edit is a
                   change to the current questions rather than a blank page. */
                'schema' => $latest->schema,
                'previous_version_id' => $latest->id,
                'created_by' => $form->created_by,
            ]);

            return $next;
        });
    }

    /**
     * What a question may say, wherever it sits.
     *
     * Shared by the row shape and the flat one rather than written twice:
     * two copies of a validation rule are two rules that will disagree.
     *
     * @return array<string, array<int, mixed>>
     */
    private function fieldRules(string $prefix): array
    {
        return [
            $prefix.'.key' => ['required', 'string', 'max:64'],
            $prefix.'.type' => ['required', Rule::in($this->fieldTypeKeys())],
            $prefix.'.label' => ['nullable', 'string', 'max:'.config('forms.limits.field_label')],
            $prefix.'.description' => ['nullable', 'string', 'max:'.config('forms.limits.field_help')],
            $prefix.'.placeholder' => ['nullable', 'string', 'max:120'],
            $prefix.'.required' => ['nullable', 'boolean'],

            /*
             * The three states a question can be in.
             *
             * `hidden` here is the STATE of a question of any type, not the
             * `hidden` field type — a text question set hidden still carries
             * its value and is simply never shown. They are separate keys and
             * both are legitimate on the same field.
             */
            $prefix.'.hidden' => ['nullable', 'boolean'],
            $prefix.'.disabled' => ['nullable', 'boolean'],

            /* The label, kept out of the client's way but still the name the
               business reads this answer under. */
            $prefix.'.hide_label' => ['nullable', 'boolean'],

            $prefix.'.default_value' => ['nullable', 'string', 'max:'.config('forms.limits.field_default')],
            $prefix.'.help_position' => ['nullable', Rule::in(config('forms.help_positions'))],

            /* A cap the client is held to, and whether they can watch it
               approaching. */
            $prefix.'.max_length' => ['nullable', 'integer', 'min:1', 'max:'.config('forms.limits.field_max_length')],
            $prefix.'.show_counter' => ['nullable', 'boolean'],

            /* What to say when the answer will not do. Blank falls back to
               StyleDesk's own wording. */
            $prefix.'.error_message' => ['nullable', 'string', 'max:'.config('forms.limits.field_error')],

            /*
             * A before-and-after question's two sides.
             *
             * Each side has its own name, its own instruction, its own
             * count and its own answer to "must this be filled in" — a
             * treatment form legitimately requires the before photographs
             * and lets the after ones arrive later.
             */
            $prefix.'.before_label' => ['nullable', 'string', 'max:60'],
            $prefix.'.after_label' => ['nullable', 'string', 'max:60'],
            $prefix.'.before_help' => ['nullable', 'string', 'max:'.config('forms.limits.field_help')],
            $prefix.'.after_help' => ['nullable', 'string', 'max:'.config('forms.limits.field_help')],
            $prefix.'.before_required' => ['nullable', 'boolean'],
            $prefix.'.after_required' => ['nullable', 'boolean'],
            $prefix.'.max_before' => ['nullable', 'integer', 'min:1', 'max:'.config('forms.uploads.max_files')],
            $prefix.'.max_after' => ['nullable', 'integer', 'min:1', 'max:'.config('forms.uploads.max_files')],
            /* Never more than the disk will take. A larger number here would
               be a promise the storage layer breaks. */
            $prefix.'.max_kb' => ['nullable', 'integer', 'min:256', 'max:'.config('forms.uploads.max_kb')],
            $prefix.'.file_types' => ['nullable', 'array'],
            $prefix.'.file_types.*' => [Rule::in(config('forms.uploads.types'))],
            $prefix.'.options' => ['nullable', 'array', 'max:'.config('forms.limits.field_options')],
            $prefix.'.options.*' => ['nullable', 'string', 'max:120'],
            /* Only on a date question, and only one of the shipped patterns:
               it decides how the answer is read back. */
            $prefix.'.format' => ['nullable', Rule::in(array_keys(config('forms.date_formats')))],
            /* How a tick-list arranges its own answers. */
            $prefix.'.option_layout' => ['nullable', Rule::in(config('forms.option_layouts'))],
        ];
    }

    /**
     * How the form looks, held to the shipped vocabulary.
     *
     * Every one of these becomes a style on a page a client opens, so none of
     * them is a value the browser gets to invent. The colour is the only free
     * text and it has to be a hex triple.
     *
     * @return array<string, mixed>
     */
    private function validatedTheme(Request $request): array
    {
        $theme = config('forms.theme');

        return $request->validate([
            'theme.width' => ['nullable', Rule::in(array_keys($theme['widths']))],
            'theme.alignment' => ['nullable', Rule::in($theme['alignments'])],
            'theme.label_position' => ['nullable', Rule::in($theme['label_positions'])],
            'theme.field_style' => ['nullable', Rule::in(array_keys($theme['field_styles']))],
            'theme.radius' => ['nullable', Rule::in(array_keys($theme['radii']))],
            'theme.background' => ['nullable', Rule::in($theme['backgrounds'])],
            'theme.background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.button_style' => ['nullable', Rule::in($theme['button_styles'])],
            'theme.row_spacing' => ['nullable', Rule::in(array_keys($theme['row_spacings']))],
            'theme.row_spacing_px' => ['nullable', 'integer', 'min:0', 'max:200'],
            'theme.default_layout' => ['nullable', Rule::in($theme['row_layouts'])],
            /* The wording on the buttons, and where they sit. Blank wording
               falls back to StyleDesk's own, which is the only answer that
               stays translated. */
            'theme.submit_label' => ['nullable', 'string', 'max:40'],
            'theme.cancel_label' => ['nullable', 'string', 'max:40'],
            'theme.show_cancel' => ['nullable', 'boolean'],
            'theme.button_alignment' => ['nullable', Rule::in($theme['button_alignments'])],
            'theme.save_progress' => ['nullable', 'boolean'],
        ])['theme'] ?? [];
    }

    /** Every type the builder offers, flattened out of its groups. */
    private function fieldTypeKeys(): array
    {
        return array_merge(...array_map(
            'array_keys',
            array_values(config('forms.field_types')),
        ));
    }

    /** Version 1, unpublished and with no questions in it yet. */
    private function firstVersionFor(Form $form, int $userId): FormVersion
    {
        return FormVersion::create([
            'form_id' => $form->id,
            'version' => 1,
            'created_by' => $userId,
        ]);
    }

    /**
     * A category belonging to somebody else is not a category.
     *
     * The id arrives from the browser, so the tenant is read from the signed-in
     * user and never from the request.
     */
    private function ownCategory(Request $request): Exists
    {
        return Rule::exists('form_categories', 'id')
            ->where('tenant_id', $request->user()->tenant->getTenantKey());
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($this->holds($request, $permission), 403);
    }

    private function holds(Request $request, string $permission): bool
    {
        return (bool) $request->user()?->hasPermission($permission, 'own');
    }
}
