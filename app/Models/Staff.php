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
            if ($staff->role_id !== null || $staff->role === null) {
                return;
            }

            /**
             * The tenant may not be on the model yet.
             *
             * BelongsToTenant stamps tenant_id on `creating`, which Eloquent
             * fires *after* `saving` — so a row created without an explicit
             * tenant_id (onboarding seeds the owner that way) reached here
             * with a null tenant, this hook gave up, and the owner ended up
             * with a role name and no permissions at all. Falling back to the
             * tenant in scope is what the stamp is about to do anyway.
             */
            $tenantId = $staff->tenant_id ?? (tenancy()->initialized ? tenant('id') : null);

            if ($tenantId === null) {
                return;
            }

            $staff->role_id = Role::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
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

        if ($this->invite_status === 'failed') {
            return 'invite-failed';
        }

        // Queued but not yet delivered. Shown apart from "pending invite" so
        // a stalled queue is visible on the screen rather than only in the
        // jobs table.
        if ($this->user_id === null && $this->invite_status === 'pending') {
            return 'invite-queued';
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
    /**
     * The role to show, from the linked record or the name on the row.
     *
     * Two sources because there are two ways to be right: role_id is the one
     * permissions resolve through, and `role` is the string every existing
     * caller writes. A row with the second and not the first is a defect, but
     * showing a dash for it hides the defect behind what looks like a person
     * with no role.
     */
    public function roleName(): string
    {
        if ($this->roleRecord) {
            return $this->roleRecord->name;
        }

        return config('role_defaults.'.$this->role.'.name')
            ?? ucfirst(str_replace('-', ' ', (string) $this->role));
    }

    public function displayName(): string
    {
        return trim(($this->preferred_name ?: $this->first_name).' '.$this->last_name);
    }

    /**
     * How the directory names someone: legal name, then what they go by.
     *
     * "Katherine Wu (Kit)" rather than displayName()'s "Kit Wu". A directory
     * is scanned by people looking for a record, and the name on the record
     * is the one they were hired under — so that leads, with the preferred
     * name beside it rather than in place of it.
     */
    public function directoryName(): string
    {
        $legal = trim($this->first_name.' '.$this->last_name);

        // Nothing to add when the two are the same, or the row would read
        // "Kit Wu (Kit)".
        if ($this->preferred_name === null || $this->preferred_name === $this->first_name) {
            return $legal;
        }

        return $legal.' ('.$this->preferred_name.')';
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
