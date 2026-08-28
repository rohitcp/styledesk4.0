<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A label a business classifies clients with — VIP, Bridal, Walk-in.
 *
 * The colour is stored as a key into the palette in config, not as a hex
 * value, so a shade can be corrected in one place rather than in every row
 * that chose it.
 */
class ClientTag extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'position' => 0, 'color' => 'slate'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('label');
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class);
    }

    /**
     * The tags a new business starts with.
     *
     * Seeded rather than left to each business to invent, because "VIP" and
     * "No-show risk" are the same idea in every salon and typing them out is
     * not the work anyone signed up for. Half start switched off — they exist
     * to be turned on, not to fill a dropdown before a single client has been
     * classified.
     *
     * firstOrCreate, so running it again on an existing business adds what is
     * missing without touching a label someone has since edited.
     */
    public static function seedDefaultsFor(Tenant $tenant): void
    {
        foreach (config('clients.seed_tags') as $position => $tag) {
            static::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'label' => $tag['label']],
                [
                    'color' => $tag['color'],
                    'is_active' => $tag['active'],
                    'position' => $position,
                ],
            );
        }
    }

    /**
     * Whether this tag may be removed outright.
     *
     * A tag someone has put on a client is part of that client's record:
     * deleting it would quietly rewrite history on every client carrying it.
     * Deactivating keeps the record and takes the tag out of every list it
     * could be chosen from, which is what "remove" almost always means here.
     */
    public function isDeletable(): bool
    {
        return $this->clients()->count() === 0;
    }

    /** The palette entry, falling back rather than rendering an empty colour. */
    public function hex(): string
    {
        return config('clients.tag_colors.'.$this->color, config('clients.tag_colors.slate'));
    }
}
