<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One email sent to many clients.
 *
 * The audience is held as RULES, not as a list of people. "Clients with no
 * visit in ninety days" answers differently on the day it sends than on the
 * day it was written, and the day it sends is the one that matters. The rules
 * become a list of recipients at the moment sending starts, and that list is
 * then fixed — a report has to say who it actually went to.
 *
 * What can be done to a campaign depends on where it has got to, and that is
 * the whole reason `status` is not a boolean. A draft can be edited and
 * deleted; a scheduled campaign can be rescheduled or called off; one that has
 * started sending can be neither, because the emails are already leaving.
 */
class EmailCampaign extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected $attributes = ['status' => 'draft'];

    /** Where a campaign can be, in the order it gets there. */
    public const STATUSES = ['draft', 'scheduled', 'sending', 'sent', 'paused', 'cancelled', 'failed'];

    /** Past this point the emails are going out and the content is fixed. */
    public const LOCKED = ['sending', 'sent'];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'content' => 'array',
            'scheduled_for' => 'datetime',
            'estimated_at' => 'datetime',
            'sending_started_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function scopeMatching(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(fn (Builder $inner) => $inner
            ->where('name', 'like', $like)
            ->orWhere('subject', 'like', $like));
    }

    /**
     * Whether the content and the audience can still be changed.
     *
     * A campaign that is going out cannot be edited, and neither can one that
     * has gone: the first would change what half the list receives midway,
     * and the second would rewrite what people were actually sent.
     */
    public function isEditable(): bool
    {
        return ! in_array($this->status, self::LOCKED, true);
    }

    /** Whether it is waiting for a time that has not arrived yet. */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_for !== null;
    }

    public function statusLabel(): string
    {
        return __('marketing.statuses.'.$this->status);
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'sent' => 'styledesk_badge--active',
            'sending' => 'styledesk_badge--attention',
            'scheduled' => 'styledesk_badge--info',
            'failed', 'cancelled' => 'styledesk_badge--setup',
            default => 'styledesk_badge--soon',
        };
    }

    /**
     * A rate, as a whole percentage of what was delivered.
     *
     * Opens and clicks are measured against DELIVERED rather than against
     * sent: an address that bounced was never given the chance to open
     * anything, and counting it makes every campaign look worse than it was.
     */
    public function rate(string $of): int
    {
        $base = max(0, (int) $this->delivered_count);

        if ($base === 0) {
            return 0;
        }

        return (int) round(((int) $this->{$of.'_count'}) / $base * 100);
    }

    /** Delivery is measured against what was actually sent. */
    public function deliveryRate(): int
    {
        return $this->sent_count > 0
            ? (int) round($this->delivered_count / $this->sent_count * 100)
            : 0;
    }
}
