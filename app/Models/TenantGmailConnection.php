<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The mailbox a business has connected.
 *
 * Deliberately not `BelongsToTenant`: this is read during the OAuth callback,
 * where tenancy is resolved from the signed-in user rather than initialised,
 * and a global scope that silently returns nothing there would look exactly
 * like "not connected".
 */
class TenantGmailConnection extends Model
{
    public const STATUS_CONNECTED = 'connected';

    /**
     * Connected, and cannot be relied on.
     *
     * Reached when a refresh fails or Google never issued a refresh token.
     * The distinction from disconnected matters: the business believes it is
     * set up, so the screen has to say otherwise rather than quietly showing a
     * Connect button as though nothing had ever happened.
     */
    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            /* Encrypted at rest. The refresh token is a standing key to
               somebody's mailbox — the most dangerous thing this application
               stores. */
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    /**
     * Whether the access token needs renewing before the next send.
     *
     * A minute of slack, because a token that expires while the request is in
     * flight fails in a way that reads as a broken connection.
     */
    public function accessTokenHasExpired(): bool
    {
        return $this->access_expires_at === null
            || $this->access_expires_at->subMinute()->isPast();
    }

    /** Say what is wrong, and stop pretending the connection works. */
    public function flagNeedsAttention(string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_NEEDS_ATTENTION,
            'last_error' => mb_substr($reason, 0, 255),
        ])->save();
    }

    public function statusLabel(): string
    {
        return __('client_email.connection.'.$this->status);
    }
}
