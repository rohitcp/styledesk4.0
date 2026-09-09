<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A grouping of services, owned by one tenant.
 *
 * BelongsToTenant adds the global scope, so every query is filtered by the
 * active tenant without a caller having to remember. That is the isolation the
 * spec asks to be enforced in the backend rather than the UI: even a
 * hand-crafted request naming another tenant's category id finds nothing.
 */
class ServiceCategory extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $guarded = [];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Categories that may be assigned to a new service. */
    public function scopeAssignable($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->orderBy('display_order')
            ->orderBy('name');
    }

    /** Supplied by StyleDesk: deactivatable and reorderable, never deletable. */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    /**
     * Whether it can be removed rather than merely switched off.
     *
     * Two rules, and both matter. A default has no way back once deleted, so
     * it never is. A category in use is the heading on somebody's price list,
     * and deleting it would uncategorise those services without anyone being
     * asked.
     */
    public function isDeletable(): bool
    {
        return ! $this->isSystem() && $this->services()->count() === 0;
    }

    /**
     * Seed a new tenant with the default list.
     *
     * Matched on `key` rather than on the name, which is what actually makes
     * it idempotent: keyed on the name, a business that renamed "Hair" got a
     * second Hair the next time this ran, because the name it was looked up
     * by no longer existed.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (config('service_categories') as $order => $name) {
            static::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'key' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_system' => true,
                    'status' => self::STATUS_ACTIVE,
                    'display_order' => $order,
                ]
            );
        }
    }
}
