<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A one-time code, issued before anybody has been identified.
 *
 * The two rules this model exists to hold:
 *
 * **A code is hashed like a password.** A database dump should not hand over
 * a live second factor, and a readable code is one that anybody with database
 * access could use to sign in as somebody else.
 *
 * **An unknown address is treated exactly like a known one.** The screen after
 * "enter your email" is the same either way, and it has to be: an address that
 * answers differently is an address somebody can test, which turns the login
 * page into a list of who works here.
 */
class BackofficeLoginCode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Issue one for an address, and say what it is.
     *
     * The plain code is returned rather than stored: this is the only moment
     * it exists in the open, and it goes straight into the email.
     *
     * Any code still standing for the address is spent first. Two live codes
     * would mean the older one still works after somebody has asked for a new
     * one, which is not what "resend" means to the person pressing it.
     *
     * @return array{0: self, 1: string}
     */
    public static function issueFor(string $email, ?string $ip = null, ?string $userAgent = null): array
    {
        $email = mb_strtolower(trim($email));

        self::query()->where('email', $email)->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $length = (int) config('backoffice.verification.code_length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $row = self::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('backoffice.verification.ttl_minutes')),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [$row, $code];
    }

    /** The one still standing for this address, if there is one. */
    public static function liveFor(string $email): ?self
    {
        return self::query()
            ->where('email', mb_strtolower(trim($email)))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function isSpent(): bool
    {
        return $this->consumed_at !== null
            || $this->expires_at->isPast()
            || $this->attempts >= (int) config('backoffice.verification.max_attempts');
    }

    /**
     * Check a guess, and count it.
     *
     * The count is written whether the guess was right or wrong, and the code
     * is spent the moment it is used — a code that still works after it has
     * been used once is not one-time.
     */
    public function matches(string $guess): bool
    {
        if ($this->isSpent()) {
            return false;
        }

        $this->increment('attempts');

        if (! Hash::check(trim($guess), $this->code_hash)) {
            return false;
        }

        $this->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    /**
     * Whether this address has been asked for too many codes lately.
     *
     * Counted over the code's own lifetime rather than a fixed minute: the
     * limit somebody hits by pressing Resend is the one that should stop
     * them, and a per-minute rule lets an attacker sit just under it all day.
     */
    public static function tooManyRecentlyFor(string $email, int $limit = 5): bool
    {
        return self::query()
            ->where('email', mb_strtolower(trim($email)))
            ->where('created_at', '>=', Carbon::now()->subMinutes((int) config('backoffice.verification.ttl_minutes')))
            ->count() >= $limit;
    }
}
