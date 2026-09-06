<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ClientFileRecord;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * One client's files, as every screen that shows them reads them.
 *
 * Its own class because four things ask the same question and must not get
 * four answers: the All Files table, the Before & After gallery, the card on
 * the profile and the JSON the tab reloads itself with. A shape assembled in
 * each of them would be four places to fix the day a column moves — and the
 * gallery quietly showing a treatment the table does not is exactly the kind
 * of disagreement a client would notice before anybody here did.
 *
 * Nothing here builds a path. A file is reached by its own route, which
 * checks the permission again on every request and writes down that it was
 * read — see App\Http\Controllers\ClientFileController.
 */
class ClientFilePresenter
{
    /**
     * Everything the Files tab needs, in one payload.
     *
     * @return array<string, mixed>
     */
    public function tab(Client $client, ?User $user = null): array
    {
        $files = $this->files($client);

        return [
            /* Standalone only. A treatment photograph is in the table too,
               but as part of its record — see `records`. */
            'files' => $files->map(fn (ClientFile $file) => $this->file($file, $client))->values()->all(),
            'records' => $this->records($client),
            'options' => $this->options($client),
            'can' => [
                'upload' => (bool) $user?->hasPermission('clients.upload_files', 'own'),
                'manage' => (bool) $user?->hasPermission('clients.manage_files', 'own'),
            ],
        ];
    }

    /**
     * What the workflow page is reopening, where it is reopening anything.
     *
     * One handle for both kinds, because the page takes one: a batch id
     * brings back an upload with every file that was on it, and a numeric id
     * brings back a treatment with both its sides. Null for anything that is
     * not this client's, so a handle from elsewhere opens a blank form rather
     * than somebody else's work.
     *
     * @return array<string, mixed>|null
     */
    public function resumable(Client $client, ?string $handle): ?array
    {
        if (blank($handle)) {
            return null;
        }

        if (ctype_digit($handle)) {
            $record = $this->records($client, null, (int) $handle);

            return $record === [] ? null : ['kind' => 'record'] + $record[0];
        }

        $files = $client->files()
            ->where('batch_id', $handle)
            ->with(['storedFile', 'uploader', 'service', 'booking'])
            ->orderBy('id')
            ->get();

        if ($files->isEmpty()) {
            return null;
        }

        $first = $files->first();

        /* The shared details are the same on every row in the batch, so the
           first one carries them — and the name comes back without the number
           the batch put on the end of it, which is not something anybody
           typed. */
        return [
            'kind' => 'standard',
            'batch' => $handle,
            'name' => $files->count() > 1
                ? preg_replace('/\s\d+$/', '', (string) $first->name)
                : $first->name,
            'note' => $first->note,
            'category' => $first->category,
            'service_id' => $first->service_id,
            'booking_id' => $first->booking_id,
            'status' => $first->status,
            'files' => $files->map(fn (ClientFile $file) => $this->file($file, $client))->all(),
        ];
    }

    /**
     * The files that are not part of a treatment, newest first.
     *
     * @return Collection<int, ClientFile>
     */
    private function files(Client $client): Collection
    {
        return $client->files()
            ->standalone()
            ->with(['storedFile', 'uploader', 'service', 'booking'])
            ->get();
    }

    /**
     * The treatment records, each with both its sides.
     *
     * @return array<int, array<string, mixed>>
     */
    private function records(Client $client, ?int $limit = null, ?int $only = null): array
    {
        return $client->fileRecords()
            ->with(['files.storedFile', 'service', 'staff', 'booking', 'author'])
            ->when($only !== null, fn ($query) => $query->whereKey($only))
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get()
            ->map(function (ClientFileRecord $record) use ($client) {
                $before = $record->side('before');
                $after = $record->side('after');

                return [
                    'id' => $record->id,
                    'title' => $record->title,
                    /* When this record last had something added, which is
                       what "most recently uploaded" means. Not the treatment
                       date — a colour done in March and photographed today
                       is today's upload — and not the row's own timestamp,
                       which does not move when a photograph is added to it. */
                    'uploaded_at' => $record->files
                        ->pluck('created_at')
                        ->push($record->created_at)
                        ->filter()
                        ->max()?->toIso8601String(),
                    'status' => $record->status,
                    'is_draft' => $record->isDraft(),
                    'date' => $record->treatment_date?->toDateString(),
                    'date_label' => $record->treatment_date?->isoFormat('D MMM Y'),
                    'note' => $record->note,
                    'service_id' => $record->service_id,
                    'service' => $record->service?->name,
                    'booking_id' => $record->booking_id,
                    'booking' => $record->booking?->reference,
                    'staff_id' => $record->staff_id,
                    'staff' => $this->staffName($record->staff),
                    'author' => $record->author?->name,
                    'before' => $before->map(fn (ClientFile $file) => $this->file($file, $client))->all(),
                    'after' => $after->map(fn (ClientFile $file) => $this->file($file, $client))->all(),
                    'count' => $before->count() + $after->count(),
                ];
            })
            ->all();
    }

    /**
     * One file, as every view of it reads it.
     *
     * @return array<string, mixed>
     */
    private function file(ClientFile $file, Client $client): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'note' => $file->note,
            'category' => $file->category,
            'category_label' => $file->category
                ? __('clients.module.workspace.files.categories.'.$file->category)
                : null,
            'kind' => $file->kind(),
            'status' => $file->status,
            'is_draft' => $file->isDraft(),
            /* The upload this file arrived in. Reopening a draft brings back
               all of them, not whichever row was clicked. */
            'batch' => $file->batch_id,
            'is_image' => $file->isImage(),
            'extension' => $file->storedFile?->extension,
            'size' => $file->readableSize(),
            'record_id' => $file->record_id,
            'side' => $file->side,
            'service_id' => $file->service_id,
            'service' => $file->service?->name,
            'booking_id' => $file->booking_id,
            'booking' => $file->booking?->reference,
            'uploaded_at' => $file->created_at?->toIso8601String(),
            'uploaded_label' => $file->created_at?->isoFormat('D MMM Y · h:mm A'),
            'uploaded_by' => $file->uploader?->name,
            /* Both routes check the permission again and record the read.
               There is no URL to the disk itself. */
            'url' => route('clients.files.show', ['client' => $client->id, 'file' => $file->id]),
            'download_url' => route('clients.files.download', ['client' => $client->id, 'file' => $file->id]),
        ];
    }

    /**
     * What an upload form can be pointed at.
     *
     * This client's own bookings rather than the diary: a treatment record
     * belongs to a visit this person made, and offering somebody else's is
     * offering a mistake.
     *
     * @return array<string, mixed>
     */
    public function options(Client $client): array
    {
        return [
            'services' => Service::query()->active()->inOrder()
                ->get(['id', 'name'])
                ->map(fn (Service $service) => ['id' => $service->id, 'name' => $service->name])
                ->all(),

            'bookings' => Booking::query()
                ->where('client_id', $client->id)
                ->whereNot('status', 'draft')
                ->with('services')
                ->orderByDesc('date')
                ->limit(50)
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'name' => trim(($booking->reference ?: '').' · '.$booking->date?->isoFormat('D MMM Y'), ' ·'),
                    /* What the appointment already says, so choosing it
                       fills the rest of the form in. A treatment
                       photographed at a booking was that booking's service,
                       done by that booking's stylist, on that booking's day
                       — asking the reader to restate all three is asking
                       them to copy something the record already knows.

                       The first service of the visit: a booking of four is
                       one appointment with a lead service, and the reader
                       can change it. */
                    'service_id' => $booking->services->first()?->service_id,
                    'staff_id' => $booking->staff_id,
                    'date' => $booking->date?->toDateString(),
                    /* What the year and month pickers narrow on. Carried per
                       booking rather than sent as two more lists: the lists
                       are whatever these actually contain, so neither can
                       offer a month with no appointment in it.

                       The month's name comes from here rather than from the
                       browser, which would say it in a different language
                       from the page around it. */
                    'year' => $booking->date?->format('Y'),
                    'month' => $booking->date?->format('m'),
                    'month_label' => $booking->date?->translatedFormat('F'),
                ])
                ->all(),

            'staff' => Staff::query()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Staff $staff) => ['id' => $staff->id, 'name' => $this->staffName($staff)])
                ->all(),

            'categories' => collect(config('clients.file_categories', []))
                ->map(fn (string $key) => [
                    'id' => $key,
                    'name' => __('clients.module.workspace.files.categories.'.$key),
                ])
                ->all(),
        ];
    }

    private function staffName(?Staff $staff): ?string
    {
        return $staff ? trim($staff->first_name.' '.$staff->last_name) : null;
    }
}
