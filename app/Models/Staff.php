<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\StaffOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'date_of_birth' => 'date',
            'started_on' => 'date',
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
    /**
     * The working pattern this person is on — the template their schedule is
     * generated from, never the schedule itself.
     */
    public function shiftRule(): BelongsTo
    {
        return $this->belongsTo(ShiftRule::class);
    }

    /** The dated shifts this person works. */
    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    /**
     * A small report on this person, from the data that actually exists.
     *
     * Shifts and the hours in them — the appointment figures the spec also
     * asks for need a booking module, and a card reading "—" is a card that
     * teaches the reader to ignore the row. Structured as a list so the rest
     * slot in beside these rather than replacing them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scheduleSummary(): array
    {
        $week = [now()->startOfWeek(), now()->endOfWeek()];
        $month = [now()->startOfMonth(), now()->endOfMonth()];

        $between = fn (array $range) => $this->shifts()
            ->whereDate('date', '>=', $range[0]->toDateString())
            ->whereDate('date', '<=', $range[1]->toDateString())
            ->where('status', '!=', 'cancelled')
            ->get();

        $thisWeek = $between($week);
        $thisMonth = $between($month);

        $hours = fn ($shifts) => round(
            $shifts->sum(fn (StaffShift $shift) => $shift->workedMinutes()) / 60, 1
        );

        return [
            ['key' => 'shifts_this_week', 'value' => $thisWeek->count()],
            ['key' => 'hours_this_week', 'value' => $hours($thisWeek)],
            ['key' => 'shifts_this_month', 'value' => $thisMonth->count()],
            ['key' => 'hours_this_month', 'value' => $hours($thisMonth)],
            ['key' => 'upcoming_shifts', 'value' => $this->shifts()
                ->whereDate('date', '>=', now()->toDateString())
                ->where('status', '!=', 'cancelled')
                ->count()],
            ['key' => 'services', 'value' => $this->services()->count()],
        ];
    }

    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    /**
     * The chairs, rooms or stations this person works at.
     *
     * The mirror of a service's own mapping: the service says which rooms
     * will do, this says which of them this person uses. An empty set is not
     * "none" but "no restriction recorded" — the same reading as locations,
     * and the one a single-chair business never has to think about.
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class);
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

        /* Away, not gone. Kept apart from "inactive" because the two mean
           different things to a rota: an inactive person has left the
           business, someone on leave is coming back and their record, their
           services and their room stay where they are. */
        if ($this->membership_status === 'on-leave') {
            return 'on-leave';
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

    /**
     * How many people are on the team right now.
     *
     * Counted through status(), not with a WHERE: several of the things that
     * stop somebody being active are facts about other records — an
     * unaccepted invitation, an archive timestamp — and a SQL copy of that
     * rule would be a second definition free to disagree with the first. The
     * directory pages in memory for the same reason, and at the scale a staff
     * list actually reaches this is one small query.
     *
     * Not memoised: a static would outlive the request in a test or under a
     * long-running worker and hand back a count from a page ago. The layout
     * asks once and passes the answer to both navigation partials.
     */
    public static function activeCount(): int
    {
        return static::query()
            ->get(['id', 'user_id', 'is_active', 'membership_status', 'invite_status', 'archived_at'])
            ->filter(fn (self $member) => $member->status() === 'active')
            ->count();
    }

    /**
     * The status in the reader's language.
     *
     * The config still decides which statuses exist and what colour each
     * badge is; only the wording comes from the language file, with the
     * config's own label as the fallback.
     */
    public function statusLabel(): string
    {
        return StaffOptions::statusLabel($this->status())
            ?? config('staff.statuses.'.$this->status().'.label', 'Unknown');
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
            return $this->roleRecord->label();
        }

        /**
         * The fallback path, for a staff row whose role_id was never linked.
         * Translated the same way, so a directory does not show one person's
         * role in Spanish and the next one's in English purely because of a
         * missing foreign key.
         */
        $key = 'roles.'.$this->role.'.name';

        if (trans()->has($key)) {
            return __($key);
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
