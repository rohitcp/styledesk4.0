<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Wizard progress for one tenant.
 *
 * The source of truth for which step a user is on. Deliberately server-side:
 * the prototype's localStorage draft is a prototype store, and clearing
 * browser storage must not change a user's position in onboarding.
 */
class TenantOnboarding extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_onboarding';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_completed' => 'boolean',
            'location_completed' => 'boolean',
            'hours_completed' => 'boolean',
            'services_completed' => 'boolean',
            'team_completed' => 'boolean',
            'booking_completed' => 'boolean',
            'completed_at' => 'datetime',
            'getting_started_dismissed_at' => 'datetime',
        ];
    }

    /**
     * Whether a given step id has been completed.
     */
    public function hasCompleted(string $step): bool
    {
        return (bool) ($this->{$step.'_completed'} ?? false);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Whether enough is set up to let someone into the application.
     *
     * Spec section 23: account, verified address, business, location and
     * timezone. Services, team and online booking are explicitly skippable
     * and must not hold anyone out of the product — the point of the wizard
     * is to get a business running, not to collect every field first.
     *
     * Distinct from isComplete(), which means the user actually walked the
     * wizard to the end.
     */
    public function meetsMinimumSetup(): bool
    {
        return $this->business_completed
            && $this->location_completed
            && $this->hours_completed;
    }
}
