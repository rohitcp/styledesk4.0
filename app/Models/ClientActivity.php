<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One thing that happened to a client.
 *
 * Written once and never again. There is no update path and no delete route:
 * an audit trail somebody can edit is not one anybody can rely on, and the
 * whole value of the row is that it still says what it said on the day.
 *
 * It outlives what it describes. A note recorded here and deleted tomorrow
 * leaves both entries behind — "note added", then "note deleted" — which is
 * the question the timeline exists to answer.
 */
class ClientActivity extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * Created, and never updated.
     *
     * A second timestamp on an immutable row is a column that can only ever
     * lie, so there is not one to write.
     */
    public const UPDATED_AT = null;

    /** The tabs the timeline is filtered by. */
    public const CATEGORIES = ['bookings', 'notes', 'client', 'tags', 'payments', 'email'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'meta' => 'array',
            'is_private' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BookingPayment::class, 'payment_id');
    }

    /** Whoever did it, where a person did. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nothing here is ever rewritten.
     *
     * Enforced on the model as well as by the absence of a route, because the
     * next person to add a feature will reach for `->update()` before they
     * read the migration.
     */
    protected static function booted(): void
    {
        static::updating(function (): bool {
            return false;
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /** Newest first, which is the only order a timeline is read in. */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeInCategory(Builder $query, ?string $category): Builder
    {
        return $category === null || $category === '' || $category === 'all'
            ? $query
            : $query->where('category', $category);
    }

    /**
     * "Booking rescheduled", in the reader's own language.
     *
     * The dot in the type becomes an underscore on the way to the language
     * file. Laravel reads a key like `events.booking.created` by walking the
     * array one segment at a time, so a key that itself contains a dot is one
     * it can never reach — and what comes back is the key string, printed on
     * the page.
     */
    public function title(): string
    {
        return __('clients.module.workspace.activity.events.'.str_replace('.', '_', $this->type));
    }

    /**
     * Who did it, or StyleDesk where nothing did.
     *
     * Null is not missing data: an automated confirmation and a receptionist
     * pressing a button are different facts, and a timeline that showed a
     * blank for one of them would look broken rather than automatic.
     */
    public function actor(): string
    {
        return $this->user?->name ?? __('clients.module.workspace.activity.system');
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    /**
     * What it said, or nothing where the reader may not be told.
     *
     * Two gates, and a note has to pass both.
     *
     * Reading colleagues' notes is its own permission — seeing a client is
     * not the same as seeing what was written about them — so without it the
     * entry says a note was added and stops there.
     *
     * A private note's body is never shown here at all, whoever is reading.
     * Who may read one is decided per note by its author and their role, and
     * this row outlives the note: for a deleted one there is nothing left to
     * ask. The note card is where a permitted reader reads the body; the
     * timeline's job is only to say that it happened.
     *
     * Everything else — a tag, an amount, a service — is not a secret.
     */
    public function readableDescription(bool $canViewNotes): ?string
    {
        if ($this->category !== 'notes') {
            return $this->description;
        }

        return $canViewNotes && ! $this->is_private ? $this->description : null;
    }
}
