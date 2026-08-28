<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ClientOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One phone number on a client record.
 *
 * Type and priority are separate: "work" says what kind of number it is,
 * `is_primary` says which one to ring first. A client whose work mobile is
 * the number they actually answer needs both.
 */
class ClientPhone extends Model
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

    /** The kind of number, in the reader's language. */
    public function typeLabel(): string
    {
        return ClientOptions::phoneTypes()[$this->type] ?? $this->type;
    }
}
