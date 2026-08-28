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
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'default_capacity' => 'integer',
        ];
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

    /** The ones a business may put a new resource in. */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position')->orderBy('name');
    }

    /** Supplied by StyleDesk: deactivatable and reorderable, never deletable. */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    /** The heading it sits under, translated. */
    public function groupLabel(): ?string
    {
        return $this->group ? __('resources.category_groups.'.$this->group) : null;
    }

    /**
     * Whether it can be removed rather than merely switched off.
     *
     * Two rules, and both matter. A default has no way back once deleted, so
     * it never is. A category in use is the label on somebody's chairs, and
     * deleting it would leave them uncategorised without anyone being asked.
     */
    public function isDeletable(): bool
    {
        return ! $this->isSystem() && $this->resources()->count() === 0;
    }

    /**
     * The categories a new business starts with.
     *
     * Matched on `key` rather than on the name: a business that renamed
     * "Styling chair" to "Chair" used to get a second styling chair the next
     * time defaults were seeded, because the name it was looked up by no
     * longer existed.
     *
     * firstOrCreate, so running it again adds what is missing without
     * touching a name — or an order — someone has since changed.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (config('resources.seed_categories') as $position => $category) {
            static::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenant->getTenantKey(),
                    'key' => $category['key'],
                ],
                [
                    'name' => __('resources.categories.'.$category['key']),
                    'group' => $category['group'],
                    'is_system' => true,
                    'default_capacity' => $category['capacity'],
                    'position' => $position,
                    'is_active' => true,
                ],
            );
        }
    }
}
