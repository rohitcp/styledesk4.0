<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Services: the work a business sells.
 *
 * Two rules carry the module. A service is never deleted, because it names
 * appointments that already happened; and the time a booking consumes is not
 * the time the client is in the chair, because processing and cleanup take
 * the room without taking the stylist.
 */
class ServicesTest extends TestCase
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

    private function service(array $attributes = []): Service
    {
        return Service::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut and finish',
            'duration_minutes' => 60,
        ]);
    }

    private function category(string $name = 'Bespoke work'): ServiceCategory
    {
        return ServiceCategory::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name,
            'status' => ServiceCategory::STATUS_ACTIVE,
            'display_order' => 1,
        ]);
    }

    private function resource(string $name = 'Massage Room 1'): Resource
    {
        return Resource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name,
            'capacity' => 1,
        ]);
    }

    private function location(string $name = 'Downtown'): Location
    {
        return $this->tenant->locations()->create([
            'name' => $name, 'address_line1' => '1 High Street', 'city' => 'Leeds',
            'postal_code' => 'LS1 1AA', 'country' => 'GB', 'timezone' => 'Europe/London',
        ]);
    }

    // ------------------------------------------------------------- the page

    /**
     * The page mounts the shared grid; the rows come from their own endpoint.
     *
     * Two assertions rather than one because they are two things: the screen
     * has to mount a grid pointed at the right URL, and that URL has to
     * return this business's services.
     */
    public function test_the_listing_mounts_the_shared_grid(): void
    {
        $this->service();

        $this->actingAs($this->owner)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('data-grid', false)
            ->assertSee(route('services.data'), false);
    }

    /** An empty module explains itself rather than offering filters over nothing. */
    public function test_a_business_with_no_services_sees_the_empty_state(): void
    {
        $this->actingAs($this->owner)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee(__('services.none_yet'))
            ->assertDontSee(__('services.search_placeholder'));
    }

    /**
     * A row carries everything its column needs, already worded.
     *
     * The durations, prices and counts are phrases in the reader's language,
     * so they are built here rather than in the browser — and a row that
     * lists four stylists by name is a row twice the height of every other
     * one, which is why several become a count.
     */
    public function test_a_row_carries_its_columns_already_worded(): void
    {
        $location = $this->location();
        $category = $this->category('Colour');

        $staff = collect(['Ada', 'Grace'])->map(fn (string $name) => Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => $name, 'last_name' => 'Stylist',
            'email' => mb_strtolower($name).'@styledesk.test', 'role' => 'stylist',
        ]));

        $service = $this->service([
            'name' => 'Balayage',
            'service_category_id' => $category->id,
            'duration_minutes' => 90,
            'requires_resource' => true,
            'online_booking_enabled' => true,
        ]);

        $service->staff()->sync($staff->pluck('id'));
        $service->locations()->sync([$location->id]);
        $service->syncPrices([Currencies::primaryFor($this->tenant) => '120.00']);

        $row = $this->actingAs($this->owner)
            ->getJson(route('services.data'))
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Balayage', $row['name']);
        $this->assertSame('Colour', $row['category']);
        $this->assertSame('1 h 30 min', $row['duration']);
        $this->assertStringContainsString('120.00', $row['price']);
        /* Two stylists, so a count rather than two names. */
        $this->assertSame(trans_choice('services.staff_count', 2), $row['staff']);
        /* One location, so its name. */
        $this->assertSame('Downtown', $row['location']);
        $this->assertSame(__('services.resource_required'), $row['resource']);
        $this->assertSame(__('services.status.online_enabled'), $row['online']);
        $this->assertSame(__('services.status.active'), $row['status']);
    }

    /**
     * A service nobody may perform is offered by anyone, and one with no
     * locations is offered everywhere.
     *
     * Empty is not nothing here: a blank cell would read as missing data on
     * the one setting a single-site business never opens.
     */
    public function test_an_unassigned_service_reads_as_anyone_and_everywhere(): void
    {
        $this->service();

        $row = $this->actingAs($this->owner)
            ->getJson(route('services.data'))
            ->assertOk()
            ->json('data.0');

        $this->assertSame(__('services.anyone'), $row['staff']);
        $this->assertSame(__('services.everywhere'), $row['location']);
    }

    public function test_the_rows_can_be_searched(): void
    {
        $this->service(['name' => 'Beard trim']);
        $this->service(['name' => 'Blow dry']);

        $names = $this->actingAs($this->owner)
            ->getJson(route('services.data', ['search' => 'beard']))
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Beard trim'], $names);
    }

    /**
     * A service with no locations is offered at all of them.
     *
     * The filter has to agree, or a single-site business that never opened
     * the locations field would filter its own list down to nothing.
     */
    public function test_filtering_by_location_keeps_services_offered_everywhere(): void
    {
        $downtown = $this->location();
        $northside = $this->location('Northside');

        $this->service(['name' => 'Everywhere cut']);
        $this->service(['name' => 'Northside only'])->locations()->sync([$northside->id]);

        $names = $this->actingAs($this->owner)
            ->getJson(route('services.data', ['location' => [$downtown->id]]))
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Everywhere cut'], $names);
    }

    public function test_the_rows_can_be_filtered_to_services_booked_in_person(): void
    {
        $this->service(['name' => 'Online cut', 'online_booking_enabled' => true]);
        $this->service(['name' => 'Consultation only', 'online_booking_enabled' => false]);

        $names = $this->actingAs($this->owner)
            ->getJson(route('services.data', ['booking' => 'internal']))
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Consultation only'], $names);
    }

    public function test_the_rows_can_be_filtered_by_staff_member(): void
    {
        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Ada', 'last_name' => 'Stylist',
            'email' => 'ada@styledesk.test', 'role' => 'stylist',
        ]);

        $this->service(['name' => 'Ada only'])->staff()->sync([$staff->id]);
        $this->service(['name' => 'Anyone']);

        $names = $this->actingAs($this->owner)
            ->getJson(route('services.data', ['staff' => [$staff->id]]))
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Ada only'], $names);
    }

    /**
     * The row's actions travel with the row.
     *
     * Which entries a reader may see is a permission question the server has
     * already answered; a grid that assembled the menu itself would be a
     * second place for that rule to live.
     */
    public function test_a_row_carries_its_own_actions_menu(): void
    {
        $service = $this->service();

        $menu = $this->actingAs($this->owner)
            ->getJson(route('services.data'))
            ->assertOk()
            ->json('data.0.menu');

        $labels = collect($menu)->pluck('label')->filter()->values()->all();

        $this->assertSame([
            __('services.view'),
            __('common.edit'),
            __('services.duplicate'),
            __('services.retire'),
        ], $labels);

        /* Anything that changes something posts. A menu entry that mutated
           through a link is a link a browser may follow while prefetching. */
        $retire = collect($menu)->firstWhere('label', __('services.retire'));
        $this->assertSame('PATCH', $retire['method']);
        $this->assertSame(route('services.toggle', $service), $retire['url']);
        $this->assertNotNull($retire['confirm']);
    }

    /** The rows a business may not see are not in anyone else's grid. */
    public function test_the_grid_shows_only_this_businesss_services(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other4', 'business_email' => 'hi@other4.test',
        ]);

        Service::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'name' => 'Their cut', 'duration_minutes' => 30,
        ]);

        $this->service(['name' => 'Ours']);

        $names = $this->actingAs($this->owner)
            ->getJson(route('services.data'))
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Ours'], $names);
    }

    // --------------------------------------------------------- the form page

    /**
     * Adding is a page, not a dialog.
     *
     * The listing links to it rather than carrying the form: five sections of
     * fields inside a modal is a scroll within a scroll, and a modal cannot
     * be linked to or come back from a failed validation intact.
     */
    public function test_the_listing_links_to_the_add_page_rather_than_opening_a_dialog(): void
    {
        $this->service();

        $this->actingAs($this->owner)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee(route('services.create'), false)
            ->assertDontSee('data-service-add', false);
    }

    public function test_the_add_page_renders_its_form(): void
    {
        $this->actingAs($this->owner)
            ->get(route('services.create'))
            ->assertOk()
            ->assertSee(__('services.add_title'))
            ->assertSee(__('services.timings'))
            ->assertSee(__('services.online_booking'));
    }

    /**
     * The edit page opens with the service already in it.
     *
     * The combos are rendered with their selection by the server, which is
     * the whole reason a page beats the dialog this replaced: the dialog had
     * to reach into a Vue island from a script to do the same job.
     */
    public function test_the_edit_page_opens_with_the_service_in_it(): void
    {
        $location = $this->location();
        $service = $this->service(['name' => 'Balayage', 'duration_minutes' => 90, 'processing_minutes' => 30]);
        $service->locations()->sync([$location->id]);
        $service->syncPrices([Currencies::primaryFor($this->tenant) => '120.00']);

        $response = $this->actingAs($this->owner)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertSee(__('services.edit_title'));

        $response->assertSee('value="Balayage"', false)
            ->assertSee('value="90"', false)
            ->assertSee('value="30"', false)
            ->assertSee('value="120.00"', false);

        /* The location combo is handed its selection as a prop, so the
           chosen id has to reach the page inside the island's props. */
        $this->assertStringContainsString(
            '"modelValue":["'.$location->id.'"]',
            $response->getContent(),
        );
    }

    /**
     * A refused submission comes back with what was typed.
     *
     * The dialog could not do this — `back()` reopened the page with the
     * modal shut and the fields empty. It is the reason this is a page.
     */
    public function test_a_refused_submission_returns_to_the_form_with_the_input_intact(): void
    {
        $response = $this->actingAs($this->owner)
            ->from(route('services.create'))
            ->post(route('services.store'), [
                'name' => '',
                'duration_minutes' => 75,
                'processing_minutes' => 20,
            ]);

        $response->assertRedirect(route('services.create'))
            ->assertSessionHasErrors('name');

        $this->actingAs($this->owner)
            ->get(route('services.create'))
            ->assertOk()
            ->assertSee('value="75"', false)
            ->assertSee('value="20"', false);
    }

    /** Saving lands on the listing, where the new service can be seen. */
    public function test_saving_returns_to_the_listing(): void
    {
        $this->actingAs($this->owner)
            ->post(route('services.store'), ['name' => 'Beard trim', 'duration_minutes' => 20])
            ->assertRedirect(route('services.index'));
    }

    /** The edit page of another business's service is not found, not forbidden. */
    public function test_another_business_service_cannot_be_opened_for_editing(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other3', 'business_email' => 'hi@other3.test',
        ]);

        $theirs = Service::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'name' => 'Their cut', 'duration_minutes' => 30,
        ]);

        $this->actingAs($this->owner)
            ->get(route('services.edit', $theirs))
            ->assertNotFound();
    }

    // --------------------------------------------------------- adding

    public function test_a_service_is_created_with_its_timings_staff_and_locations(): void
    {
        $category = $this->category();
        $location = $this->location();
        $room = $this->resource();

        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'sam@styledesk.test', 'role' => 'stylist',
        ]);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Full head colour',
                'service_category_id' => $category->id,
                'duration_minutes' => 45,
                'preparation_minutes' => 5,
                'processing_minutes' => 30,
                'cleanup_minutes' => 10,
                'buffer_minutes' => 5,
                'online_booking_enabled' => 1,
                'requires_resource' => 1,
                'resources' => [$room->id],
                'staff' => [$staff->id],
                'locations' => [$location->id],
            ])
            ->assertRedirect();

        $service = Service::withoutGlobalScopes()->where('name', 'Full head colour')->firstOrFail();

        $this->assertSame(45, $service->duration_minutes);
        $this->assertSame(30, $service->processing_minutes);
        $this->assertTrue($service->requires_resource);
        $this->assertSame([$staff->id], $service->staff->pluck('id')->all());
        $this->assertSame([$location->id], $service->locations->pluck('id')->all());
    }

    /**
     * The diary is charged for the whole appointment, not the haircut.
     *
     * 45 minutes of colour with 30 developing either side of 5 and 10 is 95
     * minutes of the room. A calendar built on duration_minutes alone would
     * hand the chair to somebody else while the client was still in it.
     */
    public function test_the_booked_time_is_every_period_and_not_just_the_duration(): void
    {
        $service = $this->service([
            'duration_minutes' => 45, 'preparation_minutes' => 5,
            'processing_minutes' => 30, 'cleanup_minutes' => 10, 'buffer_minutes' => 5,
        ]);

        $this->assertSame(95, $service->bookedMinutes());
    }

    public function test_a_service_needs_a_name_and_a_duration(): void
    {
        $this->actingAs($this->owner)
            ->post(route('services.store'), ['name' => '', 'duration_minutes' => 0])
            ->assertSessionHasErrors(['name', 'duration_minutes']);
    }

    /** A mistyped duration is refused at the form, not drawn across a week. */
    public function test_a_duration_beyond_the_cap_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Marathon',
                'duration_minutes' => config('service_options.max_duration_minutes') + 1,
            ])
            ->assertSessionHasErrors('duration_minutes');
    }

    /**
     * A colour off the palette is allowed; a value that is not a colour is
     * not.
     *
     * The form's last card opens a colour picker, so the eight swatches are
     * a shortlist rather than the whole set. The check that remains is the
     * one that matters: the value reaches a style attribute.
     */
    public function test_a_custom_colour_is_kept_and_a_malformed_one_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Neon', 'duration_minutes' => 30, 'color' => '#ff00ff',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('#ff00ff', Service::withoutGlobalScopes()->where('name', 'Neon')->firstOrFail()->color);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Broken', 'duration_minutes' => 30, 'color' => 'red; background:url(x)',
            ])
            ->assertSessionHasErrors('color');
    }

    /**
     * The custom card wears the colour it was saved with.
     *
     * Without it a service coloured from the picker would reopen with the
     * card empty and the first palette swatch selected — silently changing
     * the colour the calendar draws.
     */
    public function test_the_custom_card_reopens_holding_a_custom_colour(): void
    {
        $service = $this->service(['color' => '#ff00ff']);

        $this->actingAs($this->owner)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertSee('value="#ff00ff" checked', false)
            ->assertSee('--service-color: #ff00ff', false);
    }

    // --------------------------------------------------------- the view page

    /**
     * A row opens the service, not its form.
     *
     * Reading what a service is costs nothing; landing on an editable form is
     * one stray keystroke away from changing a price.
     */
    public function test_a_row_opens_the_view_page(): void
    {
        $service = $this->service();

        $row = $this->actingAs($this->owner)
            ->getJson(route('services.data'))
            ->assertOk()
            ->json('data.0');

        $this->assertSame(route('services.show', $service), $row['url']);
    }

    public function test_the_view_page_shows_the_service_read_only(): void
    {
        $location = $this->location();
        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Ada', 'last_name' => 'Stylist',
            'email' => 'ada@styledesk.test', 'role' => 'stylist',
        ]);

        $service = $this->service([
            'name' => 'Balayage', 'duration_minutes' => 90, 'processing_minutes' => 30,
        ]);
        $service->staff()->sync([$staff->id]);
        $service->locations()->sync([$location->id]);
        $service->syncPrices([Currencies::primaryFor($this->tenant) => '120.00']);

        $this->actingAs($this->owner)
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('Balayage')
            ->assertSee('1 h 30 min')
            ->assertSee('120.00')
            ->assertSee('Ada Stylist')
            ->assertSee('Downtown')
            /* Back and Edit, the pair every other detail page carries. */
            ->assertSee(route('services.index'), false)
            ->assertSee(route('services.edit', $service), false)
            /* Read-only: the view page shows values, never fields. */
            ->assertDontSee('name="duration_minutes"', false);
    }

    /** A retired service says so, and says what it still does. */
    public function test_the_view_page_explains_a_retired_service(): void
    {
        $service = $this->service(['is_active' => false]);

        $this->actingAs($this->owner)
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertSee(__('services.status.inactive'))
            ->assertSee(__('services.retired_notice'));
    }

    /** Another business's service is not found, not forbidden. */
    public function test_another_business_service_cannot_be_viewed(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other5', 'business_email' => 'hi@other5.test',
        ]);

        $theirs = Service::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'name' => 'Their cut', 'duration_minutes' => 30,
        ]);

        $this->actingAs($this->owner)
            ->get(route('services.show', $theirs))
            ->assertNotFound();
    }

    /**
     * A deposit belongs to the price it is a deposit on.
     *
     * 20% of one price and 20% of another are different amounts, so the
     * setting cannot live on the service — and switching it on for one price
     * must leave every other price alone.
     */
    public function test_each_price_carries_its_own_deposit(): void
    {
        $currency = Currencies::primaryFor($this->tenant);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Balayage', 'duration_minutes' => 90,
                'price' => [$currency => '120.00'],
                'deposit' => [$currency => ['required' => 1, 'type' => 'percent', 'value' => '20']],
            ])
            ->assertSessionHasNoErrors();

        $price = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail()
            ->prices->firstWhere('currency_code', $currency);

        $this->assertTrue($price->deposit_required);
        $this->assertSame('percent', $price->deposit_type);
        $this->assertSame(20, $price->deposit_value);
        $this->assertSame('20%', $price->depositLabel());
    }

    /** A fixed deposit is stored in minor units, like the price beside it. */
    public function test_a_fixed_deposit_is_stored_in_minor_units(): void
    {
        $currency = Currencies::primaryFor($this->tenant);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Colour', 'duration_minutes' => 60,
                'price' => [$currency => '120.00'],
                'deposit' => [$currency => ['required' => 1, 'type' => 'fixed', 'value' => '40.00']],
            ])
            ->assertSessionHasNoErrors();

        $price = Service::withoutGlobalScopes()->where('name', 'Colour')->firstOrFail()
            ->prices->firstWhere('currency_code', $currency);

        $this->assertSame(4000, $price->deposit_value);
        $this->assertSame('40.00', $price->depositValue());
    }

    /**
     * The service-level flag is a summary of its prices rather than a setting
     * of its own — the listing shows one chip.
     */
    public function test_the_service_flag_follows_whether_any_price_takes_a_deposit(): void
    {
        $currency = Currencies::primaryFor($this->tenant);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Trim', 'duration_minutes' => 20,
                'price' => [$currency => '20.00'],
                'deposit' => [$currency => ['required' => 0]],
            ])
            ->assertSessionHasNoErrors();

        $service = Service::withoutGlobalScopes()->where('name', 'Trim')->firstOrFail();
        $this->assertFalse($service->deposit_required);

        $this->actingAs($this->owner)
            ->patch(route('services.update', $service), [
                'name' => 'Trim', 'duration_minutes' => 20,
                'price' => [$currency => '20.00'],
                'deposit' => [$currency => ['required' => 1, 'type' => 'fixed', 'value' => '5.00']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($service->fresh()->deposit_required);
    }

    /** A deposit switched on has to say how much it is. */
    public function test_a_deposit_without_a_value_is_refused(): void
    {
        $currency = Currencies::primaryFor($this->tenant);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Vague', 'duration_minutes' => 30,
                'price' => [$currency => '50.00'],
                'deposit' => [$currency => ['required' => 1, 'type' => 'percent', 'value' => '']],
            ])
            ->assertSessionHasErrors('deposit');
    }

    /** A deposit larger than the whole price is a typo, not a policy. */
    public function test_a_deposit_percentage_over_one_hundred_is_refused(): void
    {
        $currency = Currencies::primaryFor($this->tenant);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Greedy', 'duration_minutes' => 30,
                'price' => [$currency => '50.00'],
                'deposit' => [$currency => ['required' => 1, 'type' => 'percent', 'value' => '150']],
            ])
            ->assertSessionHasErrors('deposit');
    }

    // --------------------------------------------------------- editing

    public function test_a_service_can_be_edited(): void
    {
        $service = $this->service(['name' => 'Old name']);

        $this->actingAs($this->owner)
            ->patch(route('services.update', $service), [
                'name' => 'New name', 'duration_minutes' => 90,
            ])
            ->assertRedirect();

        $this->assertSame('New name', $service->fresh()->name);
        $this->assertSame(90, $service->fresh()->duration_minutes);
    }

    /**
     * Retired, never deleted: the service names appointments that already
     * happened, and removing the row would rewrite them.
     */
    public function test_a_service_is_retired_rather_than_deleted(): void
    {
        $service = $this->service();

        $this->actingAs($this->owner)
            ->patch(route('services.toggle', $service))
            ->assertRedirect();

        $this->assertFalse($service->fresh()->is_active);
        $this->assertNotNull(Service::withoutGlobalScopes()->find($service->id));
    }

    /**
     * A duplicate starts retired.
     *
     * One that were bookable the instant it appeared would be bookable at
     * the original's price under the original's name until somebody got
     * round to changing it.
     */
    public function test_a_duplicated_service_copies_the_original_but_starts_retired(): void
    {
        $location = $this->location();
        $service = $this->service(['name' => 'Signature facial', 'duration_minutes' => 50]);
        $service->locations()->sync([$location->id]);

        $response = $this->actingAs($this->owner)
            ->post(route('services.duplicate', $service))
            ->assertSessionHas('duplicated_from', 'Signature facial');

        $copy = Service::withoutGlobalScopes()
            ->where('name', __('services.copy_of', ['name' => 'Signature facial']))
            ->firstOrFail();

        /* Straight into the copy's own form: a duplicate is the start of an
           edit, and nobody copies a service to leave it as it was. */
        $response->assertRedirect(route('services.edit', $copy));

        $this->assertFalse($copy->is_active);
        $this->assertSame(50, $copy->duration_minutes);
        $this->assertSame([$location->id], $copy->locations->pluck('id')->all());
        $this->assertTrue($service->fresh()->is_active);
    }

    /**
     * Delete removes it from every list without rewriting history.
     *
     * Soft, always: a service names the appointments it was booked for, and
     * a hard delete would rewrite last year's takings.
     */
    public function test_a_service_can_be_deleted_without_losing_its_history(): void
    {
        $service = $this->service(['name' => 'Old cut']);

        $this->actingAs($this->owner)
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('services.index'));

        $this->assertNull(Service::query()->find($service->id));
        $this->assertNotNull(Service::withoutGlobalScopes()->find($service->id)->deleted_at);
    }

    /** The edit page offers it, behind the shared confirmation. */
    public function test_the_edit_page_offers_delete_behind_a_confirmation(): void
    {
        $service = $this->service(['name' => 'Old cut']);

        $this->actingAs($this->owner)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertSee(__('services.delete'))
            ->assertSee('data-confirm-title="'.e(__('services.delete_title')).'"', false)
            ->assertSee(route('services.destroy', $service), false);
    }

    // --------------------------------------------------------- isolation

    /** A service belonging to another business is not found, not forbidden. */
    public function test_another_business_service_is_not_reachable(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $theirs = Service::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'name' => 'Their cut', 'duration_minutes' => 30,
        ]);

        $this->actingAs($this->owner)
            ->patch(route('services.update', $theirs), ['name' => 'Mine now', 'duration_minutes' => 30])
            ->assertNotFound();

        $this->assertSame('Their cut', $theirs->fresh()->name);
    }

    /**
     * Staff are checked against this business, not merely absent from the
     * dropdown: the dropdown is not where the rule lives.
     */
    public function test_a_staff_member_from_another_business_cannot_be_assigned(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other2', 'business_email' => 'hi@other2.test',
        ]);

        $theirStaff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'first_name' => 'Not', 'last_name' => 'Ours',
            'email' => 'not@ours.test', 'role' => 'stylist',
        ]);

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Cut', 'duration_minutes' => 30, 'staff' => [$theirStaff->id],
            ])
            ->assertSessionHasErrors('staff.0');
    }

    // --------------------------------------------------- resource mapping

    /**
     * The mapping is to rows, not to the word "room".
     *
     * Availability is a question about Massage Room 2 at three on Tuesday,
     * and only an actual resource can answer it — which is why what is stored
     * is a set of ids rather than a kind.
     */
    public function test_a_service_is_mapped_to_the_resources_that_can_perform_it(): void
    {
        $first = $this->resource('Massage Room 1');
        $second = $this->resource('Massage Room 2');
        $this->resource('Treatment Room 1');

        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Swedish Massage',
                'duration_minutes' => 60,
                'requires_resource' => 1,
                'resources' => [$first->id, $second->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $service = Service::withoutGlobalScopes()->where('name', 'Swedish Massage')->firstOrFail();

        $this->assertSame(
            [$first->id, $second->id],
            $service->resources->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_requiring_a_resource_without_choosing_one_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from(route('services.create'))
            ->post(route('services.store'), [
                'name' => 'Swedish Massage',
                'duration_minutes' => 60,
                'requires_resource' => 1,
            ])
            ->assertRedirect(route('services.create'))
            ->assertSessionHasErrors(['resources' => __('services.resources_required')]);

        $this->assertDatabaseMissing('services', ['name' => 'Swedish Massage']);
    }

    public function test_a_service_that_needs_no_resource_may_name_none(): void
    {
        $this->actingAs($this->owner)
            ->post(route('services.store'), [
                'name' => 'Consultation',
                'duration_minutes' => 15,
                'requires_resource' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            Service::withoutGlobalScopes()->where('name', 'Consultation')->firstOrFail()->requires_resource,
        );
    }

    /**
     * Another business's room cannot be claimed by naming its id. The list on
     * the form is scoped, so this can only arrive from a hand-made request —
     * which is exactly why the rule is on the server as well.
     */
    public function test_a_resource_belonging_to_another_business_is_refused(): void
    {
        $other = Tenant::create(['name' => 'Elsewhere', 'slug' => 'elsewhere']);
        $theirs = Resource::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(), 'name' => 'Their room', 'capacity' => 1,
        ]);

        $this->actingAs($this->owner)
            ->from(route('services.create'))
            ->post(route('services.store'), [
                'name' => 'Swedish Massage',
                'duration_minutes' => 60,
                'requires_resource' => 1,
                'resources' => [$theirs->id],
            ])
            ->assertSessionHasErrors('resources.0');
    }

    /**
     * The switch going off does not throw the list away: turning it back on
     * must not cost the reader the mapping they built.
     */
    public function test_turning_the_requirement_off_keeps_the_mapping(): void
    {
        $room = $this->resource();
        $service = $this->service(['requires_resource' => true]);
        $service->resources()->sync([$room->id]);

        $this->actingAs($this->owner)
            ->patch(route('services.update', $service), [
                'name' => $service->name,
                'duration_minutes' => 60,
                'requires_resource' => 0,
                'resources' => [$room->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $service->refresh()->load('resources');

        $this->assertFalse($service->requires_resource);
        $this->assertSame([$room->id], $service->resources->pluck('id')->all());
    }

    public function test_the_form_offers_the_resources_and_the_view_page_names_them(): void
    {
        $room = $this->resource('Couples Massage Room');
        $service = $this->service(['requires_resource' => true]);
        $service->resources()->sync([$room->id]);

        $this->actingAs($this->owner)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertSee('Requires a Resource', false)
            ->assertSee('data-resource-requirement', false)
            ->assertSee('Couples Massage Room', false);

        $this->actingAs($this->owner)
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('Couples Massage Room', false);
    }
}
