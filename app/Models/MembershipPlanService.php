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
            'position' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
