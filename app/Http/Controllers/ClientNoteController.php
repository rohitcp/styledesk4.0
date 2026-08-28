<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantStorageContract;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Staff;
use App\Support\NoteHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Notes on a client's record.
 *
 * Their own permissions, because reading a client is not the same as reading
 * what colleagues have written about them: `clients.view_notes` and
 * `clients.add_notes` exist so a business can hand the front desk the client
 * list without handing them the clinical history.
 *
 * A private note adds a second question on top of that permission — not "may
 * you read notes" but "may you read *this* note" — and it is answered here as
 * well as in the view. A screen that hides content the response still carried
 * has not kept it private.
 */
class ClientNoteController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $this->authorizeNotes($request, $client, 'clients.add_notes');

        $data = $this->validated($request);

        $note = $client->clientNotes()->create([
            'tenant_id' => $client->tenant_id,
            'body' => $data['body'],
            'format' => 'html',
            'is_important' => (bool) ($data['is_important'] ?? false),
            'is_private' => (bool) ($data['is_private'] ?? false),
            // Stamped once, from the session rather than the form: who wrote
            // a note is not something the form gets to claim.
            'created_by' => $request->user()->id,
        ]);

        $this->syncAccess($note, $data);

        return $this->respond($request, $client, $note, __('clients.module.workspace.notes.added'));
    }

    /**
     * Editing is for the note's own author.
     *
     * A note is a record of what one person observed on one day; letting a
     * colleague rewrite it under the original byline would make every note on
     * the record unreliable. Anyone holding the permission can still add
     * their own.
     */
    public function update(Request $request, Client $client, ClientNote $note): RedirectResponse|JsonResponse
    {
        $this->authorizeNotes($request, $client, 'clients.add_notes');
        $this->assertOwnNote($request, $note, $client);

        $data = $this->validated($request);

        $note->forceFill([
            'body' => $data['body'],
            'format' => 'html',
            'is_important' => (bool) ($data['is_important'] ?? false),
            'is_private' => (bool) ($data['is_private'] ?? false),
        ])->save();

        $this->syncAccess($note, $data);

        return $this->respond($request, $client, $note, __('clients.module.workspace.notes.updated'));
    }

    public function destroy(Request $request, Client $client, ClientNote $note): RedirectResponse|JsonResponse
    {
        $this->authorizeNotes($request, $client, 'clients.delete');
        abort_unless($note->client_id === $client->id, 404);

        /**
         * A note nobody may read is still a note somebody may delete —
         * but not one they should be able to delete by guessing its id.
         * Deleting is refused for the same reason reading is.
         */
        abort_unless($note->isVisibleTo($request->user()), 403);

        $note->delete();

        $message = __('clients.module.workspace.notes.deleted');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'id' => $note->id]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        /**
         * The audience is validated against this business's people, so a
         * hand-made post cannot name a stranger — or worse, someone at
         * another salon — as a reader of this client's note.
         */
        $colleagues = Staff::query()->whereNotNull('user_id')->pluck('user_id')->all();

        /**
         * The body is cleaned before it is validated, not after.
         *
         * Validation asks "is there a note here", and a post of nothing but
         * <script> tags has to answer no — cleaning first is what makes the
         * question about what will be stored rather than about what was
         * sent.
         */
        $request->merge(['body' => NoteHtml::clean($request->input('body'))]);

        return $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'is_important' => ['nullable', 'boolean'],
            'is_private' => ['nullable', 'boolean'],
            'access' => ['nullable', 'array'],
            'access.*' => [Rule::in($colleagues)],
        ], [
            'body.required' => __('clients.module.workspace.notes.body_required'),
        ]);
    }

    /**
     * An image dropped into a note.
     *
     * Stored through TenantStorageService like every other file in the app,
     * so it lands in this business's own folder and moves to DigitalOcean
     * with everything else the day the environment says so. Nothing here
     * knows which disk that is.
     */
    public function image(Request $request, Client $client, TenantStorageContract $storage): JsonResponse
    {
        $this->authorizeNotes($request, $client, 'clients.add_notes');

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'image.max' => __('clients.module.workspace.notes.image_too_large'),
            'image.mimes' => __('clients.module.workspace.notes.image_type'),
        ]);

        $file = $storage->upload($request->file('image'), 'editor-attachment', 'client', $client->id, 'public');

        return response()->json([
            'id' => $file->id,
            'url' => $storage->url($file),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncAccess(ClientNote $note, array $data): void
    {
        /**
         * A note that is no longer private has no audience to keep. Leaving
         * the rows behind would mean a note flipped back to private silently
         * regaining a list its author never re-confirmed.
         */
        $note->accessUsers()->sync(
            ($data['is_private'] ?? false) ? array_map('intval', $data['access'] ?? []) : []
        );
    }

    /**
     * The composer saves without a page load, so the answer carries the note
     * card as the server would have drawn it — one source of truth for that
     * markup rather than a second copy of it written in JavaScript.
     */
    private function respond(Request $request, Client $client, ClientNote $note, string $message): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('toast', ['type' => 'success', 'message' => $message]);
        }

        $note->load(['author', 'accessUsers']);
        $note->readable = $note->isVisibleTo($request->user());

        return response()->json([
            'message' => $message,
            'id' => $note->id,
            'html' => view('clients.partials._note-card', [
                'note' => $note,
                'client' => $client,
                'canDelete' => $request->user()->hasPermission('clients.delete', 'own'),
            ])->render(),
        ]);
    }

    private function authorizeNotes(Request $request, Client $client, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission, 'own'), 403);
        abort_unless($client->tenant_id === $request->user()->tenant?->getTenantKey(), 404);
    }

    private function assertOwnNote(Request $request, ClientNote $note, Client $client): void
    {
        abort_unless($note->client_id === $client->id, 404);
        abort_unless($note->created_by === $request->user()->id, 403);
    }
}
