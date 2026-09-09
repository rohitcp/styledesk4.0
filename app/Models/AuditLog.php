<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One administrative change, per §35.
 *
 * Append-only by convention: nothing in the application updates or deletes a
 * row here, because a history that can be edited answers no question worth
 * asking.
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Record a change.
     *
     * The actor's name is stored alongside their id, not looked up through
     * the relation: the point of a history is to still read correctly after
     * the account it names has been deleted.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(
        string $action,
        ?User $actor,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $subjectLabel = null,
    ): self {
        return self::withoutGlobalScopes()->create([
            'tenant_id' => $actor?->tenant_id ?? tenant('id'),
            'action' => $action,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => Request::ip(),
            // Truncated rather than dropped: a browser string longer than the
            // column would otherwise fail the insert and lose the whole entry.
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
