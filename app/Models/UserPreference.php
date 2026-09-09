<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's display preferences.
 *
 * Read through App\Support\AccountPreferences rather than directly: every
 * column here is nullable and means "whatever the business says", so a caller
 * reading `$user->preferences->date_format` gets null for most people and
 * formats nothing. The resolver is what turns null into an answer.
 */
#[Fillable([
    'date_format',
    'time_format',
    'timezone',
    'first_day_of_week',
    'calendar_view',
    'show_weekends',
    'show_cancelled',
    'show_resource_color',
    'show_staff_color',
])]
class UserPreference extends Model
{
    protected function casts(): array
    {
        return [
            'first_day_of_week' => 'integer',
            'show_weekends' => 'boolean',
            'show_cancelled' => 'boolean',
            'show_resource_color' => 'boolean',
            'show_staff_color' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
