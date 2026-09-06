<?php

namespace App\Models;

use App\Contracts\TenantStorageContract;
use App\Http\Middleware\EnsureCanManageSettings;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

#[Fillable(['first_name', 'last_name', 'display_name', 'job_title', 'phone', 'phone_country', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'email_verification_code', 'pending_email_token'])]
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
            'email_verification_code_expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'pending_email_expires_at' => 'datetime',
            'activity_seen_at' => 'datetime',
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
     * Send the verification email, minting the code that goes in it.
     *
     * Overridden rather than left to the framework because the email carries
     * a six-digit code as well as a link, and the code has to exist before
     * the message is built. Every send issues a fresh one, so the code in the
     * newest email is the only one that works — a resend silently invalidates
     * the code in the email before it, which is what a reader who asked for a
     * new email expects.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail(
            $this->issueEmailVerificationCode(),
            (int) config('auth.verification.expire', 60),
        ));
    }

    /**
     * Mint a code, store its hash, and return the plain digits to be mailed.
     *
     * Six digits with a leading zero preserved: str_pad rather than a plain
     * random_int, or one code in ten would arrive as five characters and not
     * fit the boxes on the page.
     */
    public function issueEmailVerificationCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(
                (int) config('auth.verification.expire', 60)
            ),
        ])->save();

        return $code;
    }

    /**
     * Whether these digits are the live code for this account.
     *
     * Expiry is checked before the hash so that an expired code cannot be
     * distinguished from a wrong one by how long the answer takes.
     */
    public function emailVerificationCodeMatches(string $code): bool
    {
        if ($this->email_verification_code === null || $this->email_verification_code_expires_at === null) {
            return false;
        }

        if ($this->email_verification_code_expires_at->isPast()) {
            return false;
        }

        return Hash::check($code, $this->email_verification_code);
    }

    /**
     * Retire the code.
     *
     * Called once the address is verified, whether that happened through the
     * code or through the link: a code that still works after the account is
     * verified is a credential nobody needs any more.
     */
    public function clearEmailVerificationCode(): void
    {
        $this->forceFill([
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();
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
        return $this->hasPermission(EnsureCanManageSettings::PERMISSION, 'all');
    }

    /**
     * This user's Role record in their tenant, or null.
     *
     * The owner is resolved from tenants.owner_user_id rather than from a
     * staff row, for the same reason roleInTenant() does: the owner exists
     * from the moment the business is created, before onboarding has seeded
     * them as staff.
     */
    public function role(): ?Role
    {
        if ($this->tenant_id === null) {
            return null;
        }

        if ($this->tenant?->owner_user_id === $this->id) {
            return Role::withoutGlobalScopes()
                ->where('tenant_id', $this->tenant_id)
                ->where('key', Role::OWNER)
                ->first();
        }

        $staff = Staff::withoutGlobalScopes()
            ->with('roleRecord.permissions')
            ->where('tenant_id', $this->tenant_id)
            ->where('user_id', $this->id)
            ->first();

        return $staff?->roleRecord;
    }

    /**
     * Whether this user may do something, at least at the given scope.
     *
     * `$scope` is what the *caller* needs, not what the user holds: asking for
     * 'own' means "may they do this to their own records", and someone granted
     * 'all' satisfies it. Defaulting to 'own' makes the loosest question the
     * default, so a caller that forgets to think about scope asks the safest
     * version rather than the widest.
     */
    public function hasPermission(string $permission, string $scope = 'own'): bool
    {
        return (bool) $this->role()?->grants($permission, $scope);
    }

    /** The widest scope this user holds for a permission, or null. */
    public function permissionScope(string $permission): ?string
    {
        return $this->role()?->scopeFor($permission);
    }

    public function isOwner(): bool
    {
        return $this->tenant_id !== null && $this->tenant?->owner_user_id === $this->id;
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

    /**
     * This user's staff record in their tenant, or null.
     *
     * Null is a real answer, not a fault: the owner has rights from the
     * moment the business exists, which is before onboarding seeds them as
     * staff. Callers that ask "which location do they work at" must treat
     * null as "not tied to one" rather than as an error.
     *
     * Memoised because scope checks ask for it several times in one request,
     * and the question cannot change part-way through.
     */
    public function staffRecord(): ?Staff
    {
        if ($this->tenant_id === null) {
            return null;
        }

        return $this->resolvedStaffRecord ??= Staff::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->where('user_id', $this->id)
            ->first();
    }

    /**
     * Two letters standing in for a face.
     *
     * Never blank: a byline with an empty circle beside it reads as a note
     * nobody wrote, which is the one thing a signed note must not do.
     */
    public function initials(): string
    {
        $initials = mb_substr($this->first_name ?: '', 0, 1).mb_substr($this->last_name ?: '', 0, 1);

        return $initials === '' ? '?' : mb_strtoupper($initials);
    }

    /**
     * Their photo, wherever it was uploaded.
     *
     * Three places, narrowest first. What they uploaded in My Account is a
     * stored_files row and wins outright — it is the picture they chose for
     * themselves, through the storage component that knows who owns it. Then
     * the older `avatar_path`, still written by screens this feature has not
     * touched, and last the staff record's, which an administrator set.
     */
    public function avatarUrl(): ?string
    {
        if ($this->avatar_file_id !== null) {
            $url = app(TenantStorageContract::class)->url($this->avatar_file_id);

            if ($url !== null) {
                return $url;
            }
        }

        $path = $this->avatar_path ?: $this->staffRecord()?->avatar_path;

        return $path ? Storage::disk('brand')->url($path) : null;
    }

    /** The stored file behind the account photo, when there is one. */
    public function avatarFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'avatar_file_id');
    }

    /**
     * What this person is called on screen.
     *
     * Their own answer first: `display_name` is the name they chose for
     * themselves, which is the one clients see, and it is deliberately
     * separate from the legal first and last name the business keeps.
     */
    public function displayName(): string
    {
        return $this->display_name !== null && $this->display_name !== ''
            ? $this->display_name
            : $this->name;
    }

    /**
     * This person's display preferences, created on first read.
     *
     * A row rather than null, so a caller can write to it without checking.
     * App\Support\AccountPreferences is what reads it — every column is
     * nullable and means "whatever the business says".
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /** The preference row, made if this person has never had one. */
    public function preferencesRow(): UserPreference
    {
        return $this->preferences()->firstOrCreate([]);
    }

    /** Where this person's notification answers differ from the defaults. */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    /** Backing store for staffRecord(); not an attribute, so it is never saved. */
    private ?Staff $resolvedStaffRecord = null;
}
