<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * How a business files its forms.
 *
 * The nine StyleDesk ships with are seeded as rows the business then owns,
 * not read from config at runtime: a tattoo studio wants "Aftercare" where a
 * med spa wants "Pre-Treatment", and a business that cannot rename one ends
 * up filing everything under "General".
 */
class FormCategory extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The order the business put them in, then oldest first as a tiebreak. */
    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * What to call it.
     *
     * A shipped category is translated; one the business named is its own
     * words and is never translated. `key` is what tells them apart.
     */
    public function label(): string
    {
        return $this->key === null
            ? (string) $this->name
            : __('forms.categories.'.$this->key);
    }

    /**
     * Give a business the categories it starts with.
     *
     * Idempotent on the key, so running it twice — a seeder and then an
     * onboarding step — does not produce eighteen categories.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (config('forms.default_categories') as $position => $key) {
            self::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'key' => $key],
                [
                    /* Stored in English and translated on the way out, so a
                       business that never renames it reads it in their own
                       language and one that does keeps their wording. */
                    'name' => __('forms.categories.'.$key, [], 'en'),
                    'position' => $position,
                ],
            );
        }
    }
}
