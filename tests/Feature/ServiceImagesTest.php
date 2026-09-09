<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Service;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A service's default picture and the gallery behind it.
 *
 * Driven through the routes rather than through ServiceImageSync directly,
 * because what is being asserted is the shape of the round trip: pictures are
 * uploaded before the service exists and claimed when it is saved, in both
 * the wizard and the Services module.
 */
class ServiceImagesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private ?Location $location = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

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

    /** Upload a picture the way the page does, and return its id. */
    private function uploadImage(string $name = 'cut.jpg'): int
    {
        $response = $this->actingAs($this->owner)
            ->post('http://styledesk.test/services/images', [
                'image' => UploadedFile::fake()->image($name, 400, 400),
            ]);

        $response->assertOk()->assertJsonStructure(['id', 'url', 'name']);

        return (int) $response->json('id');
    }

    /** Put this business back into the wizard, at step 3. */
    private function wizardTenant(): void
    {
        TenantOnboarding::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->update(['current_step' => 'services', 'completed_at' => null]);

        $this->tenant->syncCurrencies(['USD']);
    }

    /** The one location every saved service has to name. */
    private function location(): Location
    {
        return $this->location ??= Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Main Location', 'address_line1' => '1 Main St', 'city' => 'Leeds',
            'postal_code' => 'LS1 1AA', 'country' => 'GB', 'timezone' => 'Europe/London',
        ]);
    }

    private function service(array $attributes = []): Service
    {
        return Service::withoutGlobalScopes()->create($attributes + [
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Cut and finish',
            'duration_minutes' => 60,
        ]);
    }

    // ----------------------------------------------------------- uploading

    public function test_an_uploaded_picture_is_stored_unattached_until_a_service_claims_it(): void
    {
        $id = $this->uploadImage();

        $file = StoredFile::withoutGlobalScopes()->findOrFail($id);

        $this->assertSame(Service::IMAGE_CATEGORY, $file->category);
        $this->assertSame('public', $file->visibility);
        $this->assertNull($file->entity_id, 'The service does not exist yet, so nothing may be attached.');
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post('http://styledesk.test/services/images', [
                'image' => UploadedFile::fake()->create('prices.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('image');
    }

    public function test_an_image_over_five_megabytes_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post('http://styledesk.test/services/images', [
                'image' => UploadedFile::fake()->image('huge.jpg')->size(5121),
            ])
            ->assertSessionHasErrors('image');
    }

    public function test_a_pending_picture_can_be_discarded_before_it_is_saved(): void
    {
        $id = $this->uploadImage();

        $this->actingAs($this->owner)
            ->delete('http://styledesk.test/services/images/'.$id)
            ->assertOk();

        /* Soft-deleted, not gone: stored_files keeps its history, and
           withoutGlobalScopes drops the soft-delete scope along with the
           tenant one — so the row is still findable and it is deleted_at
           that says what happened to it. */
        $this->assertSoftDeleted('stored_files', ['id' => $id]);
    }

    public function test_a_picture_already_on_a_service_is_not_discarded_by_that_route(): void
    {
        $service = $this->service();
        $id = $this->uploadImage();

        StoredFile::withoutGlobalScopes()->whereKey($id)
            ->update(['entity_type' => 'service', 'entity_id' => (string) $service->id]);

        /* Removing a picture from a saved service is a change to that service,
           so it belongs to the save — not to a fire-and-forget delete that a
           reader who then abandoned the form could not undo. */
        $this->actingAs($this->owner)
            ->delete('http://styledesk.test/services/images/'.$id)
            ->assertStatus(422);

        $this->assertNotSoftDeleted('stored_files', ['id' => $id]);
    }

    // ------------------------------------------------------ services module

    public function test_saving_a_service_attaches_its_pictures_and_marks_the_default(): void
    {
        $first = $this->uploadImage('one.jpg');
        $second = $this->uploadImage('two.jpg');

        $this->actingAs($this->owner)
            ->post('http://styledesk.test/services', [
                'name' => 'Balayage',
                'duration_minutes' => 90,
                'locations' => [$this->location()->id],
                'images' => [$first, $second],
                'default_image_id' => $second,
            ])
            ->assertRedirect(route('services.index'));

        $service = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail();

        $this->assertSame($second, (int) $service->image_file_id);
        $this->assertEqualsCanonicalizing(
            [$first, $second],
            $service->images()->map(fn (StoredFile $f) => (int) $f->id)->all(),
        );
    }

    public function test_the_default_leads_the_gallery(): void
    {
        $first = $this->uploadImage('one.jpg');
        $second = $this->uploadImage('two.jpg');

        $this->actingAs($this->owner)->post('http://styledesk.test/services', [
            'name' => 'Balayage', 'duration_minutes' => 90,
            'locations' => [$this->location()->id],
            'images' => [$first, $second], 'default_image_id' => $second,
        ]);

        $service = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail();

        $this->assertSame($second, (int) $service->orderedImages()->first()->id);
    }

    public function test_removing_a_picture_on_save_deletes_it(): void
    {
        $first = $this->uploadImage('one.jpg');
        $second = $this->uploadImage('two.jpg');

        $this->actingAs($this->owner)->post('http://styledesk.test/services', [
            'name' => 'Balayage', 'duration_minutes' => 90,
            'locations' => [$this->location()->id],
            'images' => [$first, $second], 'default_image_id' => $first,
        ]);

        $service = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail();

        $this->actingAs($this->owner)->patch('http://styledesk.test/services/'.$service->id, [
            'name' => 'Balayage', 'duration_minutes' => 90,
            'locations' => [$this->location()->id],
            'images' => [$second], 'default_image_id' => $second,
        ]);

        $this->assertSoftDeleted('stored_files', ['id' => $first]);
        $this->assertNotSoftDeleted('stored_files', ['id' => $second]);
        $this->assertSame($second, (int) $service->fresh()->image_file_id);
    }

    public function test_a_default_that_was_not_sent_falls_back_to_the_first_picture(): void
    {
        $first = $this->uploadImage('one.jpg');

        $this->actingAs($this->owner)->post('http://styledesk.test/services', [
            'name' => 'Balayage', 'duration_minutes' => 90,
            'locations' => [$this->location()->id],
            'images' => [$first], 'default_image_id' => 999999,
        ]);

        $service = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail();

        $this->assertSame($first, (int) $service->image_file_id);
    }

    public function test_a_service_saved_without_pictures_has_none(): void
    {
        $this->actingAs($this->owner)->post('http://styledesk.test/services', [
            'name' => 'Blow dry', 'duration_minutes' => 30,
            'locations' => [$this->location()->id],
        ])->assertRedirect(route('services.index'));

        $service = Service::withoutGlobalScopes()->where('name', 'Blow dry')->firstOrFail();

        $this->assertNull($service->image_file_id);
        $this->assertCount(0, $service->images());
    }

    public function test_a_duplicate_does_not_inherit_the_original_picture(): void
    {
        $id = $this->uploadImage();

        $this->actingAs($this->owner)->post('http://styledesk.test/services', [
            'name' => 'Balayage', 'duration_minutes' => 90,
            'locations' => [$this->location()->id],
            'images' => [$id], 'default_image_id' => $id,
        ]);

        $original = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail();

        $this->actingAs($this->owner)->post('http://styledesk.test/services/'.$original->id.'/duplicate');

        $copy = Service::withoutGlobalScopes()->where('name', '!=', 'Balayage')->latest('id')->firstOrFail();

        /* The pictures point at the original, so a copied image_file_id would
           show a picture the copy's own gallery does not contain. */
        $this->assertNull($copy->image_file_id);
        $this->assertSame($id, (int) $original->fresh()->image_file_id);
    }

    // ---------------------------------------------------- onboarding step 3

    /**
     * The wizard rewrites the whole price list on every save — services are
     * deleted and recreated — so a picture has to survive being re-pointed at
     * a service row that did not exist a moment ago.
     */
    public function test_the_wizard_attaches_pictures_to_the_services_it_creates(): void
    {
        $this->wizardTenant();
        $id = $this->uploadImage();

        $this->actingAs($this->owner->fresh())
            ->post('http://styledesk.test/onboarding/services', [
                'services' => [
                    ['name' => 'Cut', 'duration_minutes' => 45, 'images' => [$id], 'default_image_id' => $id],
                ],
            ])
            ->assertRedirect(route('onboarding.team'));

        $service = Service::withoutGlobalScopes()->where('name', 'Cut')->firstOrFail();

        $this->assertSame($id, (int) $service->image_file_id);
        $this->assertSame((string) $service->id, (string) StoredFile::withoutGlobalScopes()->find($id)->entity_id);
    }

    public function test_a_picture_survives_the_step_being_saved_a_second_time(): void
    {
        $this->wizardTenant();
        $id = $this->uploadImage();

        $payload = [
            'services' => [
                ['name' => 'Cut', 'duration_minutes' => 45, 'images' => [$id], 'default_image_id' => $id],
            ],
        ];

        $this->actingAs($this->owner->fresh())->post('http://styledesk.test/onboarding/services', $payload);

        /* Back to the step and Continue again, which is what a reader does
           after using Back. The service row is new; the picture is not. */
        $this->actingAs($this->owner->fresh())->post('http://styledesk.test/onboarding/services', $payload);

        $service = Service::withoutGlobalScopes()->where('name', 'Cut')->latest('id')->firstOrFail();

        $this->assertNotSoftDeleted('stored_files', ['id' => $id]);
        $this->assertSame($id, (int) $service->image_file_id);
        $this->assertSame((string) $service->id, (string) StoredFile::withoutGlobalScopes()->find($id)->entity_id);
    }

    public function test_a_picture_dropped_from_the_step_is_collected(): void
    {
        $this->wizardTenant();
        $kept = $this->uploadImage('kept.jpg');
        $dropped = $this->uploadImage('dropped.jpg');

        $this->actingAs($this->owner->fresh())->post('http://styledesk.test/onboarding/services', [
            'services' => [
                ['name' => 'Cut', 'duration_minutes' => 45,
                    'images' => [$kept, $dropped], 'default_image_id' => $kept],
            ],
        ]);

        $this->actingAs($this->owner->fresh())->post('http://styledesk.test/onboarding/services', [
            'services' => [
                ['name' => 'Cut', 'duration_minutes' => 45, 'images' => [$kept], 'default_image_id' => $kept],
            ],
        ]);

        /* Deleting and recreating the services leaves the dropped picture
           pointing at a row that no longer exists; nothing else would ever
           collect it. */
        $this->assertSoftDeleted('stored_files', ['id' => $dropped]);
        $this->assertNotSoftDeleted('stored_files', ['id' => $kept]);
    }

    public function test_the_step_renders_the_pictures_a_service_already_has(): void
    {
        $this->wizardTenant();
        $id = $this->uploadImage();

        $this->actingAs($this->owner->fresh())->post('http://styledesk.test/onboarding/services', [
            'services' => [
                ['name' => 'Cut', 'duration_minutes' => 45, 'images' => [$id], 'default_image_id' => $id],
            ],
        ]);

        /* Re-opening the step must show the service as it stands. The picker
           is nested inside the repeater island rather than mounted on the
           page, so what the page can be asked is whether the props that seed
           it carry this service's picture. */
        $this->actingAs($this->owner->fresh())
            ->get('http://styledesk.test/onboarding/services')
            ->assertOk()
            ->assertSee('data-vue-component="ServiceRepeater"', false)
            /* Raw, not escaped: @json sits inside a single-quoted attribute,
               so the JSON's own double quotes are left as they are. */
            ->assertSee('"default_image_id":'.$id, false)
            ->assertSee('"images":[{"id":'.$id, false)
            ->assertSee('"imageUploadUrl"', false);
    }

    // ------------------------------------------------------- another tenant

    public function test_one_business_cannot_attach_another_businesss_picture(): void
    {
        $other = Tenant::create(['name' => 'Rival Salon', 'slug' => 'rival']);

        $theirFile = StoredFile::withoutGlobalScopes()->create([
            'tenant_id' => $other->getTenantKey(),
            'category' => Service::IMAGE_CATEGORY,
            'original_filename' => 'theirs.jpg', 'stored_filename' => 'theirs.jpg',
            'storage_disk' => 'local', 'storage_path' => 'x/theirs.jpg',
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'file_size' => 10,
            'visibility' => 'public',
        ]);

        $this->actingAs($this->owner)->post('http://styledesk.test/services', [
            'name' => 'Balayage', 'duration_minutes' => 90,
            'locations' => [$this->location()->id],
            'images' => [$theirFile->id], 'default_image_id' => $theirFile->id,
        ]);

        $service = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail();

        $this->assertNull($service->image_file_id, 'An id from another business must not attach.');
        $this->assertSame(
            (string) $other->getTenantKey(),
            (string) $theirFile->fresh()->tenant_id,
            'And it must certainly not be re-pointed.',
        );
    }

    public function test_a_file_of_another_category_cannot_be_turned_into_a_service_picture(): void
    {
        $document = StoredFile::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'category' => 'client-file',
            'original_filename' => 'consent.pdf', 'stored_filename' => 'consent.pdf',
            'storage_disk' => 'local', 'storage_path' => 'x/consent.pdf',
            'mime_type' => 'application/pdf', 'extension' => 'pdf', 'file_size' => 10,
            'visibility' => 'private',
        ]);

        $this->actingAs($this->owner)->post('http://styledesk.test/services', [
            'name' => 'Balayage', 'duration_minutes' => 90,
            'locations' => [$this->location()->id],
            'images' => [$document->id], 'default_image_id' => $document->id,
        ]);

        $service = Service::withoutGlobalScopes()->where('name', 'Balayage')->firstOrFail();

        $this->assertNull($service->image_file_id);
        $this->assertSame('client-file', $document->fresh()->category);
        $this->assertNull($document->fresh()->entity_id);
    }
}
