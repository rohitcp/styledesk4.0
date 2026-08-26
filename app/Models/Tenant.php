<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A StyleDesk business (salon, clinic, studio).
 *
 * StyleDesk runs single-database tenancy: every tenant's rows live in the one
 * schema and are separated by a `tenant_id` column, not by a separate
 * database. See config/tenancy.php for why DatabaseTenancyBootstrapper is off.
 *
 * The base model keeps any attribute not listed in getCustomColumns() inside a
 * `data` JSON column. Anything worth querying, indexing or joining on must
 * therefore be declared below and given a real column in the migration.
 */
class Tenant extends BaseTenant
{
    use HasDomains;

    /**
     * `status` has a database-level default, but that value is not reflected on
     * the in-memory model until it is refreshed. Declaring it here means
     * Tenant::create([...])->status reads 'active' straight away rather than ''.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Attributes stored as real columns rather than inside the `data` blob.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
            'business_phone',
            'business_phone_country',
            'business_email',
            'website',
            'logo_path',
            'currency',
        ];
    }

    /**
     * Users belonging to this tenant.
     *
     * StyleDesk is one-tenant-per-user: membership is the `users.tenant_id`
     * foreign key, so this is a plain HasMany rather than a pivot.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function onboarding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantOnboarding::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function bookingSettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BookingSettings::class);
    }

    /**
     * Route key so tenant URLs read /t/acme rather than /t/<uuid>.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
