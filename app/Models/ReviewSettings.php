<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Whether this business asks its clients what they thought, and when.
 *
 * Switching reviews off stops the asking. It erases nothing: the wording
 * stays, and every review already given stays readable in the reports and on
 * the client's record — a business having a difficult month must be able to
 * stop asking without losing a year of answers.
 */
class ReviewSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'review_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'google_enabled' => 'boolean',
            'categories' => 'array',
        ];
    }

    /**
     * This business's settings, whether or not anybody has saved any.
     *
     * Unsaved rather than created on read: a business that has never opened
     * the screen has no row, and reading the defaults must not quietly write
     * one. The screen's save is what makes it real.
     */
    public static function forTenant(?Tenant $tenant): self
    {
        $existing = $tenant === null
            ? null
            : self::query()->where('tenant_id', $tenant->getTenantKey())->first();

        return $existing ?? new self([
            'tenant_id' => $tenant?->getTenantKey(),
            'is_enabled' => false,
            'delay' => '1h',
            'channel' => 'email',
            'categories' => [],
            'google_enabled' => true,
        ]);
    }

    /** The delays a business may choose between, most immediate first. */
    public static function delays(): array
    {
        return array_keys(config('reviews.delays'));
    }

    /** Every channel, including the ones nothing can deliver yet. */
    public static function channels(): array
    {
        return array_keys(config('reviews.channels'));
    }

    /** The ones a save may actually name. */
    public static function availableChannels(): array
    {
        return collect(config('reviews.channels'))
            ->filter(fn (array $channel) => $channel['available'])
            ->keys()
            ->all();
    }
}
