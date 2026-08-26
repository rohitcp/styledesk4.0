<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to deliver an invitation email.
 *
 * Deliberately not tenant-scoped: it hangs off an invitation that already is,
 * and this is operational data an administrator reads when a member reports a
 * missing email, not tenant content.
 */
class TeamInvitationDelivery extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(TeamInvitation::class, 'team_invitation_id');
    }
}
