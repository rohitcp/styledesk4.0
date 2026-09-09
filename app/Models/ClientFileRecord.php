<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One treatment, photographed before and after.
 *
 * A record rather than a pair of tagged files: what a stylist is showing a
 * client is the comparison, and a comparison needs to know what was done, on
 * what day, by whom. Six loose images with "before" written on them cannot
 * answer any of that, and cannot be shown side by side without guessing which
 * of them go together.
 *
 * Both sides may hold several images — a colour is photographed from three
 * angles — and either may be empty for a while: a before taken this morning
 * has no after until the work is finished, and refusing to save it until then
 * would mean holding the photographs somewhere else in the meantime.
 */
class ClientFileRecord extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** Started and not finished, or finished. See ClientFile. */
    public const DRAFT = 'draft';

    public const SAVED = 'saved';

    protected function casts(): array
    {
        return ['treatment_date' => 'date'];
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClientFile::class, 'record_id')->orderBy('position')->orderBy('id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The images on one side of the treatment.
     *
     * @return Collection<int, ClientFile>
     */
    public function side(string $side): Collection
    {
        return $this->files->where('side', $side)->values();
    }
}
