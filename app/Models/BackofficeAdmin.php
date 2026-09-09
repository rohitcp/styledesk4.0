<?php

declare(strict_types=1);

namespace App\Models;

use App\Mail\BackofficePasswordResetMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

/**
 * Somebody who administers StyleDesk itself.
 *
 * Not a `User`. A StyleDesk employee belongs to no salon, and every
 * tenant-scoped model in the application is filtered by a global scope that
 * would have nothing to filter them by — so they authenticate against their
 * own guard, their own table and their own session cookie. A stolen salon
 * session can never reach this console, which is the point.
 *
 * `BelongsToTenant` is deliberately absent and must stay absent.
 */
class BackofficeAdmin extends Authenticatable
{
    use Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'disabled_by');
    }

    /**
     * Mail the console's own reset link.
     *
     * Overridden because the inherited implementation sends Laravel's
     * ResetPassword notification, and that notification builds its URL from
     * `route('password.reset')` — the salon application's screen. An
     * administrator following it landed on the wrong console, on a form
     * posting to the wrong broker, holding a token that form could never
     * redeem. The console is a separate guard with a separate broker and a
     * separate token table; the link has to be separate too.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('backoffice.password.reset', [
            'token' => $token,
            /* Carried in the query string because the reset form posts the
               address back with the token, and asking the reader to retype it
               is a mismatch waiting to happen. */
            'email' => $this->email,
        ]);

        Mail::to($this->email)->send(new BackofficePasswordResetMail(
            admin: $this,
            resetUrl: $url,
            minutes: (int) config('auth.passwords.backoffice_admins.expire'),
        ));
    }

    // ------------------------------------------------------------ the role

    public function isSuperOwner(): bool
    {
        return $this->role === 'super-owner';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** What this administrator's role is called, in the reader's language. */
    public function roleLabel(): string
    {
        return __('backoffice.roles.'.$this->role);
    }

    /**
     * Whether this administrator may do a thing.
     *
     * Read from the config rather than from a table: the roles ship with the
     * code, and a copy in the database would be a second answer to the same
     * question — one of which would eventually be wrong.
     *
     * A role nobody has defined grants nothing. That is deliberate: a typo in
     * a role name should close doors rather than open them.
     */
    public function can($permission, $arguments = []): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $granted = config('backoffice.roles.'.$this->role.'.permissions', []);

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    /**
     * Whether this administrator may act on that one.
     *
     * Only a Super Owner may touch a Super Owner, and nobody at all may
     * disable or demote the last one. Checked here rather than in each
     * controller, because "who may act on whom" is one rule and three
     * spellings of it is two ways to get it wrong.
     */
    public function mayManage(self $other): bool
    {
        if (! $this->can('admins.manage')) {
            return false;
        }

        return $other->isSuperOwner() ? $this->isSuperOwner() : true;
    }

    /** The last way in. Refuses to leave the platform without an owner. */
    public function isLastSuperOwner(): bool
    {
        return $this->isSuperOwner()
            && self::query()->where('role', 'super-owner')->where('status', self::STATUS_ACTIVE)->count() <= 1;
    }

    // ------------------------------------------------------------- scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * The administrator this address belongs to, or null.
     *
     * Case and spacing are normalised because people type addresses, and an
     * administrator refused for a capital letter would blame the product.
     */
    public static function forEmail(?string $email): ?self
    {
        return $email === null || trim($email) === ''
            ? null
            : self::query()->where('email', mb_strtolower(trim($email)))->first();
    }
}
