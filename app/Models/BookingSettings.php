<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Public booking rules for one tenant. The booking URL is derived from
 * tenants.slug rather than stored, so the two cannot disagree after a rename.
 */
class BookingSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'booking_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allow_new_clients' => 'boolean',
            'allow_existing_clients' => 'boolean',
            'require_card' => 'boolean',
            'require_email' => 'boolean',
            'require_phone' => 'boolean',
        ];
    }
}
