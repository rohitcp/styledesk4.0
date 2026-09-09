<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A structured preference staff can assign to a client.
 *
 * "Morning appointments", "Sensitive scalp", "Fragrance-free products" — the
 * things a salon needs to remember about a person and act on, kept as records
 * rather than free text so a booking can eventually be filtered by them.
 *
 * Deactivated rather than deleted where a business changes its mind: a
 * preference already assigned to two hundred clients should stop being
 * offered without removing what those records say.
 */
class ClientPreference extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'position' => 0];

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
}
