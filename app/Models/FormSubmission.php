<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AnswersWhenFlagged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * One client, one form, once.
 *
 * Assignment and submission are one row: a form's life is a single line —
 * assigned, sent, viewed, started, completed, signed — and splitting it would
 * mean a request with no answers and answers with no request, joined on the
 * way to every screen showing either.
 *
 * Expiry is applied when a submission is read rather than by a nightly sweep,
 * the same shape as loyalty point expiry: a business that sets a deadline
 * gets one with no scheduled job, and `completed_at` still says when the form
 * was actually filled in.
 */
class FormSubmission extends Model
{
    use BelongsToTenant;

    public const STATUS_NOT_SENT = 'not_sent';

    public const STATUS_SENT = 'sent';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answers' => AnswersWhenFlagged::class,
            'answers_encrypted' => 'boolean',
            'is_test' => 'boolean',
            'assigned_at' => 'datetime',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------- relationships

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // -------------------------------------------------------------- scopes

    /** The business's own tests, kept out of the client's history. */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_test', false);
    }

    /**
     * Finished, and still counting.
     *
     * Both halves matter: a form completed two years ago under a
     * twelve-month rule is not one the business may rely on today, and a
     * client asked to redo it has not "not done it".
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_SIGNED])
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /** Asked for and not yet finished. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_NOT_SENT,
            self::STATUS_SENT,
            self::STATUS_VIEWED,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    // ------------------------------------------------------- what it is

    public function isComplete(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_SIGNED], true);
    }

    /**
     * Past its date.
     *
     * Read rather than stored, so a business that lengthens its validity rule
     * does not have to rewrite a table of statuses to mean it.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Where this one is, for the reader.
     *
     * Expiry wins over the stored status, because "Completed" on a form the
     * business may no longer rely on is the answer that gets a client treated
     * on a two-year-old medical history.
     */
    public function statusKey(): string
    {
        return $this->isComplete() && $this->hasExpired() ? 'expired' : (string) $this->status;
    }

    public function statusLabel(): string
    {
        return __('forms.submission_statuses.'.$this->statusKey());
    }

    /**
     * Locked.
     *
     * A signed submission is evidence and never changes. What a business may
     * do with a wrong one is ask for another.
     */
    public function isLocked(): bool
    {
        return $this->signed_at !== null;
    }

    /**
     * A token no amount of guessing finds.
     *
     * The client has no StyleDesk account, so this is the whole of the
     * authorisation on the public link. Following `review_token`: random,
     * long, and checked for collision because unique() on the column is a
     * constraint rather than a retry.
     */
    public static function newToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }
}
