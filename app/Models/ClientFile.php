<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One file on a client's record.
 *
 * The row a person deals with; `stored_file` is the row the disk deals with.
 * Both, because they answer different questions — what the receptionist
 * called it, filed it under and attached it to lives here, and where the
 * bytes are lives there, so moving a business to another provider does not
 * touch a single thing anybody typed.
 *
 * A standard upload and one half of a before-and-after are the same shape: a
 * treatment photo simply has a `record_id` and a `side`. That is what lets
 * the All Files view be one query.
 */
class ClientFile extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** Which end of a treatment a photograph shows. */
    public const SIDES = ['before', 'after'];

    /**
     * Started and not finished, or finished.
     *
     * A draft is on the record and visible in the Files tab under its own
     * badge — it is not hidden work. What it is not is something anybody
     * should read as complete.
     */
    public const DRAFT = 'draft';

    public const SAVED = 'saved';

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ClientFileRecord::class, 'record_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Everything that is not part of a treatment record. */
    public function scopeStandalone(Builder $query): Builder
    {
        return $query->whereNull('record_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /**
     * What kind of thing this is, as the listing's Type column says it.
     *
     * Read from the stored file's own extension rather than from a column of
     * its own: a column would be a second answer to a question that already
     * has one, and the two would disagree the first time a file was replaced.
     */
    public function kind(): string
    {
        if ($this->record_id !== null) {
            return 'before-after';
        }

        return $this->isImage() ? 'image' : 'document';
    }

    public function isImage(): bool
    {
        return in_array(strtolower((string) $this->storedFile?->extension), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /** The size as a person would say it, or nothing where the file has gone. */
    public function readableSize(): string
    {
        return $this->storedFile?->readableSize() ?? '';
    }
}
