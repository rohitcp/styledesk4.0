<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A file StyleDesk has stored, wherever it happens to live.
 *
 * Features hold one of these rather than a path. The path is an
 * implementation detail of whichever disk was in use the day the file
 * arrived, and a feature that held one would break the day the business
 * moved to DigitalOcean.
 */
class StoredFile extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Everything attached to one record. */
    public function scopeFor(Builder $query, string $entityType, int|string $entityId): Builder
    {
        return $query->where('entity_type', $entityType)->where('entity_id', $entityId);
    }

    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    /**
     * The size as a person would say it.
     *
     * Rounded to one decimal because "1.4 MB" is what a reader wants and
     * "1,468,006 bytes" is what a machine wants.
     */
    public function readableSize(): string
    {
        $bytes = (int) $this->file_size;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $bytes : round($bytes, 1)).' '.$unit;
            }

            $bytes /= 1024;
        }

        return $bytes.' GB';
    }
}
