<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One email sent to a client.
 *
 * A record of what went out, not a view onto what would go out now. Every
 * field a reader needs is copied onto the row — see the migration for why.
 */
class ClientEmailMessage extends Model
{
    use BelongsToTenant;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Mark it as gone.
     *
     * Sent, not delivered: only a provider that reports back may promote a
     * message to delivered, and nothing does yet. A status that flatters
     * itself is worse than one that stops short — the desk would stop
     * chasing a message that never arrived.
     */
    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'failure_reason' => null,
        ])->save();
    }

    /** Why it did not go, in the sender's words rather than the exception's. */
    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'failure_reason' => mb_substr($reason, 0, 255),
        ])->save();
    }

    public function statusLabel(): string
    {
        return __('client_email.statuses.'.$this->status);
    }

    public function statusTone(): string
    {
        return 'styledesk_badge--'.config('client_email.statuses.'.$this->status.'.tone', 'soon');
    }

    public function providerLabel(): string
    {
        return __('client_email.providers.'.$this->provider.'.name');
    }

    /** Who pressed send. Null is StyleDesk itself, as elsewhere in the app. */
    public function senderLabel(): string
    {
        return $this->sentBy?->name ?? __('client_email.history.system');
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
