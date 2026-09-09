<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
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
            /* Nullable booleans: null is "whatever the business says" rather
               than false, and only a cast keeps the difference — an
               uncast column comes back as 0 or 1 and `?? true` never
               fires. */
            'accepts_tips' => 'boolean',
            'tip_required' => 'boolean',
            'allow_no_tip' => 'boolean',
            'tip_value' => 'integer',
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
     * What this service costs, paid that way, in minor units.
     *
     * One place, because four screens ask it — the booking screen, the till,
     * the coupon engine and the deposit — and four answers is how a client
     * gets quoted one price and charged another.
     */
    public function priceMinorFor(string $currency, string $method = 'card'): int
    {
        return (int) ($this->prices->firstWhere('currency_code', $currency)?->minorFor($method) ?? 0);
    }

    /**
     * The deposit rule in words: "20% required", "$30.00 required", nothing.
     *
     * Read from the price row, which is where it is stored — the listing, the
     * detail page and the booking screen all ask this rather than each
     * working out their own answer from their own column.
     */
    public function depositLabelIn(string $currency): ?string
    {
        $price = $this->prices->firstWhere('currency_code', $currency);
        $label = $price?->depositLabel();

        return $label ? __('services.deposit_of', ['amount' => $label]) : null;
    }

    /**
     * The deposit this service insists on, paid this way, in minor units.
     *
     * Zero where none is required — which is most services — so a booking can
     * simply add these up across its lines.
     */
    public function requiredDepositMinorFor(string $currency, string $method = 'card'): int
    {
        return (int) ($this->prices->firstWhere('currency_code', $currency)?->requiredDepositMinor($method) ?? 0);
    }

    /**
     * The rule itself, for a screen that has to recalculate as the bill moves.
     *
     * @return array{type: string, percent: ?int, minor: ?int}|null
     */
    public function requiredDepositFor(string $currency): ?array
    {
        return $this->prices->firstWhere('currency_code', $currency)?->requiredDeposit();
    }

    /** The cash price as a form field holds it. */
    public function cashPriceIn(string $currency): string
    {
        return $this->prices->firstWhere('currency_code', $currency)?->cashAmount() ?? '';
    }

    /**
     * Both prices, for a listing that has one column for them.
     *
     * Just the amount where they are the same — which is most services, and
     * "Card $65 · Cash $65" on every row would be a column of noise.
     */
    public function pricingLabel(string $currency): ?string
    {
        $price = $this->prices->firstWhere('currency_code', $currency);

        if ($price === null) {
            return null;
        }

        $symbol = Money::symbol($currency);

        if (! $price->hasTwoPrices()) {
            return $this->priceLabel($currency) ?: null;
        }

        return __('services.pricing_pair', [
            'card' => $symbol.$price->amount(),
            'cash' => $symbol.$price->cashAmount(),
        ]);
    }

    /**
     * What this costs in cash, in one currency.
     *
     * A service priced the same either way answers with that price rather
     * than with nothing: the question "what does this cost in cash" has an
     * answer for every service, and a blank column would read as though it
     * had none.
     */
    public function cashPriceLabel(string $currency): ?string
    {
        $price = $this->prices->firstWhere('currency_code', $currency);

        if ($price === null) {
            return null;
        }

        return Money::symbol($currency).($price->hasTwoPrices() ? $price->cashAmount() : $price->amount());
    }

    /** Whether card and cash actually differ for this currency. */
    public function hasTwoPricesIn(string $currency): bool
    {
        return (bool) $this->prices->firstWhere('currency_code', $currency)?->hasTwoPrices();
    }

    /**
     * Replace the price set, and the deposit each price carries.
     *
     * The deposit is asked for once, on the service, and the caller fans that
     * one answer out to every price. It is *stored* per price because that is
     * where it has to be read from: 20% of one price and 20% of another are
     * different amounts, and the booking screen needs the figure for the
     * price it is actually charging.
     *
     * @param  array<string, string|null>  $prices  currency => decimal amount
     * @param  array<string, array<string, mixed>>  $deposits  currency => deposit
     */
    public function syncPrices(array $prices, array $deposits = [], array $cashPrices = []): void
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
                    /* Null rather than nought when nobody set one: null
                       means "the same as card", and nought would make the
                       service free for anybody paying cash. */
                    'cash_price_minor' => ($cashPrices[$currency] ?? null) === null || ($cashPrices[$currency] ?? '') === ''
                        ? null
                        : (int) round(((float) $cashPrices[$currency]) * 100),
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

        /* Written from the prices rather than from the posted switch, so the
           flag can never claim a deposit that no price actually carries. The
           listing reads this column; the booking screen reads the rows. */
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
        return $this->belongsToMany(Resource::class)->withPivot('priority');
    }

    /**
     * The same list, first choice first.
     *
     * The order lives on the pairing rather than on the resource, because the
     * same room is a body massage's first choice and a reflexology's second,
     * and one number on the room can only be one of those. A single massage
     * should be given a single room while one is free and a couple room only
     * when none is; a reflexology should be offered a chair before a bed.
     */
    public function resourcesByPreference(): BelongsToMany
    {
        return $this->resources()
            ->orderBy('resource_service.priority')
            ->orderBy('resources.position');
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

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($term, $like) {
            $inner->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))
                /* The chair or room it needs. Somebody typing "colour bar"
                   is asking which services need one, and answering only
                   from the service's own name would say none of them. */
                ->orWhereHas('resources', fn (Builder $resource) => $resource->where('resources.name', 'like', $like));

            /* Typed as a word, matched as a state. "inactive" is a thing a
                reader searches for; it is not a string in any column. */
            foreach (self::statusMatches($term) as $isActive) {
                $inner->orWhere('is_active', $isActive);
            }

            /* A price is typed the way it is read — 35, not 3500 — so the
               stored minor units are compared as major ones. */
            if (is_numeric($term)) {
                $inner->orWhereHas('prices', fn (Builder $price) => $price
                    ->whereRaw('cast(price_minor / 100 as char) like ?', [$like]));
            }
        });
    }

    /**
     * The statuses a search term names, if any.
     *
     * Matched on the start of the word rather than the whole of it, so "act"
     * finds the active ones — and against the reader's own language, because
     * that is what is on the screen they are searching.
     *
     * @return array<int, bool>
     */
    private static function statusMatches(string $term): array
    {
        $term = mb_strtolower($term);

        return collect([
            true => __('services.status.active'),
            false => __('services.status.inactive'),
        ])
            ->filter(fn (string $label) => str_starts_with(mb_strtolower($label), $term))
            ->keys()
            ->map(fn ($key) => (bool) $key)
            ->all();
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
     * "1 hr 30 min", not "90 min": the first is how the time is said out loud
     * when a client asks how long they will be here. The edit screen takes
     * minutes, because a number box can hold nothing else — so it prints this
     * same label under the field, and the two screens agree.
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
