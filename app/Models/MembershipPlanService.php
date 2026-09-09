<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of what a membership includes.
 *
 * A row rather than a key in a json blob, because "which memberships include
 * a facial" is a question the desk asks and a blob cannot answer it.
 *
 * It has no tenant of its own: it exists only inside a plan, and the plan is
 * what belongs to the business.
 */
class MembershipPlanService extends Model
{
    protected $table = 'membership_plan_services';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'credits' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * A line always grants what it describes, unless told otherwise.
     *
     * The column carries a default of 1, so a row written without naming
     * `credits` — a seeder, an import, an older code path — would quietly
     * turn a four-massage package into a one-massage one. Filling it from
     * the quantity at write time means the two can only ever differ because
     * somebody said so.
     */
    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            if ($line->credits === null) {
                $line->credits = $line->quantity;
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * How many redemptions this line grants.
     *
     * `credits` governs; `quantity` describes. They are the same number in
     * every ordinary membership, and where a row predates the split — or a
     * caller forgets to set it — the description is the honest fallback
     * rather than one credit nobody agreed to.
     */
    public function grantedCredits(): int
    {
        return max(1, (int) ($this->credits ?? $this->quantity));
    }
}
