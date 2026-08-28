<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A kind of resource — styling chairs, massage rooms.
 *
 * The category is what makes "any available styling chair" expressible. A
 * service that had to name each chair would need editing every time one is
 * added, and would silently stop offering the new one until somebody
 * remembered.
 */
class ResourceCategory extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'position' => 0, 'default_capacity' => 1];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'default_capacity' => 'integer'];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /**
     * The categories a new business starts with.
     *
     * firstOrCreate, so running it again adds what is missing without
     * touching a name someone has since edited.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (config('resources.seed_categories') as $position => $category) {
            static::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenant->getTenantKey(),
                    'name' => __('resources.categories.'.$category['key']),
                ],
                [
                    'default_capacity' => $category['capacity'],
                    'position' => $position,
                    'is_active' => true,
                ],
            );
        }
    }
}
