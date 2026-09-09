<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A category of business, e.g. Hair Salon or Med Spa.
 *
 * Global reference data, deliberately not tenant-scoped: every tenant picks
 * from the same catalogue, and administrators curate it centrally.
 */
class BusinessType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * The name in the reader's language.
     *
     * Global reference data is StyleDesk's own vocabulary, so translating it
     * is right — unlike a service name or a client note, which is the
     * business's own words and stays as typed.
     *
     * Falls back to the stored name, so a type added by a later seeder
     * without a translation reads as itself rather than as a key.
     */
    public function label(): string
    {
        $key = 'business.types.'.$this->slug;

        return trans()->has($key) ? __($key) : $this->name;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
