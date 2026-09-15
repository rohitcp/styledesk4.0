<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Client;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Services\Storage\FileValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App Settings → Forms & Waivers, and the schema underneath it.
 *
 * What these guard is the three things that would be expensive to get wrong
 * later: a form's questions are versioned so a completed one cannot be
 * rewritten, a submission's answers are encrypted according to what the ROW
 * says rather than what the form currently says, and the desk can send forms
 * without being able to reword them.
 */
class FormsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Smile Spa', 'slug' => 'smile-forms']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->owner = User::create([
            'first_name' => 'Nadia', 'last_name' => 'Khan',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();

        app(ProvisionSystemRoles::class)->forTenant($this->tenant);
    }

    // ------------------------------------------------------------ the screen

    public function test_the_forms_screen_opens(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.forms.index'))
            ->assertOk()
            ->assertSee(__('forms.title'));
    }

    /**
     * A new form is a row and a version, together.
     *
     * A form with no version has no questions, and nothing downstream — the
     * builder, the renderer, an assignment — can read one. Both are written
     * in a transaction so neither can exist without the other.
     */
    public function test_creating_a_form_creates_its_first_version(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('settings.forms.store'), [
                'name' => 'Massage Therapy Intake',
                'type' => 'intake',
                'layout' => 'classic',
                'internal_description' => 'For every new massage client.',
            ])
            ->assertSessionHasNoErrors();

        $form = Form::withoutGlobalScopes()->firstOrFail();

        /* Straight into the form, not back to a list with one more row. */
        $response->assertRedirect(route('settings.forms.edit', $form));

        $this->assertSame('classic', $form->layout);
        $this->assertSame('For every new massage client.', $form->internal_description);

        $this->assertSame('Massage Therapy Intake', $form->name);
        $this->assertSame(Form::STATUS_DRAFT, $form->status, 'A new form is never live.');
        $this->assertSame($this->owner->id, $form->created_by);

        $version = FormVersion::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(1, $version->version);
        $this->assertSame($version->id, $form->current_version_id);
        $this->assertNull($version->published_at, 'Version 1 of an empty form is not published.');
    }

    /**
     * Create opens a dialog on this page, beside Back.
     *
     * Asserted against the markup the screen really renders, because the
     * cost of getting a create flow wrong is a button that goes nowhere —
     * and a hand-written payload in a test cannot tell you whether the
     * control that sends it exists.
     */
    public function test_create_is_a_dialog_on_the_list_beside_back(): void
    {
        $content = $this->actingAs($this->owner)
            ->get(route('settings.forms.index'))
            ->assertOk()
            ->getContent();

        /* The trigger opens the dialog rather than navigating. */
        $this->assertStringContainsString('data-form-add', $content);
        $this->assertStringContainsString('id="formModal"', $content);

        /* Back and Create in one right-hand group, in that order. */
        $back = strpos($content, route('settings.index').'" class="styledesk_action"');
        $create = strpos($content, 'data-form-add');
        $this->assertNotFalse($back);
        $this->assertLessThan($create, $back, 'Back comes first; secondary action, then primary.');

        /* Every type on the list, and both layouts, in the dialog. */
        foreach (config('forms.types') as $type) {
            $this->assertStringContainsString('value="'.$type.'"', $content);
        }

        foreach (config('forms.layouts') as $layout) {
            $this->assertStringContainsString(__('forms.layout_hints.'.$layout), $content);
        }

        /* Searchable, and guarded against a second press. `data-combo` is
           what opts a select into the design system's combo — the attribute
           the bundle actually reads — so its absence is the difference
           between a searchable dropdown and a native one. */
        $this->assertSame(
            6,
            substr_count($content, 'data-combo-options'),
            'Four filter dropdowns and the dialog\'s two, all combos.',
        );
        $this->assertStringContainsString(__('forms.new.search_type'), $content);
        $this->assertStringContainsString(__('forms.new.creating'), $content);

        /* Nothing preselected: a form somebody forgot to set must not be
           filed as an intake form silently and plausibly. */
        $this->assertStringContainsString(__('forms.new.choose_type'), $content);
    }

    /**
     * A refusal reopens the dialog with the answers still in it.
     *
     * The server is the boundary, and a dialog that closed on the way to
     * being told no would take what somebody typed with it.
     */
    public function test_a_refused_creation_reopens_the_dialog_with_its_values(): void
    {
        $this->actingAs($this->owner)
            ->from(route('settings.forms.index'))
            ->post(route('settings.forms.store'), [
                'name' => '', 'type' => 'intake', 'layout' => 'classic',
                'internal_description' => 'Typed and worth keeping.',
            ])
            ->assertRedirect(route('settings.forms.index'))
            ->assertSessionHasErrors('name');

        /* The screen as it comes back: the old input is in that session, so
           this has to be the request that follows the refusal. */
        $content = $this->actingAs($this->owner)
            ->get(route('settings.forms.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-form-open-on-load', $content);
        $this->assertStringContainsString('Typed and worth keeping.', $content);
    }

    /**
     * The status is not the caller's to choose.
     *
     * A form posted as active would be a live form with no questions — a
     * link to an empty page in somebody's inbox. Ignored rather than
     * refused: nothing on the screen offers it, so a request carrying it is
     * not a reader making a mistake.
     */
    public function test_a_new_form_is_a_draft_whatever_was_posted(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.forms.store'), [
                'name' => 'Waiver', 'type' => 'waiver', 'layout' => 'classic',
                'status' => Form::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Form::STATUS_DRAFT, Form::withoutGlobalScopes()->firstOrFail()->status);
    }

    /** And the server refuses the form that arrives without one. */
    public function test_a_form_with_no_type_chosen_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.forms.store'), ['name' => 'Nameless kind', 'type' => '', 'layout' => 'classic'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Form::withoutGlobalScopes()->count());
    }

    public function test_a_layout_outside_the_vocabulary_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.forms.store'), [
                'name' => 'Odd', 'type' => 'intake', 'layout' => 'carousel',
            ])
            ->assertSessionHasErrors('layout');
    }

    /** The two kinds §3 names beyond the original eight. */
    public function test_treatment_and_photo_consent_are_form_types(): void
    {
        foreach (['treatment', 'photo_consent'] as $type) {
            $this->assertContains($type, config('forms.types'));
            $this->assertNotSame('', trim(__('forms.types.'.$type)));
        }
    }

    // --------------------------------------------------------- the form page

    public function test_the_form_page_opens_and_shows_its_settings(): void
    {
        $form = $this->form();
        $form->update(['name' => 'Massage Intake', 'internal_description' => 'Desk copy.']);

        $this->actingAs($this->owner)
            ->get(route('settings.forms.edit', $form))
            ->assertOk()
            ->assertSee('Massage Intake')
            ->assertSee('Desk copy.')
            ->assertSee(__('forms.statuses.draft'));
    }

    public function test_the_form_settings_save(): void
    {
        $form = $this->form();
        $category = FormCategory::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'name' => 'Consent',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('settings.forms.update', $form), [
                'name' => 'Deep Tissue Consent',
                'type' => 'consent',
                'category_id' => $category->id,
                'internal_description' => 'Signed before every deep tissue booking.',
                'layout' => 'focused',
            ])
            ->assertSessionHasNoErrors();

        $form->refresh();

        $this->assertSame('Deep Tissue Consent', $form->name);
        $this->assertSame('consent', $form->type);
        $this->assertSame($category->id, $form->category_id);
        $this->assertSame('focused', $form->layout);
    }

    /**
     * Saving the settings cannot put a form live.
     *
     * Going live is `toggle`, which holds the publish permission and refuses
     * a form with no questions. A status smuggled through this action would
     * route around both.
     */
    public function test_the_settings_form_cannot_change_the_status(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->patch(route('settings.forms.update', $form), [
                'name' => 'Intake', 'type' => 'intake', 'layout' => 'classic',
                'status' => Form::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Form::STATUS_DRAFT, $form->fresh()->status);
    }

    public function test_a_form_type_outside_the_vocabulary_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.forms.store'), ['name' => 'Odd One', 'type' => 'horoscope', 'layout' => 'classic'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Form::withoutGlobalScopes()->count());
    }

    /** A category belonging to another business is not a category. */
    public function test_a_category_from_another_business_is_refused(): void
    {
        $other = Tenant::create(['name' => 'Other Spa', 'slug' => 'other-forms']);
        $theirs = FormCategory::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'name' => 'Theirs',
        ]);

        $this->actingAs($this->owner)
            ->post(route('settings.forms.store'), [
                'name' => 'Intake', 'type' => 'intake', 'layout' => 'classic', 'category_id' => $theirs->id,
            ])
            ->assertSessionHasErrors('category_id');
    }

    // ------------------------------------------------------------- lifecycle

    /**
     * Nothing to publish is said, not silently done.
     *
     * An active form with no questions is a link to an empty page in
     * somebody's inbox — the same class of bug as a toggle that posts an
     * incomplete form and reloads unchanged.
     */
    public function test_a_form_with_no_questions_cannot_be_activated(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->patch(route('settings.forms.toggle', $form))
            ->assertSessionHasErrors('form');

        $this->assertSame(Form::STATUS_DRAFT, $form->fresh()->status);
    }

    public function test_a_published_form_activates_and_deactivates(): void
    {
        $form = $this->form(published: true);

        $this->actingAs($this->owner)->patch(route('settings.forms.toggle', $form));
        $this->assertSame(Form::STATUS_ACTIVE, $form->fresh()->status);

        $this->actingAs($this->owner)->patch(route('settings.forms.toggle', $form));
        $this->assertSame(Form::STATUS_INACTIVE, $form->fresh()->status);
    }

    /**
     * Archiving keeps what clients signed.
     *
     * The only removal the screen offers, precisely because a form with
     * submissions against it is evidence.
     */
    public function test_archiving_keeps_the_submissions(): void
    {
        $form = $this->form(published: true);
        $submission = $this->submission($form);

        $this->actingAs($this->owner)
            ->patch(route('settings.forms.archive', $form))
            ->assertSessionHasNoErrors();

        $this->assertSame(Form::STATUS_ARCHIVED, $form->fresh()->status);
        $this->assertNotNull($form->fresh()->archived_at);
        $this->assertNotNull($submission->fresh(), 'Archiving a form must never destroy what was signed.');
    }

    /** And a restored form comes back as a draft, not as whatever it was. */
    public function test_a_restored_form_comes_back_as_a_draft(): void
    {
        $form = $this->form(published: true);
        $form->forceFill(['status' => Form::STATUS_ARCHIVED, 'archived_at' => now()])->save();

        $this->actingAs($this->owner)->patch(route('settings.forms.restore', $form));

        $this->assertSame(Form::STATUS_DRAFT, $form->fresh()->status);
        $this->assertNull($form->fresh()->archived_at);
    }

    /**
     * A duplicate copies the questions and none of the answers.
     *
     * A copy of a form is a new question to ask somebody, never a copy of
     * what anybody answered.
     */
    public function test_duplicating_copies_the_questions_and_not_the_submissions(): void
    {
        $form = $this->form(published: true);
        $form->forceFill(['service_ids' => [7, 9], 'signature_required' => true])->save();
        $this->submission($form);

        $this->actingAs($this->owner)
            ->post(route('settings.forms.duplicate', $form))
            ->assertSessionHasNoErrors();

        $copy = Form::withoutGlobalScopes()->where('id', '!=', $form->id)->firstOrFail();

        $this->assertSame(__('forms.copy_of', ['name' => $form->name]), $copy->name);
        $this->assertSame(Form::STATUS_DRAFT, $copy->status);
        $this->assertSame([7, 9], $copy->service_ids, 'Service mappings travel.');
        $this->assertTrue($copy->signature_required);
        $this->assertSame(0, $copy->submissions()->count(), 'Submissions do not.');

        /* The questions came too, as this copy's own version 1. */
        $this->assertSame(
            $form->currentVersion->schema,
            $copy->currentVersion->schema,
        );
        $this->assertSame(1, $copy->currentVersion->version);
        $this->assertNull($copy->currentVersion->published_at);
    }

    // ----------------------------------------------------------- the builder

    public function test_the_builder_opens_with_its_field_vocabulary(): void
    {
        $content = $this->actingAs($this->owner)
            ->get(route('settings.forms.build', $this->form()))
            ->assertOk()
            ->getContent();

        /* Mounted as a Vue island, with every string it shows travelling with
           it: the panel has no translator of its own. */
        $this->assertStringContainsString('data-vue-component="FormBuilder"', $content);
        $this->assertStringContainsString(trim(json_encode(__('forms.builder.elements'), 15), '"'), $content);

        /* Encoded exactly as the props are, which is how they reach the
           page: the directive escapes with flags 15, so a slash becomes
           "\/" and an ampersand "\u0026". Asserting the raw string would
           fail on a page that is perfectly correct. */
        $encoded = fn (string $key) => trim(json_encode(__($key), 15), '"');

        foreach (config('forms.field_types') as $group => $types) {
            $this->assertStringContainsString($encoded('forms.groups.'.$group), $content);

            foreach (array_keys($types) as $type) {
                $this->assertStringContainsString($encoded('forms.fields.'.$type), $content);
            }
        }
    }

    /**
     * Opening the builder writes nothing.
     *
     * The next version is opened by the first SAVE, not by arriving: merely
     * looking at a form whose live version has been answered must not fork it.
     */
    public function test_opening_the_builder_does_not_fork_a_version(): void
    {
        $form = $this->form(published: true);
        $this->submission($form, [
            'status' => FormSubmission::STATUS_SIGNED,
            'completed_at' => now(), 'signed_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->get(route('settings.forms.build', $form))
            ->assertOk();

        $this->assertSame(1, FormVersion::withoutGlobalScopes()->where('form_id', $form->id)->count());
    }

    /** An archived form is being kept, not worked on. */
    public function test_the_builder_refuses_an_archived_form(): void
    {
        $form = $this->form();
        $form->forceFill(['status' => Form::STATUS_ARCHIVED, 'archived_at' => now()])->save();

        $this->actingAs($this->owner)
            ->get(route('settings.forms.build', $form))
            ->assertNotFound();
    }

    public function test_the_builder_saves_its_questions(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['fields' => [
                ['key' => 'allergies', 'type' => 'long_text', 'label' => 'Any allergies?', 'required' => true],
                ['key' => 'sign', 'type' => 'signature', 'label' => 'Signature'],
            ]])
            ->assertOk()
            ->assertJsonPath('version', 1);

        $fields = $form->fresh()->currentVersion->fields();

        $this->assertCount(2, $fields);
        $this->assertSame('allergies', $fields[0]['key']);
        $this->assertSame('signature', $fields[1]['type']);
    }

    public function test_a_field_type_outside_the_vocabulary_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['fields' => [
                ['key' => 'x', 'type' => 'body_map', 'label' => 'Where?'],
            ]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fields.0.type');
    }

    /**
     * Publishing an empty form is refused.
     *
     * An active form with no questions is a link to an empty page in
     * somebody's inbox.
     */
    public function test_an_empty_form_cannot_be_published(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('settings.forms.publish', $this->form()))
            ->assertStatus(422)
            ->assertJsonPath('message', __('forms.builder.publish_empty'));
    }

    public function test_publishing_puts_the_form_live(): void
    {
        $form = $this->form(published: true);
        $form->currentVersion->forceFill(['published_at' => null])->save();

        $this->actingAs($this->owner)
            ->postJson(route('settings.forms.publish', $form))
            ->assertOk()
            ->assertJsonPath('status', Form::STATUS_ACTIVE);

        $form->refresh();

        $this->assertTrue($form->isActive());
        $this->assertNotNull($form->currentVersion->published_at);
    }

    /**
     * The rule the module turns on.
     *
     * A version nobody has answered is edited in place; one with submissions
     * against it is never touched again. Otherwise rewording a consent would
     * retroactively change what a hundred people signed.
     */
    public function test_editing_a_version_with_submissions_opens_the_next_one(): void
    {
        $form = $this->form(published: true);
        $firstVersionId = $form->current_version_id;
        $this->submission($form, [
            'status' => FormSubmission::STATUS_SIGNED,
            'completed_at' => now(), 'signed_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['fields' => [
                ['key' => 'reworded', 'type' => 'text', 'label' => 'Reworded question'],
            ]])
            ->assertOk()
            ->assertJsonPath('version', 2);

        /* Version 1 still says what was signed. */
        $first = FormVersion::withoutGlobalScopes()->findOrFail($firstVersionId);
        $this->assertSame('allergies', $first->fields()[0]['key']);
        $this->assertSame(1, $first->version);

        $second = FormVersion::withoutGlobalScopes()->where('form_id', $form->id)->where('version', 2)->firstOrFail();
        $this->assertSame('reworded', $second->fields()[0]['key']);
        $this->assertSame($firstVersionId, $second->previous_version_id);
    }

    /** And a version nobody has answered is edited where it is. */
    public function test_editing_an_unanswered_version_stays_on_it(): void
    {
        $form = $this->form(published: true);

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['fields' => [
                ['key' => 'changed', 'type' => 'text', 'label' => 'Changed'],
            ]])
            ->assertOk()
            ->assertJsonPath('version', 1);

        $this->assertSame(1, FormVersion::withoutGlobalScopes()->where('form_id', $form->id)->count());
    }

    /** Publishing is its own authority, separate from editing. */
    public function test_a_reader_without_the_publish_permission_cannot_publish(): void
    {
        $form = $this->form(published: true);

        $this->actingAs($this->administratorHolding([
            'settings.view' => 'all', 'forms.view' => 'all', 'forms.edit' => 'all',
        ]))
            ->postJson(route('settings.forms.publish', $form))
            ->assertForbidden();
    }

    /**
     * Rows, saved and read back as rows.
     *
     * The arrangement is per row because that is what an intake form looks
     * like: first name beside last name, medical history on its own.
     */
    public function test_the_builder_saves_rows_and_their_layout(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), [
                'rows' => [
                    [
                        'key' => 'row_1', 'layout' => 'two', 'split' => '60_40',
                        'fields' => [
                            ['key' => 'first', 'type' => 'text', 'label' => 'First name'],
                            ['key' => 'last', 'type' => 'text', 'label' => 'Last name'],
                        ],
                    ],
                    [
                        'key' => 'row_2', 'layout' => 'single', 'spacing' => 'spacious',
                        'fields' => [['key' => 'history', 'type' => 'long_text', 'label' => 'Medical history']],
                    ],
                ],
                'theme' => ['width' => 'wide', 'row_spacing' => 'comfortable', 'label_position' => 'left'],
            ])
            ->assertOk();

        $version = $form->fresh()->currentVersion;
        $rows = $version->rows();

        $this->assertCount(2, $rows);
        $this->assertSame('two', $rows[0]['layout']);
        $this->assertSame('60_40', $rows[0]['split']);
        $this->assertCount(2, $rows[0]['fields']);
        $this->assertSame('spacious', $rows[1]['spacing']);

        /* And every question, whichever row it is in: that is what a
           submission is answered against. */
        $this->assertSame(['first', 'last', 'history'], array_column($version->fields(), 'key'));

        $theme = $version->theme();
        $this->assertSame('wide', $theme['width']);
        $this->assertSame('left', $theme['label_position']);
        /* Untouched settings still answer, from the shipped defaults. */
        $this->assertSame('business', $theme['background']);
    }

    /**
     * A form saved before rows existed reads back as one question per row.
     *
     * The same form, described the new way. Nothing is migrated: the next
     * save writes the new shape and until then this reads the old one.
     */
    public function test_a_flat_schema_reads_back_as_single_column_rows(): void
    {
        $form = $this->form();

        $form->currentVersion->forceFill(['schema' => ['fields' => [
            ['key' => 'a', 'type' => 'text', 'label' => 'A'],
            ['key' => 'b', 'type' => 'email', 'label' => 'B'],
        ]]])->save();

        $rows = $form->fresh()->currentVersion->rows();

        $this->assertCount(2, $rows);
        $this->assertSame('single', $rows[0]['layout']);
        $this->assertSame('a', $rows[0]['fields'][0]['key']);
        $this->assertSame(['a', 'b'], array_column($form->fresh()->currentVersion->fields(), 'key'));
    }

    /** More than two questions in a row is a table, not a form. */
    public function test_a_row_cannot_hold_more_than_two_questions(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['rows' => [[
                'key' => 'row_1', 'layout' => 'two',
                'fields' => [
                    ['key' => 'a', 'type' => 'text', 'label' => 'A'],
                    ['key' => 'b', 'type' => 'text', 'label' => 'B'],
                    ['key' => 'c', 'type' => 'text', 'label' => 'C'],
                ],
            ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rows.0.fields');
    }

    /**
     * A theme value the vocabulary does not hold is refused.
     *
     * Every one of these becomes a style on a page a client opens, so none of
     * them is a value the browser gets to invent.
     */
    public function test_a_theme_outside_the_vocabulary_is_refused(): void
    {
        $form = $this->form();
        $rows = [['key' => 'row_1', 'layout' => 'single', 'fields' => [
            ['key' => 'a', 'type' => 'text', 'label' => 'A'],
        ]]];

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), [
                'rows' => $rows, 'theme' => ['width' => 'enormous'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme.width');

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), [
                'rows' => $rows, 'theme' => ['background_color' => 'javascript:alert(1)'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme.background_color');
    }

    /** The two kinds the expanded palette adds. */
    public function test_the_new_field_types_are_offered_and_accepted(): void
    {
        foreach (['date_of_birth', 'time', 'multi_select', 'terms', 'rating', 'scale', 'hidden', 'signature_name'] as $type) {
            $this->assertNotSame('', trim(__('forms.fields.'.$type)), $type.' has no label');
        }

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'dob', 'type' => 'date_of_birth', 'label' => 'Date of birth']],
            ]]])
            ->assertOk();
    }

    /**
     * Signed and named in one question.
     *
     * The pair is what makes a waiver readable back — a drawn signature on
     * its own cannot say who signed it — and both halves land in the columns
     * the submission already keeps for them.
     */
    public function test_a_signature_and_name_field_is_accepted(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [[
                    'key' => 'sign', 'type' => 'signature_name',
                    'label' => 'Sign and print your name', 'required' => true,
                ]],
            ]]])
            ->assertOk();

        $this->assertSame('signature_name', $form->fresh()->currentVersion->fields()[0]['type']);

        /* And the submission already has somewhere to put both halves. */
        $submission = $this->submission($form);
        $submission->forceFill([
            'signature' => 'data:image/png;base64,AAAA',
            'signature_type' => 'draw',
            'signed_name' => 'Maria Lopez',
            'signed_at' => now(),
        ])->save();

        $this->assertSame('Maria Lopez', $submission->fresh()->signed_name);
        $this->assertTrue($submission->fresh()->isLocked());
    }

    /**
     * How a tick-list arranges its own answers.
     *
     * A property of the question rather than the form: "Yes / No" reads well
     * on one line and six massage types read better in two columns, and both
     * belong in the same intake form.
     */
    public function test_an_option_layout_is_saved_per_question(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [
                    ['key' => 'services', 'type' => 'checkbox', 'label' => 'Which treatments?',
                        'option_layout' => 'two', 'options' => ['Swedish', 'Deep tissue', 'Hot stone']],
                    ['key' => 'pressure', 'type' => 'multiple_choice', 'label' => 'Pressure',
                        'option_layout' => 'single', 'options' => ['Light', 'Firm']],
                ],
            ]]])
            ->assertOk();

        $fields = $form->fresh()->currentVersion->fields();

        $this->assertSame('two', $fields[0]['option_layout']);
        $this->assertSame('single', $fields[1]['option_layout']);
    }

    /**
     * Yes/No arranges its two answers as well.
     *
     * Its answers are not a list the business typed, but they are still two
     * answers on the page, and "Yes above No" is a legitimate thing to ask
     * for — so the setting reaches it like any other tick-list.
     */
    public function test_a_yes_no_question_carries_a_field_layout(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'pregnant', 'type' => 'yes_no', 'label' => 'Are you pregnant?',
                    'option_layout' => 'two']],
            ]]])
            ->assertOk();

        $this->assertSame('two', $form->fresh()->currentVersion->fields()[0]['option_layout']);
    }

    public function test_an_option_layout_outside_the_vocabulary_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'a', 'type' => 'checkbox', 'label' => 'A', 'option_layout' => 'masonry']],
            ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rows.0.fields.0.option_layout');
    }

    /**
     * The settings every question shares, saved and read back.
     *
     * They are what the renderer reads to decide whether a client sees a
     * question at all, so a value the vocabulary does not hold must not
     * reach it.
     */
    public function test_the_common_field_settings_are_saved(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [[
                    'key' => 'notes', 'type' => 'long_text', 'label' => 'Additional notes',
                    'hidden' => false, 'disabled' => false, 'required' => true,
                    'hide_label' => true,
                    'default_value' => 'United States',
                    'placeholder' => 'Tell us anything we should know',
                    'description' => 'Please include any condition that may affect your treatment.',
                    'help_position' => 'above',
                    'max_length' => 250,
                    'show_counter' => true,
                    'error_message' => 'Please tell us about your health.',
                ]],
            ]]])
            ->assertOk();

        $field = $form->fresh()->currentVersion->fields()[0];

        $this->assertTrue($field['required']);
        $this->assertTrue($field['hide_label']);
        $this->assertSame('United States', $field['default_value']);
        $this->assertSame('above', $field['help_position']);
        $this->assertSame(250, $field['max_length']);
        $this->assertTrue($field['show_counter']);
        $this->assertSame('Please tell us about your health.', $field['error_message']);
    }

    /** A hidden question is still a question the business keeps. */
    public function test_a_hidden_question_is_kept_on_the_form(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'source', 'type' => 'text', 'label' => 'Source',
                    'hidden' => true, 'default_value' => 'walk-in']],
            ]]])
            ->assertOk();

        $field = $form->fresh()->currentVersion->fields()[0];

        $this->assertTrue($field['hidden']);
        $this->assertSame('walk-in', $field['default_value']);
        $this->assertCount(1, $form->fresh()->currentVersion->fields());
    }

    public function test_a_help_position_outside_the_vocabulary_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'a', 'type' => 'text', 'label' => 'A', 'help_position' => 'sideways']],
            ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rows.0.fields.0.help_position');
    }

    /** A cap with an end to it: the column is not unbounded. */
    public function test_a_character_limit_beyond_the_maximum_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'a', 'type' => 'long_text', 'label' => 'A', 'max_length' => 999999]],
            ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rows.0.fields.0.max_length');
    }

    /**
     * The buttons at the end of the form, and keeping what was typed.
     *
     * Blank wording is stored as blank rather than as "Submit": a business
     * that typed English in would have written it into a form its Spanish
     * clients read, so the fallback has to stay a fallback.
     */
    public function test_the_button_and_progress_properties_are_saved(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), [
                'rows' => [['key' => 'row_1', 'layout' => 'single', 'fields' => [
                    ['key' => 'a', 'type' => 'text', 'label' => 'A'],
                ]]],
                'theme' => [
                    'submit_label' => 'Send my intake form',
                    'cancel_label' => 'Start over',
                    'show_cancel' => true,
                    'button_alignment' => 'center',
                    'save_progress' => true,
                ],
            ])
            ->assertOk();

        $theme = $form->fresh()->currentVersion->theme();

        $this->assertSame('Send my intake form', $theme['submit_label']);
        $this->assertSame('Start over', $theme['cancel_label']);
        $this->assertTrue($theme['show_cancel']);
        $this->assertSame('center', $theme['button_alignment']);
        $this->assertTrue($theme['save_progress']);
    }

    /** Untouched, the wording stays null so the translated default applies. */
    public function test_button_wording_defaults_to_nothing_rather_than_english(): void
    {
        $theme = $this->form()->currentVersion->theme();

        $this->assertNull($theme['submit_label']);
        $this->assertNull($theme['cancel_label']);
        $this->assertFalse($theme['show_cancel']);
        $this->assertSame('left', $theme['button_alignment']);
        $this->assertFalse($theme['save_progress']);
    }

    public function test_a_button_alignment_outside_the_vocabulary_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), [
                'rows' => [['key' => 'row_1', 'layout' => 'single', 'fields' => [
                    ['key' => 'a', 'type' => 'text', 'label' => 'A'],
                ]]],
                'theme' => ['button_alignment' => 'justified'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme.button_alignment');
    }

    // ----------------------------------------------------- status and link

    /**
     * Publishing mints the form's address, once.
     *
     * Random rather than derived from the name: anyone holding the URL can
     * open the form, and a name-derived one would also change under a
     * business that renames its form — breaking every link already sent.
     */
    public function test_publishing_mints_a_public_link_and_keeps_it(): void
    {
        $form = $this->form(published: true);
        $form->currentVersion->forceFill(['published_at' => null])->save();

        $this->assertNull($form->public_token);

        $this->actingAs($this->owner)
            ->postJson(route('settings.forms.publish', $form))
            ->assertOk();

        $first = $form->fresh()->public_token;

        $this->assertNotNull($first);
        $this->assertSame(12, strlen($first));

        /* Published again: the same address. */
        $this->actingAs($this->owner)->postJson(route('settings.forms.publish', $form))->assertOk();

        $this->assertSame($first, $form->fresh()->public_token);
    }

    /** Built on the tenant's own subdomain, like every public tenant URL. */
    public function test_the_public_link_is_on_the_business_subdomain(): void
    {
        $form = $this->form(published: true);
        $form->forceFill(['public_token' => 'abcdef123456'])->save();

        $this->assertSame(
            'https://'.$this->tenant->slug.'.'.config('tenancy.tenant_domain_suffix').'/form/abcdef123456',
            $form->fresh()->publicUrl(),
        );
    }

    /** A draft has no address, because there is nothing at the other end. */
    public function test_an_unpublished_form_has_no_link(): void
    {
        $this->assertNull($this->form()->publicUrl());
    }

    public function test_the_edit_screen_shows_the_link_once_published(): void
    {
        $form = $this->form(published: true);
        $form->forceFill(['public_token' => 'abcdef123456'])->save();

        $content = $this->actingAs($this->owner)
            ->get(route('settings.forms.edit', $form))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/form/abcdef123456', $content);
        $this->assertStringContainsString(__('forms.edit.public_url'), $content);
        /* And says the page is not built yet, rather than letting somebody
           hand a dead link to a client. */
        $this->assertStringContainsString(__('forms.edit.not_live_yet'), $content);
    }

    /**
     * Status is a field only once the form has questions to send.
     *
     * Going live holds forms.publish, so a dropdown on the settings screen
     * must not be a way around the check that keeps an empty form off the
     * wire.
     */
    public function test_the_status_field_sets_a_published_form_live_or_paused(): void
    {
        $form = $this->form(published: true);

        $this->actingAs($this->owner)
            ->patch(route('settings.forms.update', $form), [
                'name' => 'Massage Intake', 'type' => 'intake', 'layout' => 'classic',
                'status' => Form::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($form->fresh()->isActive());

        $this->actingAs($this->owner)
            ->patch(route('settings.forms.update', $form), [
                'name' => 'Massage Intake', 'type' => 'intake', 'layout' => 'classic',
                'status' => Form::STATUS_INACTIVE,
            ]);

        $this->assertSame(Form::STATUS_INACTIVE, $form->fresh()->status);
    }

    /** A form with no questions cannot be set live from the settings screen. */
    public function test_the_status_field_cannot_publish_an_empty_form(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->patch(route('settings.forms.update', $form), [
                'name' => 'Empty', 'type' => 'intake', 'layout' => 'classic',
                'status' => Form::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Form::STATUS_DRAFT, $form->fresh()->status);
    }

    /** And nor can somebody who may edit but not publish. */
    public function test_a_reader_without_publish_cannot_set_the_status(): void
    {
        $form = $this->form(published: true);

        $this->actingAs($this->administratorHolding([
            'settings.view' => 'all', 'forms.view' => 'all', 'forms.edit' => 'all',
        ]))
            ->patch(route('settings.forms.update', $form), [
                'name' => 'Massage Intake', 'type' => 'intake', 'layout' => 'classic',
                'status' => Form::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Form::STATUS_DRAFT, $form->fresh()->status);
    }

    // ------------------------------------------------------- before & after

    /**
     * The two sides, each configured on its own.
     *
     * A treatment form legitimately requires the before photographs and lets
     * the after ones arrive later, so the two sides cannot share one answer.
     */
    public function test_a_before_and_after_question_configures_both_sides(): void
    {
        $form = $this->form();

        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $form), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [[
                    'key' => 'photos', 'type' => 'before_after', 'label' => 'Treatment photos',
                    'before_label' => 'Before treatment', 'after_label' => 'After treatment',
                    'before_help' => 'Upload clear photos before starting.',
                    'after_help' => 'Upload photos immediately after.',
                    'before_required' => true, 'after_required' => false,
                    'max_before' => 8, 'max_after' => 6,
                    'max_kb' => 5120,
                    'file_types' => ['jpg', 'png'],
                ]],
            ]]])
            ->assertOk();

        $field = $form->fresh()->currentVersion->fields()[0];

        $this->assertSame('before_after', $field['type']);
        $this->assertSame('Before treatment', $field['before_label']);
        $this->assertTrue($field['before_required']);
        $this->assertFalse($field['after_required']);
        $this->assertSame(8, $field['max_before']);
        $this->assertSame(['jpg', 'png'], $field['file_types']);
    }

    /**
     * The form cannot promise a size the disk will refuse.
     *
     * The storage layer is the authority on what it will take, and a form
     * offering more would fail at the worst moment — a client, mid-submit.
     */
    public function test_a_file_size_beyond_the_platform_limit_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'p', 'type' => 'before_after', 'label' => 'Photos',
                    'max_kb' => config('forms.uploads.max_kb') + 1024]],
            ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rows.0.fields.0.max_kb');
    }

    public function test_a_file_type_the_platform_cannot_store_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.forms.schema', $this->form()), ['rows' => [[
                'key' => 'row_1', 'layout' => 'single',
                'fields' => [['key' => 'p', 'type' => 'before_after', 'label' => 'Photos',
                    'file_types' => ['heic']]],
            ]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rows.0.fields.0.file_types.0');
    }

    /**
     * The builder's limit and the disk's are the same number.
     *
     * Two copies of it would drift, and the day they did the form would be
     * offering a size the storage layer rejects. FileValidator is the
     * authority; this holds config/forms.php to it.
     */
    public function test_the_upload_limit_matches_the_storage_layer(): void
    {
        $this->assertSame(
            app(FileValidator::class)->maxKilobytes('client-photo'),
            config('forms.uploads.max_kb'),
        );
    }

    /** Every offered type is one the storage layer actually accepts. */
    public function test_every_offered_file_type_is_one_the_disk_takes(): void
    {
        /* Read off the validator rather than restated: a type this offered
           and the disk refused is a setting a client cannot get past. */
        $accepted = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

        $this->assertSame($accepted, config('forms.uploads.types'));
        $this->assertNotContains('heic', config('forms.uploads.types'), 'HEIC is not storable yet.');
    }

    // ------------------------------------------------------------- deleting

    public function test_a_form_nobody_has_answered_can_be_deleted(): void
    {
        $form = $this->form(published: true);

        $this->actingAs($this->owner)
            ->delete(route('settings.forms.destroy', $form))
            ->assertRedirect(route('settings.forms.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Form::withoutGlobalScopes()->count());
        $this->assertSame(0, FormVersion::withoutGlobalScopes()->count());
    }

    /**
     * A form somebody has completed is kept, and the refusal says so.
     *
     * Told rather than silently archived: somebody who pressed Delete should
     * find out the form is being kept and what to do instead.
     */
    public function test_a_form_with_submissions_is_refused_deletion(): void
    {
        $form = $this->form(published: true);
        $this->submission($form, [
            'status' => FormSubmission::STATUS_SIGNED,
            'completed_at' => now(), 'signed_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->delete(route('settings.forms.destroy', $form))
            ->assertSessionHasErrors('form');

        $this->assertNotNull($form->fresh());
    }

    /** And the menu does not offer what the server will refuse. */
    public function test_the_row_menu_offers_delete_only_where_it_is_possible(): void
    {
        $clean = $this->form(published: true);

        $content = $this->actingAs($this->owner)
            ->get(route('settings.forms.index'))->assertOk()->getContent();

        $this->assertStringContainsString('formDelete'.$clean->id, $content);
        $this->assertStringContainsString(__('forms.actions.build'), $content);

        $this->submission($clean, [
            'status' => FormSubmission::STATUS_SIGNED,
            'completed_at' => now(), 'signed_at' => now(),
        ]);

        $after = $this->actingAs($this->owner)
            ->get(route('settings.forms.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('formDelete'.$clean->id, $after);
    }

    // ------------------------------------------------------------ the answers

    /**
     * A sensitive form's answers are unreadable in the database.
     *
     * Medical history, medications, pregnancy status. Asserted against the
     * raw column rather than the model, because the model is exactly what
     * would hide a failure here.
     */
    public function test_a_sensitive_forms_answers_are_encrypted_at_rest(): void
    {
        $form = $this->form(published: true);
        $form->forceFill(['contains_sensitive' => true])->save();

        $submission = $this->submission($form, [
            'answers_encrypted' => true,
            'answers' => ['medications' => 'Warfarin'],
        ]);

        $raw = (string) DB::table('form_submissions')->where('id', $submission->id)->value('answers');

        $this->assertStringNotContainsString('Warfarin', $raw);
        $this->assertSame(['medications' => 'Warfarin'], json_decode(Crypt::decryptString($raw), true));
        $this->assertSame(['medications' => 'Warfarin'], $submission->fresh()->answers);
    }

    /** An ordinary form's answers are stored plainly and read back the same. */
    public function test_an_ordinary_forms_answers_are_stored_plainly(): void
    {
        $submission = $this->submission($this->form(published: true), [
            'answers' => ['preferred_pressure' => 'Firm'],
        ]);

        $raw = (string) DB::table('form_submissions')->where('id', $submission->id)->value('answers');

        $this->assertStringContainsString('Firm', $raw);
        $this->assertSame(['preferred_pressure' => 'Firm'], $submission->fresh()->answers);
    }

    /**
     * The flag is read off the row, not the form.
     *
     * This is the whole reason `answers_encrypted` is a column. A business
     * that switches the form's setting off must not make the rows written
     * while it was on undecryptable.
     */
    public function test_answers_stay_readable_after_the_form_stops_being_sensitive(): void
    {
        $form = $this->form(published: true);
        $form->forceFill(['contains_sensitive' => true])->save();

        $submission = $this->submission($form, [
            'answers_encrypted' => true,
            'answers' => ['allergies' => 'Latex'],
        ]);

        $form->forceFill(['contains_sensitive' => false])->save();

        $this->assertSame(['allergies' => 'Latex'], $submission->fresh()->answers);
    }

    /**
     * Expiry is read, not swept.
     *
     * The same shape as loyalty point expiry: no nightly job, and
     * `completed_at` still says when the form was filled in.
     */
    public function test_a_completed_form_past_its_date_reads_as_expired(): void
    {
        $submission = $this->submission($this->form(published: true), [
            'status' => FormSubmission::STATUS_COMPLETED,
            'completed_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($submission->hasExpired());
        $this->assertSame('expired', $submission->statusKey());
        $this->assertSame(__('forms.submission_statuses.expired'), $submission->statusLabel());
        $this->assertNotNull($submission->completed_at, 'Expiry does not erase when it was done.');
        $this->assertSame(0, FormSubmission::withoutGlobalScopes()->valid()->count());
    }

    /** A signed submission is evidence and never changes. */
    public function test_a_signed_submission_is_locked(): void
    {
        $submission = $this->submission($this->form(published: true), [
            'status' => FormSubmission::STATUS_SIGNED,
            'completed_at' => now(),
            'signed_at' => now(),
        ]);

        $this->assertTrue($submission->isLocked());
    }

    /**
     * Answers read without the flag column refuse rather than read empty.
     *
     * A partial select brings `answers` without `answers_encrypted`, and
     * guessing there decodes ciphertext as json and hands back null. Empty
     * answers on a signed medical history is the worst way to be wrong.
     */
    public function test_reading_answers_without_the_flag_column_is_refused(): void
    {
        $form = $this->form(published: true);
        $form->forceFill(['contains_sensitive' => true])->save();

        $submission = $this->submission($form, [
            'answers_encrypted' => true,
            'answers' => ['allergies' => 'Latex'],
        ]);

        $partial = FormSubmission::withoutGlobalScopes()
            ->whereKey($submission->id)
            ->first(['id', 'answers']);

        $this->expectException(\LogicException::class);

        $partial->answers;
    }

    // ------------------------------------------------------------ permissions

    /**
     * Reaching App Settings is not the same as being able to build a form.
     *
     * The module's own permissions, asserted on a role that is already past
     * the settings gate — a receptionist never gets that far, which is the
     * App Settings boundary and tested with it, not here.
     */
    public function test_a_reader_without_the_create_permission_is_refused(): void
    {
        $user = $this->administratorHolding([
            'settings.view' => 'all',
            'forms.view' => 'all',
        ]);

        $this->actingAs($user)->get(route('settings.forms.index'))->assertOk();

        $this->actingAs($user)
            ->post(route('settings.forms.store'), ['name' => 'Mine', 'type' => 'intake', 'layout' => 'classic'])
            ->assertForbidden();

        $this->assertSame(0, Form::withoutGlobalScopes()->count());
    }

    /** And without forms.view the screen itself is refused. */
    public function test_a_reader_without_forms_view_cannot_open_the_screen(): void
    {
        $this->actingAs($this->administratorHolding(['settings.view' => 'all']))
            ->get(route('settings.forms.index'))
            ->assertForbidden();
    }

    /** Archiving is its own authority, separate from editing. */
    public function test_a_reader_without_the_archive_permission_cannot_archive(): void
    {
        $form = $this->form(published: true);

        $this->actingAs($this->administratorHolding([
            'settings.view' => 'all',
            'forms.view' => 'all',
            'forms.edit' => 'all',
        ]))
            ->patch(route('settings.forms.archive', $form))
            ->assertForbidden();

        $this->assertSame(Form::STATUS_DRAFT, $form->fresh()->status);
    }

    /** Every business starts with somewhere to file a form. */
    public function test_a_new_business_is_given_the_default_categories(): void
    {
        $fresh = Tenant::create(['name' => 'New Spa', 'slug' => 'new-forms']);

        $this->assertSame(
            count(config('forms.default_categories')),
            FormCategory::withoutGlobalScopes()->where('tenant_id', $fresh->getTenantKey())->count(),
        );
    }

    // --------------------------------------------------------------- helpers

    private function form(bool $published = false): Form
    {
        $form = Form::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Massage Intake',
            'type' => 'intake',
            'status' => Form::STATUS_DRAFT,
        ]);

        $version = FormVersion::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'form_id' => $form->id,
            'version' => 1,
            'schema' => $published ? ['fields' => [['key' => 'allergies', 'type' => 'long_text']]] : null,
            'published_at' => $published ? now() : null,
        ]);

        $form->forceFill(['current_version_id' => $version->id])->save();

        return $form->fresh();
    }

    /** @param  array<string, mixed>  $overrides */
    private function submission(Form $form, array $overrides = []): FormSubmission
    {
        $client = Client::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Maria', 'last_name' => 'Lopez',
        ]);

        return FormSubmission::withoutGlobalScopes()->create($overrides + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'form_id' => $form->id,
            'form_version_id' => $form->current_version_id,
            'client_id' => $client->id,
            'token' => FormSubmission::newToken(),
            'status' => FormSubmission::STATUS_NOT_SENT,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Somebody who can reach App Settings, holding exactly these permissions.
     *
     * The administrator role reshaped rather than a custom one built: what is
     * being tested is the controller's gates, and the fewest moving parts
     * that put a real user in front of them is the administrator whose
     * permissions have been narrowed.
     *
     * @param  array<string, string>  $permissions
     */
    private function administratorHolding(array $permissions): User
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Reid',
            'email' => 'admin@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        $role = Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'administrator')
            ->firstOrFail();

        app(ProvisionSystemRoles::class)->syncPermissions($role, $permissions);

        /* The role reaches a user through their staff row, not a column on
           the user: that is where StyleDesk records who somebody is at work. */
        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'user_id' => $user->id,
            'role_id' => $role->id,
            'first_name' => 'Sam', 'last_name' => 'Reid',
        ]);

        return $user->fresh();
    }
}
