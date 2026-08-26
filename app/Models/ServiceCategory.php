<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    /**
     * Seed a new tenant with the default list.
     *
     * Idempotent on (tenant_id, name) so re-running it after a tenant has
     * renamed something cannot resurrect the original as a duplicate.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (config('service_categories') as $order => $name) {
            static::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'name' => $name],
                ['status' => self::STATUS_ACTIVE, 'display_order' => $order]
            );
        }
    }
}
