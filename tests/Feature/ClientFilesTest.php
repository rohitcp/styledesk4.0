<?php

namespace Tests\Feature;

use App\Actions\Roles\ProvisionSystemRoles;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\ClientFile;
use App\Models\ClientFileRecord;
use App\Models\Location;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Documents and treatment photographs on a client's record.
 *
 * Three things are being pinned here, and they are the three that would
 * quietly rot: that a before-and-after stays one record rather than becoming
 * loose images, that the three file permissions actually separate filing a
 * document from removing one, and that reading a client's file is written
 * into the client's own history — which is the whole of what "private client
 * information" means in practice.
 */
class ClientFilesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Client $client;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        /* A disk that is not the real one. Every upload here writes bytes,
           and a test suite that scattered them through storage/ would leave
           the next run reading yesterday's. */
        Storage::fake('tenants');

        $this->tenant = Tenant::create([
            'name' => 'Nadia Hair Studio', 'slug' => 'nadia-files', 'business_email' => 'hi@nadia.test',
        ]);

        TenantOnboarding::create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'current_step' => 'complete', 'completed_at' => now(),
        ]);

        $this->owner = User::create([
            'first_name' => 'Sarah', 'last_name' => 'Miller',
            'email' => 'owner@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $this->owner->markEmailAsVerified();
        $this->owner->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->owner->id])->save();
        $this->owner = $this->owner->fresh();

        app(ProvisionSystemRoles::class)->forTenant($this->tenant);

        $this->location = Location::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => 'Riverside', 'address_line1' => '1 River St', 'city' => 'Austin',
            'postal_code' => '78701', 'country' => 'US', 'timezone' => 'America/Chicago',
            'is_primary' => true,
        ]);

        $this->client = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Andrew', 'last_name' => 'Rogers',
            'mobile' => '+1 201-555-0188',
        ]);

        $this->actingAs($this->owner);
    }

    // ------------------------------------------------------------ helpers

    private function member(string $role, string $first = 'Sam'): User
    {
        $user = User::create([
            'first_name' => $first, 'last_name' => 'Person',
            'email' => strtolower($first).'-'.$role.'@styledesk.test', 'password' => 'Str0ng!Pass',
        ]);
        $user->markEmailAsVerified();
        $user->forceFill(['tenant_id' => $this->tenant->getTenantKey()])->save();

        Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(), 'user_id' => $user->id,
            'first_name' => $first, 'last_name' => 'Person',
            'email' => $user->email, 'role' => $role,
        ]);

        return $user->fresh();
    }

    private function service(string $name = 'Hydrating Facial'): Service
    {
        $service = Service::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'name' => $name, 'duration_minutes' => 60, 'is_active' => true,
        ]);

        $service->prices()->create(['currency_code' => 'USD', 'price_minor' => 9000]);

        return $service;
    }

    private function image(string $name = 'before.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 600);
    }

    /** One document already on the record. */
    private function upload(string $name = 'Consent form'): ClientFile
    {
        $this->postJson(route('clients.files.store', $this->client), [
            'name' => $name,
            'files' => [$this->image('consent.jpg')],
        ])->assertCreated();

        return ClientFile::withoutGlobalScopes()->latest('id')->first();
    }

    // ------------------------------------------------------- standard files

    /**
     * A document arrives, and is filed rather than merely stored.
     *
     * The name, the note and what it is about are what somebody looking for
     * it months later has to search on — the bytes on the disk answer none of
     * that.
     */
    public function test_a_document_is_filed_with_what_it_is_and_what_it_is_about(): void
    {
        $service = $this->service();

        $this->postJson(route('clients.files.store', $this->client), [
            'name' => 'Consent form',
            'note' => 'Signed at the consultation.',
            'category' => 'consent',
            'service_id' => $service->id,
            'files' => [UploadedFile::fake()->create('consent.pdf', 40, 'application/pdf')],
        ])->assertCreated();

        $file = ClientFile::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('Consent form', $file->name);
        $this->assertSame('consent', $file->category);
        $this->assertSame($service->id, $file->service_id);
        $this->assertSame($this->owner->id, $file->uploaded_by);
        $this->assertSame('document', $file->kind());

        /* The row stands for the file; the path is the storage layer's
           business and nothing above it names a disk. */
        $stored = StoredFile::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('client-file', $stored->category);
        $this->assertSame('private', $stored->visibility);
        Storage::disk('tenants')->assertExists($stored->storage_path);
    }

    /**
     * Several at once, because that is how they arrive.
     *
     * Numbered, so the list does not show four rows with the same name — a
     * reader looking for the third page of a consultation cannot pick it out
     * of four identical ones.
     */
    public function test_several_files_upload_in_one_action_and_are_told_apart(): void
    {
        $this->postJson(route('clients.files.store', $this->client), [
            'name' => 'Consultation',
            'files' => [$this->image('one.jpg'), $this->image('two.jpg'), $this->image('three.jpg')],
        ])->assertCreated();

        $names = ClientFile::withoutGlobalScopes()->pluck('name')->all();

        $this->assertCount(3, $names);
        $this->assertSame(['Consultation 1', 'Consultation 2', 'Consultation 3'], $names);
    }

    /**
     * What may be stored is decided by the storage layer's allowlist, not by
     * whatever the browser claimed it was sending.
     */
    public function test_a_file_of_a_type_the_business_does_not_take_is_refused(): void
    {
        $this->postJson(route('clients.files.store', $this->client), [
            'name' => 'Script',
            'files' => [UploadedFile::fake()->create('payload.php', 4, 'application/x-php')],
        ])->assertStatus(422);

        $this->assertDatabaseCount('client_files', 0);
        $this->assertDatabaseCount('stored_files', 0);
    }

    // ------------------------------------------------------ before & after

    /**
     * A treatment is one record, not six loose photographs.
     *
     * Both sides, the day the work happened, the service and the stylist —
     * everything that makes the comparison mean something. Loose images
     * tagged "before" could answer none of it.
     */
    public function test_a_before_and_after_is_saved_as_one_treatment_record(): void
    {
        $service = $this->service();
        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Sarah', 'last_name' => 'Johnson',
            'email' => 'sarah@nadia.test', 'role' => 'service-provider',
        ]);

        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment',
            'treatment_date' => '2026-09-04',
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'note' => 'Three passes.',
            'before' => [$this->image('b1.jpg'), $this->image('b2.jpg')],
            'after' => [$this->image('a1.jpg'), $this->image('a2.jpg'), $this->image('a3.jpg')],
        ])->assertCreated();

        $record = ClientFileRecord::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('Facial Treatment', $record->title);
        $this->assertSame('2026-09-04', $record->treatment_date->toDateString());
        $this->assertSame($service->id, $record->service_id);
        $this->assertSame($staff->id, $record->staff_id);

        $record->load('files');
        $this->assertCount(2, $record->side('before'));
        $this->assertCount(3, $record->side('after'));

        /* Every one of them is part of the record, and says so — which is
           what lets the All Files view show them as a treatment rather than
           as five unrelated pictures. */
        $this->assertSame(
            ['before-after'],
            ClientFile::withoutGlobalScopes()->get()->map->kind()->unique()->values()->all(),
        );
    }

    /**
     * One side is enough to start.
     *
     * A before taken this morning has no after until the work is done, and
     * refusing to save it until then means keeping the photographs somewhere
     * that is not the client's record.
     */
    public function test_a_record_can_be_started_with_only_the_before_images(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Colour correction',
            'treatment_date' => '2026-09-04',
            'before' => [$this->image()],
        ])->assertCreated();

        $record = ClientFileRecord::withoutGlobalScopes()->firstOrFail();
        $record->load('files');

        $this->assertCount(1, $record->side('before'));
        $this->assertCount(0, $record->side('after'));

        /* And the other half arrives later, onto the record that already
           exists rather than as a second one. */
        $this->postJson(route('clients.files.records.images', ['client' => $this->client, 'record' => $record]), [
            'side' => 'after',
            'images' => [$this->image('a.jpg'), $this->image('b.jpg')],
        ])->assertOk();

        $this->assertCount(1, ClientFileRecord::withoutGlobalScopes()->get());
        $this->assertCount(2, $record->fresh()->load('files')->side('after'));
    }

    /**
     * A record with no photographs yet is still a record.
     *
     * Named this morning, photographed after lunch: refusing to save it in
     * between meant keeping the treatment somewhere that is not the client's
     * record. See test_a_treatment_needs_nothing_but_a_name.
     */
    public function test_a_record_with_no_images_yet_is_kept(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Colour correction',
        ])->assertCreated();

        $this->assertDatabaseCount('client_file_records', 1);
        $this->assertDatabaseCount('client_files', 0);
    }

    /**
     * A record may only point at this client's own appointment.
     *
     * Otherwise one client's photographs sit beside another's visit, which is
     * the kind of mistake nobody notices until the wrong person is shown the
     * wrong picture.
     */
    public function test_a_record_cannot_be_attached_to_another_clients_booking(): void
    {
        $other = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Someone', 'last_name' => 'Else', 'mobile' => '+1 201-555-0100',
        ]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $other->id,
            'location_id' => $this->location->id,
            'reference' => Booking::nextReference(),
            'date' => '2026-09-04', 'starts_at' => '10:00', 'ends_at' => '11:00',
            'status' => 'confirmed',
        ]);

        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial',
            'treatment_date' => '2026-09-04',
            'booking_id' => $booking->id,
            'before' => [$this->image()],
        ])->assertStatus(422);
    }

    // ------------------------------------------------------------ changing

    /**
     * Editing changes what a file is called, never what it contains.
     *
     * Replacing is a separate act with its own entry in the history: swapping
     * a signed consent form for another document and correcting its name are
     * different things, and a business auditing the record has to be able to
     * tell them apart.
     */
    public function test_a_file_is_renamed_without_its_contents_changing(): void
    {
        $file = $this->upload();
        $before = $file->stored_file_id;

        $this->patchJson(route('clients.files.update', ['client' => $this->client, 'file' => $file]), [
            'name' => 'Signed consent form',
            'category' => 'consent',
        ])->assertOk();

        $file->refresh();

        $this->assertSame('Signed consent form', $file->name);
        $this->assertSame($before, $file->stored_file_id);
    }

    /** Replacing keeps the row, so the file stays where it was filed. */
    public function test_replacing_a_file_keeps_the_row_it_was_filed_under(): void
    {
        $file = $this->upload();
        $storedId = $file->stored_file_id;
        $wasPath = StoredFile::withoutGlobalScopes()->find($storedId)->storage_path;

        $this->post(route('clients.files.replace', ['client' => $this->client, 'file' => $file]), [
            'file' => $this->image('rescanned.jpg'),
        ])->assertOk();

        $file->refresh();

        $this->assertSame($storedId, $file->stored_file_id);
        $this->assertNotSame($wasPath, StoredFile::withoutGlobalScopes()->find($storedId)->storage_path);
    }

    // ------------------------------------------------------------ deleting

    /**
     * One photograph out of a treatment, without taking the treatment.
     *
     * Deleting a badly framed picture is not deleting the record of the work.
     */
    public function test_one_image_can_be_deleted_without_losing_the_record(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment', 'treatment_date' => '2026-09-04',
            'before' => [$this->image('b1.jpg'), $this->image('b2.jpg')],
            'after' => [$this->image('a1.jpg')],
        ])->assertCreated();

        $record = ClientFileRecord::withoutGlobalScopes()->firstOrFail();
        $image = $record->files()->where('side', 'before')->first();

        $this->deleteJson(route('clients.files.destroy', ['client' => $this->client, 'file' => $image]))
            ->assertOk();

        $this->assertNotNull(ClientFileRecord::withoutGlobalScopes()->find($record->id));
        $this->assertCount(2, $record->fresh()->load('files')->files);
    }

    /** The whole record, photographs and all. */
    public function test_deleting_a_record_takes_its_images_with_it(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment', 'treatment_date' => '2026-09-04',
            'before' => [$this->image('b1.jpg')], 'after' => [$this->image('a1.jpg')],
        ])->assertCreated();

        $record = ClientFileRecord::withoutGlobalScopes()->firstOrFail();

        $this->deleteJson(route('clients.files.records.destroy', ['client' => $this->client, 'record' => $record]))
            ->assertOk();

        /* Gone from the record. `withoutGlobalScopes()` drops the
           soft-delete scope along with the tenant one, so what is still there
           has to be asked for by hand. */
        $this->assertCount(0, ClientFileRecord::withoutGlobalScopes()->whereNull('deleted_at')->get());
        $this->assertCount(0, ClientFile::withoutGlobalScopes()->whereNull('deleted_at')->get());

        /* Soft deleted rather than erased: a document removed from a client's
           record is a thing somebody may have to account for afterwards. */
        $this->assertCount(2, ClientFile::withoutGlobalScopes()->get());
        $this->assertCount(2, StoredFile::withoutGlobalScopes()->whereNotNull('deleted_at')->get());
    }

    // --------------------------------------------------------- permissions

    /**
     * Filing a document and removing one are different authorities.
     *
     * The front desk scans the consent form the client just signed; deciding
     * it should no longer be on the record is somebody else's call.
     */
    public function test_the_front_desk_may_upload_and_read_but_not_delete(): void
    {
        $file = $this->upload();

        $desk = $this->member('front-desk', 'Dana');
        $this->actingAs($desk);

        $this->postJson(route('clients.files.store', $this->client), [
            'name' => 'Intake form',
            'files' => [$this->image('intake.jpg')],
        ])->assertCreated();

        $this->get(route('clients.files.show', ['client' => $this->client, 'file' => $file]))
            ->assertOk();

        $this->deleteJson(route('clients.files.destroy', ['client' => $this->client, 'file' => $file]))
            ->assertStatus(403);

        $this->patchJson(route('clients.files.update', ['client' => $this->client, 'file' => $file]), [
            'name' => 'Renamed',
        ])->assertStatus(403);
    }

    /**
     * A file is client data before it is a file.
     *
     * Somebody with no client-files permission is refused even though the
     * file exists and they are signed in to the business that owns it.
     */
    public function test_a_reader_without_the_files_permission_is_refused(): void
    {
        $file = $this->upload();

        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->firstOrFail()
            ->permissions()->where('permission', 'clients.view_files')->delete();

        $this->actingAs($this->member('front-desk', 'Dana'));

        $this->get(route('clients.files.show', ['client' => $this->client, 'file' => $file]))
            ->assertStatus(403);

        $this->getJson(route('clients.files.index', $this->client))->assertStatus(403);
    }

    /** A file id from another client is not this client's to read. */
    public function test_a_file_belonging_to_another_client_is_not_found_here(): void
    {
        $file = $this->upload();

        $other = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Someone', 'last_name' => 'Else', 'mobile' => '+1 201-555-0101',
        ]);

        $this->get(route('clients.files.show', ['client' => $other, 'file' => $file]))
            ->assertStatus(404);
    }

    // ------------------------------------------------------------- privacy

    /**
     * Reading a client's file is written into the client's own history.
     *
     * That is what "private client information" means in practice: a business
     * has to be able to answer who has seen a consent form, and a file that
     * was read leaves no other trace that it was.
     */
    public function test_opening_and_downloading_a_file_is_recorded_against_the_client(): void
    {
        $file = $this->upload();

        $this->get(route('clients.files.show', ['client' => $this->client, 'file' => $file]))->assertOk();
        $this->get(route('clients.files.download', ['client' => $this->client, 'file' => $file]))->assertOk();

        $entries = ClientActivity::withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->whereIn('type', ['file.viewed', 'file.downloaded'])
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame(['files'], $entries->pluck('category')->unique()->values()->all());
        $this->assertSame([$this->owner->id, $this->owner->id], $entries->pluck('user_id')->all());
        $this->assertSame((string) $file->id, (string) $entries->first()->subject_id);
    }

    /**
     * A gallery is not twelve entries.
     *
     * The same person opening the same file again within the hour is the same
     * fact, and a timeline nobody can read is a record nobody checks.
     */
    public function test_reading_the_same_file_twice_in_an_hour_writes_one_entry(): void
    {
        $file = $this->upload();

        $this->get(route('clients.files.show', ['client' => $this->client, 'file' => $file]))->assertOk();
        $this->get(route('clients.files.show', ['client' => $this->client, 'file' => $file]))->assertOk();
        $this->get(route('clients.files.show', ['client' => $this->client, 'file' => $file]))->assertOk();

        $this->assertCount(1, ClientActivity::withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->where('type', 'file.viewed')
            ->get());
    }

    /** Uploading and deleting are in the history too, named as they were. */
    public function test_the_history_keeps_what_a_deleted_file_was_called(): void
    {
        $file = $this->upload('Consent form');

        $this->deleteJson(route('clients.files.destroy', ['client' => $this->client, 'file' => $file]))
            ->assertOk();

        $deleted = ClientActivity::withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->where('type', 'file.deleted')
            ->firstOrFail();

        $this->assertSame('Consent form', $deleted->description);

        /* Both entries survive the file: "added", then "deleted". */
        $this->assertCount(1, ClientActivity::withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->where('type', 'file.uploaded')
            ->get());
    }

    // ---------------------------------------------------------- the screens

    /**
     * The tab opens filled, and the profile shows the newest few.
     *
     * Both read the one payload, so the card and the tab cannot disagree
     * about what is on the record.
     */
    public function test_the_profile_carries_the_files_and_the_recent_card(): void
    {
        $this->upload('Consent form');

        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment', 'treatment_date' => '2026-09-04',
            'before' => [$this->image('b1.jpg')], 'after' => [$this->image('a1.jpg')],
        ])->assertCreated();

        $response = $this->get(route('clients.show', $this->client))->assertOk();

        $response->assertViewHas('canViewFiles', true);
        $response->assertViewHas('clientFiles', function (array $payload) {
            return count($payload['files']) === 1
                && count($payload['records']) === 1
                && $payload['records'][0]['count'] === 2
                && $payload['can']['manage'] === true;
        });

        /* The Recent files card reads the same payload — it is rendered by
           the same component and teleported into the profile's right-hand
           column — so it needs somewhere to land and a way to tell which
           upload is the newest. */
        $response->assertSee('id="client-recent-files"', false);
        $response->assertViewHas('clientFiles', fn (array $payload) => filled($payload['records'][0]['uploaded_at']));
    }

    /**
     * The card shows the newest upload, whichever kind it is.
     *
     * A treatment's recency is when its newest photograph arrived — not its
     * treatment date, since a colour done in March and photographed today is
     * today's upload.
     */
    public function test_a_treatment_is_dated_by_its_newest_photograph_not_its_treatment_day(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment',
            'treatment_date' => '2026-03-01',
            'before' => [$this->image()],
        ])->assertCreated();

        $payload = $this->getJson(route('clients.files.index', $this->client))->json();
        $record = $payload['records'][0];

        $this->assertSame('2026-03-01', $record['date']);
        $this->assertStringStartsWith(now()->format('Y-m-d'), $record['uploaded_at']);
    }

    /**
     * A reader without the permission is told why the tab is empty, rather
     * than shown an empty tab and left to wonder.
     */
    public function test_a_reader_without_the_permission_sees_no_file_payload(): void
    {
        $this->upload();

        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->firstOrFail()
            ->permissions()->where('permission', 'clients.view_files')->delete();

        $this->actingAs($this->member('front-desk', 'Dana'));

        $this->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertViewHas('canViewFiles', false)
            /* No component, so nowhere for the card to land either. */
            ->assertDontSee('id="client-recent-files"', false);
    }

    // ------------------------------------------------------- drafts

    /**
     * A draft is the thing somebody has not finished filling in.
     *
     * Uploading is a page now, and a page is a sitting: a stylist photographs
     * the "before", is called away, and comes back after the appointment.
     * Every field can be blank except the file itself — an upload with
     * nothing on it is not a draft of anything.
     */
    public function test_an_upload_can_be_parked_as_a_draft_without_a_name(): void
    {
        $response = $this->postJson(route('clients.files.store', $this->client), [
            'draft' => true,
            'files' => [$this->image('scan.jpg')],
        ])->assertCreated();

        $file = ClientFile::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(ClientFile::DRAFT, $file->status);
        $this->assertTrue($file->isDraft());
        /* Named for the file it came from rather than left blank: a draft
           nobody can recognise in the list is a draft nobody comes back to. */
        $this->assertSame('scan', $file->name);
        $this->assertNotNull($file->batch_id);
        $this->assertSame($file->batch_id, $response->json('batch'));
    }

    /** Finishing still needs a name. A draft is a pause, not an exemption. */
    public function test_finishing_an_upload_still_requires_a_name(): void
    {
        $this->postJson(route('clients.files.store', $this->client), [
            'files' => [$this->image()],
        ])->assertStatus(422);
    }

    /** There has to be a file. Everything else can wait; this cannot. */
    public function test_a_draft_with_no_file_at_all_is_refused(): void
    {
        $this->postJson(route('clients.files.store', $this->client), [
            'draft' => true,
            'name' => 'Nothing yet',
        ])->assertStatus(422);

        $this->assertDatabaseCount('client_files', 0);
    }

    /**
     * Reopening a draft brings back the whole upload, not one row of it.
     *
     * Four consultation photographs are four rows sharing one name; without
     * the batch there is nothing to come back to but whichever was clicked.
     * Adding a fifth renumbers all five, so the list never shows
     * "Consultation" beside "Consultation 5".
     */
    public function test_a_draft_upload_is_resumed_as_one_batch(): void
    {
        $batch = $this->postJson(route('clients.files.store', $this->client), [
            'draft' => true,
            'name' => 'Consultation',
            'files' => [$this->image('a.jpg'), $this->image('b.jpg')],
        ])->assertCreated()->json('batch');

        $held = ClientFile::withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame(['Consultation 1', 'Consultation 2'], $held->pluck('name')->all());

        /* Back later: one taken off, one added, and the whole thing finished. */
        $this->postJson(route('clients.files.store', $this->client), [
            'batch' => $batch,
            'name' => 'Consultation',
            'category' => 'consultation',
            'remove' => [$held->first()->id],
            'files' => [$this->image('c.jpg')],
        ])->assertCreated();

        $now = ClientFile::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('id')->get();

        $this->assertCount(2, $now);
        $this->assertSame(['Consultation 1', 'Consultation 2'], $now->pluck('name')->all());
        $this->assertSame([ClientFile::SAVED, ClientFile::SAVED], $now->pluck('status')->all());
        $this->assertSame(['consultation', 'consultation'], $now->pluck('category')->all());
        /* Still one upload, not two. */
        $this->assertSame([$batch, $batch], $now->pluck('batch_id')->all());
    }

    /**
     * A treatment can be parked before it has been photographed at all.
     *
     * A before-and-after named this morning and photographed after lunch is
     * exactly what a draft is for, so the images that a finished record must
     * have are not required to park one.
     */
    public function test_a_treatment_can_be_parked_before_anything_is_photographed(): void
    {
        $response = $this->postJson(route('clients.files.records.store', $this->client), [
            'draft' => true,
            'title' => 'Colour correction',
        ])->assertCreated();

        $record = ClientFileRecord::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(ClientFileRecord::DRAFT, $record->status);
        $this->assertSame('Colour correction', $record->title);
        /* The column is not nullable, so an unstated day is today rather than
           a record nothing can read back. */
        $this->assertSame(now()->toDateString(), $record->treatment_date->toDateString());

        /* Back later, onto the record that already exists rather than a
           second one. */
        $this->postJson(route('clients.files.records.store', $this->client), [
            'record_id' => $response->json('record'),
            'title' => 'Colour correction',
            'treatment_date' => '2026-09-04',
            'before' => [$this->image('b.jpg')],
            'after' => [$this->image('a.jpg')],
        ])->assertCreated();

        $this->assertCount(1, ClientFileRecord::withoutGlobalScopes()->whereNull('deleted_at')->get());

        $record->refresh()->load('files');
        $this->assertSame(ClientFileRecord::SAVED, $record->status);
        $this->assertCount(1, $record->side('before'));
        $this->assertCount(1, $record->side('after'));
    }

    /** An unnamed draft is still findable: it is named for the day. */
    public function test_a_treatment_parked_with_nothing_filled_in_is_still_named(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), ['draft' => true])
            ->assertCreated();

        $this->assertSame(
            __('clients.module.workspace.files.untitled'),
            ClientFileRecord::withoutGlobalScopes()->firstOrFail()->title,
        );
    }

    /**
     * The treatment date is no longer asked for, so it is no longer required.
     *
     * The column stays: the gallery is ordered by it and every card prints
     * it. An unstated day is the day the record was made, and it is still
     * correctable afterwards through Edit treatment details — which is where
     * work photographed on Friday and filed on Monday gets put right.
     */
    public function test_a_treatment_saves_without_a_date_and_takes_today(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment',
            'before' => [$this->image('b.jpg')],
            'after' => [$this->image('a.jpg')],
        ])->assertCreated();

        $record = ClientFileRecord::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(ClientFileRecord::SAVED, $record->status);
        $this->assertSame(now()->toDateString(), $record->treatment_date->toDateString());

        /* Still correctable, which is the whole reason the column is kept. */
        $this->patchJson(route('clients.files.records.update', ['client' => $this->client, 'record' => $record]), [
            'title' => 'Facial Treatment',
            'treatment_date' => '2026-09-01',
        ])->assertOk();

        $this->assertSame('2026-09-01', $record->fresh()->treatment_date->toDateString());
    }

    /**
     * The name is the only thing the treatment form insists on.
     *
     * There used to be an "at least one image" rule as well. It refused
     * exactly the case the page exists to support — a treatment named before
     * it has been photographed — and taught readers to park everything as a
     * draft to get past it.
     */
    public function test_a_treatment_needs_nothing_but_a_name(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment',
        ])->assertCreated();

        $this->assertSame(
            'Facial Treatment',
            ClientFileRecord::withoutGlobalScopes()->firstOrFail()->title,
        );
    }

    /** Without one, it is a record nobody can identify. */
    public function test_a_finished_treatment_still_needs_a_name(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'before' => [$this->image()],
        ])->assertStatus(422);

        $this->assertDatabaseCount('client_file_records', 0);
    }

    /**
     * A photo the disk accepts is not refused by a stricter rule above it.
     *
     * `image` was a second answer to "what is a picture", and the stricter
     * one: it reported "the after.1 field must be an image", naming a field
     * nobody can see. What may be stored is the storage layer's allowlist
     * and nothing else.
     */
    public function test_what_counts_as_a_photograph_is_decided_in_one_place(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment',
            'after' => [$this->image('one.jpg'), UploadedFile::fake()->create('two.pdf', 20, 'application/pdf')],
        ])->assertStatus(422);

        $this->assertDatabaseCount('client_file_records', 0);
        $this->assertDatabaseCount('client_files', 0);
    }

    /**
     * The booking dates the treatment, now the date field has gone.
     *
     * Work attached to Friday's appointment happened on Friday however long
     * the photographs sat on somebody's phone. Taking the day from the
     * booking is the difference between a gallery ordered by when the work
     * was done and one ordered by when somebody got round to filing it.
     */
    public function test_a_treatment_takes_its_day_from_the_booking_it_belongs_to(): void
    {
        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Sarah', 'last_name' => 'Johnson',
            'email' => 'sarah@nadia.test', 'role' => 'service-provider',
        ]);

        $booking = Booking::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'client_id' => $this->client->id,
            'location_id' => $this->location->id,
            'staff_id' => $staff->id,
            'reference' => Booking::nextReference(),
            'date' => '2026-08-14', 'starts_at' => '10:00', 'ends_at' => '11:00',
            'status' => 'confirmed',
        ]);

        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment',
            'booking_id' => $booking->id,
            'before' => [$this->image()],
        ])->assertCreated();

        $record = ClientFileRecord::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('2026-08-14', $record->treatment_date->toDateString());
        $this->assertSame($booking->id, $record->booking_id);
    }

    /**
     * And the booking hands the form what it already knows.
     *
     * The screen fills Service and Staff from the appointment, so the
     * options it is filled from have to carry them.
     */
    public function test_the_booking_options_carry_the_service_and_staff_to_fill_in(): void
    {
        $service = $this->service();
        $staff = Staff::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'first_name' => 'Sarah', 'last_name' => 'Johnson',
            'email' => 'sarah2@nadia.test', 'role' => 'service-provider',
        ]);

        $this->postJson(route('bookings.store'), [
            'client_id' => $this->client->id,
            'staff_id' => $staff->id,
            'location_id' => $this->location->id,
            'date' => '2026-09-10', 'starts_at' => '10:00',
            'services' => [$service->id],
            'confirmation' => 'both',
        ])->assertCreated();

        $this->get(route('clients.files.create', $this->client))
            ->assertOk()
            ->assertViewHas('options', function (array $options) use ($service, $staff) {
                $booking = $options['bookings'][0] ?? null;

                return $booking !== null
                    && $booking['service_id'] === $service->id
                    && $booking['staff_id'] === $staff->id
                    && $booking['date'] === '2026-09-10';
            });
    }

    /**
     * A record id from another client is not this client's to finish.
     *
     * 404 rather than 403: the difference tells the asker which ids are real.
     */
    public function test_a_treatment_from_another_client_cannot_be_finished_here(): void
    {
        $other = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Someone', 'last_name' => 'Else', 'mobile' => '+1 201-555-0102',
        ]);

        $record = $other->fileRecords()->create([
            'tenant_id' => $this->tenant->tenant_id ?? $this->tenant->getTenantKey(),
            'title' => 'Theirs', 'treatment_date' => '2026-09-04',
        ]);

        $this->postJson(route('clients.files.records.store', $this->client), [
            'record_id' => $record->id,
            'title' => 'Mine', 'treatment_date' => '2026-09-04',
            'before' => [$this->image()],
        ])->assertStatus(404);
    }

    /**
     * The save says so on the page the reader lands on.
     *
     * The workflow leaves for the client's profile the moment a save
     * succeeds, so a message in the JSON has nowhere to be shown. It is
     * flashed for the page that comes next, where the app's own toast picks
     * it up like every other save.
     */
    public function test_saving_from_the_workflow_announces_itself_on_the_next_page(): void
    {
        $this->postJson(route('clients.files.records.store', $this->client), [
            'title' => 'Facial Treatment',
            'before' => [$this->image()],
        ])->assertCreated();

        $this->assertSame(
            ['type' => 'success', 'message' => __('clients.module.workspace.files.record_saved')],
            session('toast'),
        );

        /* A draft says it is a draft: "saved" and "parked to finish later"
           are different facts, and the reader is about to leave the screen
           that could have shown them the difference. */
        $this->postJson(route('clients.files.store', $this->client), [
            'draft' => true,
            'files' => [$this->image('scan.jpg')],
        ])->assertCreated();

        $this->assertSame(
            __('clients.module.workspace.files.draft_saved'),
            session('toast')['message'],
        );
    }

    /**
     * The tab's own edits do not.
     *
     * They stay on the page and report inline; a flash from one of those
     * would surface on whatever the reader opened next, long after the thing
     * it describes.
     */
    public function test_an_edit_made_without_leaving_the_tab_flashes_nothing(): void
    {
        $file = $this->upload();

        session()->forget('toast');

        $this->patchJson(route('clients.files.update', ['client' => $this->client, 'file' => $file]), [
            'name' => 'Renamed',
        ])->assertOk();

        $this->assertNull(session('toast'));
    }

    // ----------------------------------------------------- the workflow page

    /**
     * Adding a file is a page of its own, and it opens filled when a draft is
     * being reopened.
     */
    public function test_the_workflow_page_opens_and_carries_the_draft_being_resumed(): void
    {
        $batch = $this->postJson(route('clients.files.store', $this->client), [
            'draft' => true,
            'name' => 'Consent form',
            'files' => [$this->image()],
        ])->assertCreated()->json('batch');

        $this->get(route('clients.files.create', ['client' => $this->client, 'draft' => $batch]))
            ->assertOk()
            ->assertViewHas('draft', fn (?array $draft) => $draft !== null
                && $draft['kind'] === 'standard'
                && $draft['name'] === 'Consent form'
                && count($draft['files']) === 1);
    }

    /** A handle for something that is not this client's opens a blank form. */
    public function test_the_workflow_page_ignores_a_draft_that_is_not_this_clients(): void
    {
        $this->get(route('clients.files.create', ['client' => $this->client, 'draft' => (string) Str::uuid()]))
            ->assertOk()
            ->assertViewHas('draft', null);
    }

    /** Somebody who may not upload has no business on the page that does. */
    public function test_the_workflow_page_is_refused_without_the_upload_permission(): void
    {
        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'front-desk')
            ->firstOrFail()
            ->permissions()->where('permission', 'clients.upload_files')->delete();

        $this->actingAs($this->member('front-desk', 'Dana'));

        $this->get(route('clients.files.create', $this->client))->assertStatus(403);
    }
}
