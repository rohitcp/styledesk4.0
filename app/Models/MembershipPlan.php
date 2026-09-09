<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\TenantStorageContract;
use App\Support\Currencies;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A membership the business sells.
 *
 * One model for both kinds, because they are one thing with one difference: a
 * recurring membership bills again and a package does not. Everything else —
 * what it is called, what it costs, what it includes, where it is sold — is
 * the same set of questions, and splitting them would mean two listings, two
 * forms and a booking screen that has to merge them back together.
 *
 * Status is worked out rather than stored. Draft and Disabled are decisions
 * somebody made and live on the row; Active is simply neither of them.
 */
class MembershipPlan extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** The category every membership picture is stored under. */
    public const IMAGE_CATEGORY = 'membership-image';

    public const TYPES = ['recurring', 'package'];

    public const DISCOUNT_TYPES = ['percent', 'fixed'];

    /** The states a reader sees, in the order a plan passes through. */
    public const STATUSES = ['draft', 'active', 'disabled'];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'joining_fee_minor' => 'integer',
            'setup_fee_minor' => 'integer',
            'trial_days' => 'integer',
            'regular_value_minor' => 'integer',
            'discount_value' => 'integer',
            'priority_booking' => 'boolean',
            'allow_rollover' => 'boolean',
            'maximum_rollover' => 'integer',
            'allow_service_substitution' => 'boolean',
            'sell_in_store' => 'boolean',
            'sell_online' => 'boolean',
            'is_draft' => 'boolean',
            'is_disabled' => 'boolean',
        ];
    }

    /* ------------------------------------------------------- relations -- */

    /** What is included, and how much of it. */
    public function planServices(): HasMany
    {
        return $this->hasMany(MembershipPlanService::class)->orderBy('position');
    }

    /**
     * The services themselves, carrying their quantity.
     *
     * The pivot is named plan-first throughout, which reads better than
     * Laravel's alphabetical guess — so it has to be stated.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'membership_plan_services')
            ->withPivot(['quantity', 'position'])
            ->orderBy('membership_plan_services.position');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'membership_plan_location');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The picture on the card the client is shown.
     *
     * A stored file rather than a path, the same way a service picture is:
     * the file knows which disk it lives on and this only knows which file.
     */
    public function imageFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'image_file_id');
    }

    /**
     * Where to draw it from, or null for a plan with no picture.
     *
     * Null is also the answer for a file whose row survived but whose object
     * did not — the card falls back to its placeholder rather than rendering
     * a broken image.
     */
    public function imageUrl(): ?string
    {
        if ($this->image_file_id === null) {
            return null;
        }

        return app(TenantStorageContract::class)->url($this->image_file_id);
    }

    /* ---------------------------------------------------------- status -- */

    /**
     * Where this plan has got to.
     *
     * Order matters: a disabled plan is disabled whether or not it was ever
     * published, and a draft has not started being anything yet.
     */
    public function status(): string
    {
        if ($this->is_disabled) {
            return 'disabled';
        }

        return $this->is_draft ? 'draft' : 'active';
    }

    public function statusLabel(): string
    {
        return __('membership.statuses.'.$this->status());
    }

    public function statusClass(): string
    {
        return match ($this->status()) {
            'active' => 'styledesk_badge--active',
            'draft' => 'styledesk_badge--setup',
            default => 'styledesk_badge--danger',
        };
    }

    public function isRecurring(): bool
    {
        return $this->type === 'recurring';
    }

    /* ----------------------------------------------------------- money -- */

    /** "$79 / month", or "$150" for something bought once. */
    public function priceLabel(?string $currency = null): string
    {
        $price = Money::format($this->price_minor / 100, $currency ?: Currencies::resolve());

        return $this->isRecurring()
            ? __('membership.price_per', [
                'price' => $price,
                'period' => __('membership.periods.'.$this->billing_frequency),
            ])
            : $price;
    }

    /**
     * What the client saves, in minor units.
     *
     * Packages only, and only where the business stated a regular value. A
     * saving is a claim being made to the client, so it comes from the two
     * numbers the business typed rather than from summing today's service
     * prices — which would change the claim every time somebody edited a
     * price list.
     *
     * Never negative: a package priced above its own regular value is a
     * mistake somebody will fix, not a saving of minus fifty pounds.
     */
    public function savingMinor(): int
    {
        if ($this->isRecurring() || $this->regular_value_minor === null) {
            return 0;
        }

        return max(0, $this->regular_value_minor - $this->price_minor);
    }

    /** "10% off" or "$15 off", for a card that has to be read at a glance. */
    public function discountLabel(?string $currency = null): ?string
    {
        if ($this->discount_type === null || $this->discount_value <= 0) {
            return null;
        }

        return __('membership.discount_off', [
            'amount' => $this->discount_type === 'percent'
                ? $this->discount_value.'%'
                : Money::format($this->discount_value / 100, $currency ?: Currencies::resolve()),
        ]);
    }

    /* --------------------------------------------------------- credits -- */

    /**
     * How many of each service a cycle of this plan grants.
     *
     * The same number means two things depending on the kind, which is why
     * it is read through here rather than off the pivot: on a recurring plan
     * it is per cycle and comes back every time it bills, on a package it is
     * the whole entitlement and never comes back.
     *
     * @return array<int, int> service id => quantity
     */
    public function creditGrants(): array
    {
        return $this->planServices
            ->mapWithKeys(fn (MembershipPlanService $line) => [$line->service_id => (int) $line->quantity])
            ->all();
    }

    /**
     * The credit rules this plan actually runs under.
     *
     * A null on the plan means "whatever the business decided", so the
     * business's answer is the fallback rather than something copied onto
     * the plan when it was created — a copy would silently stop following
     * the setting the day it changed.
     *
     * @return array{expiry: string, rollover: bool, maximum_rollover: ?int, substitution: bool}
     */
    public function creditRules(MembershipSettings $settings): array
    {
        $rollover = $this->allow_rollover ?? $settings->allow_rollover;

        return [
            'expiry' => $this->credit_expiry ?? $settings->credit_expiry,
            'rollover' => $rollover,
            /* A cap only means anything with rollover on. */
            'maximum_rollover' => $rollover
                ? ($this->maximum_rollover ?? $settings->maximum_rollover)
                : null,
            'substitution' => $this->allow_service_substitution ?? $settings->allow_service_substitution,
        ];
    }

    /* ---------------------------------------------------------- scopes -- */

    /** Plans that can actually be sold today. */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_draft', false)->where('is_disabled', false);
    }

    public function scopeOfStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'draft' => $query->where('is_draft', true)->where('is_disabled', false),
            'disabled' => $query->where('is_disabled', true),
            'active' => $query->sellable(),
            default => $query,
        };
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Plans this location may sell.
     *
     * "All locations" is stored as a mode rather than as a row per branch,
     * so a plan opened everywhere keeps working the day a tenth branch is
     * added — which a pivot of nine ids would not.
     */
    public function scopeAtLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId === null) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('location_mode', 'all')
            ->orWhereHas('locations', fn (Builder $l) => $l->where('locations.id', $locationId)));
    }
}
