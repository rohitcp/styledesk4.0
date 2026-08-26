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
            'services_completed' => 'boolean',
            'team_completed' => 'boolean',
            'booking_completed' => 'boolean',
            'completed_at' => 'datetime',
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
}
