<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Someone who delivers services. user_id is nullable: the team step invites
 * people who do not have an account yet.
 */
class Staff extends Model
{
    use BelongsToTenant;

    protected $table = 'staff';

    /**
     * Mirrors the database defaults.
     *
     * A column default is applied by the database, not reflected on the model
     * that was just created — so Staff::create() without is_active produced a
     * model reading false, and status() called it inactive. The row was fine;
     * every screen rendering the model it was handed back was not.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'membership_status' => 'active',
        'login_enabled' => true,
        'invite_status' => 'not-sent',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'provides_services' => 'boolean',
            'login_enabled' => 'boolean',
            'archived_at' => 'datetime',
            'specialities' => 'array',
        ];
    }

    /**
     * Keep role_id in step with the role string.
     *
     * Every existing caller writes `role` as a string — onboarding, invitation
     * acceptance, the seeders, the tests. Resolving the id here means none of
     * them has to change and none of them can forget, which matters because a
     * staff row with a role name but no role_id resolves to no permissions at
     * all: an owner locked out of their own business by a save.
     */
    protected static function booted(): void
    {
        static::saving(function (self $staff) {
            if ($staff->role_id !== null || $staff->role === null || $staff->tenant_id === null) {
                return;
            }

            $staff->role_id = Role::withoutGlobalScopes()
                ->where('tenant_id', $staff->tenant_id)
                ->where('key', $staff->role)
                ->value('id');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The one location this person works at, or null for all of them.
     *
     * Copied from the invitation on acceptance rather than read through it,
     * because an invitation can be deleted and a staff member's location must
     * outlive it.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Not named role().
     *
     * `staff.role` is still a string column, and Eloquent resolves an
     * attribute before a relation of the same name — so $staff->role would
     * hand back "manager" rather than the Role model, silently, wherever a
     * model was expected. The column stays for now because every existing
     * caller writes it; the relation takes a name that cannot collide.
     */
    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    /**
     * The status the directory shows, derived rather than stored.
     *
     * Several of these are facts about other records — whether an invitation
     * is still live, whether archived_at is set — and a stored copy would be
     * a second answer free to disagree with the first. The order below is the
     * precedence: archived outranks everything, and an unaccepted invitation
     * outranks "active" because the person cannot get in yet.
     */
    public function status(): string
    {
        if ($this->archived_at !== null) {
            return 'archived';
        }

        if ($this->membership_status === 'suspended') {
            return 'suspended';
        }

        if ($this->invite_status === 'expired') {
            return 'invite-expired';
        }

        // Invited but not yet accepted: there is no account behind this row.
        if ($this->user_id === null && in_array($this->invite_status, ['sent', 'pending'], true)) {
            return 'pending-invite';
        }

        return $this->is_active ? 'active' : 'inactive';
    }

    public function statusLabel(): string
    {
        return config('staff.statuses.'.$this->status().'.label', 'Unknown');
    }

    public function statusClass(): string
    {
        return config('staff.statuses.'.$this->status().'.class', 'styledesk_badge--soon');
    }

    /**
     * What to call this person.
     *
     * A preferred name is the one they asked to be called, so it wins over
     * the legal first name wherever a human will read it.
     */
    public function displayName(): string
    {
        return trim(($this->preferred_name ?: $this->first_name).' '.$this->last_name);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name ?: '?', 0, 1).mb_substr($this->last_name ?: '', 0, 1));
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
