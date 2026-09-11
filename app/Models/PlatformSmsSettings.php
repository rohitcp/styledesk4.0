<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The platform's SMS arrangement: which carrier, and the keys to reach it.
 *
 * One row, for the whole of StyleDesk. Every business shares one carrier
 * account and one number, which is what makes a single 10DLC registration
 * cover them all — and what makes this the back office's business rather than
 * any one salon's.
 *
 * Every credential is encrypted at rest. They can send messages at StyleDesk's
 * expense to any number in the world, and a database dump handed to a
 * contractor should not be a carrier account handed to a contractor.
 *
 * Nothing here is tenant-scoped and nothing here should ever be shown to a
 * salon. The tenant-facing screen (App Settings → SMS) decides what a business
 * sends; this decides what carries it.
 */
class PlatformSmsSettings extends Model
{
    protected $table = 'platform_sms_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            /* Laravel's encrypted cast: written encrypted, read back in the
               clear, and unreadable to anybody holding only the database. */
            'telnyx_key' => 'encrypted',
            'telnyx_public_key' => 'encrypted',
            'clicksend_username' => 'encrypted',
            'clicksend_key' => 'encrypted',
            'clicksend_webhook_secret' => 'encrypted',
        ];
    }

    /**
     * The one row, whether or not anybody has saved it.
     *
     * Unsaved rather than created on read: an installation nobody has
     * configured has no row, and reading the defaults must not quietly write
     * one.
     */
    public static function current(): self
    {
        return self::query()->first() ?? new self([
            'is_enabled' => false,
            'provider' => 'disabled',
        ]);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(BackofficeAdmin::class, 'updated_by');
    }

    /**
     * Is this carrier configured well enough to be chosen?
     *
     * Asked before a provider is switched to, so the screen can refuse rather
     * than let somebody point the platform at an account with no key in it —
     * which is every business's messages failing at once.
     */
    public function isReady(string $provider): bool
    {
        return match ($provider) {
            'telnyx' => filled($this->telnyx_key) && filled($this->telnyx_from),
            'clicksend' => filled($this->clicksend_username)
                && filled($this->clicksend_key)
                && filled($this->clicksend_from),
            'disabled' => true,
            default => false,
        };
    }

    /** What is missing, in the reader's words. */
    public function missingFor(string $provider): array
    {
        $needed = match ($provider) {
            'telnyx' => ['telnyx_key' => $this->telnyx_key, 'telnyx_from' => $this->telnyx_from],
            'clicksend' => [
                'clicksend_username' => $this->clicksend_username,
                'clicksend_key' => $this->clicksend_key,
                'clicksend_from' => $this->clicksend_from,
            ],
            default => [],
        };

        return collect($needed)->filter(fn ($value) => blank($value))->keys()->all();
    }
}
