<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantStorageContract;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ClientFileRecord;
use App\Models\StoredFile;
use App\Services\Storage\FileValidator;
use App\Support\ClientActivityLog;
use App\Support\ClientFilePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents and treatment photographs on a client's record.
 *
 * Their own permissions, because a client's consent form is not their phone
 * number: `clients.view_files`, `clients.upload_files` and
 * `clients.manage_files` exist so a business can let the desk scan a signed
 * form without letting them remove one, and so a stylist reads the records of
 * the people they actually work on and nobody else's.
 *
 * Nothing here names a disk. Every byte goes through TenantStorageService,
 * which puts it in this business's own folder and records which disk it
 * landed on — so the same code works against a local directory and against
 * DigitalOcean, and "does this file belong to this business" has an answer.
 *
 * Reading is written down. A client's documents are client data, and "who has
 * seen this" is a question a business has to be able to answer.
 */
class ClientFileController extends Controller
{
    /**
     * Everything on this client, as the Files tab reads it.
     *
     * One request for both views. The All Files table and the Before & After
     * gallery are two readings of the same rows, and fetching them separately
     * would let the two disagree about what is on the record.
     */
    public function index(Request $request, Client $client, ClientFilePresenter $presenter): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.view_files');

        return response()->json($presenter->tab($client, $request->user()));
    }

    /**
     * The upload workflow, as a page of its own.
     *
     * A page rather than a dialog because of what is actually being done
     * here: several files, two sets of thumbnails, six fields of treatment
     * detail and previews of all of it. That does not fit a 34rem panel, and
     * a dialog that scrolls internally while the page scrolls behind it is
     * the worst of both. It is the same shape as assigning a schedule — one
     * task, no surrounding navigation to abandon it half-finished.
     *
     * `kind` picks which of the two forms; without one the page asks. `draft`
     * reopens something started earlier — a batch id for an upload, a record
     * id for a treatment.
     */
    public function create(Request $request, Client $client, ClientFilePresenter $presenter, FileValidator $validator): View
    {
        $this->authorizeFiles($request, $client, 'clients.upload_files');

        $kind = $request->string('kind')->value();

        return view('clients.files.create', [
            'client' => $client,
            'kind' => in_array($kind, ['standard', 'record'], true) ? $kind : null,
            /* What is being resumed, where anything is. Read through the
               presenter so the page and the tab describe a file the same
               way. */
            'draft' => $presenter->resumable($client, $request->string('draft')->value()),
            'options' => $presenter->options($client),
            /* The size limits, stated by the class that enforces them.
               The page turns an over-large file away as it is chosen and
               names it, rather than letting the reader watch six megabytes
               go up and then telling them one of four files was too big
               without saying which. Read from FileValidator so the browser
               cannot come to hold a different number from the disk. */
            'limits' => [
                'photo' => $validator->maxKilobytes('client-photo'),
                'document' => $validator->maxKilobytes('client-file'),
            ],
        ]);
    }

    /**
     * A document, or several of them — and the same endpoint again when the
     * draft they were saved as is finished later.
     *
     * One writer rather than a create and an update that drift: the workflow
     * page posts the whole form whether it is the first time or the fourth,
     * and "which files are on this upload now" is one answer rather than
     * three requests that can half-succeed.
     *
     * Several files because that is how they arrive: a consultation is
     * photographed four times and scanned once, and making the reader repeat
     * the form five times is making them do the computer's work. One name
     * across the batch is numbered where there is more than one, so the list
     * does not show four rows with identical names.
     */
    public function store(Request $request, Client $client, TenantStorageContract $storage, ClientFilePresenter $presenter): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.upload_files');

        $draft = $request->boolean('draft');

        $data = $request->validate([
            /* Required to finish, optional to park. A draft is by definition
               the thing somebody has not finished filling in. */
            'name' => [$draft ? 'nullable' : 'required', 'string', 'max:150'],
            'batch' => ['nullable', 'uuid'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file'],
            /* Files taken off the upload before it was saved. */
            'remove' => ['nullable', 'array'],
            'remove.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:60'],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'booking_id' => ['nullable', Rule::exists('bookings', 'id')->where('client_id', $client->id)],
        ], [
            'name.required' => __('clients.module.workspace.files.name_required'),
        ]);

        $batch = $data['batch'] ?? (string) Str::uuid();
        $uploads = $request->file('files') ?? [];

        /* What this batch already holds, less anything taken off it. Read
           before the new files are stored so the count below is the count
           the reader will see. */
        $held = $client->files()->where('batch_id', $batch)->get();

        $removing = $held->whereIn('id', $data['remove'] ?? []);
        $keeping = $held->whereNotIn('id', $removing->pluck('id'));

        if ($keeping->isEmpty() && $uploads === []) {
            /* An upload with nothing on it is not a draft of anything. Every
               other field can be left blank; there has to be a file. */
            return response()->json([
                'message' => __('clients.module.workspace.files.file_required'),
                'errors' => ['files' => [__('clients.module.workspace.files.file_required')]],
            ], 422);
        }

        $saved = DB::transaction(function () use ($client, $data, $uploads, $removing, $keeping, $batch, $draft, $storage, $request) {
            foreach ($removing as $file) {
                $this->forget($file);
            }

            $added = collect($uploads)->map(function ($upload) use ($client, $batch, $request, $storage) {
                /* Validated by the storage layer, not here: the allowlist of
                   extensions and real MIME types lives in one place, and a
                   rule copied into a controller is a rule that drifts. */
                $stored = $storage->upload($upload, 'client-file', 'client', $client->id, 'private');

                return $client->files()->create([
                    'tenant_id' => $client->tenant_id,
                    'stored_file_id' => $stored->id,
                    'batch_id' => $batch,
                    /* Named properly a few lines down, once the whole batch
                       is known. The original stands in until then so the
                       column is never blank. */
                    'name' => pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME),
                    'uploaded_by' => $request->user()->id,
                ]);
            });

            $all = $keeping->concat($added)->sortBy('id')->values();

            /* Named across the whole batch every time, so adding a fifth
               photograph to a draft of four renumbers all five rather than
               leaving "Consultation" beside "Consultation 5". */
            $base = filled($data['name'] ?? null)
                ? $data['name']
                : pathinfo((string) $all->first()->name, PATHINFO_FILENAME);

            $all->each(fn (ClientFile $file, int $index) => $file->update([
                'name' => $all->count() > 1 ? $base.' '.($index + 1) : $base,
                'note' => $data['note'] ?? null,
                'category' => $data['category'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'status' => $draft ? ClientFile::DRAFT : ClientFile::SAVED,
            ]));

            return ['added' => $added, 'all' => $all];
        });

        /* One entry per file that is new, and none for a re-save: the history
           records what arrived, not how many times somebody pressed Save. */
        $saved['added']->each(fn (ClientFile $file) => ClientActivityLog::fileUploaded($file->fresh()));

        $message = $draft
            ? __('clients.module.workspace.files.draft_saved')
            : trans_choice('clients.module.workspace.files.uploaded', $saved['all']->count(), ['count' => $saved['all']->count()]);

        return response()->json([
            'message' => $this->announce($message),
            'batch' => $batch,
            'files' => $presenter->tab($client, $request->user()),
        ], 201);
    }

    /**
     * A treatment, photographed at both ends — and the same endpoint again
     * when a draft of one is finished later.
     *
     * The record and its photographs are written together or not at all: a
     * half-saved before-and-after is a set of loose images nobody can tell
     * apart, and the whole value of the thing is that the two sides belong to
     * each other.
     *
     * Either side may be empty. A before taken this morning has no after
     * until the work is done, and refusing to save it until then would mean
     * keeping the photographs somewhere that is not the client's record.
     */
    public function storeRecord(Request $request, Client $client, TenantStorageContract $storage, ClientFilePresenter $presenter): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.upload_files');

        $draft = $request->boolean('draft');
        $data = $this->validatedRecord($request, $client, $draft);

        $request->validate([
            'record_id' => ['nullable', 'integer'],
            /* `file` and nothing more. What counts as a picture is decided
               once, by the storage layer's allowlist of extensions and real
               MIME types — a second `image` rule here was a different answer
               to the same question, and it was the stricter one: a photo
               straight off a phone (HEIC) passed the browser's own
               `accept="image/*"`, passed the disk's allowlist, and was
               refused here with "the after.1 field must be an image", which
               names a field nobody can see and a reason nobody can act on.
               See App\Services\Storage\FileValidator. */
            'before' => ['nullable', 'array', 'max:10'],
            'before.*' => ['file'],
            'after' => ['nullable', 'array', 'max:10'],
            'after.*' => ['file'],
            'remove' => ['nullable', 'array'],
            'remove.*' => ['integer'],
        ]);

        $record = null;

        if ($id = $request->integer('record_id')) {
            $record = ClientFileRecord::query()->find($id);

            /* A record id from another client is not this client's to
               finish. 404 rather than 403, or the difference tells the asker
               which ids are real. */
            abort_if($record === null || $record->client_id !== $client->id, 404);
        }

        $before = $request->file('before') ?? [];
        $after = $request->file('after') ?? [];
        $removing = collect($request->input('remove', []));

        /* The name is the only thing this form insists on. A treatment
           named this morning and photographed after lunch is a record worth
           having, and so is one whose photographs never arrive — what the
           desk should not be able to save is a record nobody can identify.

           There used to be an "at least one image" rule here as well. It
           refused exactly the case the page exists to support and taught
           readers to park everything as a draft to get past it. */

        $isNew = $record === null;

        $record = DB::transaction(function () use ($client, $data, $record, $before, $after, $removing, $draft, $storage, $request) {
            $attributes = $data + ['status' => $draft ? ClientFileRecord::DRAFT : ClientFileRecord::SAVED];

            if ($record === null) {
                $record = $client->fileRecords()->create($attributes + [
                    'tenant_id' => $client->tenant_id,
                    'created_by' => $request->user()->id,
                ]);
            } else {
                $record->update($attributes);
            }

            /* Photographs taken off before the record was saved. Only this
               record's own: an id from somewhere else is ignored rather than
               obeyed. */
            $record->files()->whereIn('id', $removing)->get()->each(fn (ClientFile $file) => $this->forget($file));

            $this->attachImages($client, $record, $before, 'before', $storage, $request);
            $this->attachImages($client, $record, $after, 'after', $storage, $request);

            return $record;
        });

        ClientActivityLog::fileRecordSaved(
            $record->fresh(),
            $isNew ? 'file_record.created' : 'file_record.updated',
            $request->user()->id,
            ['before' => count($before), 'after' => count($after)],
        );

        $message = $draft
            ? __('clients.module.workspace.files.draft_saved')
            : __('clients.module.workspace.files.record_saved');

        return response()->json([
            'message' => $this->announce($message),
            'record' => $record->id,
            'files' => $presenter->tab($client, $request->user()),
        ], 201);
    }

    /** More photographs on a record that already exists. */
    public function addImages(Request $request, Client $client, ClientFileRecord $record, TenantStorageContract $storage, ClientFilePresenter $presenter): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.upload_files');
        $this->assertRecordBelongs($record, $client);

        $request->validate([
            'side' => ['required', Rule::in(ClientFile::SIDES)],
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'image'],
        ]);

        $this->attachImages($client, $record, $request->file('images'), $request->string('side')->value(), $storage, $request);

        ClientActivityLog::fileRecordSaved($record, 'file_record.updated', $request->user()->id);

        return response()->json([
            'message' => __('clients.module.workspace.files.record_updated'),
            'files' => $presenter->tab($client, $request->user()),
        ]);
    }

    /** The treatment's own details, without touching its photographs. */
    public function updateRecord(Request $request, Client $client, ClientFileRecord $record, ClientFilePresenter $presenter): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.manage_files');
        $this->assertRecordBelongs($record, $client);

        $record->update($this->validatedRecord($request, $client));

        ClientActivityLog::fileRecordSaved($record, 'file_record.updated', $request->user()->id);

        return response()->json([
            'message' => __('clients.module.workspace.files.record_updated'),
            'files' => $presenter->tab($client, $request->user()),
        ]);
    }

    /**
     * What the file is called, what it says, and what it is about.
     *
     * Never what it contains — replacing the bytes is replace(), because
     * swapping a signed consent form for another document is a different act
     * from correcting its name, and a business auditing the record has to be
     * able to tell them apart.
     */
    public function update(Request $request, Client $client, ClientFile $file, ClientFilePresenter $presenter): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.manage_files');
        $this->assertFileBelongs($file, $client);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:60'],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            'booking_id' => ['nullable', Rule::exists('bookings', 'id')->where('client_id', $client->id)],
        ], [
            'name.required' => __('clients.module.workspace.files.name_required'),
        ]);

        $file->update([
            'name' => $data['name'],
            'note' => $data['note'] ?? null,
            'category' => $data['category'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'booking_id' => $data['booking_id'] ?? null,
        ]);

        ClientActivityLog::fileUpdated($file, $request->user()->id);

        return response()->json([
            'message' => __('clients.module.workspace.files.updated'),
            'files' => $presenter->tab($client, $request->user()),
        ]);
    }

    /**
     * Swap the contents, keep the row.
     *
     * The id is what the record, the timeline and any link point at, so
     * correcting a badly scanned form must not mean losing where it was
     * filed. The storage layer removes the old object once the new one is
     * safely stored.
     */
    public function replace(Request $request, Client $client, ClientFile $file, TenantStorageContract $storage, ClientFilePresenter $presenter): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.manage_files');
        $this->assertFileBelongs($file, $client);

        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $storage->replace($file->stored_file_id, $request->file('file'));

        ClientActivityLog::fileReplaced($file, $request->user()->id);

        return response()->json([
            'message' => __('clients.module.workspace.files.replaced'),
            'files' => $presenter->tab($client, $request->user()),
        ]);
    }

    /**
     * One file off the record.
     *
     * The row and the object both go. Soft deleted on both tables rather than
     * erased: a document removed from a client's record is a thing somebody
     * may have to account for later, and a hard delete leaves a timeline
     * saying a file existed and nothing about what became of it.
     */
    public function destroy(Request $request, Client $client, ClientFile $file): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.manage_files');
        $this->assertFileBelongs($file, $client);

        /* Read before the row goes: afterwards there is nothing left to name
           it by, and "a file was deleted" without saying which answers
           nothing. */
        $name = (string) $file->name;
        $id = (int) $file->id;

        $this->forget($file);

        ClientActivityLog::fileDeleted((int) $client->id, $name, $id, $request->user()->id);

        return response()->json(['message' => __('clients.module.workspace.files.deleted'), 'id' => $id]);
    }

    /** The whole treatment record, photographs and all. */
    public function destroyRecord(Request $request, Client $client, ClientFileRecord $record): JsonResponse
    {
        $this->authorizeFiles($request, $client, 'clients.manage_files');
        $this->assertRecordBelongs($record, $client);

        $title = (string) $record->title;

        DB::transaction(function () use ($record) {
            StoredFile::query()->whereIn('id', $record->files()->pluck('stored_file_id'))->delete();
            $record->files()->delete();
            $record->delete();
        });

        ClientActivityLog::fileRecordDeleted((int) $client->id, $title, $request->user()->id);

        return response()->json(['message' => __('clients.module.workspace.files.record_deleted'), 'id' => $record->id]);
    }

    /**
     * The file itself, streamed to somebody who may have it.
     *
     * Through here rather than by a URL to the disk: a link that works
     * because it was guessed is not a permission check, and the local disk
     * cannot sign URLs at all. Every request is checked again, and written
     * down — see ClientActivityLog::fileAccessed for why it is not one row
     * per scroll.
     */
    public function show(Request $request, Client $client, ClientFile $file, TenantStorageContract $storage): StreamedResponse
    {
        $this->authorizeFiles($request, $client, 'clients.view_files');
        $this->assertFileBelongs($file, $client);

        ClientActivityLog::fileAccessed($file, $request->user()->id, 'viewed');

        return $storage->show($file->stored_file_id);
    }

    public function download(Request $request, Client $client, ClientFile $file, TenantStorageContract $storage): StreamedResponse
    {
        $this->authorizeFiles($request, $client, 'clients.view_files');
        $this->assertFileBelongs($file, $client);

        ClientActivityLog::fileAccessed($file, $request->user()->id, 'downloaded');

        return $storage->download($file->stored_file_id);
    }

    // ------------------------------------------------------------- helpers

    /**
     * Store several images against one side of a record.
     *
     * @param  array<int, UploadedFile>  $images
     */
    private function attachImages(Client $client, ClientFileRecord $record, array $images, string $side, TenantStorageContract $storage, Request $request): void
    {
        /* Continued from where the side already ends, so photographs added
           this afternoon sit after this morning's rather than shuffling into
           the middle of them. */
        $position = (int) $record->files()->where('side', $side)->max('position');

        foreach ($images as $image) {
            $stored = $storage->upload($image, 'client-photo', 'client', $client->id, 'private');

            $client->files()->create([
                'tenant_id' => $client->tenant_id,
                'stored_file_id' => $stored->id,
                'record_id' => $record->id,
                'side' => $side,
                'name' => $record->title.' · '.__('clients.module.workspace.files.sides.'.$side),
                'position' => ++$position,
                'service_id' => $record->service_id,
                'booking_id' => $record->booking_id,
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRecord(Request $request, Client $client, bool $draft = false): array
    {
        $data = $request->validate([
            /* Required to finish, optional to park. A treatment somebody has
               not named yet is exactly what a draft is for. */
            'title' => [$draft ? 'nullable' : 'required', 'string', 'max:150'],
            'service_id' => ['nullable', Rule::exists('services', 'id')],
            /* This client's bookings only. A treatment record pointing at
               somebody else's appointment would put one client's photographs
               beside another's visit. */
            'booking_id' => ['nullable', Rule::exists('bookings', 'id')->where('client_id', $client->id)],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')],
            /* Not asked for on the workflow page any more, so never
               required here. The column stays — the gallery is ordered by it
               and every card prints it — and an unstated day is the day the
               record was made. It is still correctable afterwards through
               Edit treatment details, which is where a photograph taken on
               Friday and filed on Monday gets put right. */
            'treatment_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => __('clients.module.workspace.files.title_required'),
        ]);

        /* The columns are not nullable, and a save that left them so would
           be a record nothing can read back. An unnamed treatment is named
           for the day it was started, which is what somebody coming back to
           it tomorrow will recognise it by. */
        $data['title'] = $data['title'] ?? __('clients.module.workspace.files.untitled');

        /* The booking's own day where one was chosen, and today otherwise.
           A treatment attached to Friday's appointment happened on Friday
           however long the photographs sat on somebody's phone, and taking
           the date from the booking is the difference between a gallery
           ordered by when the work was done and one ordered by when
           somebody got round to filing it. */
        $data['treatment_date'] ??= Booking::query()
            ->whereKey($data['booking_id'] ?? null)
            ->value('date')?->toDateString()
            ?? now()->toDateString();

        return $data;
    }

    /**
     * Say so on the page the reader lands on.
     *
     * These two are the workflow page's own saves, and the workflow leaves
     * for the client's profile the moment one succeeds. A message in the
     * JSON has nowhere to be shown — the screen that would show it is being
     * navigated away from — so it is flashed for the page that comes next,
     * where `<x-toast />` picks it up like every other save in the app.
     *
     * Only here. The Files tab's own edits stay on the page and report
     * inline; a flash from one of those would surface on whatever the reader
     * opened next, long after the thing it describes.
     */
    private function announce(string $message): string
    {
        session()->flash('toast', ['type' => 'success', 'message' => $message]);

        return $message;
    }

    /**
     * One file off the record, row and object together.
     *
     * Soft deleted on both tables rather than erased: a document removed from
     * a client's record is a thing somebody may have to account for later,
     * and a hard delete leaves a timeline saying a file existed and nothing
     * about what became of it.
     */
    private function forget(ClientFile $file): void
    {
        $file->delete();
        StoredFile::query()->whereKey($file->stored_file_id)->delete();
    }

    /**
     * May this reader do this to this client's files?
     *
     * The client is checked as well as the permission: a file id from another
     * business must 404 rather than 403, or the difference tells the asker
     * which ids are real.
     */
    private function authorizeFiles(Request $request, Client $client, string $permission): void
    {
        abort_unless($client->tenant_id === $request->user()->tenant?->getTenantKey(), 404);
        abort_unless($request->user()->hasPermission($permission, 'own'), 403);
    }

    private function assertFileBelongs(ClientFile $file, Client $client): void
    {
        abort_unless($file->client_id === $client->id, 404);
    }

    private function assertRecordBelongs(ClientFileRecord $record, Client $client): void
    {
        abort_unless($record->client_id === $client->id, 404);
    }
}
