<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What administrators did to the platform.
 *
 * Immutable, and enforced rather than promised: the model refuses to update or
 * delete itself, so there is no route to edit one however a caller comes at
 * it. A history that can be rewritten answers no question worth asking.
 *
 * Written even when nobody is signed in. A failed sign-in naming an address
 * that belongs to no administrator is precisely the row somebody will want
 * three months later, and a log that only records success records nothing.
 */
class BackofficeAuditLog extends Model
{
    /** Created only: there is no update, so there is nothing to stamp. */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(BackofficeAdmin::class, 'admin_id');
    }

    /**
     * Write one down.
     *
     * The actor's name and address are copied in beside their id rather than
     * read through the relation later: this row outlives the account it names,
     * and one that can only identify its actor through a live foreign key
     * stops identifying them the day that account goes.
     *
     * Swallows its own failures. Suspending an account must not fail because
     * the note about it could not be written — but the note is attempted
     * first, so a write that succeeds is one that was recorded.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(
        string $action,
        ?BackofficeAdmin $actor = null,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $subjectLabel = null,
        ?string $actorEmail = null,
    ): ?self {
        try {
            return self::query()->create([
                'admin_id' => $actor?->id,
                'admin_name' => $actor?->name,
                'admin_email' => $actor?->email ?? $actorEmail,
                'action' => $action,
                'subject_type' => $subject === null ? null : $subject::class,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel,
                'before' => $before === [] ? null : $before,
                'after' => $after === [] ? null : $after,
                'ip' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** What this entry says, in the reader's language. */
    public function label(): string
    {
        /* Dots are swapped for underscores before the lookup: Laravel walks a
           dotted key one segment at a time and can never reach a key that
           itself contains a dot — it hands back the key and prints it on the
           page. The client activity log learned this the same way. */
        return __('backoffice.audit.actions.'.str_replace('.', '_', $this->action));
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
