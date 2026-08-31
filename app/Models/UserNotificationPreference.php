<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer to one notification switch.
 *
 * A row exists only where somebody's answer differs from the catalogue's
 * default, so the absence of a row is meaningful and must not be read as
 * "off". App\Support\NotificationCatalog is the only thing that should
 * interpret these.
 */
#[Fillable(['type_key', 'channel', 'is_enabled'])]
class UserNotificationPreference extends Model
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
