<?php

namespace Tests\Feature;

use App\Contracts\TenantStorageContract;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use App\Services\Storage\StoragePathBuilder;
use App\Services\Storage\TenantStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The one component that stores files.
 *
 * What is worth holding here is the part that cannot be seen by looking at a
 * screen: files land in the same folder structure whatever disk is behind
 * them, a business cannot reach another business's file, and a filename is
 * never something the uploader chose.
 */
class TenantStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tenants');

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

        $this->actingAs($this->owner);
    }

    private function storage(): TenantStorageContract
    {
        return app(TenantStorageContract::class);
    }

    // ------------------------------------------------------------- folders

    /**
     * The structure the spec names, which is also the structure a Space
     * uses: the path is not something that changes on deployment.
     */
    public function test_a_client_file_lands_in_the_clients_folder(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('consent.pdf', 12, 'application/pdf'), 987);

        $expected = 'tenants/'.$this->tenant->getTenantKey().'/clients/987/files/';

        $this->assertStringStartsWith($expected, $file->storage_path);
        Storage::disk('tenants')->assertExists($file->storage_path);
    }

    public function test_an_editor_attachment_lands_beside_the_clients_files(): void
    {
        $file = app(TenantStorageService::class)
            ->uploadEditorAttachment(UploadedFile::fake()->image('swatch.jpg'), 987);

        $this->assertStringContainsString('/clients/987/editor-attachments/', $file->storage_path);
        $this->assertSame('public', $file->visibility);
    }

    public function test_branding_and_profile_images_have_their_own_folders(): void
    {
        $service = app(TenantStorageService::class);

        $logo = $service->uploadBranding(UploadedFile::fake()->image('logo.png'), 'logo');
        $photo = $service->uploadProfileImage(UploadedFile::fake()->image('me.jpg'), 42);

        $this->assertStringContainsString('/branding/', $logo->storage_path);
        $this->assertStringContainsString('/profiles/42/', $photo->storage_path);
    }

    /** A module nobody has written yet still gets a sensible home. */
    public function test_a_generic_upload_nests_under_its_entity(): void
    {
        $file = $this->storage()->upload(
            UploadedFile::fake()->image('chair.jpg'), 'resource-image', 'resource', 7,
        );

        $this->assertStringContainsString('/resources/7/', $file->storage_path);
    }

    // ------------------------------------------------------------ filenames

    /**
     * The name on disk is a UUID, never the one that arrived.
     *
     * Two people uploading "consent-form.pdf" must not overwrite each other,
     * and a stored name taken from user input is a stored name somebody can
     * put a path in.
     */
    public function test_the_stored_name_is_a_uuid_and_the_original_is_kept(): void
    {
        $file = $this->storage()->uploadClientFile(
            UploadedFile::fake()->create('../../etc/passwd.pdf', 4, 'application/pdf'), 1,
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', $file->stored_filename);
        $this->assertStringNotContainsString('..', $file->storage_path);
        $this->assertStringContainsString('passwd.pdf', $file->original_filename);
    }

    public function test_two_uploads_of_the_same_name_do_not_collide(): void
    {
        $first = $this->storage()->uploadClientFile(UploadedFile::fake()->create('form.pdf', 4, 'application/pdf'), 1);
        $second = $this->storage()->uploadClientFile(UploadedFile::fake()->create('form.pdf', 4, 'application/pdf'), 1);

        $this->assertNotSame($first->storage_path, $second->storage_path);
        Storage::disk('tenants')->assertExists($first->storage_path);
        Storage::disk('tenants')->assertExists($second->storage_path);
    }

    // ----------------------------------------------------------- validation

    public function test_an_executable_dressed_as_an_image_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->storage()->upload(
            UploadedFile::fake()->create('shell.php', 4, 'application/x-php'), 'profile-image', 'user', 1,
        );
    }

    public function test_an_oversized_image_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->storage()->upload(
            UploadedFile::fake()->image('huge.jpg')->size(6000), 'profile-image', 'user', 1,
        );
    }

    // -------------------------------------------------------------- tenancy

    /**
     * The rule the whole component exists for: a file belongs to one
     * business, and nobody outside it can reach the row at all.
     */
    public function test_another_businesss_file_is_not_reachable(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('notes.pdf', 4, 'application/pdf'), 1);

        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);
        $stranger = User::create([
            'first_name' => 'Rae', 'last_name' => 'Vale',
            'email' => 'rae@other.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->forceFill(['tenant_id' => $other->getTenantKey()])->save();

        $this->actingAs($stranger->fresh());

        $this->assertNull($this->storage()->get($file->id));
        $this->assertNull($this->storage()->metadata($file->id));
    }

    public function test_a_missing_file_and_a_forbidden_one_answer_the_same_way(): void
    {
        $this->assertNull($this->storage()->get(999999));
    }

    // ------------------------------------------------------------- lifecycle

    public function test_replacing_keeps_the_record_and_removes_the_old_object(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('v1.pdf', 4, 'application/pdf'), 1);
        $original = $file->storage_path;

        $updated = $this->storage()->replace($file->id, UploadedFile::fake()->create('v2.pdf', 6, 'application/pdf'));

        $this->assertSame($file->id, $updated->id);
        $this->assertSame('v2.pdf', $updated->original_filename);
        Storage::disk('tenants')->assertMissing($original);
        Storage::disk('tenants')->assertExists($updated->storage_path);
    }

    public function test_deleting_removes_the_object_and_keeps_the_history(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('gone.pdf', 4, 'application/pdf'), 1);

        $this->assertTrue($this->storage()->delete($file->id));

        Storage::disk('tenants')->assertMissing($file->storage_path);
        $this->assertNull(StoredFile::find($file->id));
        $this->assertNotNull(StoredFile::withTrashed()->find($file->id));
    }

    public function test_exists_answers_for_the_object_not_the_row(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('here.pdf', 4, 'application/pdf'), 1);

        $this->assertTrue($this->storage()->exists($file->id));

        Storage::disk('tenants')->delete($file->storage_path);

        $this->assertFalse($this->storage()->exists($file->id));
    }

    public function test_metadata_describes_the_file(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'), 5);

        $meta = $this->storage()->metadata($file->id);

        $this->assertSame('report.pdf', $meta['original_filename']);
        $this->assertSame('tenants', $meta['storage_disk']);
        $this->assertSame('client', $meta['entity_type']);
        $this->assertSame((string) $this->tenant->getTenantKey(), (string) $meta['tenant_id']);
        $this->assertSame($this->owner->id, $meta['uploaded_by']);
    }

    /**
     * A private file on a disk that cannot sign gets the download route,
     * which checks permissions on every request rather than trusting a URL.
     */
    public function test_a_private_file_on_a_local_disk_falls_back_to_the_download_route(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('private.pdf', 4, 'application/pdf'), 1);

        $this->assertSame(route('files.download', $file), $this->storage()->temporaryUrl($file->id));
    }

    public function test_downloading_is_refused_to_another_business(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('secret.pdf', 4, 'application/pdf'), 1);

        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other-dl', 'business_email' => 'hi@other-dl.test',
        ]);
        $stranger = User::create([
            'first_name' => 'Rae', 'last_name' => 'Vale',
            'email' => 'rae@other-dl.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->markEmailAsVerified();
        $stranger->forceFill(['tenant_id' => $other->getTenantKey()])->save();

        $this->actingAs($stranger->fresh())
            ->get(route('files.download', $file))
            ->assertNotFound();
    }

    public function test_the_owner_can_download_their_own_file(): void
    {
        $file = $this->storage()->uploadClientFile(UploadedFile::fake()->create('mine.pdf', 4, 'application/pdf'), 1);

        $this->actingAs($this->owner)
            ->get(route('files.download', $file))
            ->assertOk()
            ->assertDownload('mine.pdf');
    }

    // ---------------------------------------------------------- the switch

    /**
     * Switching provider is one environment variable. Nothing above the
     * service names a disk, so nothing above it changes.
     */
    public function test_the_disk_comes_from_config_alone(): void
    {
        $service = app(TenantStorageService::class);

        $this->assertSame('tenants', $service->resolveDisk());

        config(['filesystems.tenant_disk' => 'spaces']);

        $this->assertSame('spaces', $service->resolveDisk());
    }

    /** The same path is produced whichever disk is behind it. */
    public function test_the_folder_structure_does_not_depend_on_the_disk(): void
    {
        $paths = new StoragePathBuilder;

        $this->assertSame(
            'tenants/123/clients/987/files',
            $paths->directory('123', 'client-file', 'client', 987),
        );
    }
}
