<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One bookable thing that is not a person.
 *
 * A chair, a room, a piece of equipment. Bookable is not a property it has
 * but a question with three parts: is it retired, is it blocked right now,
 * and does it have room for the client — and the last one is why capacity is
 * a number rather than a yes.
 */
class Resource extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'capacity' => 1, 'position' => 0];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
            'booking_interval_minutes' => 'integer',
            'preparation_minutes' => 'integer',
            'cleanup_minutes' => 'integer',
            'buffer_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'resource_category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ResourceBlock::class);
    }

    /** Its own opening hours, when it does not keep the location's. */
    public function hours(): HasMany
    {
        return $this->hasMany(ResourceHour::class);
    }

    /** The services that may claim it. */
    /**
     * The services this room or chair can carry.
     *
     * The pivot's priority comes with it: how good a fit this resource is for
     * a service belongs to the pairing rather than to either side, because
     * the same room is a body massage's first choice and a reflexology's
     * second. Declared on both ends — the allocator reads it from this one,
     * and a relation that quietly drops it returns a null priority that
     * sorts as nought and puts a bed ahead of the chair.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withPivot('priority');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /** Matches a name or the category it is in. */
    public function scopeMatching(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($term, $like) {
            $inner->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))
                /* Which branch it is at. "Riverside" is a way somebody looks
                   for the chairs at Riverside, not only for the branch. */
                ->orWhereHas('location', fn (Builder $location) => $location->where('name', 'like', $like));

            /* Typed as words, matched as states. "Maintenance" and "retired"
               are things a reader searches for; neither is a string in the
               column that decides them. */
            foreach (self::availabilityMatches($term) as $status) {
                $inner->orWhere('availability_status', $status);
            }

            foreach (self::statusMatches($term) as $isActive) {
                $inner->orWhere('is_active', $isActive);
            }
        });
    }

    /**
     * The availability states a search term names, if any.
     *
     * Matched on the start of the word and against the reader's own language,
     * because the label is what is on the screen they are searching.
     *
     * @return array<int, string>
     */
    private static function availabilityMatches(string $term): array
    {
        $term = mb_strtolower($term);

        return collect(__('resources.form.availability'))
            ->filter(fn (string $label) => str_starts_with(mb_strtolower($label), $term))
            ->keys()
            ->all();
    }

    /**
     * Whether the business still has it at all, as a word.
     *
     * @return array<int, bool>
     */
    private static function statusMatches(string $term): array
    {
        $term = mb_strtolower($term);

        return collect([
            true => __('resources.form.active'),
            false => __('resources.form.inactive'),
        ])
            ->filter(fn (string $label) => str_starts_with(mb_strtolower($label), $term))
            ->keys()
            ->map(fn ($key) => (bool) $key)
            ->all();
    }

    /**
     * The block covering a moment, if there is one.
     *
     * A block with no end runs until somebody ends it — that is what "out
     * for repair, no date yet" looks like, and it has to be expressible.
     */
    public function blockAt(?Carbon $moment = null): ?ResourceBlock
    {
        $moment ??= now();

        return $this->blocks
            ->first(fn (ResourceBlock $block) => $block->covers($moment));
    }

    /**
     * Whether this can be booked at a given moment.
     *
     * Retired first, then blocked: a retired resource is not unavailable, it
     * is gone, and saying "under maintenance" about a chair that was sold
     * would send somebody looking for it.
     */
    public function isBookableAt(?Carbon $moment = null): bool
    {
        return $this->is_active && $this->blockAt($moment) === null;
    }

    /**
     * available · blocked · inactive — what the listing shows.
     *
     * Three questions in order of how final they are: retired outright, out
     * for a stated period, or marked unavailable by hand. Saying "under
     * maintenance" about a chair that was sold would send somebody looking
     * for it.
     */
    public function availabilityStatus(?Carbon $moment = null): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->blockAt($moment) !== null) {
            return 'blocked';
        }

        return $this->availability_status === 'available' ? 'available' : 'blocked';
    }

    /** Whether it keeps hours of its own rather than its location's. */
    public function hasCustomHours(): bool
    {
        return $this->availability_type === 'custom';
    }

    public function availabilityLabel(?Carbon $moment = null): string
    {
        return __('resources.availability.'.$this->availabilityStatus($moment));
    }
}
