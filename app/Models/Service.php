<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A bookable service. Priced in minor units; currency lives on the tenant.
 */
class Service extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /**
     * The storage category a service picture is filed under.
     *
     * Named here rather than written as a string at each call site: it is
     * also what FileValidator holds the upload to, and the two have to agree.
     */
    public const IMAGE_CATEGORY = 'service-image';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'online_booking_enabled' => 'boolean',
            'taxable' => 'boolean',
            'requires_resource' => 'boolean',
            'deposit_required' => 'boolean',
            'duration_minutes' => 'integer',
            'preparation_minutes' => 'integer',
            'processing_minutes' => 'integer',
            'cleanup_minutes' => 'integer',
            'buffer_minutes' => 'integer',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    /**
     * Price in one currency, as a decimal string for a form field.
     *
     * Empty rather than "0.00" when unset, so an untouched currency reads as
     * "no price yet" instead of "free".
     */
    public function priceIn(string $currency): string
    {
        $price = $this->prices->firstWhere('currency_code', $currency);

        return $price ? $price->amount() : '';
    }

    /**
     * Replace the price set, and the deposit each price carries.
     *
     * The deposit belongs to the price rather than to the service: 20% of one
     * price and 20% of another are different amounts, and one service-wide
     * setting cannot say "deposit on the premium price only".
     *
     * @param  array<string, string|null>  $prices  currency => decimal amount
     * @param  array<string, array<string, mixed>>  $deposits  currency => deposit
     */
    public function syncPrices(array $prices, array $deposits = []): void
    {
        foreach ($prices as $currency => $amount) {
            if ($amount === null || $amount === '') {
                $this->prices()->where('currency_code', $currency)->delete();

                continue;
            }

            $deposit = $deposits[$currency] ?? [];
            $required = (bool) ($deposit['required'] ?? false);
            $type = $required ? ($deposit['type'] ?? 'percent') : null;

            $this->prices()->updateOrCreate(
                ['currency_code' => $currency],
                [
                    // Rounded once, here, so no float arithmetic happens later.
                    'price_minor' => (int) round(((float) $amount) * 100),
                    'deposit_required' => $required,
                    'deposit_type' => $type,
                    /* Minor units for a fixed amount, whole percent for a
                       percentage — the column means whichever the type says. */
                    'deposit_value' => $required
                        ? ($type === 'percent'
                            ? (int) round((float) ($deposit['value'] ?? 0))
                            : (int) round(((float) ($deposit['value'] ?? 0)) * 100))
                        : null,
                ]
            );
        }

        /* The service-level flag is a summary of its prices rather than a
           setting of its own: the listing shows one chip, and "this service
           takes a deposit" is true when any of its prices does. */
        $this->forceFill(['deposit_required' => $this->prices()->where('deposit_required', true)->exists()])->save();
    }

    /**
     * The image clients see first.
     *
     * Nullable and separate from the gallery, because "which one leads" is a
     * decision about the service, while the pictures themselves are files.
     */
    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'image_file_id');
    }

    /**
     * Every picture attached to this service, the default among them.
     *
     * Not a relation: stored_files is keyed by entity_type/entity_id rather
     * than by a services_id column, so a hasMany would need a foreign key
     * that does not exist. The scopes on StoredFile say the same thing.
     *
     * @return Collection<int, StoredFile>
     */
    public function images(): Collection
    {
        return StoredFile::for('service', $this->getKey())
            ->ofCategory(self::IMAGE_CATEGORY)
            ->orderBy('id')
            ->get();
    }

    /**
     * The gallery in the order it is shown: the default first.
     *
     * A service whose default was deleted still shows a picture — the oldest
     * remaining one leads rather than the card falling back to a blank tile.
     *
     * @return Collection<int, StoredFile>
     */
    public function orderedImages(): Collection
    {
        $images = $this->images();
        $default = $this->image_file_id;

        return $images->sortBy(fn (StoredFile $file) => $file->getKey() === $default ? 0 : 1)->values();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }

    /**
     * Where this service is offered.
     *
     * An empty set means every location. That is the reading a single-site
     * business never has to think about, and the one a new service starts
     * with — the alternative, ticking all three on the way in, is a step
     * that exists only to say "no exception here".
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    /**
     * The rooms, chairs or equipment this service may be performed in.
     *
     * Actual resource rows, never the word "room": availability is a question
     * about Massage Room 2 on Tuesday at three, and a generic kind cannot
     * answer it. Any one of them will do — the booking engine needs one free,
     * not all of them — which is why this is a set rather than a column.
     *
     * Only meaningful while requires_resource is on. The mapping is kept when
     * the switch goes off, so turning it back on does not cost the reader the
     * list they built.
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class);
    }

    /** Price in the tenant's primary currency, for display. */
    public function priceFormatted(): string
    {
        return $this->priceIn((string) $this->tenant?->currency_code);
    }

    /**
     * The price with its symbol, or an empty string when there is none.
     *
     * Empty rather than "0.00", for the same reason priceIn is: a service
     * nobody has priced yet is not a free one, and a list that says it is
     * will eventually be believed.
     */
    public function priceLabel(string $currency): string
    {
        $amount = $this->priceIn($currency);

        if ($amount === '') {
            return '';
        }

        return config('currencies.currencies.'.mb_strtoupper($currency).'.symbol', '').$amount;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /** Matches a service's own name or the category it sits in. */
    public function scopeMatching(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', '%'.$term.'%'));
        });
    }

    /**
     * Everything the diary must set aside, not just the part the client sees.
     *
     * The number a booking actually consumes: a 45-minute colour with 30
     * minutes of processing and 10 of cleanup takes 85 minutes of the room,
     * and a calendar built on duration_minutes alone would double-book it.
     */
    public function bookedMinutes(): int
    {
        return $this->duration_minutes
            + $this->preparation_minutes
            + $this->processing_minutes
            + $this->cleanup_minutes
            + $this->buffer_minutes;
    }

    /**
     * A duration a person reads, rather than a count of minutes.
     *
     * "1h 30m", not "90 minutes": the first is how the time is said out loud
     * when a client asks how long they will be here.
     */
    public function durationLabel(?int $minutes = null): string
    {
        $minutes ??= $this->duration_minutes;

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return __('services.minutes_short', ['count' => $rest]);
        }

        return $rest === 0
            ? __('services.hours_short', ['count' => $hours])
            : __('services.hours_minutes_short', ['hours' => $hours, 'minutes' => $rest]);
    }

    /** Whether this is offered at a given location — everywhere, if unset. */
    public function isOfferedAt(int|string|null $locationId): bool
    {
        if ($this->locations->isEmpty()) {
            return true;
        }

        return $this->locations->contains(fn (Location $location) => (string) $location->id === (string) $locationId);
    }
}
