<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A booking somebody started but has not finished.
 *
 * Written when the services are settled, which is the first moment there is
 * anything worth keeping: a client and a list of what they asked for. What
 * happens to it afterwards is somebody else's decision — it becomes a
 * booking, or it sits in the leads list as a call to return.
 */
class BookingLead extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'expected_date' => 'date',
            'converted_at' => 'datetime',
            'contacted_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Statuses a lead can be left in by ageing rather than by a decision.
     *
     * Only these are chased: a lead somebody has already called, or one that
     * became a booking, is not a lead nobody has looked at.
     */
    public const CHASEABLE = ['new', 'in-progress', 'awaiting-confirmation', 'awaiting-deposit'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Whoever was at the desk when the call came in. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contactedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contacted_by');
    }

    /**
     * The notes written about this call.
     *
     * They belong to the client — that is where the next person to look this
     * person up will find them — so this reads them back rather than owning
     * them.
     *
     * @return Collection<int, ClientNote>
     */
    public function notes(): Collection
    {
        return ClientNote::query()
            ->with('author')
            ->where('booking_lead_id', $this->id)
            ->latest('id')
            ->get();
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingLeadEvent::class)->latest('id');
    }

    /**
     * Write down that something happened.
     *
     * Called rather than observed, because only some changes are events: a
     * lead re-saved with the same step is the same conversation carrying on,
     * and a timeline that recorded every keystroke would bury the two lines
     * anybody actually reads.
     */
    public function note(string $kind, ?string $detail = null, ?int $userId = null): BookingLeadEvent
    {
        return $this->events()->create([
            'tenant_id' => $this->tenant_id,
            'kind' => $kind,
            'detail' => $detail,
            'user_id' => $userId ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function statusLabel(): string
    {
        return __('leads.statuses.'.$this->status.'.label');
    }

    public function statusClass(): string
    {
        return config('bookings.lead_statuses.'.$this->status.'.class', 'styledesk_badge--soon');
    }

    /**
     * How far the client got, which is a different fact from what happened.
     *
     * "Follow-up required" says to ring them; "Deposit & payment" says what
     * to ring about.
     */
    public function stepLabel(): string
    {
        return __('leads.steps.'.$this->current_step);
    }

    /** Why it ended, where somebody said. */
    public function reasonLabel(): ?string
    {
        return $this->reason_code === null ? null : __('leads.reasons.'.$this->reason_code);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Whoever it is for, on file or not. */
    public function forName(): string
    {
        return $this->client?->displayName()
            ?? ($this->guest_name ?: __('bookings.walk_in_guest'));
    }

    /**
     * A reference that can be read out over the phone.
     *
     * Dated so it can be found without a search, and five digits so two calls
     * taken in the same minute cannot collide.
     */
    public static function nextReference(): string
    {
        do {
            $reference = 'BL-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
