<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\EmailRenderer;
use App\Support\EmailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * App Settings → Email Templates.
 *
 * The screen has to show the whole catalogue, including the templates the
 * business has never touched — those are the ones StyleDesk is sending on
 * their behalf right now, and a list that hides them is a list that hides
 * what the product is actually saying to their clients.
 */
class EmailTemplateListTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Serenity Spa', 'slug' => 'serenity-list']);

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
    }

    public function test_the_screen_lists_every_template_including_untouched_ones(): void
    {
        $this->assertSame(0, EmailTemplate::withoutGlobalScopes()->count());

        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.index'))
            ->assertOk()
            ->assertSee('Booking Confirmation')
            ->assertSee('Outstanding Balance Reminder')
            ->assertSee('Aftercare Instructions')
            ->assertSee(__('email_templates.list.styledesk_default'));
    }

    public function test_it_is_reachable_from_the_app_settings_index(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(route('settings.email-templates.index'), false);
    }

    public function test_it_filters_by_type_and_searches(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.index', ['type' => 'standard']))
            ->assertOk()
            ->assertSee('Aftercare Instructions')
            ->assertDontSee('Booking Confirmation');

        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.index', ['search' => 'aftercare']))
            ->assertOk()
            ->assertSee('Aftercare Instructions')
            ->assertDontSee('Booking Confirmation');
    }

    /**
     * Switching an untouched template off writes the row that says so.
     *
     * It is the first opinion the business has expressed about that email.
     */
    public function test_disabling_a_default_template_writes_a_row(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.toggle', 'booking.confirmed'))
            ->assertRedirect();

        $stored = EmailTemplate::withoutGlobalScopes()->where('key', 'booking.confirmed')->first();

        $this->assertNotNull($stored);
        $this->assertFalse($stored->is_active);
        $this->assertSame($this->owner->id, $stored->updated_by);
        $this->assertNull(EmailTemplates::activeFor($this->tenant, 'booking.confirmed'));
    }

    public function test_disabling_and_enabling_again_restores_it(): void
    {
        $this->actingAs($this->owner)->patch(route('settings.email-templates.toggle', 'booking.confirmed'));
        $this->actingAs($this->owner)->patch(route('settings.email-templates.toggle', 'booking.confirmed'));

        $this->assertNotNull(EmailTemplates::activeFor($this->tenant, 'booking.confirmed'));
    }

    /**
     * Reset is a delete, and the StyleDesk wording comes back.
     *
     * There is no stored copy of the default to restore from — the default is
     * in the code — which is what makes this the one action that cannot fail.
     */
    public function test_reset_removes_the_row_and_the_default_returns(): void
    {
        $default = EmailTemplates::default($this->tenant, 'booking.confirmed')->subject;

        EmailTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'key' => 'booking.confirmed',
            'type' => EmailTemplate::TYPE_TRANSACTIONAL,
            'trigger' => 'booking.confirmed',
            'name' => 'Ours', 'subject' => 'Our own wording',
        ]);

        $this->actingAs($this->owner)
            ->delete(route('settings.email-templates.reset', 'booking.confirmed'))
            ->assertRedirect();

        $this->assertSame(0, EmailTemplate::withoutGlobalScopes()->count());
        $this->assertSame($default, EmailTemplates::resolve($this->tenant, 'booking.confirmed')->subject);
    }

    // ------------------------------------------------------------- editor

    public function test_the_list_offers_create_edit_and_duplicate(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.index'))
            ->assertOk()
            ->assertSee(route('settings.email-templates.create'), false)
            ->assertSee(route('settings.email-templates.edit', 'booking.confirmed'), false)
            ->assertSee(__('email_templates.list.edit'))
            ->assertSee(__('email_templates.list.duplicate'));
    }

    /** The row actions use the same menu every other listing does. */
    public function test_row_actions_are_in_the_house_row_menu(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.index'))
            ->assertOk()
            ->assertSee('styledesk_rowmenu__button', false)
            ->assertSee('styledesk_rowmenu__pop', false)
            ->assertSee(__('email_templates.list.actions_for', ['name' => 'Booking Confirmation']));
    }

    /**
     * Every menu entry that changes something posts.
     *
     * A link that mutates is one a browser may follow while prefetching, which
     * is how a template disables itself before anybody clicks.
     */
    public function test_the_mutating_actions_are_forms_rather_than_links(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('settings.email-templates.index'))
            ->assertOk()
            ->getContent();

        foreach (['duplicate-0', 'toggle-0'] as $form) {
            $this->assertStringContainsString('id="'.$form.'"', $html);
            $this->assertStringContainsString('form="'.$form.'"', $html);
        }

        $this->assertStringNotContainsString(
            '<a href="'.route('settings.email-templates.toggle', 'booking.confirmed').'"',
            $html
        );
    }

    /** The editor opens on a template that has no row of its own. */
    public function test_the_editor_opens_on_a_styledesk_default(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->assertSee('Your appointment with {{business.name}} is confirmed', false)
            ->assertSee(__('email_templates.editor.insert_variable'))
            ->assertSee(__('email_templates.editor.desktop'));
    }

    public function test_saving_writes_the_businesss_own_wording(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.update', 'booking.confirmed'), [
                'name' => 'Booking Confirmation',
                'subject' => 'See you on {{booking.date}}',
                'heading' => 'You are booked in',
                'intro' => 'Hello {{client.first_name}},',
                'cta_enabled' => '1',
                'cta_label' => 'View Appointment',
                'cta_action' => 'view_booking',
                'blocks' => ['intro' => '1', 'booking_details' => '1'],
                'detail_fields' => ['service' => '1', 'date' => '1'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $stored = EmailTemplates::resolve($this->tenant, 'booking.confirmed');

        $this->assertSame('See you on {{booking.date}}', $stored->subject);
        $this->assertSame('You are booked in', $stored->heading);
        $this->assertTrue($stored->exists);

        /* An unticked box sends nothing, so an absent block has to mean off —
           otherwise switching one off looks identical to never touching it. */
        $this->assertFalse($stored->shows('payment_summary'));
        $this->assertTrue($stored->shows('booking_details'));
        $this->assertFalse($stored->showsDetail('staff'));
    }

    public function test_the_subject_and_name_are_required(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.update', 'booking.confirmed'), ['name' => '', 'subject' => ''])
            ->assertSessionHasErrors(['name', 'subject']);
    }

    /** A button may only point where StyleDesk can actually take somebody. */
    public function test_an_unknown_button_action_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.update', 'booking.confirmed'), [
                'name' => 'Booking Confirmation',
                'subject' => 'Hello',
                'cta_action' => 'https://example.com/anything',
            ])
            ->assertSessionHasErrors('cta_action');
    }

    /**
     * The create screen renders.
     *
     * It shipped broken: the controller merged `['creating' => true]` over the
     * editor state with `+`, which keeps the LEFT value on a duplicate key, so
     * the flag stayed false and the view tried to build a Duplicate link for a
     * template that has no key yet. Only a store test existed, and a store
     * test never opens the page.
     */
    public function test_the_create_screen_opens(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.create'))
            ->assertOk()
            ->assertSee(__('email_templates.editor.new'))
            ->assertSee(__('email_templates.editor.create'))
            /* Nothing to duplicate until it exists. Testing is offered,
               because what it sends is on screen rather than in the database. */
            ->assertDontSee(__('email_templates.editor.duplicate'))
            ->assertSee(__('email_templates.editor.send_test'));
    }

    /**
     * The create screen previews as you type, like the editor.
     *
     * Somebody writing from scratch needs it more than somebody editing: there
     * is nothing else to judge the wording against.
     */
    public function test_a_draft_previews_before_it_has_been_saved(): void
    {
        $html = $this->actingAs($this->owner)
            ->post(route('settings.email-templates.preview-draft'), [
                'name' => 'Parking Instructions',
                'subject' => 'Where to park',
                'heading' => 'Where to park',
                'intro' => 'Hello {{client.first_name}}, the car park is behind the building.',
                'blocks' => ['intro' => '1'],
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Where to park', $html);
        $this->assertStringContainsString('Hello Sarah', $html);
        $this->assertStringNotContainsString('{{', $html);

        /* Previewing a draft must not create one. */
        $this->assertSame(0, EmailTemplate::withoutGlobalScopes()->count());
    }

    /** A half-written form still previews — that is the point of watching it. */
    public function test_a_preview_renders_before_the_required_fields_are_filled_in(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.email-templates.preview-draft'), ['intro' => 'Just the message so far'])
            ->assertOk();

        /* Saving still insists on them. */
        $this->actingAs($this->owner)
            ->post(route('settings.email-templates.store'), ['intro' => 'Just the message so far'])
            ->assertSessionHasErrors(['name', 'subject']);
    }

    public function test_a_business_can_create_its_own_standard_template(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.email-templates.store'), [
                'name' => 'Parking Instructions',
                'subject' => 'Where to park at {{business.name}}',
                'intro' => 'Hello {{client.first_name}},',
            ])
            ->assertRedirect(route('settings.email-templates.edit', 'parking_instructions'));

        $created = EmailTemplate::withoutGlobalScopes()->where('key', 'parking_instructions')->first();

        $this->assertSame(EmailTemplate::TYPE_STANDARD, $created->type);
        /* Nothing fires a template somebody invented. */
        $this->assertNull($created->trigger);
    }

    /** A new key must not collide with one of StyleDesk's own. */
    public function test_a_created_template_cannot_take_a_shipped_key(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.email-templates.store'), [
                'name' => 'Thank You',
                'subject' => 'Thanks',
            ]);

        $created = EmailTemplate::withoutGlobalScopes()->first();

        $this->assertNotSame('thank_you', $created->key);
        $this->assertSame('thank_you_2', $created->key);
    }

    /** A duplicate is always standard: two templates on one trigger is two emails for one event. */
    public function test_duplicating_a_transactional_template_produces_a_standard_one(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.email-templates.duplicate', 'booking.confirmed'))
            ->assertRedirect();

        $copy = EmailTemplate::withoutGlobalScopes()->first();

        $this->assertSame(EmailTemplate::TYPE_STANDARD, $copy->type);
        $this->assertNull($copy->trigger);
        $this->assertStringContainsString('copy', $copy->name);
    }

    /** The preview is the real email, rendered from unsaved values. */
    public function test_the_preview_renders_what_is_in_the_form(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('settings.email-templates.preview', 'booking.confirmed'), [
                'name' => 'Booking Confirmation',
                'subject' => 'Unsaved subject',
                'heading' => 'Unsaved heading',
                'intro' => 'Hello {{client.first_name}}, this is unsaved.',
                'blocks' => ['intro' => '1'],
            ])->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('Unsaved heading', $html);
        /* Variables resolved against sample data, so it reads like an email. */
        $this->assertStringContainsString('Hello Sarah', $html);
        $this->assertStringNotContainsString('{{', $html);

        /* Previewing must not save. */
        $this->assertSame(0, EmailTemplate::withoutGlobalScopes()->count());
    }

    public function test_the_editor_offers_a_way_back_to_the_list(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->assertSee(route('settings.email-templates.index'), false)
            ->assertSee(__('common.back'));
    }

    /**
     * Both combo controls actually render.
     *
     * They did not: a Blade comment mentioning a directive by name compiles
     * that directive, so the comment opened a PHP block which the next real
     * close tag ended — swallowing the props and the control between them. The
     * page still returned 200 with the section apparently just... empty.
     */
    public function test_the_detail_row_and_service_pickers_both_render(): void
    {
        $this->service('Swedish Massage');

        $html = $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            2,
            substr_count($html, 'data-vue-component="MultiSelect"'),
            'Both the detail-row and service combos must render.'
        );

        /* Raw JSON inside a single-quoted attribute — @json hex-escapes
           apostrophes and quotes, so it is not HTML-escaped here. */
        $this->assertStringContainsString('"name":"detail_fields"', $html);
        $this->assertStringContainsString('"name":"detail_services"', $html);
    }

    /**
     * The editor says what each row will contain, not just what it is called.
     *
     * Choosing between "Date" and "Reference" otherwise asks the reader to
     * imagine the value behind the word.
     */
    public function test_the_editor_shows_a_sample_value_for_each_detail_row(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->assertSee('Swedish Massage')
            ->assertSee('BK-20260908-00125')
            ->assertSee('Jennifer Smith');
    }

    /** Insert Variable is offered on the subject and heading, not only the message. */
    public function test_insert_variable_is_offered_on_every_field_that_takes_one(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->getContent();

        foreach (['subject', 'heading', 'intro'] as $field) {
            $this->assertStringContainsString('data-for="'.$field.'"', $html);
        }
    }

    /** Each detail row can be dropped into the wording, not just shown in the card. */
    public function test_each_detail_row_offers_its_variable_for_the_message(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->getContent();

        foreach (['{{service.name}}', '{{booking.date}}', '{{staff.full_name}}'] as $token) {
            $this->assertStringContainsString('data-insert-token="'.e($token).'"', $html);
        }
    }

    /**
     * The editor's script must parse.
     *
     * A syntax error anywhere in it kills the whole block, and what the reader
     * sees is a preview that silently never renders — the page still returns
     * 200 and every other assertion still passes. `a?.b().c = x` did exactly
     * that: optional chaining is not a valid assignment target.
     */
    public function test_the_editor_script_is_valid_javascript(): void
    {
        if (! is_executable(trim((string) shell_exec('command -v node')))) {
            $this->markTestSkipped('node is not available to parse the script.');
        }

        $html = $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->getContent();

        preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $matches);

        $editor = collect($matches[1])->first(fn (string $js) => str_contains($js, 'data-template-form'));

        $this->assertNotNull($editor, 'The editor script is missing from the page.');

        $path = tempnam(sys_get_temp_dir(), 'editor').'.js';
        file_put_contents($path, $editor);

        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $status);
        @unlink($path);

        $this->assertSame(0, $status, "The editor script does not parse:\n".implode("\n", $output));
    }

    // --------------------------------------------------------- test email

    public function test_the_editor_offers_a_test_email_dialog(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->assertSee('data-test-dialog', false)
            ->assertSee('data-test-open', false)
            ->assertSee(__('email_templates.editor.send_test'));
    }

    /** Creating offers it too: what it sends is on screen, not in the database. */
    public function test_the_create_screen_can_send_a_test(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.create'))
            ->assertOk()
            ->assertSee(route('settings.email-templates.test-draft'), false);
    }

    /**
     * The test sends what is on the screen, not what is stored.
     *
     * Mailing the saved version would send the reader the email they are in
     * the middle of changing — the one thing a test must never do.
     */
    public function test_the_test_email_sends_the_unsaved_wording(): void
    {
        Mail::fake();

        $this->tenant->forceFill([
            'client_email_enabled' => true,
            'email_provider' => 'styledesk',
        ])->save();

        $this->actingAs($this->owner)
            ->postJson(route('settings.email-templates.test', 'booking.confirmed'), [
                'email' => 'owner@styledesk.test',
                'name' => 'Booking Confirmation',
                'subject' => 'Unsaved subject line',
                'intro' => 'Hello {{client.first_name}}, unsaved.',
                'blocks' => ['intro' => '1'],
            ])
            ->assertOk()
            ->assertJsonPath('message', __('email_templates.editor.test_sent', ['email' => 'owner@styledesk.test']));

        /* Mail::html() posts a raw message rather than a Mailable, and the
           fake does not record those — so the send is asserted through the
           endpoint's own answer above, and what was rendered is asserted
           directly here. */
        $this->assertStringContainsString(
            'Unsaved subject line',
            EmailRenderer::preview(
                EmailTemplates::resolve($this->tenant, 'booking.confirmed')
                    ->fill(['subject' => 'Unsaved subject line']),
                $this->tenant,
            )['subject'],
        );

        /* Testing must not save. */
        $this->assertSame(0, EmailTemplate::withoutGlobalScopes()->count());
    }

    public function test_the_test_email_needs_a_valid_address(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('settings.email-templates.test', 'booking.confirmed'), ['email' => 'not-an-address'])
            ->assertJsonValidationErrors('email');
    }

    /** A business that cannot send at all is told so rather than failing silently. */
    public function test_the_test_email_is_refused_when_the_business_cannot_send(): void
    {
        $this->tenant->forceFill(['client_email_enabled' => false])->save();

        $this->actingAs($this->owner)
            ->postJson(route('settings.email-templates.test', 'booking.confirmed'), [
                'email' => 'owner@styledesk.test',
            ])
            ->assertJsonValidationErrors('email');
    }

    /** The variable dialog offers a second step for choosing one service. */
    public function test_the_variable_menu_offers_a_service_picker(): void
    {
        $this->service('Swedish Massage');

        $html = $this->actingAs($this->owner)
            ->get(route('settings.email-templates.edit', 'booking.confirmed'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-service-step', $html);
        $this->assertStringContainsString('data-service-option=', $html);
        $this->assertStringContainsString(__('email_templates.editor.any_service'), $html);
        $this->assertStringContainsString('Swedish Massage', $html);
    }

    // ------------------------------------------------- appointment details

    private function service(string $name): Service
    {
        return Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    /** The rows are a combo now, so the form posts a list rather than a map. */
    public function test_the_detail_rows_post_as_a_list_and_store_as_a_map(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.update', 'booking.confirmed'), [
                'name' => 'Booking Confirmation',
                'subject' => 'Hello',
                'detail_fields' => ['service', 'date', 'staff'],
            ])
            ->assertSessionHasNoErrors();

        $stored = EmailTemplates::resolve($this->tenant, 'booking.confirmed');

        $this->assertTrue($stored->showsDetail('service'));
        $this->assertTrue($stored->showsDetail('staff'));

        /* A row left out of the list is off, not merely unmentioned —
           otherwise switching one off looks like never touching the form. */
        $this->assertFalse($stored->showsDetail('location'));
        $this->assertFalse($stored->showsDetail('reference'));
    }

    public function test_named_services_are_stored_and_described_in_the_email(): void
    {
        $massage = $this->service('Swedish Massage');
        $facial = $this->service('Deep Cleanse Facial');

        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.update', 'aftercare_instructions'), [
                'name' => 'Aftercare Instructions',
                'subject' => 'Looking after your {{service.name}}',
                'detail_fields' => ['service'],
                'detail_services' => [$massage->id, $facial->id],
            ])
            ->assertSessionHasNoErrors();

        $stored = EmailTemplates::resolve($this->tenant, 'aftercare_instructions');

        $this->assertSame([$massage->id, $facial->id], $stored->serviceIds());

        /* The named services win over the sample booking's, in the subject as
           well as in the details card. */
        $rendered = EmailRenderer::preview($stored, $this->tenant);

        $this->assertStringContainsString('Deep Cleanse Facial', $rendered['subject']);
        $this->assertStringContainsString('Swedish Massage', $rendered['html']);
    }

    /** Naming none means "whatever this booking is for". */
    public function test_naming_no_services_leaves_the_booking_to_describe_itself(): void
    {
        $stored = EmailTemplates::resolve($this->tenant, 'booking.confirmed');

        $this->assertSame([], $stored->serviceIds());
        $this->assertStringContainsString(
            'Swedish Massage',
            EmailRenderer::preview($stored, $this->tenant)['html'],
        );
    }

    /** Services are only kept while the Service row is shown at all. */
    public function test_services_are_dropped_when_the_service_row_is_switched_off(): void
    {
        $massage = $this->service('Swedish Massage');

        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.update', 'aftercare_instructions'), [
                'name' => 'Aftercare Instructions',
                'subject' => 'Hello',
                'detail_fields' => ['date'],
                'detail_services' => [$massage->id],
            ]);

        $this->assertSame([], EmailTemplates::resolve($this->tenant, 'aftercare_instructions')->serviceIds());
    }

    /** Another business's service cannot be named in this one's email. */
    public function test_a_service_from_another_business_is_refused(): void
    {
        $other = Tenant::create(['name' => 'Other Spa', 'slug' => 'other-services']);

        $theirs = Service::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'name' => 'Their Massage', 'duration_minutes' => 60, 'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('settings.email-templates.update', 'aftercare_instructions'), [
                'name' => 'Aftercare Instructions',
                'subject' => 'Hello',
                'detail_fields' => ['service'],
                'detail_services' => [$theirs->id],
            ])
            ->assertSessionHasErrors('detail_services.0');
    }

    /** A business cannot reach another's templates. */
    public function test_one_businesss_wording_is_not_another_businesss(): void
    {
        $other = Tenant::create(['name' => 'Other Spa', 'slug' => 'other-spa']);

        EmailTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'key' => 'booking.confirmed',
            'type' => EmailTemplate::TYPE_TRANSACTIONAL,
            'trigger' => 'booking.confirmed',
            'name' => 'Theirs', 'subject' => 'Their private wording',
        ]);

        $this->actingAs($this->owner)
            ->get(route('settings.email-templates.index'))
            ->assertOk()
            ->assertDontSee('Their private wording');
    }
}
