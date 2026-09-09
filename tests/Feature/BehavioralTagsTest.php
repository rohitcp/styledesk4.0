<?php

namespace Tests\Feature;

use App\Models\BehavioralTag;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Behavioural tags: StyleDesk's own, not the business's.
 *
 * What matters here is the boundary — a business chooses whether each one is
 * applied, and nothing else. The key is what reporting and the future rule
 * engine join on, so renaming or deleting one has to be impossible rather
 * than merely discouraged.
 */
class BehavioralTagsTest extends TestCase
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

    public function test_a_new_business_gets_the_whole_catalogue(): void
    {
        $this->assertSame(
            count(config('behavioral_tags.tags')),
            $this->tenant->behavioralTags()->count(),
        );

        // Applied unless the business says otherwise.
        $this->assertSame(0, $this->tenant->behavioralTags()->where('is_active', false)->count());
    }

    /** A tag added to the catalogue later reaches businesses that exist. */
    public function test_seeding_again_adds_only_what_is_missing(): void
    {
        $tag = $this->tenant->behavioralTags()->where('tag_key', 'no_show_risk')->firstOrFail();
        $tag->forceFill(['is_active' => false])->save();

        $this->tenant->behavioralTags()->where('tag_key', 'frequent_booker')->delete();

        BehavioralTag::seedDefaultsFor($this->tenant);

        $this->assertSame(count(config('behavioral_tags.tags')), $this->tenant->behavioralTags()->count());
        // The one switched off stays off.
        $this->assertFalse($tag->fresh()->is_active);
    }

    public function test_a_tag_can_be_switched_off_and_on(): void
    {
        $tag = $this->tenant->behavioralTags()->where('tag_key', 'no_show_risk')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('settings.clients.behavioral.toggle', $tag))
            ->assertRedirect();

        $this->assertFalse($tag->fresh()->is_active);

        $this->actingAs($this->owner)
            ->patch(route('settings.clients.behavioral.toggle', $tag));

        $this->assertTrue($tag->fresh()->is_active);
    }

    /** The label, category and rule come from the catalogue, not the row. */
    public function test_a_tag_reads_its_wording_from_the_catalogue(): void
    {
        $tag = $this->tenant->behavioralTags()->where('tag_key', 'no_show_risk')->firstOrFail();

        $this->assertSame('No-show risk', $tag->label());
        $this->assertSame('Attendance', $tag->categoryLabel());
        $this->assertStringContainsString('no-shows', $tag->rule());
    }

    public function test_the_card_lists_the_tags_with_their_rules(): void
    {
        $this->actingAs($this->owner)
            ->get(route('settings.clients.show'))
            ->assertOk()
            ->assertSee('Behavioural tags')
            ->assertSee('Frequent booker')
            ->assertSee('Six or more completed bookings in the last six months.')
            // Grouped by category, in the catalogue's order.
            ->assertSee('Booking frequency');
    }

    /**
     * Only Owner and Admin configure them, like the rest of App Settings.
     */
    public function test_other_roles_cannot_switch_a_tag(): void
    {
        $user = User::create([
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => 'front@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => 'Sam', 'last_name' => 'Person',
            'email' => $user->email, 'role' => 'front-desk',
        ]);

        $tag = $this->tenant->behavioralTags()->where('tag_key', 'no_show_risk')->firstOrFail();

        // App Settings sends anyone who may not configure it back to the
        // dashboard rather than showing them a wall — the same refusal every
        // other settings route gives.
        $this->actingAs($user->fresh())
            ->patch(route('settings.clients.behavioral.toggle', $tag))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($tag->fresh()->is_active);
    }

    public function test_another_businesss_tag_is_out_of_reach(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $theirs = $other->behavioralTags()->where('tag_key', 'no_show_risk')->firstOrFail();

        $this->actingAs($this->owner)
            ->patch(route('settings.clients.behavioral.toggle', $theirs))
            ->assertNotFound();
    }
}
