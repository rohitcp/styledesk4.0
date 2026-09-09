<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One copy of one campaign, and what became of it.
 *
 * The only place a per-client answer can live: who opened it, who clicked,
 * who bounced, who unsubscribed because of it. The tallies on the campaign
 * are written FROM these rows and are a convenience, never a substitute —
 * "which clients opened it" is a question only this table can answer.
 *
 * The address is copied rather than read through the client, because a client
 * who changes their email next month has not changed where this one went.
 */
class EmailCampaignRecipient extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
