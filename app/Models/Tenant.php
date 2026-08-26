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
     * Neither the base model nor HasDataColumn declares casts, so this does
     * not need to merge anything from the parent.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

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
            'country_code',
            'currency_code',
            'owner_user_id',
            'timezone',
            'default_language',
            'trial_started_at',
            'trial_ends_at',
            'subscription_status',
            'plan_id',
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

    public function countries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantCountry::class)->orderBy('position');
    }

    /**
     * Replace the set of operating countries.
     *
     * The first code given becomes the primary and is mirrored into
     * country_code, which is what every later screen filters on. Writing both
     * in one place is what stops the mirror drifting from the table.
     *
     * @param  array<int, string>  $codes
     */
    public function syncCountries(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return;
        }

        $this->countries()->whereNotIn('country_code', $codes)->delete();

        foreach ($codes as $position => $code) {
            TenantCountry::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $this->getTenantKey(), 'country_code' => $code],
                ['position' => $position]
            );
        }

        $this->forceFill(['country_code' => $codes[0]])->save();
    }

    public function currencies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantCurrency::class)->orderBy('position');
    }

    /**
     * Replace the set of trading currencies.
     *
     * Mirrors the first into currency_code, the same arrangement as countries:
     * the list is the record, the mirrored column is what the rest of the app
     * reads.
     *
     * @param  array<int, string>  $codes
     */
    public function syncCurrencies(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return;
        }

        $this->currencies()->whereNotIn('currency_code', $codes)->delete();

        foreach ($codes as $position => $code) {
            TenantCurrency::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $this->getTenantKey(), 'currency_code' => $code],
                ['position' => $position]
            );
        }

        $this->forceFill(['currency_code' => $codes[0]])->save();
    }

    public function languages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TenantLanguage::class)->orderBy('position');
    }

    /**
     * Replace the supported languages, first entry primary.
     *
     * @param  array<int, string>  $codes
     */
    public function syncLanguages(array $codes): void
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return;
        }

        $this->languages()->whereNotIn('language_code', $codes)->delete();

        foreach ($codes as $position => $code) {
            TenantLanguage::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $this->getTenantKey(), 'language_code' => $code],
                ['position' => $position]
            );
        }

        $this->forceFill(['default_language' => $codes[0]])->save();
    }

    /**
     * The operating country, with a fallback.
     *
     * Onboarding always sets this at the business step, but a tenant created
     * another way — a seeder, a fixture, a row migrated before the column
     * existed — may not have it. One accessor means the controller and the
     * view cannot disagree about what the fallback is.
     */
    public function countryCode(): string
    {
        return $this->country_code ?: 'US';
    }

    /** The currency symbol for display, falling back to the code itself. */
    public function currencySymbol(): string
    {
        return config('currencies.currencies.'.$this->currency_code.'.symbol', (string) $this->currency_code);
    }

    public function businessTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BusinessType::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Whole days left in the trial, floored at zero.
     *
     * Shown in the app chrome, so it must never read as negative once the
     * trial has lapsed — that is what subscription_status is for.
     */
    public function trialDaysRemaining(): int
    {
        if ($this->trial_ends_at === null) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->startOfDay(), false));
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
