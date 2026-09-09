<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * An internal note about a member of staff.
 *
 * Scheduling arrangements, training, a conversation about availability. Never
 * shown to a client or on the public booking pages — these are notes the
 * business keeps about the business.
 *
 * The same shape as ClientNote on purpose: it is the same idea, and a second
 * shape would mean a second editor and a second permission rule.
 */
class StaffNote extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Newest first: the last thing said is the thing being caught up on. */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Whether this user wrote it.
     *
     * Editing and deleting are your own note's business; an owner or
     * administrator may remove anybody's, which the policy decides rather
     * than this.
     */
    public function wasWrittenBy(?User $user): bool
    {
        return $user !== null && $this->created_by === $user->id;
    }

    public function authorName(): string
    {
        return $this->author?->name ?? __('staff.notes.someone');
    }

    public function authorInitials(): string
    {
        $name = trim((string) $this->author?->name);

        if ($name === '') {
            return '—';
        }

        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
