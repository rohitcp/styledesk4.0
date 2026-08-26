<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A pending offer to join a business.
 *
 * Also the team list's record of a person before they have an account: the
 * name, role and location live here from the moment the invite is sent, and a
 * Staff row is only created once someone accepts. That keeps "invited" and
 * "works here" as two distinguishable states rather than one row with a flag,
 * so a revoked invite leaves nothing behind for a booking to point at.
 */
class TeamInvitation extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    /** Days an invitation stays valid, per the spec. */
    public const EXPIRES_AFTER_DAYS = 7;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * The plain token, held in memory only for the request that created it.
     *
     * Never persisted and never returned by a later read: the only way to
     * learn it is to be the process that generated it, or to receive the
     * email. A resend mints a new one rather than recovering the old.
     */
    public ?string $plainToken = null;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * What this person will be bookable for once they accept.
     *
     * Mirrors service_staff, which is where these are copied on acceptance.
     * Kept here rather than on a staff row created up front, so an invitation
     * that is never accepted leaves nothing a booking could point at.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_team_invitation');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(TeamInvitationDelivery::class);
    }

    // ------------------------------------------------------------- tokens

    /**
     * Hash a plain token for storage and lookup.
     *
     * SHA-256 rather than bcrypt precisely because this must be *searchable*:
     * a bcrypt hash is salted, so finding the matching row would mean reading
     * every invitation and verifying each one. The token is 64 random hex
     * characters from a CSPRNG, not a human-chosen secret, so the offline
     * guessing attack bcrypt exists to slow down does not apply.
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Mint a new token, replacing any previous one.
     *
     * Writing the new hash is what invalidates the old link: the old token
     * hashes to a value no row holds any more, so it stops resolving at the
     * moment this is saved. There is no separate revocation list to keep in
     * step.
     */
    public function regenerateToken(): string
    {
        $plain = Str::random(64);

        $this->plainToken = $plain;
        $this->token_hash = self::hashToken($plain);

        return $plain;
    }

    public function scopeForToken(Builder $query, string $plain): Builder
    {
        return $query->where('token_hash', self::hashToken($plain));
    }

    // ------------------------------------------------------------- status

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * The status to show, which is not always the status stored.
     *
     * Expiry is a moment passing, not an event anything fires, so a pending
     * invitation becomes expired without a write ever happening. Deriving it
     * here means the UI and the acceptance check cannot disagree just because
     * no scheduled job has run yet.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->hasExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    /** Whether this invitation can still be accepted right now. */
    public function isAcceptable(): bool
    {
        return $this->effectiveStatus() === self::STATUS_PENDING;
    }

    /** Pending or expired invitations can be sent again; the rest cannot. */
    public function isResendable(): bool
    {
        return in_array($this->effectiveStatus(), [self::STATUS_PENDING, self::STATUS_EXPIRED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_ACCEPTED => 'Active',
            self::STATUS_EXPIRED => 'Invite expired',
            self::STATUS_REVOKED => 'Invite revoked',
            default => 'Pending invite',
        };
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Stamp the send, extending the window from now.
     *
     * Resending has to move expires_at as well as sent_at; leaving the old
     * expiry would hand someone a fresh link that dies tomorrow.
     */
    public function markSending(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'sent_at' => now(),
            'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
            'revoked_at' => null,
        ])->save();
    }

    public function revoke(): void
    {
        /**
         * The token is replaced, not merely marked revoked.
         *
         * Status alone would rely on every future code path remembering to
         * check it. Rotating the hash means the revoked link cannot resolve to
         * a row at all, which is a guarantee rather than a convention.
         */
        $this->regenerateToken();

        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();
    }
}
