<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Currencies;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A coupon or an offer.
 *
 * One model for both, because they are one thing with one difference: a
 * coupon is typed in and an offer applies itself. Everything else — what it
 * takes off, what it applies to, who may use it, when and how often — is the
 * same set of questions.
 *
 * Status is worked out rather than stored. Draft and Disabled are decisions
 * somebody made and live on the row; Scheduled, Active and Expired are what
 * the dates say today, and a stored column would be wrong every midnight
 * until something rewrote it.
 */
class Promotion extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    public const TYPES = ['coupon', 'offer'];

    public const DISCOUNT_TYPES = ['percent', 'fixed'];

    public const APPLIES_TO = ['booking', 'all_services', 'services', 'categories'];

    public const ELIGIBILITY = ['all', 'new', 'existing', 'selected'];

    /** The states a reader sees, in the order a promotion passes through. */
    public const STATUSES = ['draft', 'scheduled', 'active', 'expired', 'disabled'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'days' => 'array',
            'discount_value' => 'integer',
            'min_spend_minor' => 'integer',
            'total_limit' => 'integer',
            'per_client_limit' => 'integer',
            'allow_online' => 'boolean',
            'combinable' => 'boolean',
            'is_draft' => 'boolean',
            'is_disabled' => 'boolean',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function serviceCategories(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class);
    }

    /*
     * The pivot tables are named promotion-first throughout, which reads
     * better than Laravel's alphabetical guess. Two of them therefore have
     * to be stated: `location_promotion` and `client_promotion` are what it
     * would look for otherwise.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'promotion_location');
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'promotion_client');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Where this promotion has got to, today.
     *
     * Order matters: a disabled promotion is disabled whatever its dates
     * say, and a draft has not started being anything yet.
     */
    public function status(): string
    {
        if ($this->is_disabled) {
            return 'disabled';
        }

        if ($this->is_draft) {
            return 'draft';
        }

        $today = Carbon::today();

        if ($this->starts_on !== null && $this->starts_on->gt($today)) {
            return 'scheduled';
        }

        if ($this->ends_on !== null && $this->ends_on->lt($today)) {
            return 'expired';
        }

        return 'active';
    }

    public function statusLabel(): string
    {
        return __('promotions.statuses.'.$this->status());
    }

    public function statusClass(): string
    {
        return match ($this->status()) {
            'active' => 'styledesk_badge--active',
            'scheduled' => 'styledesk_badge--info',
            'draft' => 'styledesk_badge--setup',
            'expired' => 'styledesk_badge--soon',
            default => 'styledesk_badge--danger',
        };
    }

    public function isCoupon(): bool
    {
        return $this->type === 'coupon';
    }

    /** "20%" or "$25.00", for a listing that has to be read at a glance. */
    public function discountLabel(?string $currency = null): string
    {
        return $this->discount_type === 'percent'
            ? $this->discount_value.'%'
            : Money::format($this->discount_value / 100, $currency ?: Currencies::resolve());
    }

    /** How many times it has been used, and against what it was allowed. */
    public function usageLabel(): string
    {
        $used = $this->redemptions_count ?? $this->redemptions()->count();

        return $this->total_limit === null
            ? (string) $used
            : $used.' / '.$this->total_limit;
    }

    /* ------------------------------------------------------------- scopes */

    /** Promotions a booking could actually use today. */
    public function scopeUsable(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query
            ->where('is_draft', false)
            ->where('is_disabled', false)
            ->whereDate('starts_on', '<=', $today)
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    public function scopeOfStatus(Builder $query, string $status): Builder
    {
        $today = Carbon::today()->toDateString();

        return match ($status) {
            'draft' => $query->where('is_draft', true)->where('is_disabled', false),
            'disabled' => $query->where('is_disabled', true),
            'scheduled' => $query->where('is_draft', false)->where('is_disabled', false)
                ->whereDate('starts_on', '>', $today),
            'expired' => $query->where('is_draft', false)->where('is_disabled', false)
                ->whereNotNull('ends_on')->whereDate('ends_on', '<', $today),
            'active' => $query->usable(),
            default => $query,
        };
    }
}
