<?php

namespace App\Models;

use App\Http\Middleware\EnsureCanManageSettings;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Display name, derived rather than stored.
     *
     * The sign-up form collects first and last name separately, so a stored
     * `name` column would be a third copy that drifts as soon as either half
     * is edited. This keeps ->name working for views and notifications.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * This user's role in their tenant.
     *
     * The owner is read from tenants.owner_user_id rather than from a staff
     * row, because the owner exists from the moment the business is created —
     * before onboarding step 4 has seeded them as staff. Without that, the
     * person who created the business would briefly have fewer rights than the
     * people they invite.
     */
    public function roleInTenant(): ?string
    {
        if ($this->tenant_id === null) {
            return null;
        }

        if ($this->tenant?->owner_user_id === $this->id) {
            return 'owner';
        }

        return Staff::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->where('user_id', $this->id)
            ->value('role');
    }

    /**
     * Whether this user is definitely somebody else's colleague.
     *
     * Phrased as a negative on purpose. `hasRole('owner')` answers "is the
     * owner recorded as you", which is false both for a receptionist and for
     * a tenant whose owner_user_id was never set — and those two must not be
     * treated the same by the onboarding gates. This only returns true when
     * there is an owner on record and it is somebody else.
     */
    public function isNotTenantOwner(): bool
    {
        $ownerId = $this->tenant?->owner_user_id;

        return $ownerId !== null && $ownerId !== $this->id;
    }

    /**
     * Whether this user may open App Settings.
     *
     * One method rather than a role list repeated in the middleware, the nav
     * and the tests: those three drifting apart is exactly how a nav item
     * ends up hidden from someone the backend still lets in, or shown to
     * someone it does not.
     */
    public function canManageSettings(): bool
    {
        return $this->hasRole(...EnsureCanManageSettings::ROLES);
    }

    public function hasRole(string ...$roles): bool
    {
        $role = $this->roleInTenant();

        return $role !== null && in_array($role, $roles, true);
    }

    /**
     * The business this user belongs to.
     *
     * StyleDesk is one-tenant-per-user. This is what the central app at
     * styledesk.app resolves tenancy from — see
     * App\Http\Middleware\InitializeTenancyFromUser.
     *
     * `tenant_id` is intentionally absent from #[Fillable]: it decides which
     * business's data the user can see, so it is never set from request input.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
