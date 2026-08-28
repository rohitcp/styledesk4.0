<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ClientOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One email address on a client record. See ClientPhone: same division
 * between what kind of address it is and which one to write to first.
 */
class ClientEmail extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function typeLabel(): string
    {
        return ClientOptions::emailTypes()[$this->type] ?? $this->type;
    }
}
