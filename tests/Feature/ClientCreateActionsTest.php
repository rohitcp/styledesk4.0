<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two ways of finishing the Add client form.
 *
 * Both create the same record; they differ only in where the reader is left.
 * That difference is the whole of what these tests are about, plus the one
 * thing it must not break — a refused form still coming back with everything
 * that was typed into it.
 */
class ClientCreateActionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio', 'slug' => 'nadia', 'business_email' => 'hello@nadia.test',
        ]);

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

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        /* A null override drops the key rather than sending it empty: the
           buttons this form actually has either send their field or do not
           send it at all, and a key present-but-empty is a third case the
           browser never produces. */
        return array_filter(
            array_merge([
                'first_name' => 'Amelia',
                'last_name' => 'Hart',
                'status' => Client::STATUS_ACTIVE,
                'confirm_duplicate' => 1,
            ], $overrides),
            fn ($value) => $value !== null,
        );
    }

    // ------------------------------------------------------------ the page

    public function test_the_form_offers_both_ways_of_saving_then_cancel(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.create'))
            ->assertOk()
            /* In order: the two saves lead, Cancel follows them. */
            ->assertSeeInOrder([
                __('clients.module.add'),
                __('clients.module.add_another'),
                __('common.cancel'),
            ])
            /* Each save says which it is in its own name and value, which is
               what lets the server tell them apart without JavaScript. */
            ->assertSee('name="after_save" value="show"', false)
            ->assertSee('name="after_save" value="another"', false);
    }

    /**
     * The booking card's three fields share one row.
     *
     * Asserted on the class because that is the whole of the change and the
     * only thing that can undo it: nothing else on the page would fail if the
     * grid quietly went back to two columns and left the status stranded.
     */
    public function test_the_booking_card_puts_its_three_fields_on_one_row(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.create'))
            ->assertOk()
            ->assertSee(__('clients.module.cards.booking'))
            ->assertSee('grid sm:grid-cols-3 gap-x-4 gap-y-4', false)
            ->assertSee(__('clients.defaults.status'));
    }

    // --------------------------------------------------- live validation

    /**
     * The form carries the shared validator's hooks.
     *
     * Asserted on the markup because the markup is the contract: the module
     * finds a form by data-validate-form and a field by data-rules, and a
     * field that lost its rules would simply stop being checked — silently,
     * and only in the browser.
     */
    public function test_the_form_opts_into_the_shared_live_validation(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.create'))
            ->assertOk()
            ->assertSee('data-validate-form', false)
            ->assertSee('data-validation-messages', false)
            /* The same messages the sign-up form validates with. */
            ->assertSee(e(__('common.validation.required')), false)
            /* Required by the business's own field settings, not by the page. */
            ->assertSee('data-rules="required|max:100"', false)
            /* Every message has a box to be written into, addressed to the
               field's id — which is what SD.setError looks for. */
            ->assertSee('data-error-for="first_name"', false);
    }

    public function test_the_email_rows_can_ask_whether_an_address_is_already_used(): void
    {
        $this->actingAs($this->owner)
            ->get(route('clients.create'))
            ->assertOk()
            ->assertSee('data-rules="email|max:255"', false)
            ->assertSee('data-remote-check', false);
    }

    public function test_an_address_already_on_a_client_is_reported_as_taken(): void
    {
        $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amelia', 'email' => 'amelia@example.com',
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('clients.email-in-use', ['value' => 'AMELIA@example.com']))
            ->assertOk()
            /* Case-insensitively: the same inbox is the same person however
               it was typed. */
            ->assertJson(['ok' => false, 'message' => __('clients.module.validation.email_taken')]);
    }

    public function test_an_unused_address_is_reported_as_free(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('clients.email-in-use', ['value' => 'nobody@example.com']))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_the_lookup_says_whether_never_who(): void
    {
        $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amelia', 'last_name' => 'Hart', 'email' => 'amelia@example.com',
        ]);

        /* A form anyone with clients.create can open must not become a way of
           asking the address book who someone is, one guess at a time. */
        $this->actingAs($this->owner)
            ->getJson(route('clients.email-in-use', ['value' => 'amelia@example.com']))
            ->assertOk()
            ->assertDontSee('Amelia')
            ->assertDontSee('Hart');
    }

    public function test_the_lookup_cannot_see_another_businesss_clients(): void
    {
        $other = Tenant::create(['name' => 'Rival Salon', 'slug' => 'rival']);
        $other->clients()->create([
            'client_ref' => Client::nextRef($other->getTenantKey()),
            'first_name' => 'Theirs', 'email' => 'theirs@example.com',
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('clients.email-in-use', ['value' => 'theirs@example.com']))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_the_lookup_is_closed_to_a_signed_out_visitor(): void
    {
        $this->getJson(route('clients.email-in-use', ['value' => 'amelia@example.com']))
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------- add client

    public function test_add_client_lands_on_the_new_client(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload(['after_save' => 'show']))
            ->assertRedirect(route('clients.show', Client::latest('id')->firstOrFail()))
            ->assertSessionHas('toast.message', __('clients.module.created'));
    }

    // --------------------------------------------------- save & add another

    public function test_save_and_add_another_returns_to_an_empty_form(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload(['after_save' => 'another']))
            ->assertRedirect(route('clients.create'))
            ->assertSessionHas('toast.message', __('clients.module.created'));

        $this->assertSame('Amelia', Client::latest('id')->firstOrFail()->first_name);
    }

    public function test_the_fresh_form_holds_nothing_from_the_client_just_saved(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload(['after_save' => 'another']));

        /* Nothing is passed back through withInput, so the page is built from
           nothing at all — which is the only way of clearing a form that
           cannot leave a stray value behind. */
        $this->actingAs($this->owner)
            ->get(route('clients.create'))
            ->assertOk()
            ->assertDontSee('value="Amelia"', false)
            ->assertDontSee('value="Hart"', false);
    }

    public function test_a_second_client_can_be_added_straight_after(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload(['after_save' => 'another']));

        $this->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'first_name' => 'Bea', 'last_name' => 'Okafor', 'after_save' => 'another',
            ]))
            ->assertRedirect(route('clients.create'));

        $this->assertSame(2, Client::count());
        $this->assertEqualsCanonicalizing(
            ['Amelia', 'Bea'],
            Client::pluck('first_name')->all(),
        );
    }

    // ------------------------------------------------------------- refusals

    public function test_a_refused_form_keeps_what_was_typed_and_creates_nothing(): void
    {
        $this->from(route('clients.create'))
            ->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'first_name' => '', 'last_name' => 'Hart', 'after_save' => 'another',
            ]))
            ->assertRedirect(route('clients.create'))
            ->assertSessionHasErrors('first_name')
            /* The form is not cleared by a refusal, whichever button was
               pressed: the reader has to be able to correct one field rather
               than type the lot again. */
            ->assertSessionHasInput('last_name', 'Hart');

        $this->assertSame(0, Client::count());
    }

    // ------------------------------------------------------ duplicate check

    public function test_confirming_a_duplicate_keeps_the_reader_on_the_path_they_chose(): void
    {
        $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amelia', 'last_name' => 'Hart', 'email' => 'amelia@example.com',
        ]);

        /* The warning, asked for from the "add another" button. */
        $this->from(route('clients.create'))
            ->actingAs($this->owner)
            ->post(route('clients.store'), $this->payload([
                'emails' => ['r0' => ['email' => 'amelia@example.com', 'type' => 'personal', 'priority' => 'primary']],
                'confirm_duplicate' => null,
                'after_save' => 'another',
            ]))
            ->assertRedirect(route('clients.create'))
            ->assertSessionHas('duplicates');

        /* Confirming sends no after_save of its own, so the intent flashed
           with the warning is what decides where the reader ends up. */
        $this->actingAs($this->owner)
            ->withSession(['after_save' => 'another'])
            ->post(route('clients.store'), $this->payload([
                'emails' => ['r0' => ['email' => 'amelia@example.com', 'type' => 'personal', 'priority' => 'primary']],
                'confirm_duplicate' => 1,
                'after_save' => null,
            ]))
            ->assertRedirect(route('clients.create'));
    }

    public function test_confirming_a_duplicate_from_the_primary_button_still_opens_the_profile(): void
    {
        $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amelia', 'last_name' => 'Hart', 'email' => 'amelia@example.com',
        ]);

        /* A flashed "another" must not outrank a button that says otherwise:
           pressing Add client after the warning means Add client. */
        $this->actingAs($this->owner)
            ->withSession(['after_save' => 'another'])
            ->post(route('clients.store'), $this->payload([
                'emails' => ['r0' => ['email' => 'amelia@example.com', 'type' => 'personal', 'priority' => 'primary']],
                'confirm_duplicate' => 1,
                'after_save' => 'show',
            ]))
            ->assertRedirect(route('clients.show', Client::latest('id')->firstOrFail()));
    }
}
