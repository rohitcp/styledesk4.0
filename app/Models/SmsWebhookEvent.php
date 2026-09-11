<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A delivery event the carrier has already told us about.
 *
 * Not tenant-scoped, deliberately: the webhook arrives before anything is
 * known about which business the message belonged to, and the whole purpose
 * is to recognise a repeat before that work is done.
 */
class SmsWebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
