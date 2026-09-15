<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A form, filled in by the person it was sent to.
 *
 * The token in the URL is the whole of the authorisation, so what these guard
 * is mostly what the route refuses: a paused form, an unpublished one, an
 * empty one and a token that is simply wrong all look the same from outside.
 */
class PublicFormTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'VIV Life', 'slug' => 'vivlife']);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        /* The subdomain the tenancy middleware identifies the business from.
           Onboarding registers this for a real business; a test has to say
           so itself. */
        $this->tenant->domains()->create(['domain' => $this->tenant->slug]);
    }

    /** The address the settings screen shows is the address that answers. */
    public function test_a_published_form_opens_on_the_business_subdomain(): void
    {
        $form = $this->publishedForm();

        $this->get($this->url($form))
            ->assertOk()
            ->assertSee($form->name)
            ->assertSee('data-vue-component="FormPreview"', false);
    }

    /** Sent to somebody, not listed. */
    public function test_the_public_form_is_not_indexed(): void
    {
        $this->get($this->url($this->publishedForm()))
            ->assertOk()
            ->assertSee('noindex', false);
    }

    /**
     * Everything short of a live form is the same 404.
     *
     * The difference between "no such form" and "that form is paused" is not
     * something a stranger needs, and telling them narrows the guessing.
     */
    public function test_a_form_that_is_not_live_is_not_found(): void
    {
        $paused = $this->publishedForm();
        $paused->forceFill(['status' => Form::STATUS_INACTIVE])->save();
        $this->get($this->url($paused))->assertNotFound();

        $archived = $this->publishedForm('t2');
        $archived->forceFill(['status' => Form::STATUS_ARCHIVED])->save();
        $this->get($this->url($archived))->assertNotFound();

        /* Active, but its version was never published. */
        $unpublished = $this->publishedForm('t3');
        $unpublished->currentVersion->forceFill(['published_at' => null])->save();
        $this->get($this->url($unpublished))->assertNotFound();

        /* Active and published, with nothing on it to answer. */
        $empty = $this->publishedForm('t4');
        $empty->currentVersion->forceFill(['schema' => ['rows' => []]])->save();
        $this->get($this->url($empty))->assertNotFound();

        $this->get('https://vivlife.'.config('tenancy.tenant_domain_suffix').'/form/nosuchtoken')
            ->assertNotFound();
    }

    public function test_answers_are_stored_against_the_form(): void
    {
        $form = $this->publishedForm();

        $this->postJson($this->url($form), [
            'answers' => json_encode(['name' => 'Maria Lopez', 'allergies' => 'Latex']),
        ])->assertOk();

        $submission = FormSubmission::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($form->id, $submission->form_id);
        $this->assertSame($form->current_version_id, $submission->form_version_id);
        $this->assertSame(FormSubmission::STATUS_COMPLETED, $submission->status);
        $this->assertSame('client_link', $submission->source);
        $this->assertSame(['name' => 'Maria Lopez', 'allergies' => 'Latex'], $submission->answers);

        /* Nobody yet: the link is the form's, not a client's. */
        $this->assertNull($submission->client_id);
    }

    /**
     * An answer to a question the form does not ask is not stored.
     *
     * A stale tab or somebody probing; neither is something to keep.
     */
    public function test_answers_to_unknown_questions_are_dropped(): void
    {
        $form = $this->publishedForm();

        $this->postJson($this->url($form), [
            'answers' => json_encode(['name' => 'Maria', 'not_a_field' => 'anything']),
        ])->assertOk();

        $this->assertSame(
            ['name' => 'Maria'],
            FormSubmission::withoutGlobalScopes()->firstOrFail()->answers,
        );
    }

    /**
     * Required is checked again here.
     *
     * The panel refuses an empty required question, and a request that did
     * not come from the panel is exactly the one that would not have been.
     */
    public function test_a_missing_required_answer_is_refused(): void
    {
        $form = $this->publishedForm(required: true);

        $this->postJson($this->url($form), ['answers' => json_encode(['allergies' => 'None'])])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame(0, FormSubmission::withoutGlobalScopes()->count());
    }

    /** And the business's own wording is what the client is told. */
    public function test_a_custom_error_message_is_used(): void
    {
        $form = $this->publishedForm(required: true, error: 'We need your name to find your booking.');

        $this->postJson($this->url($form), ['answers' => json_encode([])])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'We need your name to find your booking.');
    }

    /**
     * A sensitive form's answers are unreadable in the database.
     *
     * Asserted against the raw column, because the model is exactly what
     * would hide a failure here.
     */
    public function test_a_sensitive_forms_public_answers_are_encrypted(): void
    {
        $form = $this->publishedForm();
        $form->forceFill(['contains_sensitive' => true])->save();

        $this->postJson($this->url($form), ['answers' => json_encode(['allergies' => 'Warfarin'])])
            ->assertOk();

        $raw = (string) DB::table('form_submissions')->value('answers');

        $this->assertStringNotContainsString('Warfarin', $raw);
        $this->assertSame(['allergies' => 'Warfarin'], json_decode(Crypt::decryptString($raw), true));
    }

    /** A signature lands in the columns that exist for it. */
    public function test_a_signature_is_recorded_on_the_submission(): void
    {
        $form = $this->publishedForm(signature: true);

        $this->postJson($this->url($form), [
            'answers' => json_encode(['name' => 'Maria', 'sign' => 'data:image/png;base64,AAAA']),
        ])->assertOk();

        $submission = FormSubmission::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(FormSubmission::STATUS_SIGNED, $submission->status);
        $this->assertSame('data:image/png;base64,AAAA', $submission->signature);
        $this->assertSame('draw', $submission->signature_type);
        $this->assertNotNull($submission->signed_at);
        $this->assertTrue($submission->isLocked());
    }

    /**
     * Files arrive owned by the submission, and say which side they answered.
     *
     * `stored_files` has no column for it, and the two sides are the whole
     * point of a before-and-after: a photograph that cannot say which half it
     * belongs to is one nobody can use.
     */
    public function test_before_and_after_files_are_stored_and_labelled(): void
    {
        Storage::fake('local');

        $form = $this->publishedForm(uploads: true);

        $this->post($this->url($form), [
            'answers' => json_encode(['name' => 'Maria']),
            'files' => [
                'photos' => [
                    'before' => [UploadedFile::fake()->image('before1.jpg')],
                    'after' => [UploadedFile::fake()->image('after1.jpg'), UploadedFile::fake()->image('after2.jpg')],
                ],
            ],
        ])->assertOk();

        $submission = FormSubmission::withoutGlobalScopes()->firstOrFail();
        $stored = StoredFile::withoutGlobalScopes()->get();

        $this->assertCount(3, $stored);
        $this->assertSame(['form-submission'], $stored->pluck('entity_type')->unique()->all());
        $this->assertSame([$submission->id], $stored->pluck('entity_id')->unique()->all());

        $answers = $submission->answers;

        $this->assertCount(1, $answers['photos']['before']);
        $this->assertCount(2, $answers['photos']['after']);
    }

    /** A required side with nothing in it stops the submission. */
    public function test_a_required_before_side_is_refused_when_empty(): void
    {
        Storage::fake('local');

        $form = $this->publishedForm(uploads: true, requireBefore: true);

        /* Files mean a multipart post, and Accept asks for the refusal as
           json — without it a ValidationException redirects, which is right
           for a browser form and useless to assert on. */
        $this->post(
            $this->url($form),
            ['answers' => json_encode(['name' => 'Maria'])],
            ['Accept' => 'application/json'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos.before');

        $this->assertSame(0, FormSubmission::withoutGlobalScopes()->count());
    }

    /**
     * The same form on the application's own domain.
     *
     * A tenant subdomain has to be SERVED — wildcard DNS and a vhost that
     * answers for it — and where that is not true a link on the subdomain is
     * one nobody can open. Both addresses answer; config decides which is
     * handed out.
     */
    public function test_the_form_also_opens_on_the_central_domain(): void
    {
        $form = $this->publishedForm();

        $this->get(config('app.url').'/form/'.$form->public_token)
            ->assertOk()
            ->assertSee($form->name);
    }

    /** And the token is what says which business it belongs to there. */
    public function test_a_central_submission_is_stored_for_the_right_business(): void
    {
        $form = $this->publishedForm();

        $this->postJson(config('app.url').'/form/'.$form->public_token, [
            'answers' => json_encode(['name' => 'Maria Lopez']),
        ])->assertOk();

        $submission = FormSubmission::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($form->id, $submission->form_id);
        $this->assertSame($this->tenant->getTenantKey(), $submission->tenant_id);
    }

    /** Which address the settings screen hands out is a config answer. */
    public function test_the_advertised_link_follows_the_configured_host(): void
    {
        $form = $this->publishedForm();

        config(['forms.public_link_host' => 'subdomain']);
        $this->assertSame(
            'https://'.$this->tenant->slug.'.'.config('tenancy.tenant_domain_suffix').'/form/'.$form->public_token,
            $form->publicUrl(),
        );

        config(['forms.public_link_host' => 'central']);
        $this->assertSame(
            config('app.url').'/form/'.$form->public_token,
            $form->fresh()->publicUrl(),
        );
    }

    // --------------------------------------------------------------- helpers

    private function url(Form $form): string
    {
        return 'https://'.$this->tenant->slug.'.'.config('tenancy.tenant_domain_suffix')
            .'/form/'.$form->public_token;
    }

    private function publishedForm(
        string $token = 'tok123456789',
        bool $required = false,
        bool $signature = false,
        bool $uploads = false,
        bool $requireBefore = false,
        ?string $error = null,
    ): Form {
        $fields = [[
            'key' => 'name', 'type' => 'text', 'label' => 'Your name',
            'required' => $required, 'error_message' => $error,
        ], [
            'key' => 'allergies', 'type' => 'long_text', 'label' => 'Any allergies?',
        ]];

        if ($signature) {
            $fields[] = ['key' => 'sign', 'type' => 'signature', 'label' => 'Sign here'];
        }

        if ($uploads) {
            $fields[] = [
                'key' => 'photos', 'type' => 'before_after', 'label' => 'Treatment photos',
                'before_label' => 'Before', 'after_label' => 'After',
                'before_required' => $requireBefore, 'after_required' => false,
            ];
        }

        $form = Form::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Massage Intake', 'type' => 'intake',
            'status' => Form::STATUS_ACTIVE,
            'public_token' => $token,
        ]);

        $version = FormVersion::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'form_id' => $form->id, 'version' => 1,
            'schema' => ['rows' => [[
                'key' => 'row_1', 'layout' => 'single', 'fields' => $fields,
            ]]],
            'published_at' => now(),
        ]);

        $form->forceFill(['current_version_id' => $version->id])->save();

        return $form->fresh();
    }
}
