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
            ->withPivot(['quantity', 'credits', 'position'])
            ->orderBy('membership_plan_services.position');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'membership_plan_location');
    }

    /**
     * What this costs, once per currency the business prices in.
     *
     * The money lives here rather than on the plan: a business selling in
     * dollars and pounds sets two prices, and nothing converts between them.
     * The columns on the plan itself are the primary currency's copy, kept in
     * step by syncPrices so nothing that still reads them can disagree.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(MembershipPlanPrice::class);
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

    /**
     * Whether clients can buy this right now.
     *
     * The same three answers status() gives, read the other way round: a
     * draft has never been on sale and a plan taken off sale is not on it,
     * and neither can be bought.
     */
    public function isOnSale(): bool
    {
        return $this->status() === 'active';
    }

    /**
     * Whether the details may be changed.
     *
     * Not while it is on sale. A price or a benefit edited underneath a
     * membership somebody is halfway through buying is a plan that meant two
     * things in one afternoon, and there is no answer to "which did I buy?"
     * that a client would accept. Taking it off sale first stops new
     * purchases and leaves every existing membership exactly as it was.
     *
     * A draft is editable throughout — being unfinished is what a draft is.
     */
    public function isEditable(): bool
    {
        return ! $this->isOnSale();
    }

    public function isRecurring(): bool
    {
        return $this->type === 'recurring';
    }

    /* ----------------------------------------------------------- money -- */

    /**
     * This plan's money in one currency, or nothing.
     *
     * Null where the business does not sell this plan in that currency —
     * which is a real answer, and the reason the sale screen can refuse
     * rather than quote a price in the wrong money. Falls back to the plan's
     * own columns only for a plan saved before it had rows, so an old plan
     * keeps its price rather than becoming unsellable.
     */
    public function priceIn(?string $currency = null): ?MembershipPlanPrice
    {
        $code = mb_strtoupper($currency ?: Currencies::resolve());

        $row = $this->relationLoaded('prices')
            ? $this->prices->firstWhere('currency_code', $code)
            : $this->prices()->where('currency_code', $code)->first();

        if ($row !== null) {
            return $row;
        }

        /* A plan from before this table existed. Its one price is in the
           business's primary currency, so it answers for that and nothing
           else. */
        if ($this->prices()->exists() || $code !== mb_strtoupper(Currencies::primaryFor($this->tenant))) {
            return null;
        }

        return new MembershipPlanPrice([
            'membership_plan_id' => $this->id,
            'currency_code' => $code,
            'price_minor' => (int) $this->price_minor,
            'regular_value_minor' => $this->regular_value_minor,
            'joining_fee_minor' => $this->joining_fee_minor,
            'setup_fee_minor' => $this->setup_fee_minor,
        ]);
    }

    /**
     * Replace the price set.
     *
     * The whole set every time, because the form posts the whole set: a
     * currency the business stopped pricing in has to actually go, or the
     * sale screen keeps offering a price nobody maintains.
     *
     * The primary currency is also written back onto the plan's own columns.
     * Those are a copy, not a second answer — everything that still reads
     * them gets the same number the rows hold, and one save writes both.
     *
     * @param  array<string, array<string, string|null>>  $prices  currency => amounts
     */
    public function syncPrices(array $prices, ?string $primary = null): void
    {
        $primary = mb_strtoupper($primary ?: Currencies::primaryFor($this->tenant));
        $minor = fn ($amount) => $amount === null || $amount === '' ? null : (int) round(((float) $amount) * 100);

        $kept = [];

        foreach ($prices as $currency => $amounts) {
            $currency = mb_strtoupper((string) $currency);
            $price = $minor($amounts['price'] ?? null);

            /* No price is not a price of nothing: a currency left blank is
               one this plan is not sold in, and its row goes. */
            if ($price === null) {
                continue;
            }

            $kept[] = $currency;

            $this->prices()->updateOrCreate(
                ['currency_code' => $currency],
                [
                    'price_minor' => $price,
                    /* Each belongs to one kind of plan; the other is nulled
                       so a package that was once recurring stops carrying a
                       joining fee nobody can see. */
                    'regular_value_minor' => $this->isRecurring() ? null : $minor($amounts['regular_value'] ?? null),
                    'joining_fee_minor' => $this->isRecurring() ? $minor($amounts['joining_fee'] ?? null) : null,
                    'setup_fee_minor' => $this->isRecurring() ? $minor($amounts['setup_fee'] ?? null) : null,
                ]
            );
        }

        $this->prices()->whereNotIn('currency_code', $kept ?: ['-'])->delete();

        $lead = $this->prices()->where('currency_code', $primary)->first()
            ?? $this->prices()->orderBy('id')->first();

        if ($lead !== null) {
            $this->forceFill([
                'price_minor' => $lead->price_minor,
                'regular_value_minor' => $lead->regular_value_minor,
                'joining_fee_minor' => $lead->joining_fee_minor,
                'setup_fee_minor' => $lead->setup_fee_minor,
            ])->save();
        }

        $this->load('prices');
    }

    /** Whether this plan can be sold in that money at all. */
    public function isPricedIn(?string $currency = null): bool
    {
        return $this->priceIn($currency) !== null;
    }

    /** "$79 / month", or "$150" for something bought once. */
    public function priceLabel(?string $currency = null): string
    {
        $code = $currency ?: Currencies::resolve();
        $minor = $this->priceIn($code)?->price_minor ?? $this->price_minor;

        $price = Money::format($minor / 100, $code);

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
    public function savingMinor(?string $currency = null): int
    {
        if ($this->isRecurring()) {
            return 0;
        }

        return $this->priceIn($currency)?->savingMinor() ?? 0;
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
            ->mapWithKeys(fn (MembershipPlanService $line) => [$line->service_id => $line->grantedCredits()])
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
