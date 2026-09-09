<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Models\TenantOnboarding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Private notes, and who may read them.
 *
 * The rule this file exists to hold is the one the UI cannot be trusted with:
 * a note the reader may not see must not reach them at all. Hiding it on the
 * page would leave the text in the response for anyone who looked, so every
 * assertion here is against the response body rather than against what is
 * rendered from it.
 */
class ClientPrivateNotesTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'She asked us not to mention the wedding.';

    private Tenant $tenant;

    private User $owner;

    private Client $client;

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

        $this->client = $this->tenant->clients()->create([
            'client_ref' => Client::nextRef($this->tenant->getTenantKey()),
            'first_name' => 'Amelia', 'last_name' => 'Hart',
        ]);
    }

    private function member(string $role, string $first = 'Sam'): User
    {
        $user = User::create([
            'first_name' => $first, 'last_name' => 'Person',
            'email' => Str()->lower($first).'-'.$role.'@styledesk.test', 'password' => 'Str0ng!Pass',
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

    /** A private note written by someone else, with an explicit audience. */
    private function privateNote(User $author, array $readers = []): ClientNote
    {
        $note = $this->client->clientNotes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'body' => self::SECRET,
            'is_private' => true,
            'created_by' => $author->id,
        ]);

        $note->accessUsers()->sync(collect($readers)->pluck('id')->all());

        return $note;
    }

    // -------------------------------------------------------- who may read

    public function test_a_named_reader_receives_the_note(): void
    {
        $author = $this->member('front-desk', 'Ana');
        $reader = $this->member('service-provider', 'Bea');

        $this->privateNote($author, [$reader]);

        $this->actingAs($reader)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee(self::SECRET, false);
    }

    /**
     * The whole point of the feature: a colleague who was not named gets the
     * note's existence and nothing else.
     */
    public function test_an_unnamed_colleague_never_receives_the_body(): void
    {
        $author = $this->member('front-desk', 'Ana');
        $outsider = $this->member('service-provider', 'Bea');

        $this->privateNote($author);

        $response = $this->actingAs($outsider)
            ->get(route('clients.show', $this->client))
            ->assertOk();

        $response->assertDontSee(self::SECRET, false);
        $response->assertSee(__('clients.module.workspace.notes.no_access'), false);
    }

    /** Access comes from the role, so an owner needs no invitation. */
    public function test_the_owner_can_always_read_a_private_note(): void
    {
        $this->privateNote($this->member('front-desk', 'Ana'));

        $this->actingAs($this->owner)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee(self::SECRET, false);
    }

    public function test_an_admin_can_always_read_a_private_note(): void
    {
        $admin = $this->member('administrator', 'Cara');
        $this->privateNote($this->member('front-desk', 'Ana'));

        $this->actingAs($admin)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee(self::SECRET, false);
    }

    public function test_an_author_can_read_their_own_private_note(): void
    {
        $author = $this->member('front-desk', 'Ana');
        $this->privateNote($author);

        $this->actingAs($author)
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertSee(self::SECRET, false);
    }

    /**
     * The timeline names the event, never its contents — a private note that
     * leaked through the Activity tab would be just as leaked.
     */
    public function test_the_activity_timeline_does_not_carry_a_restricted_body(): void
    {
        $this->privateNote($this->member('front-desk', 'Ana'));

        $this->actingAs($this->member('service-provider', 'Bea'))
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertDontSee(self::SECRET, false);
    }

    /**
     * An important note is printed on the profile itself. A private one that
     * is also important must not take that as a way around the rule.
     */
    public function test_an_important_private_note_is_not_raised_to_a_reader_without_access(): void
    {
        $note = $this->privateNote($this->member('front-desk', 'Ana'));
        $note->forceFill(['is_important' => true])->save();

        $this->actingAs($this->member('service-provider', 'Bea'))
            ->get(route('clients.show', $this->client))
            ->assertOk()
            ->assertDontSee(self::SECRET, false);
    }

    // ------------------------------------------------------------- writing

    public function test_a_private_note_records_who_may_read_it(): void
    {
        $reader = $this->member('service-provider', 'Bea');

        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => self::SECRET,
                'is_private' => '1',
                'access' => [$reader->id],
            ])
            ->assertRedirect();

        $note = $this->client->clientNotes()->firstOrFail();

        $this->assertTrue($note->is_private);
        $this->assertSame([$reader->id], $note->accessUsers->pluck('id')->all());
    }

    /**
     * An audience on a note that is not private is not a smaller audience —
     * the note is readable by everyone — so keeping the rows would only
     * mislead whoever read them later.
     */
    public function test_a_standard_note_keeps_no_audience(): void
    {
        $reader = $this->member('service-provider', 'Bea');

        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => 'Prefers the 9am slot.',
                'access' => [$reader->id],
            ])
            ->assertRedirect();

        $note = $this->client->clientNotes()->firstOrFail();

        $this->assertFalse($note->is_private);
        $this->assertCount(0, $note->accessUsers);
    }

    /** Somebody at another salon is not someone to share a note with. */
    public function test_access_cannot_name_a_stranger(): void
    {
        $other = Tenant::create([
            'name' => 'Other Salon', 'slug' => 'other', 'business_email' => 'hi@other.test',
        ]);

        $stranger = User::create([
            'first_name' => 'Rae', 'last_name' => 'Vale',
            'email' => 'rae@other.test', 'password' => 'Str0ng!Pass',
        ]);
        $stranger->forceFill(['tenant_id' => $other->getTenantKey()])->save();

        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => self::SECRET,
                'is_private' => '1',
                'access' => [$stranger->id],
            ])
            ->assertSessionHasErrors('access.0');

        $this->assertCount(0, $this->client->clientNotes()->get());
    }

    /**
     * Turning privacy off drops the audience with it, so a note flipped back
     * to private cannot silently regain a list nobody re-confirmed.
     */
    public function test_making_a_note_public_clears_its_audience(): void
    {
        $reader = $this->member('service-provider', 'Bea');
        $note = $this->privateNote($this->owner, [$reader]);

        $this->actingAs($this->owner)
            ->patch(route('clients.notes.update', [$this->client, $note]), [
                'body' => self::SECRET,
            ])
            ->assertRedirect();

        $this->assertFalse($note->fresh()->is_private);
        $this->assertCount(0, $note->fresh()->accessUsers);
    }

    // ------------------------------------------------------------ deleting

    /** What may not be read may not be deleted by guessing its id either. */
    public function test_a_restricted_note_cannot_be_deleted_by_someone_without_access(): void
    {
        $note = $this->privateNote($this->member('front-desk', 'Ana'));

        $this->actingAs($this->member('service-provider', 'Bea'))
            ->delete(route('clients.notes.destroy', [$this->client, $note]))
            ->assertForbidden();

        $this->assertNotNull($note->fresh());
    }

    // ------------------------------------------------------------ composer

    /**
     * The composer saves without a page load, so the response carries the
     * card the server would have drawn.
     */
    public function test_the_composer_is_answered_with_the_rendered_note(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('clients.notes.store', $this->client), [
                'body' => 'Prefers the 9am slot.',
                'is_important' => '1',
            ])
            ->assertOk();

        $response->assertJsonPath('message', __('clients.module.workspace.notes.added'));
        $this->assertStringContainsString('Prefers the 9am slot.', $response->json('html'));
        $this->assertStringContainsString(__('clients.module.workspace.notes.important_badge'), $response->json('html'));
    }

    // ---------------------------------------------------------- rich text

    /**
     * The editor posts HTML, and HTML from a browser is a string a person
     * chose. What is stored is what the allowlist left behind.
     */
    public function test_a_note_keeps_its_formatting_and_loses_everything_else(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => '<p>Colour <strong>6.1</strong> only</p><script>alert(1)</script><p onclick="x()">no handlers</p>',
            ])
            ->assertRedirect();

        $note = $this->client->clientNotes()->firstOrFail();

        $this->assertSame('html', $note->format);
        $this->assertStringContainsString('<strong>6.1</strong>', $note->body);
        $this->assertStringNotContainsString('script', $note->body);
        $this->assertStringNotContainsString('onclick', $note->body);
    }

    /** A link may not smuggle in a scheme that runs code. */
    public function test_a_javascript_link_loses_its_href(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => '<p><a href="javascript:alert(1)">tap here</a></p>',
            ])
            ->assertRedirect();

        $body = $this->client->clientNotes()->firstOrFail()->body;

        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('tap here', $body);
    }

    /**
     * An image pointing at somebody else's server would call it every time a
     * colleague opened this client — telling whoever runs it who read which
     * client's notes, and when.
     */
    public function test_a_remote_image_is_dropped(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => '<p>Before<img src="https://tracker.test/pixel.gif">after</p>',
            ])
            ->assertRedirect();

        $body = $this->client->clientNotes()->firstOrFail()->body;

        $this->assertStringNotContainsString('tracker.test', $body);
        $this->assertStringContainsString('Before', $body);
    }

    /** Markup with no words in it is not a note. */
    public function test_markup_with_no_content_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), ['body' => '<p></p><script>alert(1)</script>'])
            ->assertSessionHasErrors('body');

        $this->assertCount(0, $this->client->clientNotes()->get());
    }

    /**
     * Notes written before the editor existed are plain text, and rendering
     * them as markup would swallow their line breaks.
     */
    public function test_an_older_plain_text_note_is_still_escaped(): void
    {
        $note = $this->client->clientNotes()->create([
            'tenant_id' => $this->tenant->getTenantKey(),
            'body' => "Line one\n<b>not bold</b>",
            'format' => 'text',
            'created_by' => $this->owner->id,
        ]);

        $this->assertStringContainsString('&lt;b&gt;', $note->bodyHtml()->toHtml());
        $this->assertStringContainsString('<br', $note->bodyHtml()->toHtml());
    }

    // ------------------------------------------------------ image uploads

    /**
     * Stored through the shared storage component, so it lands in this
     * business's own folder rather than in a directory of its own.
     */
    public function test_an_image_is_stored_and_answered_with_its_url(): void
    {
        Storage::fake('tenants');

        $response = $this->actingAs($this->owner)
            ->postJson(route('clients.notes.images', $this->client), [
                'image' => UploadedFile::fake()->image('swatch.jpg'),
            ])
            ->assertOk();

        $file = StoredFile::firstOrFail();

        $this->assertSame(route('files.show', $file), $response->json('url'));
        $this->assertStringContainsString('/clients/'.$this->client->id.'/editor-attachments/', $file->storage_path);
        Storage::disk('tenants')->assertExists($file->storage_path);
    }

    public function test_an_upload_that_is_not_an_image_is_refused(): void
    {
        Storage::fake('tenants');

        $this->actingAs($this->owner)
            ->postJson(route('clients.notes.images', $this->client), [
                'image' => UploadedFile::fake()->create('payload.svg', 8, 'image/svg+xml'),
            ])
            ->assertStatus(422);

        $this->assertCount(0, StoredFile::all());
    }

    /** Uploading is part of writing a note, so it needs that permission. */
    public function test_a_role_without_add_notes_cannot_upload_an_image(): void
    {
        Storage::fake('tenants');

        $user = $this->member('service-provider', 'Bea');

        Role::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->getTenantKey())
            ->where('key', 'service-provider')
            ->firstOrFail()
            ->permissions()->where('permission', 'clients.add_notes')->delete();

        $this->actingAs($user->fresh())
            ->postJson(route('clients.notes.images', $this->client), [
                'image' => UploadedFile::fake()->image('swatch.jpg'),
            ])
            ->assertForbidden();
    }

    /**
     * The upload endpoint answers with an absolute URL, so that is exactly
     * what the editor puts in the note. An allowlist that only recognised
     * the relative path would throw away every image anyone uploaded.
     */
    public function test_an_uploaded_image_survives_being_saved(): void
    {
        $url = Storage::disk('brand')->url('notes/swatch.jpg');

        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => '<p>Swatch<img src="'.$url.'" alt="swatch"></p>',
            ])
            ->assertRedirect();

        $body = $this->client->clientNotes()->firstOrFail()->body;

        // Stored as a path, so the picture survives the business moving to
        // its own domain.
        $this->assertStringContainsString('src="/storage/brand/notes/swatch.jpg"', $body);
    }

    /** A lookalike path on somebody else's host is still somebody else's host. */
    public function test_a_remote_lookalike_image_is_still_dropped(): void
    {
        $this->actingAs($this->owner)
            ->post(route('clients.notes.store', $this->client), [
                'body' => '<p>Hi<img src="https://evil.test/storage/brand/notes/x.jpg"></p>',
            ])
            ->assertRedirect();

        $this->assertStringNotContainsString('evil.test', $this->client->clientNotes()->firstOrFail()->body);
    }
}
