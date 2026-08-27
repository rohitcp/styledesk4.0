<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    /** The palette entry, falling back rather than rendering an empty colour. */
    public function hex(): string
    {
        return config('clients.tag_colors.'.$this->color, config('clients.tag_colors.slate'));
    }
}
