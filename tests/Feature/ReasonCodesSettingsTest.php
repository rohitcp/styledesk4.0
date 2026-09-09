<?php

namespace Tests\Feature;

use App\Models\ReasonCode;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App Settings → Reasons: why things happened, as lists rather than boxes.
 *
 * The same two rules as every other catalogue in StyleDesk. A reason we
 * supplied may be renamed, reordered and switched off but never deleted,
 * because the March cancellation filed under it still has to say why. A
 * reason the business invented may be deleted, because nothing else ever
 * supplied it.
 */
class ReasonCodesSettingsTest extends TestCase
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

    private function mine(): Builder
    {
        return ReasonCode::withoutGlobalScopes()->where('tenant_id', $this->tenant->getTenantKey());
    }

    private function system(string $type = 'booking-cancellation'): ReasonCode
    {
        return (clone $this->mine())->where('type', $type)->where('is_system', true)->orderBy('display_order')->firstOrFail();
    }

    private function custom(string $name = 'Stylist ran late', string $type = 'booking-cancellation'): ReasonCode
    {
        return ReasonCode::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'type' => $type, 'key' => null, 'name' => $name,
            'is_system' => false, 'is_active' => true, 'display_order' => 99,
        ]);
    }

    private function expectedTotal(): int
    {
        return collect(config('reasons.types'))->sum(fn (array $definition) => count($definition['reasons']));
    }

    // ------------------------------------------------------------- defaults

    /**
     * A salon already knows why bookings fall through; typing two hundred
     * reasons out is not the work anyone signed up for.
     */
    public function test_a_new_business_starts_with_the_whole_library(): void
    {
        $this->assertSame($this->expectedTotal(), (clone $this->mine())->count());
        $this->assertTrue((clone $this->mine())->get()->every(fn (ReasonCode $r) => $r->isSystem()));
    }

    /**
     * Seeding again after a rename adds nothing.
     *
     * Matched on key rather than name, so a business that says it differently
     * does not get a second copy the next time defaults are seeded.
     */
    public function test_seeding_again_after_a_rename_or_a_switch_off_changes_nothing(): void
    {
        $reason = $this->system();
        $reason->forceFill(['name' => 'Changed their mind', 'is_active' => false])->save();

        ReasonCode::seedDefaultsFor($this->tenant);

        $this->assertSame($this->expectedTotal(), (clone $this->mine())->count());
        $this->assertSame('Changed their mind', $reason->fresh()->name);
        $this->assertFalse($reason->fresh()->is_active);
    }

    /** "Other" is not an answer on its own, so choosing it asks for one. */
    public function test_other_is_seeded_asking_for_details(): void
    {
        $other = (clone $this->mine())->where('key', config('reasons.details_key'))->get();

        $this->assertTrue($other->isNotEmpty());
        $this->assertTrue($other->every(fn (ReasonCode $r) => $r->requires_details));
    }

    // ------------------------------------------------------------- the page

    public function test_the_index_lists_every_type(): void
    {
        $response = $this->actingAs($this->owner)->get(route('settings.reasons.index'))->assertOk();

        foreach (array_keys(config('reasons.types')) as $type) {
            $response->assertSee(ReasonCode::typeLabel($type));
        }
    }

    public function test_one_type_lists_its_reasons(): void
    {
        $this->custom();

        $this->actingAs($this->owner)
            ->get(route('settings.reasons.show', 'booking-cancellation'))
            ->assertOk()
            ->assertSee('Stylist ran late')
            ->assertSee($this->system()->name);
    }

    public function test_an_unknown_type_is_not_a_page(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.reasons.show', 'because-i-said-so'))
            ->assertNotFound();
    }

    // ----------------------------------------------------------- the rules

    public function test_a_custom_reason_can_be_added(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.reasons.store', 'booking-cancellation'), [
                'name' => 'Stylist ran late', 'description' => 'Ours, not theirs.',
            ])
            ->assertRedirect();

        $created = (clone $this->mine())->where('name', 'Stylist ran late')->firstOrFail();

        $this->assertFalse($created->isSystem());
        /* No key: a key is what marks a reason as ours, and this one is not. */
        $this->assertNull($created->key);
        $this->assertSame('Ours, not theirs.', $created->description);
    }

    /** Two reasons a reader cannot tell apart is a count split for nothing. */
    public function test_a_duplicate_name_within_a_type_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('settings.reasons.store', 'booking-cancellation'), [
                'name' => $this->system()->name,
            ])
            ->assertSessionHasErrors('name');
    }

    /**
     * The same words can mean different things in different lists — "Other"
     * lives in all nine — so the name is only unique within its own.
     */
    public function test_the_same_name_in_another_type_is_allowed(): void
    {
        $this->custom('Stylist ran late');

        $this->actingAs($this->owner)
            ->post(route('settings.reasons.store', 'no-show'), ['name' => 'Stylist ran late'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, (clone $this->mine())->where('name', 'Stylist ran late')->count());
    }

    /**
     * A system reason is StyleDesk's words for it, not every business's — and
     * the key underneath is what survives the rename.
     */
    public function test_a_system_reason_can_be_renamed_and_keeps_its_key(): void
    {
        $reason = $this->system();
        $key = $reason->key;

        $this->actingAs($this->owner)
            ->patch(route('settings.reasons.update', $reason), ['name' => 'Changed their mind'])
            ->assertRedirect();

        $this->assertSame('Changed their mind', $reason->fresh()->name);
        $this->assertSame($key, $reason->fresh()->key);
        $this->assertTrue($reason->fresh()->isRenamed());
    }

    /** Off is not deletion: what was filed under it keeps saying so. */
    public function test_a_reason_can_be_switched_off_and_on(): void
    {
        $reason = $this->system();

        $this->actingAs($this->owner)->patch(route('settings.reasons.toggle', $reason))->assertRedirect();
        $this->assertFalse($reason->fresh()->is_active);

        $this->actingAs($this->owner)->patch(route('settings.reasons.toggle', $reason))->assertRedirect();
        $this->assertTrue($reason->fresh()->is_active);
    }

    /**
     * A default that can be removed is one the business has no way back to,
     * and a March cancellation pointing at nothing.
     */
    public function test_a_system_reason_cannot_be_deleted(): void
    {
        $reason = $this->system();

        $this->actingAs($this->owner)
            ->delete(route('settings.reasons.destroy', $reason))
            ->assertForbidden();

        $this->assertNotNull($reason->fresh());
    }

    public function test_a_custom_reason_can_be_deleted(): void
    {
        $reason = $this->custom();

        $this->actingAs($this->owner)
            ->delete(route('settings.reasons.destroy', $reason))
            ->assertRedirect();

        $this->assertNull($reason->fresh());
    }

    /** Asking for details is the business's call on their own reasons too. */
    public function test_a_reason_can_be_set_to_ask_for_details(): void
    {
        $reason = $this->custom();

        $this->actingAs($this->owner)
            ->patch(route('settings.reasons.update', $reason), [
                'name' => 'Stylist ran late', 'requires_details' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($reason->fresh()->requires_details);
    }

    /**
     * The whole order at once, rather than a pair of swapped ids: two people
     * dragging at the same time would otherwise leave a list neither of them
     * arranged.
     */
    public function test_the_list_can_be_reordered(): void
    {
        $reasons = (clone $this->mine())->where('type', 'booking-cancellation')->orderBy('display_order')->take(3)->get();
        $reversed = $reasons->pluck('id')->reverse()->values();

        $this->actingAs($this->owner)
            ->post(route('settings.reasons.reorder', 'booking-cancellation'), ['order' => $reversed->all()])
            ->assertRedirect();

        $after = (clone $this->mine())->whereIn('id', $reversed)->orderBy('display_order')->pluck('id');

        $this->assertSame($reversed->all(), $after->all());
    }

    /** Only the switched-on ones are what another screen would offer. */
    public function test_usable_leaves_out_what_was_switched_off(): void
    {
        $reason = $this->system();
        $reason->forceFill(['is_active' => false])->save();

        $usable = ReasonCode::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->ofType('booking-cancellation')->usable()->pluck('id');

        $this->assertNotContains($reason->id, $usable->all());
    }

    /** One business's edits are not another's. */
    public function test_a_business_only_sees_its_own_library(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hello@other.test',
        ]);
        ReasonCode::seedDefaultsFor($other);

        ReasonCode::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'type' => 'booking-cancellation', 'key' => null, 'name' => 'Their own wording',
            'is_system' => false, 'is_active' => true, 'display_order' => 99,
        ]);

        $this->actingAs($this->owner)
            ->get(route('settings.reasons.show', 'booking-cancellation'))
            ->assertOk()
            ->assertDontSee('Their own wording');
    }
}
